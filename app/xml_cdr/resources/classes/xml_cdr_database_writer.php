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
 * Saves a processed CDR record to the database.
 *
 * Writes to:
 *  - v_xml_cdr (main record)
 *  - v_xml_cdr_flow (call flow detail)
 *  - v_xml_cdr_json (when cdr.format == 'json' and cdr.storage == 'db')
 *  - v_xml_cdr_log  (when cdr.log == 'true')
 *  - Filesystem archive (when cdr.storage == 'dir')
 *
 * Then deletes the source CDR file from the xml_cdr directory.
 *
 * This listener must NOT throw xml_cdr_pipeline_exception.  All database
 * errors are wrapped and re-thrown as plain RuntimeException so the service
 * can catch them and move the file to failed/sql for retry.
 */
class xml_cdr_database_writer implements xml_cdr_listener {

	/** @var database Database connection. */
	private database $database;

	/**
	 * @param database $database Active database connection.
	 */
	public function __construct(database $database) {
		$this->database = $database;
	}

	/**
	 * Persist the fully-processed CDR to the database and archive to disk if configured.
	 *
	 * Writes to v_xml_cdr, v_xml_cdr_flow, and optionally v_xml_cdr_json, v_xml_cdr_log,
	 * and a filesystem archive directory. Deletes the source CDR file after a successful save.
	 *
	 * @param settings       $settings Application settings.
	 * @param xml_cdr_record $record   The fully-processed CDR record.
	 *
	 * @return void
	 */
	public function on_cdr(settings $settings, xml_cdr_record $record): void {
		$uuid        = $record->xml_cdr_uuid ?? '';
		$domain_uuid = $record->domain_uuid ?? '';
		$domain_name = $record->domain_name ?? '';

		if (empty($uuid) || !is_uuid($uuid)) {
			// Nothing to write without a valid UUID
			return;
		}

		// Main CDR row
		$main = $record->to_array();
		$main['xml_cdr_uuid'] = $uuid;
		if (!empty($domain_uuid)) {
			$main['domain_uuid'] = $domain_uuid;
		}
		if (!empty($domain_name)) {
			$main['domain_name'] = $domain_name;
		}

		$data = [];
		$data['xml_cdr'][0] = $main;

		// Call flow
		$call_details = json_decode(json_encode($record->parsed()), true);
		$data['xml_cdr_flow'][0] = [
			'xml_cdr_flow_uuid' => uuid(),
			'xml_cdr_uuid'      => $uuid,
			'domain_uuid'       => $domain_uuid,
			'call_flow'         => json_encode($this->build_call_flow($call_details)),
		];

		// Optional JSON storage
		if ($settings->get('cdr', 'format') === 'json' && $settings->get('cdr', 'storage') === 'db') {
			$data['xml_cdr_json'][0] = [
				'xml_cdr_json_uuid' => uuid(),
				'xml_cdr_uuid'      => $uuid,
				'domain_uuid'       => $domain_uuid,
				'json'              => json_encode($record->parsed()),
			];
		}

		// Optional raw XML storage
		if ($settings->get('cdr', 'format') === 'xml' && $settings->get('cdr', 'storage') === 'db') {
			$data['xml_cdr'][0]['xml'] = $record->get_raw_content();
		}

		// CDR log
		$log_content = $record->log_content ?? '';
		if (!empty($log_content) && $settings->get('cdr', 'log', false)) {
			$data['xml_cdr_log'][0] = [
				'xml_cdr_log_uuid' => uuid(),
				'xml_cdr_uuid'     => $uuid,
				'domain_uuid'      => $domain_uuid,
				'log_date'         => 'now()',
				'log_content'      => $log_content,
			];
		}

		// Write to database
		$this->database->save($data);

		// Filesystem archive
		if ($settings->get('cdr', 'storage') === 'dir') {
			$this->archive_to_disk($record, $settings, $uuid);
		}

		// Delete the source file now that it has been safely stored
		$source = $record->source_file ?? '';
		if (!empty($source) && file_exists($source)) {
			unlink($source);
		}
	}
	/**
	 * Build the call flow array from decoded call_details (same logic as xml_cdr::call_flow()).
	 */
	private function build_call_flow(?array $call_details): array {
		if (empty($call_details['callflow'])) {
			return [];
		}

		$call_flow_array = $call_details['callflow'];

		// Normalise to indexed array
		if (!isset($call_flow_array[0])) {
			$call_flow_array = [$call_flow_array];
		}

		// Chronological order
		$call_flow_array = array_reverse($call_flow_array);

		// Add profile end times
		$end_uepoch = (string)($call_details['variables']['end_uepoch'] ?? '');
		foreach ($call_flow_array as $i => $row) {
			if (isset($call_flow_array[$i + 1]['times']['profile_created_time'])) {
				$call_flow_array[$i]['times']['profile_end_time'] = $call_flow_array[$i + 1]['times']['profile_created_time'];
			} else {
				$call_flow_array[$i]['times']['profile_end_time'] = urldecode($end_uepoch);
			}
		}

		// Duration formatting
		foreach ($call_flow_array as $i => $row) {
			foreach ($row['times'] as $name => $value) {
				if ($value > 0) {
					$created = (int)($call_flow_array[$i]['times']['profile_created_time'] ?? 0);
					$end     = (int)($call_flow_array[$i]['times']['profile_end_time'] ?? 0);
					$secs    = round($end / 1000000 - $created / 1000000);
					$call_flow_array[$i]['times']['profile_duration_seconds']   = $secs;
					$call_flow_array[$i]['times']['profile_duration_formatted'] = gmdate('G:i:s', (int)$secs);
					break;
				}
			}
		}

		return $call_flow_array;
	}

	private function archive_to_disk(xml_cdr_record $record, settings $settings, string $uuid): void {
		$start_epoch = $record->start_epoch ?? time();
		$archive_dir = $settings->get('switch', 'log', '/var/log/freeswitch')
			. '/xml_cdr/archive/'
			. date('Y/M/d', (int)$start_epoch);

		if (!is_dir($archive_dir)) {
			mkdir($archive_dir, 0770, true);
		}

		$format = $settings->get('cdr', 'format', 'xml');
		if ($format === 'xml') {
			$file = $archive_dir . '/' . $uuid . '.xml';
			file_put_contents($file, $record->get_raw_content());
		} else {
			$file = $archive_dir . '/' . $uuid . '.json';
			file_put_contents($file, json_encode($record->parsed()));
		}
	}

}
