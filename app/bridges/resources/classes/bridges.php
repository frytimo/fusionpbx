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
	 * Set in the constructor. Must be a database object and cannot be null.
	 * @var database Database Object
	 */
	private $database;

	/**
	 * Settings object set in the constructor. Must be a settings object and cannot be null.
	 * @var settings Settings Object
	 */
	private $settings;

	/**
	 * User UUID set in the constructor. This can be passed in through the $settings_array associative array or set in the session global array
	 * @var string
	 */
	private $user_uuid;

	/**
	 * Domain UUID set in the constructor. This can be passed in through the $settings_array associative array or set in the session global array
	 * @var string
	 */
	private $domain_uuid;

	/**
	 * declare private variables
	 */
	private $permission_prefix;

	private $list_page;
	private $table;
	private $uuid_prefix;
	private $toggle_field;
	private $toggle_values;

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
		$table = app_schema::table('bridges');
		$table
			->primary_key()
			->foreign_key('domains')
			->name()
			->description()
			->enabled()
			->field('destination', true);

		return [$table];
	}
}
