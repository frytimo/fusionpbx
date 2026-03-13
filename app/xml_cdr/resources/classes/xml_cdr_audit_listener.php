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
 * Writes an audit log entry to the database for each successfully processed
 * CDR record.
 *
 * Active only when the 'cdr.audit_log' setting is true (default: false).
 *
 * The audit log records: timestamp, source filename, uuid, domain, status,
 * and direction.  It writes to the v_xml_cdr_log table using the 'audit'
 * log_type to distinguish from the raw CDR log written by xml_cdr_database_writer.
 */
class xml_cdr_audit_listener implements xml_cdr_listener {

	/** @var database Database connection. */
	private database $database;

	/**
	 * @param database $database Active database connection.
	 */
	public function __construct(database $database) {
		$this->database = $database;
	}

	/**
	 * Write an audit log entry to v_xml_cdr_log when cdr.audit_log is enabled.
	 *
	 * @param settings       $settings Application settings.
	 * @param xml_cdr_record $record   The fully-processed CDR record.
	 *
	 * @return void
	 */
	public function on_cdr(settings $settings, xml_cdr_record $record): void {
		if (!$settings->get('cdr', 'audit_log', false)) {
			return;
		}

		$uuid        = $record->xml_cdr_uuid ?? '';
		$domain_uuid = $record->domain_uuid ?? '';

		if (empty($uuid)) {
			return;
		}

		$content = json_encode([
			'event'            => 'cdr_processed',
			'timestamp'        => date('c'),
			'source_filename'  => $record->source_filename ?? '',
			'xml_cdr_uuid'     => $uuid,
			'domain_uuid'      => $domain_uuid,
			'domain_name'      => $record->domain_name ?? '',
			'status'           => $record->status ?? '',
			'direction'        => $record->direction ?? '',
			'missed_call'      => $record->missed_call ?? '',
		]);

		$log = [];
		$log['xml_cdr_log'][0] = [
			'xml_cdr_log_uuid' => uuid(),
			'xml_cdr_uuid'     => $uuid,
			'domain_uuid'      => $domain_uuid,
			'log_date'         => 'now()',
			'log_type'         => 'audit',
			'log_content'      => $content,
		];

		$this->database->save($log);
	}

}
