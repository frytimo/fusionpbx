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
 * Portions created by the Initial Developer are Copyright (C) 2008-2025
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * Mark J Crane <markjcrane@fusionpbx.com>
 */

// define the bridges class
class bridges extends app {
	/**
	 * declare constant variables
	 */
	const app_name = 'bridges';

	const app_uuid = 'a6a7c4c5-340a-43ce-bcbc-2ed9bab8659d';

	/**
	 * declare private variables
	 */
	protected $permission_prefix;

	protected $list_page;
	protected $table;
	protected $uuid_prefix;
	protected $toggle_field;
	protected $toggle_values;

	/**
	 * App-specific hook interface names for the two-tier hook dispatch system
	 */
	protected $edit_hook_interface = 'bridge_edit_hook';
	protected $list_hook_interface = 'bridge_list_page_hook';

	/**
	 * Initializes the object with settings and default values.
	 *
	 * @param array $setting_array Associative array of setting keys to their respective values (optional)
	 */
	public function __construct(array $setting_array = []) {
		// set domain and user UUIDs
		$this->domain_uuid = $setting_array['domain_uuid'] ?? $_SESSION['domain_uuid'] ?? '';
		$this->user_uuid = $setting_array['user_uuid'] ?? $_SESSION['user_uuid'] ?? '';

		// set objects
		$config = $setting_array['config'] ?? config::load();
		$this->database = $setting_array['database'] ?? database::new(['config' => $config]);
		$this->settings = $setting_array['settings'] ?? new settings(['database' => $this->database, 'domain_uuid' => $this->domain_uuid, 'user_uuid' => $this->user_uuid]);

		// assign private variables
		$this->permission_prefix = 'bridge_';
		$this->list_page = 'bridges.php';
		$this->table = 'bridges';
		$this->uuid_prefix = 'bridge_';
		$this->toggle_field = 'bridge_enabled';
		$this->toggle_values = ['true', 'false'];

		// call parent constructor to initialize has_* flags
		parent::__construct();
	}

	protected function on_delete(array &$checked) {
		return;
	}

	public function after_delete() {
		// clear the destinations session array
		if (isset($_SESSION['destinations']['array'])) {
			unset($_SESSION['destinations']['array']);
		}
	}

	public function after_save(array $array) {
		// clear the destinations session array
		if (isset($_SESSION['destinations']['array'])) {
			unset($_SESSION['destinations']['array']);
		}
	}

	/**
	 * Toggles the state of the specified records.
	 *
	 * @param array $records An array of record IDs to delete, where each ID is an associative array
	 *                       containing 'uuid' and 'checked' keys. The 'checked' value indicates
	 *                       whether the corresponding checkbox was checked for deletion.
	 *
	 * @return void No return value; this method modifies the database state and sets a message.
	 */
	public function on_toggle(array &$uuids) {
		$sql = "select " . $this->uuid_prefix . "uuid as uuid, " . $this->toggle_field . " as toggle from v_" . $this->table . " ";
		$sql .= "where (domain_uuid = :domain_uuid or domain_uuid is null) ";
		$sql .= "and " . $this->uuid_prefix . "uuid in (" . implode(', ', $uuids) . ") ";
		$parameters['domain_uuid'] = $this->domain_uuid;
		$rows = $this->database->select($sql, $parameters, 'all');
		if (!empty($rows)) {
			$states = [];
			foreach ($rows as $row) {
				$states[$row['uuid']] = $row['toggle'];
			}

			// build update array
			$array = [];
			$x = 0;
			foreach ($states as $uuid => $state) {
				$array[$this->table][$x][$this->uuid_prefix . 'uuid'] = $uuid;
				$array[$this->table][$x][$this->toggle_field] = $state == $this->toggle_values[0] ? $this->toggle_values[1] : $this->toggle_values[0];
				$x++;
			}
			$uuids = $array;
		}
	}

	public function after_toggle() {
		// clear the destinations session array
		if (isset($_SESSION['destinations']['array'])) {
			unset($_SESSION['destinations']['array']);
		}
	}

