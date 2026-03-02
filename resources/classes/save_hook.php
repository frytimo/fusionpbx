<?php


class save_hook implements page_edit_hook {

	public static function on_pre_save(url $url, array &$data): void {
		// Example: Add a timestamp before saving
		$data['last_updated'] = date('Y-m-d H:i:s');
	}

	public static function on_post_save(url $url, array $data): void {
		// Example: Log the save action
		error_log("Page saved: " . $url->get_path());
	}
}
