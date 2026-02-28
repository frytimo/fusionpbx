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

/**
 * Example implementation of the bridges_hook interface
 * 
 * This class demonstrates how to hook into the bridges list functionality
 * to add custom processing before/after actions and display.
 * 
 * To use this example:
 * 1. Copy this file and rename it to your custom class (e.g., my_bridges_hook.php)
 * 2. Update the class name and implementation logic
 * 3. The autoloader will automatically discover it via the interface implementation
 * 
 * Usage examples:
 * - Validate bridge data before copying/toggling/deleting
 * - Auto-generate audit logs after actions
 * - Modify bridge destinations before display
 * - Add custom fields or decorators to table rows
 * - Filter out bridges based on custom criteria
 */

class bridges_hook_example implements bridges_hook {

	/**
	 * Hook: Before bridge action processing
	 * 
	 * Called after POST data is extracted but before permission checks.
	 * Use this to validate or modify the action/bridges data before execution.
	 * 
	 * @param settings $settings The settings object
	 * @param string $action The action (copy|toggle|delete)
	 * @param array $bridges The bridges array with checked items and their UUIDs
	 */
	public static function on_bridges_action_pre(settings $settings, string $action, array &$bridges): void {
		// Example: Log all actions
		error_log("Bridge action pre-processing: action=" . $action . ", count=" . count($bridges));

		// Example: Validate bridge UUIDs before processing
		foreach ($bridges as $index => $bridge) {
			if (!is_uuid($bridge['uuid'] ?? null)) {
				// Remove invalid entries
				unset($bridges[$index]);
			}
		}
	}

	/**
	 * Hook: After bridge action completes
	 * 
	 * Called after delete/copy/toggle action completes and before redirect.
	 * Use this for cleanup, logging, cache invalidation, etc.
	 * 
	 * @param settings $settings The settings object
	 * @param string $action The action that was performed
	 * @param array $bridges The bridges that were processed
	 */
	public static function on_bridges_action_post(settings $settings, string $action, array $bridges): void {
		// Example: Create audit log entry
		error_log("Bridge action completed: action=" . $action . ", count=" . count($bridges));

		// Example: Invalidate custom cache
		// apcu_delete('my_bridges_cache_key');

		// Example: Trigger external API update
		// self::notify_external_system($action, $bridges);
	}

	/**
	 * Hook: Before database query is executed
	 * 
	 * Called before the SQL query is built for the bridges list.
	 * Use this to:
	 * - Add additional search/filter parameters
	 * - Modify search logic
	 * - Add domain/context filtering
	 * 
	 * @param settings $settings The settings object
	 * @param array $parameters The query parameters array (passed by reference)
	 */
	public static function on_bridges_query_pre(settings $settings, array &$parameters): void {
		// Example: Add a custom parameter that could be used in SQL
		// $parameters['custom_filter'] = $settings->get('domain', 'custom_bridge_filter', '');

		error_log("Bridge query parameters: " . json_encode($parameters));
	}

	/**
	 * Hook: After bridges are fetched from database
	 * 
	 * Called after the SELECT query completes and before display.
	 * Use this to:
	 * - Filter or sort results
	 * - Enrich data with additional fields
	 * - Apply custom business logic
	 * - Cache the results
	 * 
	 * @param settings $settings The settings object
	 * @param array $bridges The bridges fetched from database (passed by reference)
	 */
	public static function on_bridges_list_post_fetch(settings $settings, array &$bridges): void {
		// Example: Add custom field to each bridge
		foreach ($bridges as &$bridge) {
			// Add a computed field (e.g., based on bridge_enabled status)
			$bridge['custom_status'] = ($bridge['bridge_enabled'] === 'true') ? 'Active' : 'Inactive';

			// Example: Enrich with additional data from another source
			// $bridge['call_count'] = self::get_bridge_call_count($bridge['bridge_uuid']);
		}

		error_log("Bridges returned from fetch: " . count($bridges));
	}

	/**
	 * Hook: Before rendering each table row
	 * 
	 * Called for each bridge row before the <tr> HTML is generated.
	 * Use this to:
	 * - Modify how individual rows are displayed
	 * - Add/remove data fields
	 * - Apply conditional formatting data
	 * - Decorate rows with additional metadata
	 * 
	 * @param settings $settings The settings object
	 * @param array $row The bridge row data (passed by reference)
	 * @param int $row_index Zero-based row index in the table
	 */
	public static function on_bridges_row_render(settings $settings, array &$row, int $row_index): void {
		// Example: Mark every other row
		$row['_custom_css_class'] = ($row_index % 2 === 0) ? 'even' : 'odd';

		// Example: Highlight bridges based on criteria
		if ($row['bridge_enabled'] === 'true' && strpos($row['bridge_destination'], '5000') !== false) {
			$row['_highlight'] = true;
			$row['_highlight_reason'] = 'Conference bridge to extension 5000';
		}

		// Example: Add tooltip or description
		if (strlen($row['bridge_description']) === 0) {
			$row['bridge_description'] = '[No description provided]';
		}

		// Note: You can add custom fields with '_' prefix to avoid conflicts
		// These are available in the row rendering context
	}
}
