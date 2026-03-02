<?php
/**
 * Render bridges hooks and base class
 *
 * This file defines the render_bridges abstract class which implements the render_bridges_hooks interface.
 * It provides default no-op implementations for all hook methods, allowing subclasses to override only what they need.
 *
 * The render_bridges_hooks interface extends the generic list_page_hooks interface, and can be used to define bridge-specific hooks if needed.
 *
 * To create custom behavior for the bridges list page, create a new class that extends render_bridges and override the desired hook methods.
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

    public static function on_list_pre_action(settings $settings, string &$action, array &$items): void {
        // Default: do nothing
    }

    public static function on_list_post_action(settings $settings, string $action, array $items): void {
        // Default: do nothing
    }

    public static function on_list_pre_query(settings $settings, array &$parameters): void {
        // Default: do nothing
    }

    public static function on_list_post_query(settings $settings, array &$items): void {
        // Default: do nothing
    }

    public static function on_list_render_row(settings $settings, array &$row, int $row_index): void {
        // Default: do nothing
    }
}
