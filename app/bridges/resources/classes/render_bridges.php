<?php
/**
 * Render bridges hooks and base class
 *
 * This file defines the render_bridges abstract class which implements the bridge_hooks interface.
 * It provides default no-op implementations for all hook methods, allowing subclasses to override only what they need.
 *
 * The bridge_hooks interface combines bridge_list_page_hook and bridge_edit_hook, which in turn extend
 * the generic list_page_hook and page_edit_hook interfaces.
 *
 * To create custom behavior for the bridges pages, create a new class that extends render_bridges and override the desired hook methods.
 * The autoloader will automatically discover your class based on the interface implementation.
 *
 * Example use cases:
 * - Validate bridge data before actions
 * - Log actions or create audit entries
 * - Modify query parameters before fetching data
 * - Decorate table rows with custom CSS classes or fields
 * - Filter out certain bridges from display based on custom logic
 */
abstract class render_bridges implements bridge_hooks {

	// page_edit_hook methods

	public static function on_pre_save(url $url, array &$data): void {
		// Default: do nothing
	}

	public static function on_post_save(url $url, array $data): void {
		// Default: do nothing
	}

	// page_hook methods

	public static function on_pre_action(url $url, string &$action, array &$items): void {
		// Default: do nothing
	}

	public static function on_post_action(url $url, string $action, array $items): void {
		// Default: do nothing
	}

	public static function on_pre_query(url $url, array &$parameters): void {
		// Default: do nothing
	}

	public static function on_post_query(url $url, array &$items): void {
		// Default: do nothing
	}

	public static function on_pre_render(url $url, template $template): void {
		// Default: do nothing
	}

	public static function on_post_render(url $url, string &$html): void {
		// Default: do nothing
	}

	// list_page_hook methods

	public static function on_render_row(url $url, array &$row, int $row_index): void {
		// Default: do nothing
	}
}
