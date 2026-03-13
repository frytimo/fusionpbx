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
 * Resolves call-center UUIDs and normalises _undef_ placeholders.
 *
 * FreeSWITCH sets call-center channel variables to the literal string
 * '_undef_' when the variable has no value.  This enricher converts
 * those placeholders to empty strings and looks up
 * call_center_queue_uuid when only the queue extension name is known.
 *
 * Priority: 30 — runs after extension enricher.
 */
class enricher_call_center implements xml_cdr_enricher {

	/** @var database Database connection. */
	private database $database;

	/**
	 * @param database $database Active database connection.
	 */
	public function __construct(database $database) {
		$this->database = $database;
	}

	/**
	 * Returns the sort priority; lower values run first in the enricher chain.
	 *
	 * @return int
	 */
	public function priority(): int {
		return 30;
	}

	/**
	 * Normalise call-center placeholder variables and resolve call_center_queue_uuid.
	 *
	 * @param settings       $settings Application settings.
	 * @param xml_cdr_record $record   CDR record to enrich.
	 *
	 * @return void
	 */
	public function __invoke(settings $settings, xml_cdr_record $record): void {
		$xml = $record->parsed();
		if ($xml === null) {
			return;
		}

		// Normalise _undef_ placeholders
		$undef_vars = [
			'cc_member_uuid',
			'cc_member_session_uuid',
			'cc_agent_uuid',
			'call_center_queue_uuid',
			'cc_queue_joined_epoch',
		];
		foreach ($undef_vars as $var) {
			if (isset($xml->variables->{$var}) && (string)$xml->variables->{$var} === '_undef_') {
				$xml->variables->{$var} = '';
			}
		}

		// Resolve call_center_queue_uuid if missing but queue name is known
		if (empty($record->call_center_queue_uuid) && !empty($xml->variables->cc_queue)) {
			$domain_uuid = $record->domain_uuid ?? '';
			if (!empty($domain_uuid)) {
				$sql  = "SELECT call_center_queue_uuid FROM v_call_center_queues ";
				$sql .= "WHERE domain_uuid = :domain_uuid ";
				$sql .= "AND queue_extension = :queue_extension LIMIT 1";
				$params = [
					'domain_uuid'     => $domain_uuid,
					'queue_extension' => explode('@', (string)$xml->variables->cc_queue)[0],
				];
				$uuid = $this->database->select($sql, $params, 'column');
				if (!empty($uuid)) {
					$record->call_center_queue_uuid = $uuid;
				}
			}
		}
	}

}
