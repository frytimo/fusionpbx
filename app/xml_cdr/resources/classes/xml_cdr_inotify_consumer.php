<?php

/*
 * FusionPBX
 * Version: MPL 1.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is FusionPBX
 *
 * The Initial Developer of the Original Code is
 * Mark J Crane <markjcrane@fusionpbx.com>
 * Portions created by the Initial Developer are Copyright (C) 2008-2025
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * Mark J Crane <markjcrane@fusionpbx.com>
 * Tim Fry <tim@fusionpbx.com>
 */

/**
 * Uses the PHP inotify extension to watch the xml_cdr directory for
 * IN_CLOSE_WRITE and IN_MOVED_TO events, delivering new CDR files to
 * the pipeline in near-real time.
 *
 * Startup housekeeping is delegated to one or more xml_cdr_consumer
 * instances passed to the constructor (typically xml_cdr_filesystem_consumer
 * for the initial directory scan and xml_cdr_retry_consumer for retries).
 *
 * When the inotify extension is unavailable (or the watch fails) the
 * consumer falls back to a polling loop that re-runs all delegate consumers
 * at the configured idle_time interval.
 *
 * Graceful shutdown is achieved by passing a reference to $running:
 *   $running = true;
 *   $consumer->consume($settings, $on_record, $running);
 *   // … call pcntl_signal to set $running = false on SIGTERM
 */
class xml_cdr_inotify_consumer implements xml_cdr_consumer {

	/** @var xml_cdr_consumer[] Consumers to run before entering the watch loop. */
	private array $delegates;

	/** @var int Seconds to sleep when idle (polling fallback). */
	private int $idle_time;

	/** @var string Full path to the xml_cdr directory to watch. */
	private string $cdr_dir;

	/**
	 * @param string             $cdr_dir   Full path to the xml_cdr directory.
	 * @param int                $idle_time Polling interval in seconds (fallback).
	 * @param xml_cdr_consumer   ...$delegates Startup consumers.
	 */
	public function __construct(string $cdr_dir, int $idle_time, xml_cdr_consumer ...$delegates) {
		$this->cdr_dir    = rtrim($cdr_dir, '/');
		$this->idle_time  = $idle_time;
		$this->delegates  = $delegates;
	}

	/**
	 * Static factory — reads configuration from settings.
	 *
	 * @param settings           $settings  Application settings.
	 * @param xml_cdr_consumer   ...$delegates Startup-time consumers.
	 *
	 * @return self
	 */
	public static function create(settings $settings, xml_cdr_consumer ...$delegates): self {
		$cdr_dir   = $settings->get('switch', 'log', '/var/log/freeswitch') . '/xml_cdr';
		$idle_time = (int)$settings->get('cdr', 'watchdog_interval', 30);
		return new self($cdr_dir, $idle_time, ...$delegates);
	}

	/**
	 * Run the full consumer lifecycle:
	 *  1. Execute all delegate consumers (startup scan / retry scan).
	 *  2. If inotify is available, enter the inotify event loop.
	 *  3. Otherwise, fall back to a polling loop.
	 *
	 * The optional $running reference allows external SIGTERM/SIGHUP handlers
	 * to gracefully exit the loop.
	 *
	 * @param settings $settings  Application settings.
	 * @param callable $on_record Callback: fn(xml_cdr_record): void
	 * @param bool     &$running  Loop-control flag; set to false to stop.
	 *
	 * @return void
	 */
	public function consume(settings $settings, callable $on_record, bool &$running = true): void {
		// 1. Run all delegates first (startup scan / retry pass)
		foreach ($this->delegates as $delegate) {
			$delegate->consume($settings, $on_record);
		}

		// 2. Choose event source: inotify preferred, polling as fallback
		if (function_exists('inotify_init')) {
			$this->inotify_loop($settings, $on_record, $running);
		} else {
			$this->polling_loop($settings, $on_record, $running);
		}
	}
	// Private
	private function inotify_loop(settings $settings, callable $on_record, bool &$running): void {
		$fd = inotify_init();
		if ($fd === false) {
			// inotify_init failed; fall back to polling
			$this->polling_loop($settings, $on_record, $running);
			return;
		}

		// Make reads non-blocking so we can check $running periodically
		stream_set_blocking($fd, false);

		$watch = inotify_add_watch($fd, $this->cdr_dir, IN_CLOSE_WRITE | IN_MOVED_TO);
		if ($watch === false) {
			fclose($fd);
			$this->polling_loop($settings, $on_record, $running);
			return;
		}

		while ($running) {
			$events = inotify_read($fd);

			if (empty($events)) {
				// No events yet — sleep briefly to avoid a busy-wait
				usleep(500000); // 0.5 s
				continue;
			}

			foreach ($events as $event) {
				if ($event['mask'] & IN_Q_OVERFLOW) {
					// Queue overflowed — re-run all delegate consumers
					foreach ($this->delegates as $delegate) {
						$delegate->consume($settings, $on_record);
					}
					continue;
				}

				if (!isset($event['name'])) {
					continue;
				}

				$filename = $event['name'];
				if (!$this->is_cdr_file($filename)) {
					continue;
				}

				$full_path = $this->cdr_dir . '/' . $filename;
				if (!is_file($full_path)) {
					continue;
				}

				$raw = file_get_contents($full_path);
				if ($raw === false || strlen($raw) < 1) {
					continue;
				}

				if (str_ends_with($filename, '.cdr.json')) {
					$record = xml_cdr_record::from_json($raw, $full_path);
				} else {
					$record = xml_cdr_record::from_xml($raw, $full_path);
				}

				$on_record($record);
			}
		}

		inotify_rm_watch($fd, $watch);
		fclose($fd);
	}

	private function polling_loop(settings $settings, callable $on_record, bool &$running): void {
		while ($running) {
			foreach ($this->delegates as $delegate) {
				$delegate->consume($settings, $on_record);
			}
			sleep($this->idle_time);
		}
	}

	private function is_cdr_file(string $filename): bool {
		return str_ends_with($filename, '.cdr.xml') || str_ends_with($filename, '.cdr.json');
	}

}
