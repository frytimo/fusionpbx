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
 * Interface for xml_cdr notifiers.
 *
 * Notifiers receive an xml_cdr_event when the pipeline encounters a non-happy-
 * path outcome: 'skipped', 'discarded', 'import_failed', 'dead_letter', or 'error'.
 * They are discovered via the auto_loader just like listeners, allowing any
 * FusionPBX application to register a notifier without modifying core xml_cdr code.
 *
 * Successful processing is fully handled by listeners (database_writer, audit_listener)
 * and does not fire notifiers.
 *
 * Implementations MUST:
 *  - Be fast and non-blocking; long-running actions should be deferred.
 *  - Use only $event->source_filename (basename) when referring to the source
 *    file — by the time notifiers run, the file may have been moved or deleted.
 *  - Never throw exceptions; swallow errors gracefully to avoid disrupting other notifiers.
 */
interface xml_cdr_notifier {

	/**
	 * React to a non-happy-path pipeline event.
	 *
	 * @param settings      $settings Application settings.
	 * @param xml_cdr_event $event    Event describing what happened.
	 *
	 * @return void
	 */
	public function on_event(settings $settings, xml_cdr_event $event): void;

}
