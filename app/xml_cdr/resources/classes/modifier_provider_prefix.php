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
 * Strips the provider prefix from the destination_number.
 *
 * When the provider_prefix channel variable is present and the
 * destination_number starts with that prefix, the prefix is removed.
 *
 * Priority: 25 — runs after caller_id extraction.
 */
class modifier_provider_prefix implements xml_cdr_modifier {

	/**
	 * Returns the sort priority; lower values run first in the modifier chain.
	 *
	 * @return int
	 */
	public function priority(): int {
		return 25;
	}

	/**
	 * Strip the provider_prefix from the start of destination_number.
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

		if (!isset($xml->variables->provider_prefix)) {
			return;
		}

		$prefix = (string)$xml->variables->provider_prefix;
		if (empty($prefix)) {
			return;
		}

		$destination = $record->destination_number ?? '';
		if (str_starts_with($destination, $prefix)) {
			$record->destination_number = substr($destination, strlen($prefix));
		}
	}

}
