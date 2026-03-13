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
 * Represents a pipeline event emitted during non-happy-path CDR processing.
 *
 * Notifiers receive one of these for every outcome that is not a successful
 * database write (skipped, discarded, import_failed, dead_letter, error).
 *
 * Listeners (database_writer, audit_listener) handle successful outcomes,
 * so there is no 'processed' event type here.
 */
class xml_cdr_event {

	/**
	 * The CDR record being processed at the time of the event.
	 * May be partially populated (enrichers/modifiers may not have run yet).
	 *
	 * @var xml_cdr_record
	 */
	public xml_cdr_record $record;

	/**
	 * One of: 'skipped', 'discarded', 'import_failed', 'dead_letter', 'error'.
	 *
	 * @var string
	 */
	public string $event_type;

	/**
	 * Human-readable reason for the event.
	 *
	 * @var string
	 */
	public string $reason;

	/**
	 * The exception that caused this event, if any.
	 *
	 * @var Throwable|null
	 */
	public ?Throwable $exception;

	/**
	 * Outcome string mirroring event_type for convenience.
	 *
	 * @var string
	 */
	public string $outcome;

	/**
	 * Unix timestamp with microseconds (microtime(true)) of when the event was created.
	 *
	 * @var float
	 */
	public float $timestamp;

	/**
	 * Basename of the source file. Stable even after the file has been moved
	 * or deleted. Use this — not $record->source_file — when logging or alerting.
	 *
	 * @var string
	 */
	public string $source_filename;

	private function __construct() {
		// Use the static factory.
	}

	/**
	 * Create a new xml_cdr_event.
	 *
	 * @param xml_cdr_record $record     The CDR record.
	 * @param string         $event_type One of the defined event type constants.
	 * @param string         $reason     Human-readable reason.
	 * @param Throwable|null $exception  The originating exception, if any.
	 *
	 * @return self
	 */
	public static function create(
		xml_cdr_record $record,
		string $event_type,
		string $reason,
		?Throwable $exception = null
	): self {
		$event                  = new self();
		$event->record          = $record;
		$event->event_type      = $event_type;
		$event->reason          = $reason;
		$event->exception       = $exception;
		$event->outcome         = $event_type;
		$event->timestamp       = microtime(true);
		$event->source_filename = $record->source_filename;

		return $event;
	}

}
