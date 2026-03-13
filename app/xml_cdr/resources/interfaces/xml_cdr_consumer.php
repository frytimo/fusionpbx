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
 * Interface for xml_cdr consumers.
 *
 * A consumer is responsible for discovering CDR files and delivering them
 * one at a time to the pipeline via the $on_record callable. Consumers
 * may delegate to other consumers (constructor injection) to compose
 * behaviours such as startup scan + inotify watch.
 *
 * Implementations must not perform CDR field transformation — that is the
 * responsibility of enrichers and modifiers.
 *
 * The $on_record callable receives a single xml_cdr_record and may throw
 * xml_cdr_pipeline_exception subclasses; the consumer MUST NOT catch those —
 * they are handled by the pipeline runner.
 */
interface xml_cdr_consumer {

	/**
	 * Begin consuming CDR records and invoke $on_record for each one found.
	 *
	 * @param settings $settings   Application settings.
	 * @param callable $on_record  Callback: fn(xml_cdr_record): void
	 *
	 * @return void
	 */
	public function consume(settings $settings, callable $on_record): void;

}
