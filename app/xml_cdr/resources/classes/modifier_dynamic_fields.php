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
 * Populates dynamically-configured CDR fields from the settings.
 *
 * Reads the 'cdr.field' multi-value setting which is a list of
 * comma-separated dotted paths into the XML object.  Each path is of
 * the form  "var[,parent[,...]],$field_name".  The modifier walks the
 * SimpleXML tree along the specified path and stores the decoded value
 * as an extra field on the record.
 *
 * Example setting values:
 *   "variables,my_custom_var"       → $xml->variables->my_custom_var
 *   "variables,foo,my_field"        → $xml->variables->foo->my_field
 *
 * Priority: 50 — runs after all core field extractors.
 */
class modifier_dynamic_fields implements xml_cdr_modifier {

	/**
	 * Returns the sort priority; lower values run first in the modifier chain.
	 *
	 * @return int
	 */
	public function priority(): int {
		return 50;
	}

	/**
	 * Extract custom fields configured via the 'cdr' -> 'field' multi-value setting.
	 *
	 * @param settings       $settings Application settings.
	 * @param xml_cdr_record $record   CDR record to modify.
	 *
	 * @return void
	 */
	public function __invoke(settings $settings, xml_cdr_record $record): void {
		$fields = $settings->get('cdr', 'field', []);
		if (empty($fields)) {
			return;
		}

		$xml = $record->parsed();
		if ($xml === null) {
			return;
		}

		foreach ((array)$fields as $field_spec) {
			$parts      = explode(',', $field_spec);
			$field_name = end($parts);

			// Don't overwrite an already-set extra field
			if ($record->get_field($field_name) !== null) {
				continue;
			}

			$count = count($parts);
			switch ($count) {
				case 1:
					$value = urldecode((string)($xml->variables->{$parts[0]} ?? ''));
					break;
				case 2:
					$value = urldecode((string)($xml->{$parts[0]}->{$parts[1]} ?? ''));
					break;
				case 3:
					$value = urldecode((string)($xml->{$parts[0]}->{$parts[1]}->{$parts[2]} ?? ''));
					break;
				case 4:
					$value = urldecode((string)($xml->{$parts[0]}->{$parts[1]}->{$parts[2]}->{$parts[3]} ?? ''));
					break;
				case 5:
					$value = urldecode((string)($xml->{$parts[0]}->{$parts[1]}->{$parts[2]}->{$parts[3]}->{$parts[4]} ?? ''));
					break;
				default:
					continue 2;
			}

			if ($value !== '') {
				$record->set_field($field_name, $value);
			}
		}
	}

}
