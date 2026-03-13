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
 * Thrown when a CDR record should be skipped without deleting the source file.
 *
 * Use cases: duplicate UUID (record already in DB), record belongs to another
 * hostname, or any condition where the file should remain on disk untouched.
 *
 * The pipeline runner will:
 *  - Stop enricher/modifier/listener processing for this record
 *  - Leave the source file in place
 *  - Fire all notifiers with event_type='skipped'
 */
class xml_cdr_skip_exception extends xml_cdr_pipeline_exception {
	// Intentionally empty — use the standard RuntimeException constructor.
}
