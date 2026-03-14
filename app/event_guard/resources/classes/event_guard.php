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
 * Portions created by the Initial Developer are Copyright (C) 2019-2025
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * Mark J Crane <markjcrane@fusionpbx.com>
 */

/**
 * event_guard_logs class
 */
class event_guard extends app {
	/**
	 * declare constant variables
	 */
	const app_name = 'event_guard';

	const app_uuid = 'c5b86612-1514-40cb-8e2c-3f01a8f6f637';

	// class-level configuration constants
	const PERMISSION_PREFIX = 'event_guard_log_';
	const TABLE             = 'event_guard_logs';
	const TOGGLE_FIELD      = '';
	const TOGGLE_VALUES     = ['block', 'pending'];
	const LIST_PAGE         = 'event_guard_logs.php';


	/**
	 * declare the variables
	 */
	private $name;

	private $config;

	/**
	 * Initializes the object with setting array.
	 *
	 * @param array $setting_array An array containing settings for domain, user, and database connections. Defaults to
	 *                             an empty array.
	 *
	 * @return void
	 */
	public function __construct($params = []) {
		// assign the variables
		$this->name = 'event_guard_log';
		$this->config = config::load();
		$this->database = database::new(['config' => $this->config]);

		//initialize the parent class
		parent::__construct();
	}

	/**
	 * Removes all duplicate IPs from the logs leaving the most recent entries. If there are many IPs then this could be a heavy operation.
	 * @return null
	 */
	public function sweep() {
		$driver = $this->config->get('database.0.driver');
		$prefix = database::TABLE_PREFIX;
		if ($driver === 'pgsql') {
			$sql = "DELETE FROM {$prefix}event_guard_logs";
			$sql .= " WHERE event_guard_log_uuid IN (";
			$sql .= "	SELECT event_guard_log_uuid FROM (";
			$sql .= "		SELECT event_guard_log_uuid,";
			$sql .= "			   ROW_NUMBER() OVER (PARTITION BY ip_address ORDER BY insert_date DESC) AS row_num";
			$sql .= "		FROM {$prefix}event_guard_logs";
			$sql .= "	) subquery";
			$sql .= "	WHERE row_num > 1";
			$sql .= ");";
		}
		if ($driver === 'mysql') {
			$sql .= "DELETE t FROM {$prefix}event_guard_logs t";
			$sql .= "	JOIN (";
			$sql .= "		SELECT event_guard_log_uuid";
			$sql .= "		FROM (";
			$sql .= "			SELECT event_guard_log_uuid,";
			$sql .= "				   ROW_NUMBER() OVER (PARTITION BY ip_address ORDER BY insert_date DESC) AS row_num";
			$sql .= "			FROM {$prefix}event_guard_logs";
			$sql .= "		) subquery";
			$sql .= "		WHERE row_num > 1";
			$sql .= "	) to_delete";
			$sql .= "	ON t.event_guard_log_uuid = to_delete.event_guard_log_uuid";
		}
		if (!empty($sql)) {
			$this->database->execute($sql);
		}

		return;
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
			// Add multi-lingual support
			$language = new text;
			$text = $language->get();

			// Validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'], 'negative');
				header('Location: ' . static::LIST_PAGE);
				exit;
			}

