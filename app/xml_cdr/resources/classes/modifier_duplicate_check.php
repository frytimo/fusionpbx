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
 * Checks for duplicate CDR records.
 *
 * If a record with the same xml_cdr_uuid already exists in the database the
 * source file is no longer needed; the modifier throws
 * xml_cdr_discard_exception so the service deletes the file.
 *
 * This mirrors the existing behaviour of xml_cdr::xml_array() where a
 * duplicate UUID causes an early unlink() + return false.
 *
 * Priority: 10 — runs immediately after xml sanitisation.
 */
class modifier_duplicate_check implements xml_cdr_modifier {

	/** @var database Database connection. */
	private database $database;

	/**
	 * @param database $database Active database connection.
	 */
	public function __construct(database $database) {
		$this->database = $database;
	}

	/**
	 * Returns the sort priority; lower values run first in the modifier chain.
	 *
	 * @return int
	 */
	public function priority(): int {
		return 10;
	}

	/**
	 * Skip the CDR if a row with the same UUID already exists in v_xml_cdr.
	 *
	 * @param settings       $settings Application settings.
	 * @param xml_cdr_record $record   CDR record to evaluate.
	 *
	 * @return void
	 * @throws xml_cdr_skip_exception When a duplicate UUID is found.
	 */
	public function __invoke(settings $settings, xml_cdr_record $record): void {
		$xml = $record->parsed();
		if ($xml === null) {
			return;
		}

		// Extract UUID
		$uuid = trim(urldecode((string)($xml->variables->uuid ?? '')));
		if (empty($uuid)) {
			$uuid = trim(urldecode((string)($xml->variables->call_uuid ?? '')));
		}

		if (empty($uuid) || !is_uuid($uuid)) {
			return;
		}

		// Store on the record for downstream modifiers
		$record->xml_cdr_uuid = $uuid;

		// Check for existing record
		$sql    = "SELECT COUNT(xml_cdr_uuid) FROM v_xml_cdr WHERE xml_cdr_uuid = :uuid";
		$params = ['uuid' => $uuid];
		$count  = (int)$this->database->select($sql, $params, 'column');

		if ($count > 0) {
			throw new xml_cdr_discard_exception("Duplicate uuid: $uuid");
		}
	}

}
