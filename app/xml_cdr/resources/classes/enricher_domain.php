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
 * Resolves the domain UUID for a CDR record.
 *
 * The domain_uuid is looked up from the domains table using the domain_name
 * already present on the record.  If domain_name is empty the enricher is
 * a no-op; a missing domain does not halt the pipeline.
 *
 * Priority: 10 — runs before extension and call-center enrichers.
 */
class enricher_domain implements xml_cdr_enricher {

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
		return 10;
	}

	/**
	 * Resolve domain_uuid for the CDR record from the database.
	 *
	 * @param settings       $settings Application settings.
	 * @param xml_cdr_record $record   CDR record to enrich.
	 *
	 * @return void
	 */
	public function __invoke(settings $settings, xml_cdr_record $record): void {
		if (!empty($record->domain_uuid)) {
			return;
		}

		$domain_name = $record->domain_name ?? '';
		if ($domain_name === '') {
			return;
		}

		$sql = "SELECT domain_uuid FROM v_domains WHERE domain_name = :domain_name LIMIT 1";
		$params = ['domain_name' => $domain_name];
		$row = $this->database->select($sql, $params, 'row');
		if (!empty($row['domain_uuid'])) {
			$record->domain_uuid = $row['domain_uuid'];
		}
	}

}
