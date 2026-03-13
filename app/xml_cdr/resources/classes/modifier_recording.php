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
 * Resolves the call recording path and name from the CDR XML.
 *
 * Resolution order (mirrors xml_cdr::xml_array()):
 *  1. record_path + record_name variables.
 *  2. cc_record_filename.
 *  3. last_app == record_session → last_arg.
 *  4. sofia_record_file.
 *  5. api_on_answer containing uuid_record.
 *  6. conference_recording.
 *  7. current_application_data containing api_on_answer uuid_record.
 *  8. Filesystem check: <recordings_dir>/<domain>/<year>/<month>/<day>/<uuid>.{wav,mp3}.
 *  9. Filesystem check with bridge_uuid.
 *
 * Only populates record_path/record_name if the file exists on disk.
 *
 * Priority: 40.
 */
class modifier_recording implements xml_cdr_modifier {

	/**
	 * Returns the sort priority; lower values run first in the modifier chain.
	 *
	 * @return int
	 */
	public function priority(): int {
		return 40;
	}

	/**
	 * Resolve record_path, record_name, and record_length from the CDR XML.
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

		$record_path   = null;
		$record_name   = null;
		$record_length = null;

		// 1. record_path + record_name
		if (isset($xml->variables->record_path) && isset($xml->variables->record_name)) {
			$record_path   = urldecode((string)$xml->variables->record_path);
			$record_name   = urldecode((string)$xml->variables->record_name);
			$record_length = urldecode((string)($xml->variables->record_seconds ?? $xml->variables->duration ?? ''));
		}

		// 2. cc_record_filename
		if (empty($record_name) && isset($xml->variables->cc_record_filename)) {
			$fn            = urldecode((string)$xml->variables->cc_record_filename);
			$record_path   = dirname($fn);
			$record_name   = basename($fn);
			$record_length = urldecode((string)($xml->variables->record_seconds ?? ''));
		}

		// 3. last_app = record_session
		if (empty($record_name) && urldecode((string)($xml->variables->last_app ?? '')) === 'record_session') {
			$fn            = urldecode((string)($xml->variables->last_arg ?? ''));
			$record_path   = dirname($fn);
			$record_name   = basename($fn);
			$record_length = urldecode((string)($xml->variables->record_seconds ?? ''));
		}

		// 4. sofia_record_file
		if (empty($record_name) && !empty($xml->variables->sofia_record_file)) {
			$fn            = urldecode((string)$xml->variables->sofia_record_file);
			$record_path   = dirname($fn);
			$record_name   = basename($fn);
			$record_length = urldecode((string)($xml->variables->record_seconds ?? ''));
		}

		// 5. api_on_answer with uuid_record
		if (empty($record_name) && !empty($xml->variables->api_on_answer)) {
			$command = str_replace("\n", " ", urldecode((string)$xml->variables->api_on_answer));
			$parts   = explode(' ', $command);
			if (($parts[0] ?? '') === 'uuid_record' && isset($parts[3])) {
				$record_path   = dirname($parts[3]);
				$record_name   = basename($parts[3]);
				$record_length = urldecode((string)($xml->variables->duration ?? ''));
			}
		}

		// 6. conference_recording
		if (empty($record_name) && !empty($xml->variables->conference_recording)) {
			$fn            = urldecode((string)$xml->variables->conference_recording);
			$record_path   = dirname($fn);
			$record_name   = basename($fn);
			$record_length = urldecode((string)($xml->variables->duration ?? ''));
		}

		// 7. current_application_data
		if (empty($record_name) && !empty($xml->variables->current_application_data)) {
			$commands = explode(',', urldecode((string)$xml->variables->current_application_data));
			foreach ($commands as $cmd_str) {
				$cmd = explode('=', $cmd_str, 2);
				if (($cmd[0] ?? '') === 'api_on_answer') {
					$a       = explode(']', $cmd[1] ?? '', 2);
					$command = str_replace("'", '', $a[0]);
					$parts   = explode(' ', $command);
					if (($parts[0] ?? '') === 'uuid_record' && isset($parts[3])) {
						$record_path   = dirname($parts[3]);
						$record_name   = basename($parts[3]);
						$record_length = urldecode((string)($xml->variables->duration ?? ''));
					}
				}
			}
		}

		// 8-9. Filesystem fallbacks
		if (empty($record_name)) {
			$this->filesystem_fallback($xml, $record, $settings, $record_path, $record_name, $record_length);
		}

		// Only store if the file exists
		if (!empty($record_path) && !empty($record_name)) {
			if (file_exists($record_path . '/' . $record_name)) {
				$record->record_path   = $record_path;
				$record->record_name   = $record_name;
				$record->record_length = $record_length ?: $record->duration;
			}
		}
	}
	private function filesystem_fallback(
		\SimpleXMLElement $xml,
		xml_cdr_record $record,
		settings $settings,
		?string &$record_path,
		?string &$record_name,
		?string &$record_length
	): void {
		$domain_name  = $record->domain_name ?? '';
		$start_epoch  = $record->start_epoch ?? 0;
		$base_path    = $settings->get('switch', 'recordings', '/var/lib/freeswitch/recordings');
		$date_path    = date('Y/M/d', $start_epoch ?: time());
		$path         = $base_path . '/' . $domain_name . '/archive/' . $date_path;

		$uuid        = $record->xml_cdr_uuid ?? '';
		$bridge_uuid = $record->bridge_uuid ?? '';

		$candidates = [];
		if (!empty($uuid)) {
			$candidates[] = [$uuid . '.wav', $uuid . '.mp3'];
		}
		if (!empty($bridge_uuid)) {
			$candidates[] = [$bridge_uuid . '.wav', $bridge_uuid . '.mp3'];
		}

		foreach ($candidates as [$wav, $mp3]) {
			if (file_exists($path . '/' . $wav)) {
				$record_path   = $path;
				$record_name   = $wav;
				$record_length = urldecode((string)($xml->variables->duration ?? ''));
				return;
			}
			if (file_exists($path . '/' . $mp3)) {
				$record_path   = $path;
				$record_name   = $mp3;
				$record_length = urldecode((string)($xml->variables->duration ?? ''));
				return;
			}
		}
	}

}
