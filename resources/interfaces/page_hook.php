<?php

/**
 * Abstract base class for list page hooks
 * Provides default no-op implementations
 */
interface page_hook extends list_page_hook {

	/**
	 * Called before processing a list action (copy, toggle, delete)
	 * Allows hooks to validate or modify action parameters before execution
	 *
	 * @param settings $settings The settings object
	 * @param string   $action   The action being performed (copy, toggle, delete)
	 * @param array    $items    The array containing UUIDs and data
	 *
	 * @return void
	 */	
    public static function on_pre_action(settings $settings, string &$action, array &$items): void;

	/**
	 * Called after processing a list action completes
	 * Allows hooks to perform cleanup or additional updates after action execution
	 *
	 * @param settings $settings The settings object
	 * @param string   $action   The action that was performed (copy, toggle, delete)
	 * @param array    $items    The array that was processed
	 *
	 * @return void
	 */
	public static function on_post_action(settings $settings, string $action, array $items): void;

	/**
	 * Called before building the SQL query for the list
	 * Allows hooks to modify search parameters or query conditions
	 *
	 * @param settings $settings   The settings object
	 * @param array    $parameters Query parameters array (passed by reference for modification)
	 *
	 * @return void
	 */
	public static function on_pre_query(settings $settings, array &$parameters): void;

	/**
	 * Called after fetching the list from the database
	 * Allows hooks to modify, filter, or enrich the records before display
	 *
	 * @param settings $settings The settings object
	 * @param array    $list     The records fetched from database (passed by reference for modification)
	 *
	 * @return void
	 */
	public static function on_post_query(settings $settings, array &$items): void;

	/**
	 * Called for each table row during rendering
	 * Allows hooks to customize individual row data before display
	 *
	 * @param settings $settings The settings object
	 * @param template $template The template object
	 *
	 * @return void
	 */
	public static function on_pre_render(settings $settings, template $template): void;

	/**
	 * Called after the HTML for the list page is generated
	 * Allows hooks to modify the final HTML output before it is sent to the browser
	 *
	 * @param settings $settings The settings object
	 * @param string   &$html    The generated HTML output (passed by reference for modification)
	 *
	 * @return void
	 */
	public static function on_post_render(settings $settings, string &$html): void;
}
