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
 Portions created by the Initial Developer are Copyright (C) 2008-2023
 the Initial Developer. All Rights Reserved.

 Contributor(s):
 Mark J Crane <markjcrane@fusionpbx.com>
*/

//define the contacts class
class contacts implements app_config_db {

	/**
	 * declare constant variables
	 */
	const app_name = 'contacts';
	const app_uuid = '04481e0e-a478-c559-adad-52bd4174574c';

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
	 * Domain UUID set in the constructor. This can be passed in through the $settings_array associative array or set
	 * in the session global array
	 *
	 * @var string
	 */
	private $domain_uuid;

	/**
	 * declare private variables
	 */
	private $permission_prefix;
	private $list_page;
	private $tables;
	private $table;
	private $uuid_prefix;

	/**
	 * declare public variables
	 */
	public $contact_uuid;


	/**
	 * Constructor for the class.
	 *
	 * This method initializes the object with setting_array and session data.
	 *
	 * @param array $setting_array An optional array of settings to override default values. Defaults to [].
	 */
	public function __construct(array $setting_array = []) {
		//set domain and user UUIDs
		$this->domain_uuid = $setting_array['domain_uuid'] ?? $_SESSION['domain_uuid'] ?? '';

		//set objects
		$this->database = $setting_array['database'] ?? database::new();

		//assign private variables
		$this->permission_prefix = 'contact_';
		$this->list_page         = 'contacts.php';
		$this->tables[]          = 'contact_addresses';
		$this->tables[]          = 'contact_attachments';
		$this->tables[]          = 'contact_emails';
		$this->tables[]          = 'contact_groups';
		$this->tables[]          = 'contact_notes';
		$this->tables[]          = 'contact_phones';
		$this->tables[]          = 'contact_relations';
		$this->tables[]          = 'contact_settings';
		$this->tables[]          = 'contact_times';
		$this->tables[]          = 'contact_urls';
		$this->tables[]          = 'contact_users';
		$this->tables[]          = 'contacts';
		$this->uuid_prefix       = 'contact_';
	}

	/**
	 * Deletes one or multiple records.
	 *
	 * @param array $records An array of record IDs to delete, where each ID is an associative array
	 *                       containing 'uuid' and 'checked' keys. The 'checked' value indicates
	 *                       whether the corresponding checkbox was checked for deletion.
	 *
	 * @return void No return value; this method modifies the database state and sets a message.
	 */
	public function delete($records) {
		if (permission_exists($this->permission_prefix . 'delete')) {

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
						if (is_array($this->tables) && @sizeof($this->tables) != 0) {
							foreach ($this->tables as $table) {
								$array[$table][$x][$this->uuid_prefix . 'uuid'] = $record['uuid'];
								$array[$table][$x]['domain_uuid']               = $this->domain_uuid;
							}
						}
					}
				}

