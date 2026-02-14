<?php

/**
 * access controls class
 */
class access_controls extends app {
	/**
	 * declare constant variables
	 */
	const app_name = 'access_controls';

	const app_uuid = '1416a250-f6e1-4edc-91a6-5c9b883638fd';

	/**
	 * Set in the constructor. Must be a database object and cannot be null.
	 *
	 * @var database Database Object
	 */
	private $database;

	/**
	 * Settings object set in the constructor. Must be a settings object and cannot be null.
	 *
	 * @var settings Settings Object
	 */
	private $settings;

	/**
	 * User UUID set in the constructor. This can be passed in through the $settings_array associative array or set in
	 * the session global array
	 *
	 * @var string
	 */
	private $user_uuid;

	/**
	 * Domain UUID set in the constructor. This can be passed in through the $settings_array associative array or set
	 * in the session global array
	 *
	 * @var string
	 */
	private $domain_uuid;

	/**
	 * declare private variables
	 */
	const permission_prefix = 'access_control_';

	const list_page = 'access_controls.php';
	const table = 'access_controls';
	const uuid_prefix = 'access_control_';

	/**
	 * called when the object is created
	 */
	public function __construct(array $setting_array = []) {
		// set domain and user UUIDs
		$this->domain_uuid = $setting_array['domain_uuid'] ?? $_SESSION['domain_uuid'] ?? '';
		$this->user_uuid = $setting_array['user_uuid'] ?? $_SESSION['user_uuid'] ?? '';

		// set objects
		$config = $setting_array['config'] ?? config::load();
		$this->database = $setting_array['database'] ?? database::new(['config' => $config]);
	}

	protected function on_delete(array &$checked) {
		throw new \Exception('Not implemented');
	}

	protected function on_copy(array &$uuids) {
		throw new \Exception('Not implemented');
	}

	protected function on_toggle(array &$checked) {
		throw new \Exception('Not implemented');
	}

	public static function app_database_schema(): array {
		throw new \Exception('Not implemented');
	}

	public function after_delete() {
		// clear the access control session array
		if (isset($_SESSION['access_controls']['array'])) {
			unset($_SESSION['access_controls']['array']);
		}
	}

	/**
	 * Deletes one or more access control nodes.
	 *
	 * @param array $records Array of records to delete, where each record is an associative array containing the
	 *                       'uuid' and 'checked' keys.
	 *
	 * @return void
	 */
	public function delete_nodes(array $records) {
		if (empty($records)) {
			return;
		}

		parent::delete($records, 'access_control_nodes', 'access_control_node_');

		// clear the cache
		$cache = new cache;
		$cache->delete("configuration:acl.conf");

		// create the event socket connection
		event_socket::async("reloadacl");
	}
}
