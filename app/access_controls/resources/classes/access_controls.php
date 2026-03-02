<?php

/**
 * access controls class
 */
class access_controls extends app implements app_config_db, app_config_permissions {
	/**
	 * declare constant variables
	 */
	const app_name = 'access_controls';

	const app_uuid = '1416a250-f6e1-4edc-91a6-5c9b883638fd';

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

		// call parent constructor to initialize has_* flags
		parent::__construct();
	}

	protected function on_delete(array &$checked) {}

	protected function on_copy(array &$uuids) { return []; }

	protected function on_toggle(array &$checked) { return []; }

	public static function app_config_db(): array {
		$access_controls_table = app_config::db()->standard_table('access_controls')
			->field(name: 'default')->boolean()->indexed()
		;
		$access_control_nodes_table = app_config::db()
			->table('access_control_nodes')
				->primary_key()
				->foreign_key(foreign_table: 'access_controls')
				->columns([
					'node_type',
					'node_cidr',
					'node_description',
				])
				->timestamps()
		;
		return [$access_controls_table, $access_control_nodes_table];
	}

	public static function app_config_permissions(): array {
		return app_config::permissions()
			->prefix('access_control')
				->meld(
					['view','add','edit','delete','node_view','node_add','node_edit','node_delete'],
					['superadmin']
				)
			->to_array()
		;
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