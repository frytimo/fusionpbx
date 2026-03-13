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
 * Resolves caller ID name and number from the CDR XML.
 *
 * Priority chain (highest wins, matching xml_cdr::xml_array() logic):
 *  1. First callflow caller_profile (caller_id_name / caller_id_number).
 *  2. variables.caller_id_name / caller_id_number.
 *  3. origination_caller_id_name / number (overrides 1-2).
 *  4. effective_caller_id_name / number (overrides previous).
 *  5. If last_app == 'intercept': last_sent_callee_id_name/number.
 *  6. If sip_from_domain != domain_name: sip_from_display / sip_from_user.
 *
 * Caller ID name is sanitised to [a-zA-Z0-9\-.#*@ ].
 * Caller ID number is sanitised to [0-9\-#*].
 *
 * Also extracts: context, destination_number, network_addr, caller_destination.
 *
 * Priority: 20.
 */
class modifier_caller_id implements xml_cdr_modifier {

	/**
	 * Returns the sort priority; lower values run first in the modifier chain.
	 *
	 * @return int
	 */
	public function priority(): int {
		return 20;
	}

	/**
	 * Extract and normalise all caller-ID, direction, and routing fields from the CDR XML.
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

		// Step 1: first callflow caller_profile
		$caller_id_name   = null;
		$caller_id_number = null;
		$context            = null;
		$destination_number = null;
		$network_addr       = null;

		$i = 0;
		foreach ($xml->callflow as $row) {
			if ($i === 0) {
				$caller_id_name     = urldecode((string)$row->caller_profile->caller_id_name);
				$caller_id_number   = urldecode((string)$row->caller_profile->caller_id_number);
				$context            = urldecode((string)$row->caller_profile->context);
				$destination_number = urldecode((string)$row->caller_profile->destination_number);
				$network_addr       = urldecode((string)$row->caller_profile->network_addr);
			}
			$i++;
		}

		// Step 2: variables fallback
		if (empty($caller_id_name) && isset($xml->variables->caller_id_name)) {
			$caller_id_name = urldecode((string)$xml->variables->caller_id_name);
		}
		if (empty($caller_id_number) && isset($xml->variables->caller_id_number)) {
			$caller_id_number = urldecode((string)$xml->variables->caller_id_number);
		}
		if (empty($caller_id_number) && isset($xml->variables->sip_from_user)) {
			$caller_id_number = urldecode((string)$xml->variables->sip_from_user);
		}

		// Step 3: origination override
		if (isset($xml->variables->origination_caller_id_name)) {
			$caller_id_name = urldecode((string)$xml->variables->origination_caller_id_name);
		}
		if (isset($xml->variables->origination_caller_id_number)) {
			$caller_id_number = urldecode((string)$xml->variables->origination_caller_id_number);
		}

		// Step 4: effective override
		if (isset($xml->variables->effective_caller_id_name)) {
			$caller_id_name = urldecode((string)$xml->variables->effective_caller_id_name);
		}
		if (isset($xml->variables->effective_caller_id_number)) {
			$caller_id_number = urldecode((string)$xml->variables->effective_caller_id_number);
		}

		// Step 5: intercept override
		if (isset($xml->variables->last_app) && (string)$xml->variables->last_app === 'intercept') {
			if (!empty($xml->variables->last_sent_callee_id_name)) {
				$caller_id_name = urldecode((string)$xml->variables->last_sent_callee_id_name);
			}
			if (!empty($xml->variables->last_sent_callee_id_number)) {
				$caller_id_number = urldecode((string)$xml->variables->last_sent_callee_id_number);
			}
		}

		// Step 6: cross-domain inbound-forward correction
		$sip_from_domain = urldecode((string)($xml->variables->sip_from_domain ?? ''));
		$domain_name_var = urldecode((string)($xml->variables->domain_name ?? ''));
		if (!empty($sip_from_domain) && !empty($domain_name_var) && $sip_from_domain !== $domain_name_var) {
			if (isset($xml->variables->sip_from_display)) {
				$caller_id_name = urldecode((string)$xml->variables->sip_from_display);
			}
			if (isset($xml->variables->sip_from_user)) {
				$caller_id_number = urldecode((string)$xml->variables->sip_from_user);
			}
		}

		// Sanitise
		$record->caller_id_name   = preg_replace('#[^a-zA-Z0-9\-.\#*@ ]#', '', (string)$caller_id_name);
		$record->caller_id_number = preg_replace('#[^0-9\-\#\*]#', '', (string)$caller_id_number);

		// Destination
		if (!empty($destination_number)) {
			$record->destination_number = $destination_number;
		}
		if (!empty($context)) {
			$record->context = $context;
		}
		if (!empty($network_addr)) {
			$record->network_addr = $network_addr;
		}

		// caller_destination
		$caller_destination = null;
		if (isset($xml->variables->caller_destination)) {
			$caller_destination = urldecode((string)$xml->variables->caller_destination);
		}
		if (isset($xml->variables->sip_h_caller_destination)) {
			$caller_destination = urldecode((string)$xml->variables->sip_h_caller_destination);
		}
		if (empty($caller_destination) && isset($xml->variables->dialed_user)) {
			$caller_destination = urldecode((string)$xml->variables->dialed_user);
		}
		if (!empty($caller_destination)) {
			$record->caller_destination = $caller_destination;
		}

		// call_direction
		if (isset($xml->variables->call_direction)) {
			$record->direction = urldecode((string)$xml->variables->call_direction);
		}

		// accountcode
		if (isset($xml->variables->accountcode)) {
			$record->accountcode = urldecode((string)$xml->variables->accountcode);
		}

		// source_number
		if (isset($xml->variables->effective_caller_id_number)) {
			$record->source_number = urldecode((string)$xml->variables->effective_caller_id_number);
		}

		// network addr from sip_network_ip (override callflow value if present)
		if (isset($xml->variables->sip_network_ip)) {
			$record->network_addr = urldecode((string)$xml->variables->sip_network_ip);
		}

		// sip_call_id
		if (isset($xml->variables->sip_call_id)) {
			$record->sip_call_id = urldecode((string)$xml->variables->sip_call_id);
		}

		// pin_number
		if (isset($xml->variables->pin_number)) {
			$record->pin_number = urldecode((string)$xml->variables->pin_number);
		}

		// domain fields
		$record->domain_name = urldecode((string)($xml->variables->domain_name ?? ''));
		$record->domain_uuid = urldecode((string)($xml->variables->domain_uuid ?? '')) ?: $record->domain_uuid;
	}

}
