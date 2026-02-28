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

interface bridges_hook {
	/**
	 * Called before processing a bridge action (copy, toggle, delete)
	 * Allows hooks to validate or modify action parameters before execution
	 *
	 * @param settings $settings The settings object
	 * @param string $action The action being performed (copy, toggle, delete)
	 * @param array $bridges The bridges array containing UUIDs and data
	 * @return void
	 */
	public static function on_bridges_action_pre(settings $settings, string $action, array &$bridges): void;

	/**
	 * Called after processing a bridge action completes
	 * Allows hooks to perform cleanup or additional updates after action execution
	 *
	 * @param settings $settings The settings object
	 * @param string $action The action that was performed (copy, toggle, delete)
	 * @param array $bridges The bridges array that was processed
	 * @return void
	 */
	public static function on_bridges_action_post(settings $settings, string $action, array $bridges): void;

	/**
	 * Called before building the SQL query for the bridges list
	 * Allows hooks to modify search parameters or query conditions
	 *
	 * @param settings $settings The settings object
	 * @param array $parameters Query parameters array (passed by reference for modification)
	 * @return void
	 */
	public static function on_bridges_query_pre(settings $settings, array &$parameters): void;

	/**
	 * Called after fetching the bridges list from the database
	 * Allows hooks to modify, filter, or enrich the bridge records before display
	 *
	 * @param settings $settings The settings object
	 * @param array $bridges The bridge records fetched from database (passed by reference for modification)
	 * @return void
	 */
	public static function on_bridges_list_post_fetch(settings $settings, array &$bridges): void;

	/**
	 * Called for each table row during rendering
	 * Allows hooks to customize individual bridge row data before display
	 *
	 * @param settings $settings The settings object
	 * @param array $row The bridge row data (passed by reference for modification)
	 * @param int $row_index The zero-based index of the row in the table
	 * @return void
	 */
	public static function on_bridges_row_render(settings $settings, array &$row, int $row_index): void;
}
