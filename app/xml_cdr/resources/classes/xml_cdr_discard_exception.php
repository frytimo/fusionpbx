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
 * Thrown when a CDR record should be silently discarded and its source file deleted.
 *
 * Use cases: call_block is enabled and CDR saving for blocked calls is disabled,
 * zero-byte file, file exceeds max size, or any other condition where the record
 * is intentionally not stored and the file should be removed.
 *
 * The pipeline runner will:
 *  - Stop enricher/modifier/listener processing for this record
 *  - Delete (or move) the source file
 *  - Fire all notifiers with event_type='discarded'
 */
class xml_cdr_discard_exception extends xml_cdr_pipeline_exception {
	// Intentionally empty — use the standard RuntimeException constructor.
}