				//delete the checked rows
				if (is_array($array) && @sizeof($array) != 0) {

					//grant temp permissions
					$p = permissions::new();
					foreach ($this->tables as $table) {
						$p->add(database::singular($table) . '_delete', 'temp');
					}

					//execute delete
					$this->database->delete($array);
					unset($array);

					//revoke temp permissions
					foreach ($this->tables as $table) {
						$p->delete(database::singular($table) . '_delete', 'temp');
					}

					//set message
					message::add($text['message-delete']);
				}
				unset($records);
			}
		}
	}

	/**
	 * Deletes one or multiple records.
	 *
	 * @param array $records An array of record IDs to delete, where each ID is an associative array
	 *                       containing 'uuid' and 'checked' keys. The 'checked' value indicates
	 *                       whether the corresponding checkbox was checked for deletion.
	 *
	 * @return void No return value; this method modifies the database state and sets a message.
	 */
	public function delete_properties($records) {
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

			//check permissions and build the delete array
			$x = 0;
			foreach ($records as $property_name => $properties) {
				if (permission_exists(database::singular($property_name) . '_delete')) {
					if (is_array($properties) && @sizeof($properties) != 0) {
						foreach ($properties as $property) {
							if ($property['checked'] == 'true' && is_uuid($property['uuid'])) {
								$array[$property_name][$x][database::singular($property_name) . '_uuid'] = $property['uuid'];
								$array[$property_name][$x]['contact_uuid']                               = $this->contact_uuid;
								$array[$property_name][$x]['domain_uuid']                                = $this->domain_uuid;
								$x++;
							}
						}
					}
				}
			}

			//delete the checked rows
			if (is_array($array) && @sizeof($array) != 0) {
				//execute delete
				$this->database->delete($array);
				unset($array);
			}
			unset($records);
		}
	}

	/**
	 * Deletes one or multiple records.
	 *
	 * @param array $records An array of record IDs to delete, where each ID is an associative array
	 *                       containing 'uuid' and 'checked' keys. The 'checked' value indicates
	 *                       whether the corresponding checkbox was checked for deletion.
	 *
	 * @return void No return value; this method modifies the database state and sets a message.
	 */
	public function delete_users($records) {
		//assign private variables
		$this->permission_prefix = 'contact_user_';
		$this->table             = 'contact_users';
		$this->uuid_prefix       = 'contact_user_';

		if (permission_exists($this->permission_prefix . 'delete')) {

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

				//filter out unchecked ivr menu options, build delete array
				$x = 0;
				foreach ($records as $record) {
					if (!empty($record['checked']) && $record['checked'] == 'true' && is_uuid($record['uuid'])) {
						$array[$this->table][$x][$this->uuid_prefix . 'uuid'] = $record['uuid'];
						$array[$this->table][$x]['contact_uuid']              = $this->contact_uuid;
						$x++;
					}
				}

				//delete the checked rows
				if (is_array($array) && @sizeof($array) != 0) {
					//execute delete
					$this->database->delete($array);
					unset($array);
				}
				unset($records);
			}
		}
	}

	/**
	 * Deletes one or multiple records.
	 *
	 * @param array $records An array of record IDs to delete, where each ID is an associative array
	 *                       containing 'uuid' and 'checked' keys. The 'checked' value indicates
	 *                       whether the corresponding checkbox was checked for deletion.
	 *
	 * @return void No return value; this method modifies the database state and sets a message.
	 */
	public function delete_groups($records) {
		//assign private variables
		$this->permission_prefix = 'contact_group_';
		$this->table             = 'contact_groups';
		$this->uuid_prefix       = 'contact_group_';

		if (permission_exists($this->permission_prefix . 'delete')) {

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

				//filter out unchecked ivr menu options, build delete array
				$x = 0;
				foreach ($records as $record) {
					if (!empty($record['checked']) && $record['checked'] == 'true' && is_uuid($record['uuid'])) {
						$array[$this->table][$x][$this->uuid_prefix . 'uuid'] = $record['uuid'];
						$array[$this->table][$x]['contact_uuid']              = $this->contact_uuid;
						$x++;
					}
				}

				//delete the checked rows
				if (is_array($array) && @sizeof($array) != 0) {
					//execute delete
					$this->database->delete($array);
					unset($array);
				}
				unset($records);
			}
		}
	} //method

	public static function app_database_schema(): array {
		$contacts = app_db::standard_table('contacts')
			// fields will automatically be prefixed with 'contact_'
			->fields( [
				'type',
				'organization',
				'name_prefix',
				'name_given',
				'name_family',
				'name_middle',
				'name_suffix',
				'nickname',
				'title',
				'role',
				'category',
				'url',
				'time_zone',
				'note',
			])
			->columns([
				'last_mod_date',
				'last_mod_user',
			])
		;
		$contact_addresses = app_db::standard_table('contact_addresses')
			->parent('contacts')
			->columns([
				'address_type',
				'address_label',
				'address_primary',
				'address_street',
				'address_extended',
				'address_community',
				'address_locality',
				'address_region',
				'address_postal_code',
				'address_country',
				'address_latitude',
				'address_longitude',
				'address_description',
			])
		;
		$contact_phones = app_db::standard_table('contact_phones')
			->parent('contacts')
			->columns([
				'phone_label',
				'phone_type_voice',
				'phone_type_fax',
				'phone_type_video',
				'phone_type_text',
				'phone_speed_dial',
				'phone_country_code',
				'phone_number',
				'phone_extension',
				'phone_primary',
				'phone_description',
			])
		;
		$contact_notes = app_db::standard_table('contact_notes')
			->parent('contacts')
			->columns([
				'contact_note',
				['number' => 'contact_id']
			])
		;
		$contact_emails = app_db::table('contact_users')
			->parent('contacts')
			->columns([
				'email_label',
				'email_address',
				'email_primary',
				'email_description',
			])
			->foreign_key('users')
		;
		$contact_groups = app_db::table('contact_groups')
			->parent('contacts')
			->columns([
				'group_name',
				'group_description',
			])
			->foreign_key('groups')
		;
		$contact_settings = app_db::table('contact_settings')
			->parent('contacts')
			->columns([
				'setting_name',
				'setting_value',
				'setting_enabled',
				'setting_description',
			])
			->foreign_key('contact_settings')
		;
		$contact_relations = app_db::table('contact_relations')
			->parent('contacts')
			->columns([
				'relation_type',
				'relation_label',
				'relation_contact_uuid',
				'relation_description',
			])
			->foreign_key('contacts', 'relation_contact_uuid')
		;
		$contact_emails = app_db::table('contact_emails')
			->parent('contacts')
			->columns([
				'email_label',
				'email_address',
				'email_primary',
				'email_description',
			])
			->foreign_key('users')
		;
		$contact_urls = app_db::table('contact_urls')
			->parent('contacts')
			->columns([
				'url_label',
				'url_address',
				'url_primary',
				'url_description',
			])
			->foreign_key('contact_urls')
		;
		$contact_times = app_db::table('contact_times')
			->parent('contacts')
			->columns([
				'time_label',
				'time_type',
				'time_value',
				'time_description',
			])
			->foreign_key('contact_times')
		;
		$contact_attachments = app_db::table('contact_attachments')
			->parent('contacts')
			->columns([
				'attachment_label',
				'attachment_type',
				'attachment_path',
				'attachment_description',
			])
			->foreign_key('contact_attachments')
		;
		return [
			$contacts,
			$contact_addresses,
			$contact_phones,
			$contact_notes,
			$contact_emails,
			$contact_groups,
			$contact_settings,
			$contact_relations,
			$contact_urls,
			$contact_times,
			$contact_attachments,
		];
	}

} //class
