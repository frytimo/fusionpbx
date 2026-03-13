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
 * Value object representing a single call detail record in-flight through the pipeline.
 *
 * A single instance is created per CDR file and passed by reference through all
 * enrichers, modifiers, and listeners. The raw content is held once; the parsed
 * SimpleXMLElement is lazy-loaded and cached on first access. Callers that update
 * the raw content (e.g. modifier_xml_sanitize) must call set_raw_content() to
 * automatically invalidate the parse cache.
 *
 * Extra dynamic fields (configured via settings 'cdr' -> 'field') are stored in a
 * separate map and merged into to_array() output.
 */
class xml_cdr_record {
	// File metadata
	/** Absolute path to the source CDR file (full path including filename). */
	public string $source_file = '';

	/** Basename of the source CDR file. Stable even after the file is moved. */
	public string $source_filename = '';

	/** Format: 'xml' or 'json'. */
	public string $format = 'xml';
	// Call IDs and UUIDs
	public ?string $xml_cdr_uuid = null;
	public ?string $domain_uuid = null;
	public ?string $provider_uuid = null;
	public ?string $extension_uuid = null;
	public ?string $bridge_uuid = null;
	public ?string $originating_leg_uuid = null;
	public ?string $ring_group_uuid = null;
	public ?string $ivr_menu_uuid = null;
	public ?string $sip_call_id = null;
	// Domain / context
	public ?string $domain_name = null;
	public ?string $context = null;
	public ?string $accountcode = null;
	public ?string $default_language = null;
	// Caller / destination
	public ?string $caller_id_name = null;
	public ?string $caller_id_number = null;
	public ?string $caller_destination = null;
	public ?string $destination_number = null;
	public ?string $source_number = null;
	public ?string $network_addr = null;
	public ?string $direction = null;
	public ?string $leg = null;
	// Timing
	public ?int    $start_epoch = null;
	public ?string $start_stamp = null;
	public ?int    $answer_epoch = null;
	public ?string $answer_stamp = null;
	public ?int    $end_epoch = null;
	public ?string $end_stamp = null;
	public ?int    $duration = null;
	public ?int    $mduration = null;
	public ?int    $billsec = null;
	public ?int    $billmsec = null;
	public ?int    $hold_accum_seconds = null;
	public ?int    $pdd_ms = null;
	// Codecs / media
	public ?string $read_codec = null;
	public ?string $read_rate = null;
	public ?string $write_codec = null;
	public ?string $write_rate = null;
	public ?string $remote_media_ip = null;
	public ?float  $rtp_audio_in_mos = null;
	// Recording
	public ?string $record_path = null;
	public ?string $record_name = null;
	public ?int    $record_length = null;
	// Call outcome
	public ?string $status = null;
	public ?string $missed_call = null;
	public ?string $hangup_cause = null;
	public ?int    $hangup_cause_q850 = null;
	public ?string $sip_hangup_disposition = null;
	public ?string $last_app = null;
	public ?string $last_arg = null;
	public ?string $digits_dialed = null;
	public ?string $pin_number = null;
	public ?string $voicemail_message = null;
	// Call center
	public ?string $call_center_queue_uuid = null;
	public ?string $cc_side = null;
	public ?string $cc_member_uuid = null;
	public ?string $cc_member_session_uuid = null;
	public ?string $cc_agent_uuid = null;
	public ?string $cc_queue = null;
	public ?string $cc_agent = null;
	public ?string $cc_agent_type = null;
	public ?string $cc_agent_bridged = null;
	public ?int    $cc_queue_joined_epoch = null;
	public ?int    $cc_queue_answered_epoch = null;
	public ?int    $cc_queue_terminated_epoch = null;
	public ?int    $cc_queue_canceled_epoch = null;
	public ?string $cc_cancel_reason = null;
	public ?string $cc_cause = null;
	public ?int    $waitsec = null;
	// Conference
	public ?string $conference_name = null;
	public ?string $conference_uuid = null;
	public ?string $conference_member_id = null;
	// Secondary table data (populated by database_writer listener)
	/** JSON-encoded call flow array, built from the parsed XML callflow nodes. */
	public ?string $call_flow_json = null;

