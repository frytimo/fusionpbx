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
 * Determines the overall call status.
 *
 * Applies the same rule chain as xml_cdr::xml_array() — later rules
 * override earlier ones.  Possible values: answered, no_answer, cancelled,
 * busy, failed, missed, voicemail.
 *
 * Also extracts all time/epoch fields and codec fields.
 *
 * NOTE: modifier_missed_call must have already set $record->missed_call.
 *
 * Priority: 35.
 */
class modifier_call_status implements xml_cdr_modifier {

	const FAILED_HANGUP_CAUSES = [
		'CALL_REJECTED',
		'CHAN_NOT_IMPLEMENTED',
		'DESTINATION_OUT_OF_ORDER',
		'EXCHANGE_ROUTING_ERROR',
		'INCOMPATIBLE_DESTINATION',
		'INVALID_NUMBER_FORMAT',
		'MANDATORY_IE_MISSING',
		'NETWORK_OUT_OF_ORDER',
		'NORMAL_TEMPORARY_FAILURE',
		'NORMAL_UNSPECIFIED',
		'NO_ROUTE_DESTINATION',
		'RECOVERY_ON_TIMER_EXPIRE',
		'REQUESTED_CHAN_UNAVAIL',
		'SUBSCRIBER_ABSENT',
		'SYSTEM_SHUTDOWN',
		'UNALLOCATED_NUMBER',
	];

	/**
	 * Returns the sort priority; lower values run first in the modifier chain.
	 *
	 * @return int
	 */
	public function priority(): int {
		return 35;
	}

