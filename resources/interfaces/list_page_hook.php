<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2024
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

interface list_page_hook extends page_hook {

	/**
	 * Called for each table row during rendering
	 * Allows hooks to customize individual row data before display
	 *
	 * @param url   $url       The URL object
	 * @param array $row       The row data (passed by reference for modification)
	 * @param int   $row_index The zero-based index of the row in the table
	 *
	 * @return void
	 */
	public static function on_render_row(url $url, array &$row, int $row_index): void;
}