	/** JSON representation of the full XML variables object. */
	public ?string $full_json = null;

	/** FreeSwitch log lines for this call UUID, if log capture is enabled. */
	public ?string $log_content = null;
	// Private: raw content + parse cache + dynamic fields
	private string $raw_content = '';
	private ?SimpleXMLElement $parsed_element = null;
	private array $extra_fields = [];
	// Raw content access (with automatic parse cache invalidation)
	/**
	 * Set the raw CDR content. Automatically invalidates the parsed element cache.
	 */
	public function set_raw_content(string $content): void {
		$this->raw_content = $content;
		$this->parsed_element = null;
	}

	/**
	 * Return the raw CDR content string.
	 */
	public function get_raw_content(): string {
		return $this->raw_content;
	}
	// Lazy-parsed XML element
	/**
	 * Return the parsed SimpleXMLElement, loading it from raw_content on first call.
	 * Returns null if the content cannot be parsed or if format is 'json'.
	 */
	public function parsed(): ?SimpleXMLElement {
		if ($this->format === 'json') {
			return null;
		}
		if ($this->parsed_element === null && !empty($this->raw_content)) {
			if (PHP_VERSION_ID < 80000) {
				// phpcs:ignore
				libxml_disable_entity_loader(true);
			}
			$element = simplexml_load_string($this->raw_content, 'SimpleXMLElement', LIBXML_NOCDATA);
			$this->parsed_element = ($element !== false) ? $element : null;
		}
		return $this->parsed_element;
	}
	// Dynamic / extra fields
	/**
	 * Set an extra dynamic field (configured via settings 'cdr' -> 'field').
	 */
	public function set_field(string $name, $value): void {
		$this->extra_fields[$name] = $value;
	}

	/**
	 * Get an extra dynamic field value, or $default if not set.
	 */
	public function get_field(string $name, $default = null) {
		return $this->extra_fields[$name] ?? $default;
	}

