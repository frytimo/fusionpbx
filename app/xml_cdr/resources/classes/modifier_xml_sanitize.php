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
 * Validates and sanitises the raw CDR content.
 *
 * Checks:
 *  - Content is not empty.
 *  - For XML format: URL-decodes percent-encoded content, then verifies the
 *    result parses as well-formed XML. If it does not, a single repair pass
 *    strips leaf elements whose tag names contain characters that are illegal
 *    in XML names (e.g. '*' from FreeSWITCH mod_limit variables such as
 *    limit_usage_domain_*992110). If the repaired content parses cleanly it is
 *    stored back via set_raw_content() and processing continues normally.
 *  - For JSON format: verifies the content parses as valid JSON.
 *
 * On failure the modifier throws xml_cdr_discard_exception so the file is
 * moved to failed/xml by the service.
 *
 * Priority: 5 — must run first, before any other modifier.
 */
class modifier_xml_sanitize implements xml_cdr_modifier {

	/**
	 * Priority of this modifier in the processing pipeline.
	 * @return int Relative priority (lower runs earlier)
	 */
	public function priority(): int {
		return 5;
	}

	/**
	 * Validates and sanitises the raw CDR content.
	 *
	 * @param settings       $settings Service settings (unused)
	 * @param xml_cdr_record $record Record with raw content to validate/sanitise
	 *
	 * @return void
	 * @throws xml_cdr_discard_exception if the content is empty or malformed
	 */
	public function __invoke(settings $settings, xml_cdr_record $record): void {
		$raw = $record->get_raw_content();

		if (empty(trim($raw))) {
			throw new xml_cdr_discard_exception('Empty CDR content');
		}

		if ($record->format === 'json') {
			json_decode($raw, true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				throw new xml_cdr_discard_exception('Invalid JSON: ' . json_last_error_msg());
			}
			return;
		}

		// XML path: URL-decode if percent-encoded
		if ($raw[0] === '%') {
			$decoded = urldecode($raw);
			if (empty(trim($decoded))) {
				throw new xml_cdr_discard_exception('CDR content empty after URL-decoding');
			}
			$record->set_raw_content($decoded);
			$raw = $decoded;
		}

		// Verify the XML is well-formed (suppress libxml errors)
		libxml_use_internal_errors(true);
		$result = simplexml_load_string($raw);
		$errors = libxml_get_errors();
		libxml_clear_errors();

		if ($result === false || !empty($errors)) {
			// Attempt a single repair pass: remove leaf elements whose tag names
			// contain characters that are illegal in XML names. The most common
			// case is FreeSWITCH mod_limit variables such as:
			//   <limit_usage_domain_*992110>1</limit_usage_domain_*992110>
			// where '*' makes the tag name invalid per the XML Name production.
			//
			// The pattern only matches tags whose name BEGINS with a valid
			// NameStartChar, ensuring structural CDR elements (<cdr>, <variables>,
			// <callflow>, …) can never be accidentally stripped.
			$repaired = preg_replace(
				'/<([a-zA-Z_:][a-zA-Z0-9._:\-]*[^a-zA-Z0-9._:\-\s>\/][^\s>\/]*)>[^<]*<\/\1>/u',
				'',
				$raw
			);

			$result2 = ($repaired !== null) ? simplexml_load_string($repaired) : false;
			$errors2 = libxml_get_errors();
			libxml_clear_errors();

			if ($result2 !== false && empty($errors2)) {
				// Repair succeeded: store the cleaned content and continue.
				$record->set_raw_content($repaired);
			} else {
				libxml_use_internal_errors(false);
				$first_error = $errors[0]->message ?? 'parse error';
				throw new xml_cdr_discard_exception('Malformed XML: ' . trim($first_error));
			}
		}

		libxml_use_internal_errors(false);
	}

}
