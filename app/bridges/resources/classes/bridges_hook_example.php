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
 * Portions created by the Initial Developer are Copyright (C) 2024
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * Mark J Crane <markjcrane@fusionpbx.com>
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
class bridges_hook_example implements bridge_list_hook {
	public static function on_pre_action(settings $settings, string &$action, array &$items): void {
		throw new \Exception('Not implemented');
	}

	public static function on_post_action(settings $settings, string $action, array $items): void {
		throw new \Exception('Not implemented');
	}

	public static function on_pre_query(settings $settings, array &$parameters): void {
		throw new \Exception('Not implemented');
	}

	public static function on_post_query(settings $settings, array &$items): void {
		throw new \Exception('Not implemented');
	}

	public static function on_pre_render(settings $settings, template $template): void {
		throw new \Exception('Not implemented');
	}

	public static function on_post_render(settings $settings, string &$html): void {
		throw new \Exception('Not implemented');
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
	public static function on_render_row(settings $settings, array &$row, int $row_index): void {
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
		// Drop or hide the row
		if ($row['bridge_enabled'] === 'false') {
			// This will skip rendering this row
			unset($row);
		}
	}
}
