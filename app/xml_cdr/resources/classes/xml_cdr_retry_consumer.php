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
 * Reads records from the filesystem-backed retry queue and delivers
 * each due record back into the pipeline for re-processing.
 *
 * Retry records are stored in <failed_dir>/retry/ by xml_cdr_retry_queue.
 * Each record has a sidecar JSON metadata file that records the attempt
 * count and the earliest time the record should be re-tried.
 *
 * When the maximum attempt count is reached the record is promoted to
 * the dead-letter queue via xml_cdr_retry_queue::promote_to_dead_letter().
 */
class xml_cdr_retry_consumer implements xml_cdr_consumer {

	/** @var string Base failed directory (parent of retry/, dead/). */
	private string $failed_dir;

	/** @var int Maximum number of attempts before dead-letter promotion. */
	private int $max_attempts;

	/** @var int Base back-off interval in seconds. */
	private int $base_interval;

	/** @var array xml_cdr_notifier[] Active notifiers (for dead-letter events). */
	private array $notifiers;

	/**
	 * @param string $failed_dir    Full path to the failed base directory.
	 * @param int    $max_attempts  Maximum retry attempts.
	 * @param int    $base_interval Base back-off in seconds.
	 * @param array  $notifiers     Instantiated xml_cdr_notifier objects.
	 */
	public function __construct(
		string $failed_dir,
		int $max_attempts = 5,
		int $base_interval = 60,
		array $notifiers = []
	) {
		$this->failed_dir    = rtrim($failed_dir, '/');
		$this->max_attempts  = $max_attempts;
		$this->base_interval = $base_interval;
		$this->notifiers     = $notifiers;
	}

	/**
	 * Static factory.
	 *
	 * @param settings $settings  Application settings.
	 * @param array    $notifiers Instantiated xml_cdr_notifier objects.
	 *
	 * @return self
	 */
	public static function create(settings $settings, array $notifiers = []): self {
		$cdr_dir       = $settings->get('switch', 'log', '/var/log/freeswitch') . '/xml_cdr';
		$failed_dir    = $cdr_dir . '/failed';
		$max_attempts  = (int)$settings->get('cdr', 'retry_max_attempts', 5);
		$base_interval = (int)$settings->get('cdr', 'retry_base_interval', 60);
		return new self($failed_dir, $max_attempts, $base_interval, $notifiers);
	}

	/**
	 * Deliver all due retry records to the pipeline via $on_record.
	 *
	 * Records that fail again will be re-enqueued (or promoted to dead-letter)
	 * by the caller (xml_cdr_service) after the pipeline processes them and
	 * throws — exactly the same error path as for freshly ingested files.
	 *
	 * @param settings $settings  Application settings.
	 * @param callable $on_record Callback: fn(xml_cdr_record): void
	 *
	 * @return void
	 */
	public function consume(settings $settings, callable $on_record): void {
		foreach (xml_cdr_retry_queue::get_due_records($this->failed_dir) as $item) {
			/** @var xml_cdr_record $record */
			$record   = $item['record'];
			$attempt  = (int)$item['attempt'];
			$cdr_path = (string)$item['cdr_path'];

			if ($attempt >= $this->max_attempts) {
				// Max attempts exhausted — promote to dead-letter
				xml_cdr_retry_queue::promote_to_dead_letter(
					$cdr_path,
					$this->failed_dir,
					$record,
					$this->notifiers,
					$settings
				);
				continue;
			}

			// Tag the record so the pipeline / service knows it is a retry
			$record->set_field('retry_attempt', $attempt);
			$record->set_field('retry_cdr_path', $cdr_path);

			$on_record($record);
		}
	}

}
