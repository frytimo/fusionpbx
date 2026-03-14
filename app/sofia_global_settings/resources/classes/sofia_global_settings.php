<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2019 - 2023
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

/**
 * sofia_global_settings class
 */
class sofia_global_settings extends app {

	/**
	 * declare constant variables
	 */
	const app_name = 'sofia_global_settings';
	const app_uuid = '240c25a3-a2cf-44ea-a300-0626eca5b945';

	// class-level configuration constants
	const PERMISSION_PREFIX = 'sofia_global_setting_';
	const TABLE             = 'sofia_global_settings';
	const TOGGLE_FIELD      = 'global_setting_enabled';
	const TOGGLE_VALUES     = ['true', 'false'];
	const LIST_PAGE         = 'sofia_global_settings.php';


	/**
	 * declare the variables
	 */

	private $name;
	private $description_field;

	/**
	 * Initializes the object with setting array.
	 *
	 * @param array $setting_array An array containing settings for domain, user, and database connections. Defaults to
	 *                             an empty array.
	 *
	 * @return void
	 */
	public function __construct(array $setting_array = []) {
		//set objects
		$this->database = $setting_array['database'] ?? database::new();

		//assign the variables
		$this->name              = 'sofia_global_setting';
		$this->description_field = 'global_setting_description';

		//initialize the parent class
		parent::__construct();
	}

	/**
	 * Deletes one or more records.
	 *
	 * @param array $records An array of record IDs to delete, where each ID is an associative array
	 *                       containing 'uuid' and 'checked' keys. The 'checked' value indicates
	 *                       whether the corresponding checkbox was checked for deletion.
	 *
	 * @return void No return value; this method modifies the database state and sets a message.
	 */
	public function delete($records) {
		if (permission_exists($this->name . '_delete')) {

			//add multi-lingual support
			$language = new text;
			$text     = $language->get();

			//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'], 'negative');
				header('Location: ' . static::LIST_PAGE);
				exit;
			}

			//delete multiple records
			if (!empty($records) && @sizeof($records) != 0) {
				//build the delete array
				$x = 0;
				foreach ($records as $record) {
					//add to the array
					if (!empty($record['checked']) && $record['checked'] == 'true' && is_uuid($record['sofia_global_setting_uuid'])) {
						$array[static::TABLE][$x]['sofia_global_setting_uuid'] = $record['sofia_global_setting_uuid'];
					}

					//increment the id
					$x++;
				}

				//delete the checked rows
				if (!empty($array) && @sizeof($array) != 0) {
					//execute delete
					$this->database->delete($array);
					unset($array);

					//set message
					message::add($text['message-delete']);
				}
				unset($records);
			}
		}
	}

	/**
	 * Toggles the state of one or more records.
	 *
	 * @param array $records  An array of record IDs to delete, where each ID is an associative array
	 *                        containing 'uuid' and 'checked' keys. The 'checked' value indicates
	 *                        whether the corresponding checkbox was checked for deletion.
	 *
	 * @return void No return value; this method modifies the database state and sets a message.
	 */
	public function toggle($records) {
		if (permission_exists($this->name . '_edit')) {

			//add multi-lingual support
			$language = new text;
			$text     = $language->get();

			//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'], 'negative');
				header('Location: ' . static::LIST_PAGE);
				exit;
			}

			//toggle the checked records
			if (!empty($records) && @sizeof($records) != 0) {
				//get current toggle state
				foreach ($records as $record) {
					if (!empty($record['checked']) && $record['checked'] == 'true' && is_uuid($record['sofia_global_setting_uuid'])) {
						$uuids[] = "'" . $record['sofia_global_setting_uuid'] . "'";
					}
				}
				if (!empty($uuids) && @sizeof($uuids) != 0) {
					$sql  = "select " . $this->name . "_uuid as uuid, " . static::TOGGLE_FIELD . " as toggle from v_" . static::TABLE . " ";
					$sql  .= "where " . $this->name . "_uuid in (" . implode(', ', $uuids) . ") ";
					$rows = $this->database->select($sql, null, 'all');
					if (!empty($rows) && @sizeof($rows) != 0) {
						foreach ($rows as $row) {
							$states[$row['uuid']] = $row['toggle'];
						}
					}
					unset($sql, $parameters, $rows, $row);
				}

				//build update array
				$x = 0;
				foreach ($states as $uuid => $state) {
					//create the array
					$array[static::TABLE][$x][$this->name . '_uuid'] = $uuid;
					$array[static::TABLE][$x][static::TOGGLE_FIELD]   = $state == static::TOGGLE_VALUES[0] ? static::TOGGLE_VALUES[1] : static::TOGGLE_VALUES[0];

					//increment the id
					$x++;
				}

				//save the changes
				if (!empty($array) && @sizeof($array) != 0) {
					//save the array

					$this->database->save($array);
					unset($array);

					//set message
					message::add($text['message-toggle']);
				}
				unset($records, $states);
			}
		}
	}

	/**
	 * Copies one or more records
	 *
	 * @param array $records  An array of record IDs to delete, where each ID is an associative array
	 *                        containing 'uuid' and 'checked' keys. The 'checked' value indicates
	 *                        whether the corresponding checkbox was checked for deletion.
	 *
	 * @return void No return value; this method modifies the database state and sets a message.
	 */
	public function copy($records) {
		if (permission_exists($this->name . '_add')) {

			//add multi-lingual support
			$language = new text;
			$text     = $language->get();

			//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'], 'negative');
				header('Location: ' . static::LIST_PAGE);
				exit;
			}

			//copy the checked records
			if (!empty($records) && @sizeof($records) != 0) {

				//get checked records
				foreach ($records as $record) {
					if (!empty($record['checked']) && $record['checked'] == 'true' && is_uuid($record['sofia_global_setting_uuid'])) {
						$uuids[] = "'" . $record['sofia_global_setting_uuid'] . "'";
					}
				}

				//create the array from existing data
				if (!empty($uuids) && @sizeof($uuids) != 0) {
					$sql  = "select * from v_" . static::TABLE . " ";
					$sql  .= "where sofia_global_setting_uuid in (" . implode(', ', $uuids) . ") ";
					$rows = $this->database->select($sql, null, 'all');
					if (!empty($rows) && @sizeof($rows) != 0) {
						$x = 0;
						foreach ($rows as $row) {
							//copy data
							$array[static::TABLE][$x] = $row;

							//add copy to the description
							$array[static::TABLE][$x][$this->name . '_uuid']    = uuid();
							$array[static::TABLE][$x]['global_setting_enabled'] = $row['global_setting_enabled'] === true ? 'true' : 'false';
							$array[static::TABLE][$x][$this->description_field] = trim($row[$this->description_field] ?? '') . trim(' (' . $text['label-copy'] . ')');

							//increment the id
							$x++;
						}
					}
					unset($sql, $parameters, $rows, $row);
				}

				//save the changes and set the message
				if (!empty($array) && @sizeof($array) != 0) {
					//save the array

					$this->database->save($array);
					unset($array);

					//set message
					message::add($text['message-copy']);
				}
				unset($records);
			}
		}
	}

}