			// Delete multiple records
			if (is_array($records) && @sizeof($records) != 0) {
				// Build the delete array
				$x = 0;
				foreach ($records as $record) {
					// Add to the array
					if ($record['checked'] == 'true' && is_uuid($record['event_guard_log_uuid'])) {
						$array[static::TABLE][$x]['event_guard_log_uuid'] = $record['event_guard_log_uuid'];
					}

					// Increment the id
					$x++;
				}

				// Delete the checked rows
				if (is_array($array) && @sizeof($array) != 0) {
					// Execute delete
					$this->database->delete($array);
					unset($array);

					// Set the message
					message::add($text['message-delete']);
				}
				unset($records);
			}
		}
	}

	/**
	 * Unblocks multiple records.
	 *
	 * @param array $records An array of records to unblock, each containing 'event_guard_log_uuid' and 'checked' keys.
	 *
	 * @return void
	 */
	public function unblock($records) {
		if (permission_exists($this->name . '_unblock')) {
			// Add multi-lingual support
			$language = new text;
			$text = $language->get();

			// Validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'], 'negative');
				header('Location: ' . static::LIST_PAGE);
				exit;
			}

			// Delete multiple records
			if (is_array($records) && @sizeof($records) != 0) {
				// build the delete array
				$x = 0;
				foreach ($records as $record) {
					// add to the array
					if (!empty($record['checked']) && $record['checked'] == 'true' && is_uuid($record['event_guard_log_uuid'])) {
						$array[static::TABLE][$x]['event_guard_log_uuid'] = $record['event_guard_log_uuid'];
						$array[static::TABLE][$x]['log_status'] = 'pending';
					}

					// increment the id
					$x++;
				}

				// Delete the checked rows
				if (is_array($array) && @sizeof($array) != 0) {
					// Execute delete
					$this->database->save($array);
					unset($array);

					// Initialize the settings object
					$setting = new settings(["category" => 'switch']);

					// Send unblock event
					$cmd = "sendevent CUSTOM\n";
					$cmd .= "Event-Name: CUSTOM\n";
					$cmd .= "Event-Subclass: event_guard:unblock\n";
					$esl = event_socket::create();
					$switch_result = event_socket::command($cmd);

					// Set the message
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
			// Add multi-lingual support
			$language = new text;
			$text = $language->get();

			// Validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'], 'negative');
				header('Location: ' . static::LIST_PAGE);
				exit;
			}

			// Toggle the checked records
			if (is_array($records) && @sizeof($records) != 0) {
				// Get current toggle state
				foreach ($records as $record) {
					if (!empty($record['checked']) && $record['checked'] == 'true' && is_uuid($record['event_guard_log_uuid'])) {
						$uuids[] = "'" . $record['event_guard_log_uuid'] . "'";
					}
				}
				if (is_array($uuids) && @sizeof($uuids) != 0) {
					$sql = "select " . $this->name . "_uuid as uuid, " . static::TOGGLE_FIELD . " as toggle from v_" . static::TABLE . " ";
					$sql .= "where " . $this->name . "_uuid in (" . implode(', ', $uuids) . ") ";
					$rows = $this->database->select($sql, null, 'all');
					if (is_array($rows) && @sizeof($rows) != 0) {
						foreach ($rows as $row) {
							$states[$row['uuid']] = $row['toggle'];
						}
					}
					unset($sql, $parameters, $rows, $row);
				}

				// Build update array
				$x = 0;
				foreach ($states as $uuid => $state) {
					// Create the array
					$array[static::TABLE][$x][$this->name . '_uuid'] = $uuid;
					$array[static::TABLE][$x][static::TOGGLE_FIELD] = $state == static::TOGGLE_VALUES[0] ? static::TOGGLE_VALUES[1] : static::TOGGLE_VALUES[0];

					// Increment the id
					$x++;
				}

				// Save the changes
				if (is_array($array) && @sizeof($array) != 0) {
					// Save the array
					$this->database->save($array);
					unset($array);

					// Set the message
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
			// Add multi-lingual support
			$language = new text;
			$text = $language->get();

			// Validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'], 'negative');
				header('Location: ' . static::LIST_PAGE);
				exit;
			}

			// Copy the checked records
			if (is_array($records) && @sizeof($records) != 0) {
				// Get checked records
				foreach ($records as $record) {
					if (!empty($record['checked']) && $record['checked'] == 'true' && is_uuid($record['event_guard_log_uuid'])) {
						$uuids[] = "'" . $record['event_guard_log_uuid'] . "'";
					}
				}

				// Create the array from existing data
				if (is_array($uuids) && @sizeof($uuids) != 0) {
					$sql = "select * from v_" . static::TABLE . " ";
					$sql .= "where event_guard_log_uuid in (" . implode(', ', $uuids) . ") ";
					$rows = $this->database->select($sql, null, 'all');
					if (is_array($rows) && @sizeof($rows) != 0) {
						$x = 0;
						foreach ($rows as $row) {
							// Convert boolean values to a string
							foreach ($row as $key => $value) {
								if (gettype($value) == 'boolean') {
									$value = $value ? 'true' : 'false';
									$row[$key] = $value;
								}
							}

							// Copy data
							$array[static::TABLE][$x] = $row;

							// Add copy to the description
							$array[static::TABLE][$x]['event_guard_log_uuid'] = uuid();

							// Increment the id
							$x++;
						}
					}
					unset($sql, $parameters, $rows, $row);
				}

				// Save the changes and set the message
				if (is_array($array) && @sizeof($array) != 0) {
					// Save the array
					$this->database->save($array);
					unset($array);

					// Set the message
					message::add($text['message-copy']);
				}
				unset($records);
			}
		}
	}
}
