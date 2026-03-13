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
 * Interface for xml_cdr enrichers.
 *
 * Enrichers run before modifiers. They are responsible for adding external
 * data to the record via database lookups (e.g. resolving domain_uuid, 
 * extension_uuid). They must not perform field transformations — those
 * belong in modifiers.
 *
 * Implementations MAY throw:
 *  - xml_cdr_skip_exception    to halt pipeline and keep the source file
 *  - xml_cdr_discard_exception to halt pipeline and delete the source file
 *
 * Implementations MUST NOT write to the database or perform I/O side effects.
 */
interface xml_cdr_enricher {

	/**
	 * Enrich the record with external data from the provided settings/database.
	 *
	 * @param settings      $settings Application settings (provides database access).
	 * @param xml_cdr_record $record  The CDR record to enrich in-place.
	 *
	 * @return void
	 * @throws xml_cdr_skip_exception    If this record should be skipped.
	 * @throws xml_cdr_discard_exception If this record should be discarded.
	 */
	public function __invoke(settings $settings, xml_cdr_record $record): void;

	/**
	 * Priority for ordering enrichers (lower value = runs earlier).
	 * Default should be 100.
	 *
	 * @return int
	 */
	public function priority(): int;

}