	/**
	 * Derive timing fields, call status (answered/missed/voicemail/busy/failed), and epoch values.
	 *
	 * @param settings       $settings Application settings.
	 * @param xml_cdr_record $record   CDR record to modify.
	 *
	 * @return void
	 * @throws xml_cdr_discard_exception When start_epoch is absent.
	 */
	public function __invoke(settings $settings, xml_cdr_record $record): void {
		$xml = $record->parsed();
		if ($xml === null) {
			return;
		}

		// Validate required epoch
		if (empty($xml->variables->start_epoch)) {
			throw new xml_cdr_discard_exception('Missing start_epoch — invalid CDR record');
		}

		// Time fields
		$start_epoch = (int)urldecode((string)$xml->variables->start_epoch);
		$record->start_epoch = $start_epoch;
		$record->start_stamp = ($start_epoch > 0) ? date('c', $start_epoch) : null;

		$answer_epoch = (int)urldecode((string)($xml->variables->answer_epoch ?? ''));
		$record->answer_epoch = $answer_epoch ?: null;
		$record->answer_stamp = ($answer_epoch > 0) ? date('c', $answer_epoch) : null;

		$end_epoch = (int)urldecode((string)($xml->variables->end_epoch ?? ''));
		$record->end_epoch = $end_epoch ?: null;
		$record->end_stamp = ($end_epoch > 0) ? date('c', $end_epoch) : null;

		$billsec = (int)urldecode((string)($xml->variables->billsec ?? ''));
		$record->billsec  = $billsec;
		$record->duration = $billsec;
		$record->billmsec = (int)urldecode((string)($xml->variables->billmsec ?? ''));
		$record->mduration = $record->billmsec;
		$record->hold_accum_seconds = urldecode((string)($xml->variables->hold_accum_seconds ?? '')) ?: null;

		// Codecs
		$record->read_codec        = urldecode((string)($xml->variables->read_codec ?? '')) ?: null;
		$record->read_rate         = urldecode((string)($xml->variables->read_rate ?? '')) ?: null;
		$record->write_codec       = urldecode((string)($xml->variables->write_codec ?? '')) ?: null;
		$record->write_rate        = urldecode((string)($xml->variables->write_rate ?? '')) ?: null;
		$record->remote_media_ip   = urldecode((string)($xml->variables->remote_media_ip ?? '')) ?: null;
		$record->hangup_cause      = urldecode((string)($xml->variables->hangup_cause ?? '')) ?: null;
		$record->hangup_cause_q850 = urldecode((string)($xml->variables->hangup_cause_q850 ?? '')) ?: null;

		// Misc
		$record->default_language       = urldecode((string)($xml->variables->default_language ?? '')) ?: null;
		$record->last_app               = urldecode((string)($xml->variables->last_app ?? '')) ?: null;
		$record->last_arg               = urldecode((string)($xml->variables->last_arg ?? '')) ?: null;
		$record->originating_leg_uuid   = urldecode((string)($xml->variables->originating_leg_uuid ?? '')) ?: null;
		$record->ring_group_uuid        = urldecode((string)($xml->variables->ring_group_uuid ?? '')) ?: null;
		$record->ivr_menu_uuid          = urldecode((string)($xml->variables->ivr_menu_uuid ?? '')) ?: null;
		$record->conference_name        = urldecode((string)($xml->variables->conference_name ?? '')) ?: null;
		$record->conference_uuid        = urldecode((string)($xml->variables->conference_uuid ?? '')) ?: null;
		$record->conference_member_id   = urldecode((string)($xml->variables->conference_member_id ?? '')) ?: null;
		$record->sip_hangup_disposition = urldecode((string)($xml->variables->sip_hangup_disposition ?? '')) ?: null;

		// bridge_uuid — prefer variables, fall back to last bridge_uuids element
		$bridge_uuid = urldecode((string)($xml->variables->bridge_uuid ?? ''));
		if (empty($bridge_uuid)) {
			foreach ($xml->variables->bridge_uuids as $b) {
				$bridge_uuid = urldecode((string)$b);
			}
		}
		$record->bridge_uuid = $bridge_uuid ?: null;

		// post-dial delay
		$record->pdd_ms = (int)urldecode((string)($xml->variables->progress_mediamsec ?? ''))
			+ (int)urldecode((string)($xml->variables->progressmsec ?? ''));

		// rtp_audio_in_mos
		$mos = urldecode((string)($xml->variables->rtp_audio_in_mos ?? ''));
		if (!empty($mos)) {
			$record->rtp_audio_in_mos = $mos;
		}

		// voicemail
		$record->voicemail_message = (
			!empty($xml->variables->voicemail_answer_stamp)
			&& (int)$xml->variables->voicemail_message_seconds > 0
		) ? 'true' : 'false';

		// provider_uuid
		if (isset($xml->variables->provider_uuid)) {
			$record->provider_uuid = urldecode((string)$xml->variables->provider_uuid);
		}

		// Leg
		$record->leg = $record->leg ?? 'b';

		// cc_side overrides direction to inbound for agent legs
		if (isset($xml->variables->cc_side) && (string)$xml->variables->cc_side === 'agent') {
			$record->direction = 'inbound';
		}

		// Determine status
		$hangup_cause         = $record->hangup_cause ?? '';
		$last_bridge_hangup   = urldecode((string)($xml->variables->last_bridge_hangup_cause ?? ''));
		$destination_number   = $record->destination_number ?? '';

		$status = 'no_answer';

		if ($billsec > 0) {
			$status = 'answered';
		}
		if ($hangup_cause === 'NO_ANSWER') {
			$status = 'no_answer';
		}
		if ($hangup_cause === 'ORIGINATOR_CANCEL') {
			$status = 'cancelled';
		}
		if ($hangup_cause === 'USER_BUSY') {
			$status = 'busy';
		}
		if (in_array($hangup_cause, self::FAILED_HANGUP_CAUSES, true)) {
			$status = 'failed';
		}
		if ($status !== 'failed' && in_array($last_bridge_hangup, self::FAILED_HANGUP_CAUSES, true)) {
			$status = 'failed';
		}
		if (isset($xml->variables->cc_side) && (string)$xml->variables->cc_side === 'agent' && $billsec === 0) {
			$status = 'no_answer';
		}
		if ($record->missed_call === 'true') {
			$status = 'missed';
		}
		if (str_starts_with($destination_number, '*99')) {
			$status = 'voicemail';
		}
		if (!empty($xml->variables->voicemail_message_seconds)) {
			$status = 'voicemail';
		}

		$record->status = $status;
	}

}
