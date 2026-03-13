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
 * Filesystem-backed retry and dead-letter queue for CDR records that failed
 * database insertion.
 *
 * Directory structure under $failed_dir:
 *   failed/retry/<uuid>.cdr.xml          — copy of the original CDR content
 *   failed/retry/<uuid>.cdr.xml.retry    — JSON sidecar: {attempt, next_retry, reason}
 *   failed/dead/<uuid>.cdr.xml           — moved here after max_attempts exceeded
 *
 * Backoff formula: next_retry = now + (attempt² × base_interval_seconds)
 * Default base_interval_seconds: 60  (attempt 1 = 60s, 2 = 240s, 3 = 540s …)
 * Default max_attempts:          5
 */
class xml_cdr_retry_queue {

	const DEFAULT_MAX_ATTEMPTS      = 5;
	const DEFAULT_BASE_INTERVAL_SEC = 60;
	// Enqueue
	/**
	 * Enqueue a failed record for retry.
	 *
	 * Writes the CDR content and a JSON sidecar with retry metadata.
	 *
	 * @param xml_cdr_record $record          The record that failed.
	 * @param string         $failed_dir      Base failed directory (e.g. /var/.../xml_cdr/failed).
	 * @param string         $reason          Human-readable failure reason.
	 * @param int            $attempt         The attempt number (1 = first retry).
	 * @param int            $base_interval   Backoff base in seconds.
	 *
	 * @return void
	 */
	public static function enqueue(
		xml_cdr_record $record,
		string $failed_dir,
		string $reason,
		int $attempt = 1,
		int $base_interval = self::DEFAULT_BASE_INTERVAL_SEC
	): void {
		$retry_dir = $failed_dir . '/retry';
		if (!is_dir($retry_dir)) {
			mkdir($retry_dir, 0770, true);
		}

		// Use the CDR UUID as filename when available, else the original filename
		$name     = !empty($record->xml_cdr_uuid) ? $record->xml_cdr_uuid . '.cdr.xml'
		                                           : $record->source_filename;
		$cdr_path = $retry_dir . '/' . $name;

		// Write CDR content
		file_put_contents($cdr_path, $record->get_raw_content());

		// Write sidecar metadata
		$next_retry = time() + (($attempt ** 2) * $base_interval);
		$meta = [
			'attempt'     => $attempt,
			'next_retry'  => $next_retry,
			'reason'      => $reason,
			'source_file' => $record->source_filename,
			'format'      => $record->format,
		];
		file_put_contents($cdr_path . '.retry', json_encode($meta, JSON_PRETTY_PRINT));
	}
	// Retry scan
	/**
	 * Yield all records from the retry directory that are due for re-processing.
	 *
	 * Each yielded value is an array with keys:
	 *   'record'  => xml_cdr_record
	 *   'attempt' => int
	 *   'cdr_path'=> string (full path to .retry.cdr.xml file)
	 *
	 * @param string $failed_dir  Base failed directory.
	 *
	 * @return Generator
	 */
	public static function get_due_records(string $failed_dir): Generator {
		$retry_dir = $failed_dir . '/retry';
		if (!is_dir($retry_dir)) {
			return;
		}

		$now = time();
		foreach (glob($retry_dir . '/*.cdr.xml') as $cdr_path) {
			$sidecar = $cdr_path . '.retry';
			if (!file_exists($sidecar)) {
				continue;
			}

			$meta = json_decode(file_get_contents($sidecar), true);
			if (empty($meta) || ($meta['next_retry'] ?? PHP_INT_MAX) > $now) {
				continue;
			}

			$raw    = file_get_contents($cdr_path);
			$format = $meta['format'] ?? 'xml';
			if ($format === 'json') {
				$record = xml_cdr_record::from_json($raw, $cdr_path);
			} else {
				$record = xml_cdr_record::from_xml($raw, $cdr_path);
			}

			yield [
				'record'   => $record,
				'attempt'  => (int)($meta['attempt'] ?? 1),
				'cdr_path' => $cdr_path,
			];
		}
	}
	// Dead-letter promotion
	/**
	 * Move a CDR record (and its sidecar) to the dead-letter directory.
	 *
	 * Should be called when max_attempts is exceeded.
	 * Fires notifiers via xml_cdr_pipeline::fire_notifiers() after moving.
	 *
	 * @param string         $cdr_path    Full path to the .cdr.xml file in retry/.
	 * @param string         $failed_dir  Base failed directory.
	 * @param xml_cdr_record $record      The record being promoted.
	 * @param array          $notifiers   Instantiated xml_cdr_notifier objects.
	 * @param settings       $settings    Application settings.
	 *
	 * @return void
	 */
	public static function promote_to_dead_letter(
		string $cdr_path,
		string $failed_dir,
		xml_cdr_record $record,
		array $notifiers,
		settings $settings
	): void {
		$dead_dir = $failed_dir . '/dead';
		if (!is_dir($dead_dir)) {
			mkdir($dead_dir, 0770, true);
		}

		$filename = basename($cdr_path);
		$dest     = $dead_dir . '/' . $filename;

		rename($cdr_path, $dest);

		// Also move the sidecar if it exists
		$sidecar = $cdr_path . '.retry';
		if (file_exists($sidecar)) {
			rename($sidecar, $dead_dir . '/' . $filename . '.retry');
		}

		// Notify interested parties
		xml_cdr_pipeline::fire_notifiers(
			$notifiers, $settings, $record,
			'dead_letter',
			'Max retry attempts exceeded; moved to dead-letter queue'
		);
	}
	// Remove from retry queue
	/**
	 * Remove a successfully re-processed record from the retry directory.
	 *
	 * @param string $cdr_path Full path to the .cdr.xml file in retry/.
	 *
	 * @return void
	 */
	public static function remove(string $cdr_path): void {
		if (file_exists($cdr_path)) {
			unlink($cdr_path);
		}
		$sidecar = $cdr_path . '.retry';
		if (file_exists($sidecar)) {
			unlink($sidecar);
		}
	}

}
