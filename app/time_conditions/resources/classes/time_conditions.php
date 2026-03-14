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
	Copyright (C) 2010-2025
	All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//define the time conditions class
class time_conditions extends app {

	/**
	 * declare constant variables
	 */
	const app_name = 'time_conditions';
	const app_uuid = '4b821450-926b-175a-af93-a03c441818b1';

	// class-level configuration constants
	const PERMISSION_PREFIX = 'time_condition_';
	const LIST_PAGE         = 'time_conditions.php';
	const TABLE             = 'dialplans';
	const UUID_PREFIX       = 'dialplan_';
	const TOGGLE_FIELD      = 'dialplan_enabled';
	const TOGGLE_VALUES     = ['true', 'false'];


	/**
	 * Username set in the constructor. This can be passed in through the $settings_array associative array or set in
	 * the session global array
	 *
	 * @var string
	 */
	private $username;

	/**
	 * Domain name set in the constructor. This can be passed in through the $settings_array associative array or set
	 * in the session global array
	 *
	 * @var string
	 */
	private $domain_name;

	/**
	 * declare public/private properties
	 */
	protected $list_page;
	private $dialplan_global;

	/**
	 * Initializes the object with setting array.
	 *
	 * @param array $setting_array An array containing settings for domain, user, and database connections. Defaults to
	 *                             an empty array.
	 *
	 * @return void
	 */
	public function __construct(array $setting_array = []) {
		//set domain and user UUIDs
		$this->domain_uuid = $setting_array['domain_uuid'] ?? $_SESSION['domain_uuid'] ?? '';

		//set objects
		$this->database = $setting_array['database'] ?? database::new();

		//set the default value
		$this->dialplan_global = false;

		//assign property defaults
		$this->list_page         = 'time_conditions.php';

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
		if (permission_exists(static::PERMISSION_PREFIX . 'delete')) {

			//add multi-lingual support
			$language = new text;
			$text     = $language->get();

			//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'], 'negative');
				header('Location: ' . $this->list_page);
				exit;
			}

			//delete multiple records
			if (is_array($records) && @sizeof($records) != 0) {

				//build the delete array
				foreach ($records as $x => $record) {
					if (!empty($record['checked']) && $record['checked'] == 'true' && is_uuid($record['uuid'])) {

						//build delete array
						$array[static::TABLE][$x][static::UUID_PREFIX . 'uuid'] = $record['uuid'];
						$array['dialplan_details'][$x]['dialplan_uuid']       = $record['uuid'];

						//get the dialplan context
						$sql                         = "select dialplan_context from v_dialplans ";
						$sql                         .= "where dialplan_uuid = :dialplan_uuid ";
						$parameters['dialplan_uuid'] = $record['uuid'];
						$dialplan_contexts[]         = $this->database->select($sql, $parameters, 'column');
						unset($sql, $parameters);

					}
				}

				//delete the checked rows
				if (is_array($array) && @sizeof($array) != 0) {

					//grant temporary permissions
					$p = permissions::new();
					$p->add('dialplan_delete', 'temp');
					$p->add('dialplan_detail_delete', 'temp');

					//execute delete
					$this->database->delete($array);

					//revoke temporary permissions
					$p->delete('dialplan_delete', 'temp');
					$p->delete('dialplan_detail_delete', 'temp');

					//clear the cache
					if (is_array($dialplan_contexts) && @sizeof($dialplan_contexts) != 0) {
						$dialplan_contexts = array_unique($dialplan_contexts, SORT_STRING);
						$cache             = new cache;
						foreach ($dialplan_contexts as $dialplan_context) {
							$cache->delete("dialplan:" . $dialplan_context);
						}
					}

					//clear the destinations session array
					if (isset($_SESSION['destinations']['array'])) {
						unset($_SESSION['destinations']['array']);
					}

					//set message
					message::add($text['message-delete'] . ': ' . @sizeof($array[static::TABLE]));

				}
				unset($records, $array);

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
		if (permission_exists(static::PERMISSION_PREFIX . 'edit')) {

			//add multi-lingual support
			$language = new text;
			$text     = $language->get();

			//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'], 'negative');
				header('Location: ' . $this->list_page);
				exit;
			}

			//toggle the checked records
			if (is_array($records) && @sizeof($records) != 0) {

				//get current toggle state
				foreach ($records as $x => $record) {
					if (!empty($record['checked']) && $record['checked'] == 'true' && is_uuid($record['uuid'])) {
						$uuids[] = "'" . $record['uuid'] . "'";
					}
				}
				if (is_array($uuids) && @sizeof($uuids) != 0) {
					$sql                       = "select " . static::UUID_PREFIX . "uuid as uuid, " . static::TOGGLE_FIELD . " as toggle, dialplan_context from v_" . static::TABLE . " ";
					$sql                       .= "where (domain_uuid = :domain_uuid or domain_uuid is null) ";
					$sql                       .= "and " . static::UUID_PREFIX . "uuid in (" . implode(', ', $uuids) . ") ";
					$parameters['domain_uuid'] = $this->domain_uuid;
					$rows                      = $this->database->select($sql, $parameters, 'all');
					if (is_array($rows) && @sizeof($rows) != 0) {
						foreach ($rows as $row) {
							$states[$row['uuid']] = $row['toggle'];
							$dialplan_contexts[]  = $row['dialplan_context'];
						}
					}
					unset($sql, $parameters, $rows, $row);
				}

				//build update array
				$x = 0;
				foreach ($states as $uuid => $state) {
					$array[static::TABLE][$x][static::UUID_PREFIX . 'uuid'] = $uuid;
					$array[static::TABLE][$x][static::TOGGLE_FIELD]         = $state == static::TOGGLE_VALUES[0] ? static::TOGGLE_VALUES[1] : static::TOGGLE_VALUES[0];
					$x++;
				}

				//save the changes
				if (is_array($array) && @sizeof($array) != 0) {

					//grant temporary permissions
					$p = permissions::new();
					$p->add('dialplan_edit', 'temp');

					//save the array

					$this->database->save($array);
					unset($array);

					//revoke temporary permissions
					$p->delete('dialplan_edit', 'temp');

					//clear the cache
					if (is_array($dialplan_contexts) && @sizeof($dialplan_contexts) != 0) {
						$dialplan_contexts = array_unique($dialplan_contexts, SORT_STRING);
						$cache             = new cache;
						foreach ($dialplan_contexts as $dialplan_context) {
							$cache->delete("dialplan:" . $dialplan_context);
						}
					}

					//clear the destinations session array
					if (isset($_SESSION['destinations']['array'])) {
						unset($_SESSION['destinations']['array']);
					}

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
		if (permission_exists(static::PERMISSION_PREFIX . 'add')) {

			//add multi-lingual support
			$language = new text;
			$text     = $language->get();

			//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'], 'negative');
				header('Location: ' . $this->list_page);
				exit;
			}

			//copy the checked records
			if (is_array($records) && @sizeof($records) != 0) {

				//get checked records
				foreach ($records as $x => $record) {
					if (!empty($record['checked']) && $record['checked'] == 'true' && is_uuid($record['uuid'])) {
						$uuids[] = "'" . $record['uuid'] . "'";
					}
				}

				//create insert array from existing data
				if (is_array($uuids) && @sizeof($uuids) != 0) {

					//primary table
					$sql  = "select * from v_" . static::TABLE . " ";
					$sql  .= "where " . static::UUID_PREFIX . "uuid in (" . implode(', ', $uuids) . ") ";
					$rows = $this->database->select($sql, $parameters ?? null, 'all');
					if (is_array($rows) && @sizeof($rows) != 0) {
						$y = 0;
						foreach ($rows as $x => $row) {
							$primary_uuid = uuid();

							//convert boolean values to a string
							foreach ($row as $key => $value) {
								if (gettype($value) == 'boolean') {
									$value     = $value ? 'true' : 'false';
									$row[$key] = $value;
								}
							}

							//copy data
							$array[static::TABLE][$x] = $row;

							//overwrite
							$array[static::TABLE][$x][static::UUID_PREFIX . 'uuid'] = $primary_uuid;
							$array[static::TABLE][$x]['dialplan_description']      = trim($row['dialplan_description'] . ' (' . $text['label-copy'] . ')');

							//details sub table
							$sql_2                         = "select * from v_dialplan_details where dialplan_uuid = :dialplan_uuid";
							$parameters_2['dialplan_uuid'] = $row['dialplan_uuid'];
							$rows_2                        = $this->database->select($sql_2, $parameters_2, 'all');
							if (is_array($rows_2) && @sizeof($rows_2) != 0) {
								foreach ($rows_2 as $row_2) {

									//convert boolean values to a string
									foreach ($row_2 as $key => $value) {
										if (gettype($value) == 'boolean') {
											$value       = $value ? 'true' : 'false';
											$row_2[$key] = $value;
										}
									}

									//copy data
									$array['dialplan_details'][$y] = $row_2;

									//overwrite
									$array['dialplan_details'][$y]['dialplan_detail_uuid'] = uuid();
									$array['dialplan_details'][$y]['dialplan_uuid']        = $primary_uuid;

									//increment
									$y++;

								}
							}
							unset($sql_2, $parameters_2, $rows_2, $row_2);

							//get dialplan contexts
							$dialplan_contexts[] = $row['dialplan_context'];
						}
					}
					unset($sql, $parameters, $rows, $row);
				}

				//save the changes and set the message
				if (is_array($array) && @sizeof($array) != 0) {

					//grant temporary permissions
					$p = permissions::new();
					$p->add('dialplan_add', 'temp');
					$p->add('dialplan_detail_add', 'temp');

					//save the array
					$this->database->save($array);
					unset($array);

					//revoke temporary permissions
					$p->delete('dialplan_add', 'temp');
					$p->delete('dialplan_detail_add', 'temp');

					//clear the cache
					if (is_array($dialplan_contexts) && @sizeof($dialplan_contexts) != 0) {
						$dialplan_contexts = array_unique($dialplan_contexts, SORT_STRING);
						$cache             = new cache;
						foreach ($dialplan_contexts as $dialplan_context) {
							$cache->delete("dialplan:" . $dialplan_context);
						}
					}

					//set message
					message::add($text['message-copy']);

				}
				unset($records);
			}

		}
	} //method

} //class
