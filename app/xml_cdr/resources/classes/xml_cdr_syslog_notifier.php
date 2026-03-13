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
 * Sends a syslog message when a non-happy-path pipeline event occurs.
 *
 * Only fires for failure events (skipped, discarded, import_failed,
 * dead_letter, error) — not for successful processing.
 *
 * The message uses only $event->source_filename (the basename) because the
 * source file may have been moved or deleted by the time the notifier fires.
 *
 * Syslog facility: LOG_DAEMON
 * Syslog priority:
 *  - skipped / discarded → LOG_INFO
 *  - import_failed / dead_letter → LOG_WARNING
 *  - error → LOG_ERR
 */
class xml_cdr_syslog_notifier implements xml_cdr_notifier {

	/**
	 * Send a syslog message describing the non-happy-path pipeline event.
	 *
	 * @param settings      $settings Application settings.
	 * @param xml_cdr_event $event    Event carrying the error type, reason, and source filename.
	 *
	 * @return void
	 */
	public function on_event(settings $settings, xml_cdr_event $event): void {
		$priority = $this->syslog_priority($event->event_type);

		$message = sprintf(
			'xml_cdr_pipeline: %s file=%s reason=%s',
			$event->event_type,
			$event->source_filename,
			$event->reason
		);

		if ($event->exception !== null) {
			$message .= ' exception=' . get_class($event->exception)
				. ' msg=' . $event->exception->getMessage();
		}

		openlog('fusionpbx', LOG_PID, LOG_DAEMON);
		syslog($priority, $message);
		closelog();
	}
	private function syslog_priority(string $event_type): int {
		switch ($event_type) {
			case 'skipped':
			case 'discarded':
				return LOG_INFO;
			case 'import_failed':
			case 'dead_letter':
				return LOG_WARNING;
			default:
				return LOG_ERR;
		}
	}

}
