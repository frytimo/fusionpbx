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
 * Handles call-block CDR records.
 *
 * When the call_block channel variable is 'true' and the
 * 'call_block.save_call_detail_record' setting is false (default true),
 * the CDR record should be discarded (file deleted, no DB write).
 *
 * Priority: 10 — same tier as duplicate check; call-block is checked
 * before any field transformation is done.
 */
class modifier_call_block implements xml_cdr_modifier {

	/**
	 * Returns the sort priority; lower values run first in the modifier chain.
	 *
	 * @return int
	 */
	public function priority(): int {
		return 10;
	}

	/**
	 * Discard the CDR when call_block is set and the save-CDR setting is disabled.
	 *
	 * @param settings       $settings Application settings.
	 * @param xml_cdr_record $record   CDR record to evaluate.
	 *
	 * @return void
	 * @throws xml_cdr_discard_exception When the CDR should be discarded.
	 */
	public function __invoke(settings $settings, xml_cdr_record $record): void {
		$xml = $record->parsed();
		if ($xml === null) {
			return;
		}

		if (
			isset($xml->variables->call_block)
			&& (string)$xml->variables->call_block === 'true'
			&& !$settings->get('call_block', 'save_call_detail_record', true)
		) {
			throw new xml_cdr_discard_exception('call_block: save_call_detail_record disabled');
		}
	}

}
