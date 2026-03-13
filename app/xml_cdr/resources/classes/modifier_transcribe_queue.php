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
 * Queues a call recording for transcription.
 *
 * When the 'call_recordings.transcribe_enabled' setting is true and a
 * recording is present on the record, this modifier inserts a row into
 * the transcribe_queue table so the transcription service can process
 * the audio later.
 *
 * Priority: 60 — runs after modifier_recording has resolved the recording.
 */
class modifier_transcribe_queue implements xml_cdr_modifier {

	/** @var database Database connection. */
	private database $database;

		/**
		 * @param database $database Active database connection.
		 */
	public function __construct(database $database) {
		$this->database = $database;
	}

		/**
		 * Returns the sort priority; lower values run first in the modifier chain.
		 *
		 * @return int
		 */
	public function priority(): int {
		return 60;
	}

		/**
		 * Queue the call recording for transcription when transcription is enabled.
		 *
		 * @param settings       $settings Application settings.
		 * @param xml_cdr_record $record   CDR record to evaluate.
		 *
		 * @return void
		 */
	public function __invoke(settings $settings, xml_cdr_record $record): void {
		if (!$settings->get('call_recordings', 'transcribe_enabled', false)) {
			return;
		}

		// Only queue if a recording was resolved
		if (empty($record->record_path) || empty($record->record_name)) {
			return;
		}

		$uuid        = $record->xml_cdr_uuid ?? '';
		$domain_uuid = $record->domain_uuid ?? '';

		if (empty($uuid) || empty($domain_uuid)) {
			return;
		}

		$transcribe_queue = [];
		$transcribe_queue['transcribe_queue'][0] = [
			'transcribe_queue_uuid'    => $uuid,
			'domain_uuid'              => $domain_uuid,
			'hostname'                 => gethostname(),
			'transcribe_status'        => 'pending',
			'transcribe_app_class'     => 'call_recordings',
			'transcribe_app_method'    => 'transcribe_queue',
			'transcribe_app_params'    => json_encode([
				'domain_uuid'     => $domain_uuid,
				'xml_cdr_uuid'    => $uuid,
				'call_direction'  => $record->direction ?? '',
			]),
			'transcribe_audio_path'    => $record->record_path,
			'transcribe_audio_name'    => $record->record_name,
		];

		$this->database->save($transcribe_queue);
	}

}
