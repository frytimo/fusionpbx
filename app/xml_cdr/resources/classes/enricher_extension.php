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
 * Resolves the extension_uuid for a CDR record.
 *
 * Resolution order (mirroring the existing xml_cdr::xml_array() logic):
 *  1. extension_uuid variable already present in the XML/record → use as-is.
 *  2. DB lookup by dialed_user (extension or number_alias).
 *  3. DB lookup by referred_by_user.
 *  4. DB lookup by last_sent_callee_id_number.
 *  5. DB lookup via cc_agent → agent_id → extension_uuid.
 *
 * Priority: 20 — runs after enricher_domain (which must have set domain_uuid).
 */
class enricher_extension implements xml_cdr_enricher {

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
		return 20;
	}

		/**
		 * Resolve extension_uuid for the CDR record using a multi-step lookup chain.
		 *
		 * @param settings       $settings Application settings.
		 * @param xml_cdr_record $record   CDR record to enrich.
		 *
		 * @return void
		 */
	public function __invoke(settings $settings, xml_cdr_record $record): void {
		// Nothing to do if already resolved
		if (!empty($record->extension_uuid)) {
			return;
		}

		$domain_uuid = $record->domain_uuid ?? '';
		$xml         = $record->parsed();

		if (empty($domain_uuid) || $xml === null) {
			return;
		}

		// 1. From extension_uuid variable
		if (!empty($xml->variables->extension_uuid)) {
			$record->extension_uuid = urldecode((string)$xml->variables->extension_uuid);
			return;
		}

		// 2. dialed_user
		if (!empty($xml->variables->dialed_user)) {
			$uuid = $this->lookup_by_extension((string)$xml->variables->dialed_user, $domain_uuid);
			if (!empty($uuid)) {
				$record->extension_uuid = $uuid;
				return;
			}
		}

		// 3. referred_by_user
		if (!empty($xml->variables->referred_by_user)) {
			$uuid = $this->lookup_by_extension((string)$xml->variables->referred_by_user, $domain_uuid);
			if (!empty($uuid)) {
				$record->extension_uuid = $uuid;
				return;
			}
		}

		// 4. last_sent_callee_id_number
		if (!empty($xml->variables->last_sent_callee_id_number)) {
			$uuid = $this->lookup_by_extension((string)$xml->variables->last_sent_callee_id_number, $domain_uuid);
			if (!empty($uuid)) {
				$record->extension_uuid = $uuid;
				return;
			}
		}

		// 5. cc_agent → agent_id
		if (!empty($xml->variables->cc_agent)) {
			$sql  = "SELECT e.extension_uuid FROM v_extensions e ";
			$sql .= "INNER JOIN v_call_center_agents a ON a.agent_id = e.extension ";
			$sql .= "WHERE e.domain_uuid = :domain_uuid AND a.call_center_agent_uuid = :agent_id LIMIT 1";
			$params = [
				'domain_uuid' => $domain_uuid,
				'agent_id'    => (string)$xml->variables->cc_agent,
			];
			$uuid = $this->database->select($sql, $params, 'column');
			if (!empty($uuid)) {
				$record->extension_uuid = $uuid;
			}
		}
	}
	private function lookup_by_extension(string $user, string $domain_uuid): ?string {
		$sql  = "SELECT extension_uuid FROM v_extensions ";
		$sql .= "WHERE domain_uuid = :domain_uuid ";
		$sql .= "AND (extension = :user OR number_alias = :user) LIMIT 1";
		$params = ['domain_uuid' => $domain_uuid, 'user' => $user];
		$result = $this->database->select($sql, $params, 'column');
		return $result ?: null;
	}

}
