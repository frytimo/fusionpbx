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
 * Determines whether the call should be flagged as a missed call.
 *
 * Applies the same rule chain as xml_cdr::xml_array() — later rules
 * override earlier ones.
 *
 * Priority: 30.
 */
class modifier_missed_call implements xml_cdr_modifier {

	/**
	 * Returns the sort priority; lower values run first in the modifier chain.
	 *
	 * @return int
	 */
	public function priority(): int {
		return 30;
	}

	/**
	 * Determine whether the call was missed and set the missed_call field accordingly.
	 *
	 * @param settings       $settings Application settings.
	 * @param xml_cdr_record $record   CDR record to modify.
	 *
	 * @return void
	 */
	public function __invoke(settings $settings, xml_cdr_record $record): void {
		$xml = $record->parsed();
		if ($xml === null) {
			return;
		}

		$missed_call = 'false';

		// Explicit missed_call variable
		if (isset($xml->variables->missed_call) && (string)$xml->variables->missed_call === 'true') {
			$missed_call = 'true';
		}

		// Inbound + ORIGINATOR_CANCEL
		if (
			$record->direction === 'inbound'
			&& isset($xml->variables->hangup_cause)
			&& (string)$xml->variables->hangup_cause === 'ORIGINATOR_CANCEL'
		) {
			$missed_call = 'true';
		}

		// Call-center agent leg — not missed
		if (isset($xml->variables->cc_side) && (string)$xml->variables->cc_side === 'agent') {
			$missed_call = 'false';
		}

		// Fax — not missed
		if (isset($xml->variables->fax_success)) {
			$missed_call = 'false';
		}

		// Ring-group LOSE_RACE — not missed
		if (isset($xml->variables->hangup_cause) && (string)$xml->variables->hangup_cause === 'LOSE_RACE') {
			$missed_call = 'false';
		}

		// Ring-group NO_ANSWER + originating_leg_uuid — not missed
		if (
			isset($xml->variables->hangup_cause)
			&& (string)$xml->variables->hangup_cause === 'NO_ANSWER'
			&& isset($xml->variables->originating_leg_uuid)
		) {
			$missed_call = 'false';
		}

		// Bridged — not missed
		if (isset($xml->variables->bridge_uuid) && !empty((string)$xml->variables->bridge_uuid)) {
			$missed_call = 'false';
		}

		// CC member cancelled
		if (
			isset($xml->variables->cc_side) && (string)$xml->variables->cc_side === 'member'
			&& isset($xml->variables->cc_cause) && (string)$xml->variables->cc_cause === 'cancel'
		) {
			$missed_call = 'true';
		}

		// Voicemail destination
		$destination_number = $record->destination_number ?? '';
		if (str_starts_with($destination_number, '*99')) {
			$missed_call = 'true';
		}
		if (isset($xml->variables->voicemail_answer_stamp) && !empty((string)$xml->variables->voicemail_answer_stamp)) {
			$missed_call = 'true';
		}

		$record->missed_call = $missed_call;
	}

}
