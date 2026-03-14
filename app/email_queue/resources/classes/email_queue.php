<?php

/**
 * email_queue class
 */
class email_queue extends app {

	/**
	 * declare constant variables
	 */
	const app_name = 'email_queue';
	const app_uuid = '5befdf60-a242-445f-91b3-2e9ee3e0ddf7';

	// class-level configuration constants
	const PERMISSION_PREFIX = 'email_queue_';
	const TABLE             = 'email_queue';
	const TOGGLE_FIELD      = '';
	const TOGGLE_VALUES     = ['true', 'false'];
	const LIST_PAGE         = 'email_queue.php';


	/**
	 * declare the variables
	 */
	private $name;

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
		$this->user_uuid   = $setting_array['user_uuid'] ?? $_SESSION['user_uuid'] ?? '';

		//set objects
		$this->database = $setting_array['database'] ?? database::new();
		$this->settings = $setting_array['settings'] ?? new settings(['database' => $this->database, 'domain_uuid' => $this->domain_uuid, 'user_uuid' => $this->user_uuid]);

		//assign the variables
		$this->name          = 'email_queue';

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
			if (is_array($records) && @sizeof($records) != 0) {
				//build the delete array
				$x = 0;
				foreach ($records as $record) {
					//add to the array
					if ($record['checked'] == 'true' && is_uuid($record['uuid'])) {
						$array[static::TABLE][$x][$this->name . '_uuid']              = $record['uuid'];
						$array['email_queue_attachments'][$x][$this->name . '_uuid'] = $record['uuid'];
						//$array[static::TABLE][$x]['domain_uuid'] = $this->domain_uuid;
					}

					//increment the id
					$x++;
				}

				//delete the checked rows
				if (is_array($array) && @sizeof($array) != 0) {
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
	 * Resend multiple records.
	 *
	 * This method will resend the specified records if the permission to edit exists.
	 * It first checks if the token is valid, then it iterates over the records and
	 * updates their status to 'waiting' and resets the retry count. Finally, it saves
	 * the changes to the database.
	 *
	 * @param array $records The records to resend.
	 *
	 * @return void
	 */
	public function resend($records) {
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

			//resend multiple emails
			if (is_array($records) && @sizeof($records) != 0) {
				//build the message array
				$x = 0;
				foreach ($records as $record) {
					//add to the array
					if ($record['checked'] == 'true' && is_uuid($record['uuid'])) {
						$array[static::TABLE][$x][$this->name . '_uuid'] = $record['uuid'];
						$array[static::TABLE][$x]['email_status']        = 'waiting';
						$array[static::TABLE][$x]['email_retry_count']   = null;
					}

					//increment the id
					$x++;
				}

				//save the changes
				if (is_array($array) && @sizeof($array) != 0) {
					//save the array

					$this->database->save($array);
					unset($array);

					//set message
					message::add($text['message-resending_messages']);
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
			if (is_array($records) && @sizeof($records) != 0) {
				//get current toggle state
				foreach ($records as $record) {
					if ($record['checked'] == 'true' && is_uuid($record['uuid'])) {
						$uuids[] = "'" . $record['uuid'] . "'";
					}
				}
				if (is_array($uuids) && @sizeof($uuids) != 0) {
					$sql                       = "select " . $this->name . "_uuid as uuid, " . static::TOGGLE_FIELD . " as toggle from v_" . static::TABLE . " ";
					$sql                       .= "where " . $this->name . "_uuid in (" . implode(', ', $uuids) . ") ";
					$sql                       .= "and (domain_uuid = :domain_uuid or domain_uuid is null) ";
					$parameters['domain_uuid'] = $this->domain_uuid;
					$rows                      = $this->database->select($sql, $parameters, 'all');
					if (is_array($rows) && @sizeof($rows) != 0) {
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
				if (is_array($array) && @sizeof($array) != 0) {
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
			if (is_array($records) && @sizeof($records) != 0) {

				//get checked records
				foreach ($records as $record) {
					if ($record['checked'] == 'true' && is_uuid($record['uuid'])) {
						$uuids[] = "'" . $record['uuid'] . "'";
					}
				}

				//create the array from existing data
				if (is_array($uuids) && @sizeof($uuids) != 0) {
					$sql                       = "select * from v_" . static::TABLE . " ";
					$sql                       .= "where " . $this->name . "_uuid in (" . implode(', ', $uuids) . ") ";
					$sql                       .= "and (domain_uuid = :domain_uuid or domain_uuid is null) ";
					$parameters['domain_uuid'] = $this->domain_uuid;
					$rows                      = $this->database->select($sql, $parameters, 'all');
					if (is_array($rows) && @sizeof($rows) != 0) {
						$x = 0;
						foreach ($rows as $row) {
							//copy data
							$array[static::TABLE][$x] = $row;

							//add copy to the description
							$array[static::TABLE][$x][$this->name . '_uuid'] = uuid();

							//increment the id
							$x++;
						}
					}
					unset($sql, $parameters, $rows, $row);
				}

				//save the changes and set the message
				if (is_array($array) && @sizeof($array) != 0) {
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
