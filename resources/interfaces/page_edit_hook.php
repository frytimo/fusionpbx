<?php

interface page_edit_hook {

	/**
	 * Called before saving page data
	 *
	 * @param url $url The URL object
	 * @param array $data The page data being saved (passed by reference for modification)
	 *
	 * @return void
	 */
	public static function on_pre_save(url $url, array &$data): void;

	/**
	 * Called after saving page data
	 *
	 * @param url $url The URL object
	 * @param array $data The page data that was saved
	 *
	 * @return void
	 */
	public static function on_post_save(url $url, array $data): void;
}
