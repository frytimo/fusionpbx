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
 * Scans the xml_cdr directory for existing .cdr.xml and .cdr.json files
 * and delivers each one to the pipeline via the $on_record callable.
 *
 * This consumer is primarily used for:
 *  1. Startup scans (before inotify takes over).
 *  2. Periodic full-scan intervals.
 *  3. Overflow handling when inotify queue exceeds its limit.
 *
 * Files are read with readdir() in a loop until the directory is empty,
 * matching the existing xml_cdr_service behaviour.
 */
class xml_cdr_filesystem_consumer implements xml_cdr_consumer {

	/** @var string Full path to the xml_cdr directory. */
	private string $cdr_dir;

	/** @var int Maximum file size in bytes; oversized files are moved to failed/size. */
	private int $max_file_size;

	/** @var string Full path to the failed base directory. */
	private string $failed_dir;

	/**
	 * @param string $cdr_dir       Full path to the xml_cdr directory.
	 * @param int    $max_file_size Max file size in bytes.
	 */
	public function __construct(string $cdr_dir, int $max_file_size = 3145727) {
		$this->cdr_dir       = rtrim($cdr_dir, '/');
		$this->max_file_size = $max_file_size;
		$this->failed_dir    = $this->cdr_dir . '/failed';
	}

	/**
	 * Static factory — reads configuration from settings.
	 *
	 * @param settings $settings Application settings.
	 *
	 * @return self
	 */
	public static function create(settings $settings): self {
		$cdr_dir       = $settings->get('switch', 'log', '/var/log/freeswitch') . '/xml_cdr';
		$max_file_size = (int)$settings->get('cdr', 'max_file_size', 3145727);
		return new self($cdr_dir, $max_file_size);
	}

	/**
	 * Scan the CDR directory and invoke $on_record for each valid CDR file found.
	 *
	 * Loops until the directory is empty (files are deleted or moved by listeners,
	 * so new iterations will eventually find nothing left).
	 *
	 * @param settings $settings  Application settings.
	 * @param callable $on_record Callback: fn(xml_cdr_record): void
	 *
	 * @return void
	 */
	public function consume(settings $settings, callable $on_record): void {
		do {
			$handle = opendir($this->cdr_dir);
			if (!$handle) {
				return;
			}

			$found = false;
			while (false !== ($filename = readdir($handle))) {
				// Skip non-CDR files and subdirectories
				if (!$this->is_cdr_file($filename)) {
					continue;
				}

				$full_path = $this->cdr_dir . '/' . $filename;

				if (is_dir($full_path)) {
					continue;
				}

				$found = true;

				// Guard: file must have content
				$size = filesize($full_path);
				if ($size < 1) {
					$this->move_to_failed($full_path, 'size');
					continue;
				}

				// Guard: file must not exceed max size
				if ($size > $this->max_file_size) {
					$this->move_to_failed($full_path, 'size');
					continue;
				}

				$raw = file_get_contents($full_path);
				if ($raw === false) {
					$this->move_to_failed($full_path, 'xml');
					continue;
				}

				// Build the record and deliver it
				if (str_ends_with($filename, '.cdr.json')) {
					$record = xml_cdr_record::from_json($raw, $full_path);
				} else {
					$record = xml_cdr_record::from_xml($raw, $full_path);
				}

				$on_record($record);
			}

			closedir($handle);

			// Keep looping while files are present (listeners may have moved/deleted files
			// during this iteration, allowing new files to be found next round).
		} while ($found);
	}
	// Private helpers
	private function is_cdr_file(string $filename): bool {
		return str_ends_with($filename, '.cdr.xml') || str_ends_with($filename, '.cdr.json');
	}

	private function move_to_failed(string $source, string $sub): void {
		$dest_dir = $this->failed_dir . '/' . $sub;
		if (!is_dir($dest_dir)) {
			mkdir($dest_dir, 0770, true);
		}
		rename($source, $dest_dir . '/' . basename($source));
	}

}
