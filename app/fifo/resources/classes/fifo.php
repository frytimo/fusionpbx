<?php

/**
 * fifo class
 */
class fifo extends app {

	/**
	 * declare constant variables
	 */
	const app_name = 'fifo';
	const app_uuid = '16589224-c876-aeb3-f59f-523a1c0801f7';

	// class-level configuration constants
	const PERMISSION_PREFIX = 'fifo_';
	const TABLE             = 'fifo';
	const UUID_PREFIX       = 'fifo_';
	const TOGGLE_FIELD      = 'fifo_enabled';
	const TOGGLE_VALUES     = ['true', 'false'];
	const LIST_PAGE         = 'fifo.php';


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
		//set domain and user UUIDs
		$this->domain_uuid = $setting_array['domain_uuid'] ?? $_SESSION['domain_uuid'] ?? '';

		//set objects
		$this->database = $setting_array['database'] ?? database::new();

		//assign the variables
		$this->name              = 'fifo';
		$this->description_field = 'fifo_description';

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

				//filter out unchecked queues, build where clause for below
				$uuids = [];
				foreach ($records as $record) {
					if (!empty($record['checked']) && $record['checked'] == 'true' && !empty($record['uuid']) && is_uuid($record['uuid'])) {
						$uuids[] = "'" . $record['uuid'] . "'";
					}
				}

				//get necessary fifo queue details
				if (!empty($uuids) && is_array($uuids) && @sizeof($uuids) != 0) {
					$sql                       = "select " . static::UUID_PREFIX . "uuid as uuid, dialplan_uuid from v_" . static::TABLE . " ";
					$sql                       .= "where domain_uuid = :domain_uuid ";
					$sql                       .= "and " . static::UUID_PREFIX . "uuid in (" . implode(', ', $uuids) . ") ";
					$parameters['domain_uuid'] = $this->domain_uuid;
					$rows                      = $this->database->select($sql, $parameters, 'all');
					if (is_array($rows) && @sizeof($rows) != 0) {
						foreach ($rows as $row) {
							$fifos[$row['uuid']]['dialplan_uuid'] = $row['dialplan_uuid'];
						}
					}
					unset($sql, $parameters, $rows, $row);
				}

				//build the delete array
				$x = 0;
				foreach ($fifos as $fifo_uuid => $fifo) {
					//add to the array
					$array[static::TABLE][$x][$this->name . '_uuid'] = $fifo_uuid;
					$array[static::TABLE][$x]['domain_uuid']         = $this->domain_uuid;
					$array['fifo_members'][$x]['fifo_uuid']         = $fifo_uuid;
					$array['fifo_members'][$x]['domain_uuid']       = $this->domain_uuid;
					$array['dialplans'][$x]['dialplan_uuid']        = $fifo['dialplan_uuid'];
					$array['dialplans'][$x]['domain_uuid']          = $this->domain_uuid;

					//increment the id
					$x++;
				}

				//delete the checked rows
				if (is_array($array) && @sizeof($array) != 0) {
					//grant temporary permissions
					$p = permissions::new();
					$p->add('fifo_member_delete', 'temp');
					$p->add('dialplan_delete', 'temp');

					//execute delete
					$this->database->delete($array);
					unset($array);

					//revoke temporary permissions
					$p->delete('fifo_member_delete', 'temp');
					$p->delete('dialplan_delete', 'temp');

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
			if (is_array($records) && @sizeof($records) != 0) {
				//get current toggle state
				foreach ($records as $record) {
					if (!empty($record['checked']) && $record['checked'] == 'true' && !empty($record['uuid']) && is_uuid($record['uuid'])) {
						$uuids[] = "'" . $record['uuid'] . "'";
					}
				}
				if (!empty($uuids) && is_array($uuids) && @sizeof($uuids) != 0) {
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
							$array[static::TABLE][$x][$this->name . '_uuid']    = uuid();
							$array[static::TABLE][$x][$this->description_field] = trim($row[$this->description_field]) . ' (' . $text['label-copy'] . ')';

							//increment the id
							$x++;
						}
					}
					unset($sql, $parameters, $rows, $row);
				}

				//save the changes and set the message
				if (is_array($array) && @sizeof($array) != 0) {

					//grant temporary permissions
					$p = permissions::new();
					$p->add('fifo_member_add', 'temp');

					//save the array

					$this->database->save($array);
					unset($array);

					//revoke temporary permissions
					$p->delete('fifo_member_add', 'temp');

					//set message
					message::add($text['message-copy']);
				}
				unset($records);
			}
		}
	}

}
