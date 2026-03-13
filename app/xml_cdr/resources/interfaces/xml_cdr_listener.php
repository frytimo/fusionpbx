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
 * Interface for xml_cdr listeners.
 *
 * Listeners run after all enrichers and modifiers have completed. They handle
 * side effects such as writing records to the database, logging, or forwarding
 * events. All listeners receive the final, fully-populated record.
 *
 * Implementations MUST:
 *  - Treat $record as effectively read-only; avoid mutating it.
 *  - Handle their own error cases internally where possible.
 *
 * Implementations MUST NOT throw xml_cdr_pipeline_exception subclasses —
 * the pipeline is already in the commit phase when listeners run.
 *
 * Any other exception thrown by a listener will be caught by the pipeline
 * runner and treated as an 'error' event (notifiers will be fired).
 */
interface xml_cdr_listener {

	/**
	 * Handle the fully-processed CDR record.
	 *
	 * @param settings       $settings Application settings.
	 * @param xml_cdr_record $record   The completed CDR record.
	 *
	 * @return void
	 */
	public function on_cdr(settings $settings, xml_cdr_record $record): void;

}
