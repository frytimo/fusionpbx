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
 * Example implementation of the bridge_hooks interface
 *
 * This class demonstrates how to hook into the bridges list and edit functionality
 * to add custom processing before/after actions and display.
 *
 * By extending render_bridges, all hook methods have default no-op implementations.
 * Override only the methods you need.
 *
 * To use this example:
 * 1. Copy this file and rename it to your custom class (e.g., my_bridges_hook.php)
 * 2. Update the class name and override the desired hook methods
 * 3. The autoloader will automatically discover it via the interface implementation
 *
 * Usage examples:
 * - Validate bridge data before copying/toggling/deleting
 * - Auto-generate audit logs after actions
 * - Modify bridge destinations before display
 * - Add custom fields or decorators to table rows
 * - Filter out bridges based on custom criteria
 */
class bridges_hook_example extends render_bridges {

	/**
	 * Hook: Before rendering each table row
	 *
	 * Called for each bridge row before per-row data is passed to the template.
	 * Use this to:
	 * - Modify how individual rows are displayed
	 * - Add/remove data fields
	 * - Apply conditional formatting data
	 * - Decorate rows with additional metadata
	 *
	 * @param url   $url       The URL object
	 * @param array $row       The bridge row data (passed by reference)
	 * @param int   $row_index Zero-based row index in the table
	 */
	public static function on_render_row(url $url, array &$row, int $row_index): void {
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

	/**
	 * Hook: Before the Smarty template is rendered
	 *
	 * Called after all data is prepared and assigned but before render() is invoked.
	 * Use this to:
	 * - Assign additional variables to the template
	 * - Override variables already assigned by the page
	 * - Inject data from external sources into the view
	 *
	 * @param url      $url      The URL object
	 * @param template $template The Smarty template object (assign variables via $template->assign())
	 */
	public static function on_pre_render(url $url, template $template): void {
		// Example: inject additional data into the template before rendering
		// $template->assign('my_custom_banner', '<div class="notice">Maintenance tonight</div>');
	}

	/**
	 * Hook: After the Smarty template is rendered
	 *
	 * Called with the fully rendered HTML string passed by reference.
	 * Use this to:
	 * - Append or prepend content to the page body
	 * - Replace tokens in the rendered output
	 * - Add debug information
	 *
	 * @param url    $url  The URL object
	 * @param string $html The rendered HTML output (passed by reference for modification)
	 */
	public static function on_post_render(url $url, string &$html): void {
		// Example: append a debug comment to the rendered output
		// $html .= "\n<!-- rendered by bridges_hook_example -->";
	}
}
