<?php

/**
 * Abstract base class for base page hooks
 */
interface page_hook {
	/**
	 * Called before processing a list action (copy, toggle, delete)
	 * Allows hooks to validate or modify action parameters before execution
	 *
	 * @param url    $url    The URL object
	 * @param string $action The action being performed (copy, toggle, delete)
	 * @param array  $items  The array containing UUIDs and data
	 *
	 * @return void
	 */
	public static function on_pre_action(url $url, string &$action, array &$items): void;

	/**
	 * Called after processing a list action completes
	 * Allows hooks to perform cleanup or additional updates after action execution
	 *
	 * @param url    $url    The URL object
	 * @param string $action The action that was performed (copy, toggle, delete)
	 * @param array  $items  The array that was processed
	 *
	 * @return void
	 */
	public static function on_post_action(url $url, string $action, array $items): void;

	/**
	 * Called before building the SQL query for the list
	 * Allows hooks to modify search parameters or query conditions
	 *
	 * @param url    $url        The URL object
	 * @param array  $parameters Query parameters array (passed by reference for modification)
	 *
	 * @return void
	 */
	public static function on_pre_query(url $url, array &$parameters): void;

	/**
	 * Called after fetching the list from the database
	 * Allows hooks to modify, filter, or enrich the records before display
	 *
	 * @param url   $url   The URL object
	 * @param array $items The records fetched from database (passed by reference for modification)
	 *
	 * @return void
	 */
	public static function on_post_query(url $url, array &$items): void;

	/**
	 * Called for each table row during rendering
	 * Allows hooks to customize individual row data before display
	 *
	 * @param url      $url      The URL object
	 * @param template $template The template object
	 *
	 * @return void
	 */
	public static function on_pre_render(url $url, template $template): void;

	/**
	 * Called after the HTML for the list page is generated
	 * Allows hooks to modify the final HTML output before it is sent to the browser
	 *
	 * @param url    $url   The URL object
	 * @param string &$html The generated HTML output (passed by reference for modification)
	 *
	 * @return void
	 */
	public static function on_post_render(url $url, string &$html): void;
}
