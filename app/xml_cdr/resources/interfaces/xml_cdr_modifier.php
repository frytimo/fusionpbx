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
 * Interface for xml_cdr modifiers.
 *
 * Modifiers run after enrichers. They transform the field values already
 * present on the record (e.g. determining call status, detecting recordings,
 * computing missed_call flag). Each modifier is a focused, single-responsibility
 * transformation.
 *
 * Implementations MUST:
 *  - Mutate $record in-place; never return a new record.
 *  - Treat $settings as read-only configuration.
 *  - Keep changes idempotent where practical.
 *  - Avoid long-running or blocking operations.
 *  - Never write to persistent storage (database, files).
 *
 * Implementations MAY throw:
 *  - xml_cdr_skip_exception    to halt pipeline, keep source file (e.g. duplicate UUID)
 *  - xml_cdr_discard_exception to halt pipeline, delete source file (e.g. call_block)
 */
interface xml_cdr_modifier {

	/**
	 * Modify the CDR record in-place.
	 *
	 * @param settings       $settings Application settings.
	 * @param xml_cdr_record $record   The CDR record to modify.
	 *
	 * @return void
	 * @throws xml_cdr_skip_exception    If processing should halt and the file kept.
	 * @throws xml_cdr_discard_exception If processing should halt and the file deleted.
	 */
	public function __invoke(settings $settings, xml_cdr_record $record): void;

	/**
	 * Priority for ordering modifiers (lower value = runs earlier).
	 * Default should be 100.
	 *
	 * @return int
	 */
	public function priority(): int;

}
