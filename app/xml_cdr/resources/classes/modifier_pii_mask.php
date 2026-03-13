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
 * Masks Personally Identifiable Information (PII) fields on the record.
 *
 * This modifier is intended for use on a *clone* of the CDR record when
 * writing debug/audit output that should not expose caller numbers or names.
 * It MUST NOT be applied to the canonical record that is saved to the database.
 *
 * Enabled only when the 'cdr.pii_mask_debug_output' setting is true.
 * The debug writer (xml_cdr_console_debug_writer) creates a clone of the
 * record, applies this modifier, and logs the masked clone.
 *
 * Fields masked:
 *  - caller_id_name    → "***"
 *  - caller_id_number  → "***"
 *  - caller_destination → "***"
 *  - source_number     → "***"
 *  - network_addr      → "0.0.0.0"
 *
 * Priority: 999 — must run last.
 */
class modifier_pii_mask implements xml_cdr_modifier {

	/**
	 * Returns the sort priority; lower values run first in the modifier chain.
	 *
	 * @return int
	 */
	public function priority(): int {
		return 999;
	}

	/**
	 * Mask PII fields when cdr.pii_mask_debug_output is enabled.
	 *
	 * This modifier MUST be applied only to a clone of the canonical record.
	 *
	 * @param settings       $settings Application settings.
	 * @param xml_cdr_record $record   CDR record (or clone) to mask.
	 *
	 * @return void
	 */
	public function __invoke(settings $settings, xml_cdr_record $record): void {
		if (!$settings->get('cdr', 'pii_mask_debug_output', false)) {
			return;
		}

		$record->caller_id_name     = '***';
		$record->caller_id_number   = '***';
		$record->caller_destination = '***';
		$record->source_number      = '***';
		$record->network_addr       = '0.0.0.0';
	}

}