	/**
	 * Gets the database records using the UUIDs
	 *
	 * @param array $uuids
	 * @return never
	 */
	protected function on_copy(array &$uuids) {
		global $text;
		$array = [];
		$sql = "select * from v_" . $this->table . " ";
		$sql .= "where (domain_uuid = :domain_uuid or domain_uuid is null) ";
		$sql .= "and " . $this->uuid_prefix . "uuid in (" . implode(', ', $uuids) . ") ";
		$parameters['domain_uuid'] = $this->domain_uuid;
		$rows = $this->database->select($sql, $parameters, 'all');
		if (!empty($rows)) {
			foreach ($rows as $x => $row) {
				// convert boolean values to a string
				foreach ($row as $key => $value) {
					if (gettype($value) == 'boolean') {
						$value = $value ? 'true' : 'false';
						$row[$key] = $value;
					}
				}

				// copy data
				$array[$this->table][$x] = $row;

				// overwrite
				$array[$this->table][$x][$this->uuid_prefix . 'uuid'] = uuid();
				$array[$this->table][$x]['bridge_description'] = trim($row['bridge_description'] . ' (' . $text['label-copy'] . ')');
			}
			$uuids = $array;
		}
	}

	public static function app_database_schema(): array {
		$table = app_db::table('bridges');
		$table
			->primary_key()
			->foreign_key('domains')
			->name()
			->description()
			->enabled()
			->field('destination', true);

		return [$table];
	}

	private static function build_query_conditions(url_paging $url, bool $include_disabled = true, string $field_prefix = ''): array {
		$conditions = [];
		$parameters = [];

		$show = $url->get('show', '');
		$show_all = ($show === 'all' && permission_exists('bridge_all'));
		$domain_uuid = $url->get_settings()->get_domain_uuid();

		if (!$show_all) {
			if ((!is_uuid($domain_uuid)) && isset($_SESSION['domain_uuid']) && is_uuid($_SESSION['domain_uuid'])) {
				$domain_uuid = $_SESSION['domain_uuid'];
			}
			if (is_uuid($domain_uuid)) {
				$conditions[] = "(".$field_prefix."domain_uuid = :domain_uuid or ".$field_prefix."domain_uuid is null)";
				$parameters['domain_uuid'] = $domain_uuid;
			}
		}

		$search = $url->get('search', '');
		if ($search !== null && $search !== '') {
			$conditions[] = "(lower(".$field_prefix."bridge_name) like :search or lower(".$field_prefix."bridge_destination) like :search or lower(".$field_prefix."bridge_description) like :search)";
			$parameters['search'] = '%' . strtolower($search) . '%';
		}

		if (!$include_disabled) {
			$conditions[] = $field_prefix."bridge_enabled = :bridge_enabled";
			$parameters['bridge_enabled'] = 'true';
		}

		return [$conditions, $parameters];
	}

	public static function count(url_paging $url_paging, bool $include_disabled = true): int {
		list($conditions, $parameters) = self::build_query_conditions($url_paging, $include_disabled);
		$sql = "select count(bridge_uuid) from v_bridges";
		if (!empty($conditions)) {
			$sql .= " where " . implode(" and ", $conditions);
		}

		return $url_paging->get_settings()->database()->select($sql, $parameters, 'column');
	}

	public static function fetch(url_paging $url_paging, bool $include_disabled = true): array {
		list($conditions, $parameters) = self::build_query_conditions($url_paging, $include_disabled, 'b.');
		$sql = "select d.domain_uuid, b.bridge_uuid, d.domain_name, b.bridge_name, b.bridge_destination, cast(b.bridge_enabled as text) as bridge_enabled, b.bridge_description ";
		$sql .= "from v_bridges as b, v_domains as d ";
		$sql .= "where b.domain_uuid = d.domain_uuid ";
		if (!empty($conditions)) {
			$sql .= " and " . implode(" and ", $conditions);
		}
		$order_by = $url_paging->get('order_by', 'bridge_name');
		$order = $url_paging->get('order', 'asc');
		$sql .= order_by($order_by, $order, 'bridge_name', 'asc');
		$sql .= limit_offset($url_paging->get_rows_per_page(), $url_paging->offset());

		return $url_paging->get_settings()->database()->select($sql, $parameters, 'all');
	}
}