	/**
	 * Return all extra dynamic fields as an associative array.
	 */
	public function extra_fields(): array {
		return $this->extra_fields;
	}
	// Serialization for database persistence
	/**
	 * Return the CDR fields as a flat associative array suitable for insertion
	 * into $data['xml_cdr'][0] by the database_writer listener.
	 * Null values and empty strings are omitted.
	 */
	public function to_array(): array {
		$data = [
			'xml_cdr_uuid'           => $this->xml_cdr_uuid,
			'domain_uuid'            => $this->domain_uuid,
			'provider_uuid'          => $this->provider_uuid,
			'extension_uuid'         => $this->extension_uuid,
			'bridge_uuid'            => $this->bridge_uuid,
			'originating_leg_uuid'   => $this->originating_leg_uuid,
			'ring_group_uuid'        => $this->ring_group_uuid,
			'ivr_menu_uuid'          => $this->ivr_menu_uuid,
			'sip_call_id'            => $this->sip_call_id,
			'domain_name'            => $this->domain_name,
			'context'                => $this->context,
			'accountcode'            => $this->accountcode,
			'default_language'       => $this->default_language,
			'caller_id_name'         => $this->caller_id_name,
			'caller_id_number'       => $this->caller_id_number,
			'caller_destination'     => $this->caller_destination,
			'destination_number'     => $this->destination_number,
			'source_number'          => $this->source_number,
			'network_addr'           => $this->network_addr,
			'direction'              => $this->direction,
			'leg'                    => $this->leg,
			'start_epoch'            => $this->start_epoch,
			'start_stamp'            => $this->start_stamp,
			'answer_epoch'           => $this->answer_epoch,
			'answer_stamp'           => $this->answer_stamp,
			'end_epoch'              => $this->end_epoch,
			'end_stamp'              => $this->end_stamp,
			'duration'               => $this->duration,
			'mduration'              => $this->mduration,
			'billsec'                => $this->billsec,
			'billmsec'               => $this->billmsec,
			'hold_accum_seconds'     => $this->hold_accum_seconds,
			'pdd_ms'                 => $this->pdd_ms,
			'read_codec'             => $this->read_codec,
			'read_rate'              => $this->read_rate,
			'write_codec'            => $this->write_codec,
			'write_rate'             => $this->write_rate,
			'remote_media_ip'        => $this->remote_media_ip,
			'rtp_audio_in_mos'       => $this->rtp_audio_in_mos,
			'record_path'            => $this->record_path,
			'record_name'            => $this->record_name,
			'record_length'          => $this->record_length,
			'status'                 => $this->status,
			'missed_call'            => $this->missed_call,
			'hangup_cause'           => $this->hangup_cause,
			'hangup_cause_q850'      => $this->hangup_cause_q850,
			'sip_hangup_disposition' => $this->sip_hangup_disposition,
			'last_app'               => $this->last_app,
			'last_arg'               => $this->last_arg,
			'digits_dialed'          => $this->digits_dialed,
			'pin_number'             => $this->pin_number,
			'voicemail_message'      => $this->voicemail_message,
			'call_center_queue_uuid' => $this->call_center_queue_uuid,
			'cc_side'                => $this->cc_side,
			'cc_member_uuid'         => $this->cc_member_uuid,
			'cc_member_session_uuid' => $this->cc_member_session_uuid,
			'cc_agent_uuid'          => $this->cc_agent_uuid,
			'cc_queue'               => $this->cc_queue,
			'cc_agent'               => $this->cc_agent,
			'cc_agent_type'          => $this->cc_agent_type,
			'cc_agent_bridged'       => $this->cc_agent_bridged,
			'cc_queue_joined_epoch'  => $this->cc_queue_joined_epoch,
			'cc_queue_answered_epoch'   => $this->cc_queue_answered_epoch,
			'cc_queue_terminated_epoch' => $this->cc_queue_terminated_epoch,
			'cc_queue_canceled_epoch'   => $this->cc_queue_canceled_epoch,
			'cc_cancel_reason'       => $this->cc_cancel_reason,
			'cc_cause'               => $this->cc_cause,
			'waitsec'                => $this->waitsec,
			'conference_name'        => $this->conference_name,
			'conference_uuid'        => $this->conference_uuid,
			'conference_member_id'   => $this->conference_member_id,
		];

		// Merge in dynamic extra fields
		foreach ($this->extra_fields as $name => $value) {
			$data[$name] = $value;
		}

		// Remove nulls and empty strings so the database layer uses column defaults
		return array_filter($data, function ($v) {
			return $v !== null && $v !== '';
		});
	}
	// Static factories
	/**
	 * Create a record from raw XML content read from a .cdr.xml file.
	 *
	 * Handles URL-encoded content (files starting with '%') automatically.
	 *
	 * @param string $raw_content Raw file content.
	 * @param string $path        Absolute path to the source file.
	 *
	 * @return self
	 */
	public static function from_xml(string $raw_content, string $path): self {
		$record = new self();
		$record->source_file     = $path;
		$record->source_filename = basename($path);
		$record->format          = 'xml';
		$record->leg             = (substr($record->source_filename, 0, 2) === 'a_') ? 'a' : 'b';

		// Decode URL-encoded content (FreeSWITCH sometimes URL-encodes CDR files)
		if (isset($raw_content[0]) && $raw_content[0] === '%') {
			$raw_content = urldecode($raw_content);
		}

		$record->set_raw_content($raw_content);

		return $record;
	}

	/**
	 * Create a record from raw JSON content read from a .cdr.json file.
	 *
	 * @param string $raw_content Raw JSON file content.
	 * @param string $path        Absolute path to the source file.
	 *
	 * @return self
	 */
	public static function from_json(string $raw_content, string $path): self {
		$record = new self();
		$record->source_file     = $path;
		$record->source_filename = basename($path);
		$record->format          = 'json';
		$record->leg             = (substr(basename($path), 0, 2) === 'a_') ? 'a' : 'b';
		$record->set_raw_content($raw_content);

		return $record;
	}

}
