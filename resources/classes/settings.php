<?php

/**
 * settings class
 * 
 */
class settings {

	private $domain_uuid;
	private $user_uuid;
	private $device_uuid;
	private $device_profile_uuid;
	private $category;
	private $settings;
	private $database;

	/**
	 * Connects to the database and pulls all enabled setting values from the v_settings table.
	 * <p>
	 * The $settings array can have the following keys:<br>
	 *   <ul>
	 *     <li>domain_uuid</li>
	 *     <li>user_uuid</li>
	 *     <li>device_uuid</li>
	 *     <li>device_profile_uuid</li>
	 *     <li>category</li>
	 *   </ul>
	 * </p>
	 * <p>NOTE:<br>
	 * If the v_settings table does not exist or the database connection fails, the settings object will silently fail
	 * resulting in empty settings.
	 * </p>
	 * @param array $settings
	 * @depends database::new()
	 */
	public function __construct(array $settings = []) {

		//initialize the internal settings to be an array
		$this->settings = [];

		//open a database connection
		$this->database = database::new();

		//set the values from the array
		$this->domain_uuid = $settings['domain_uuid'] ?? null;
		$this->user_uuid = $settings['user_uuid'] ?? null;
		$this->device_uuid = $settings['device_uuid'] ?? null;
		$this->device_profile_uuid = $settings['device_profile_uuid'] ?? null;
		$this->category = $settings['category'] ?? null;

		//set the default settings for all domains
		$this->default_settings();

		//set the domain settings overriding the defaults for all domains
		if (!empty($this->domain_uuid)) {
			$this->domain_settings();
		}

		//set the user settings overriding the defaults for the domain if set
		if (!empty($this->user_uuid)) {
			$this->user_settings();
		}

		//debug show the settings
		//print_r($this->settings);

		//add settings to the session array
		if (!defined('STDIN') && !empty($this->settings)) {
			foreach($this->settings as $key => $row) {
				$_SESSION[$key] = $row;
			}
		}

	}

	/**
	 * Get the value from the loaded settings.
	 * <p>If <i>$category</i> is empty, the entire settings array is returned.
	 * If <i>$subcategory</i> is empty, the category array is returned.
	 * If both <i>$category</i> and <i>$subcategory</i> are supplied, the value or value array
	 * is returned provided the category/subcategory exists. If both <i>$category</i> and <i>$subcategory</i>
	 * are supplied but <i>$category</i> and <i>$subcategory</i> are not set in the array, the default value
	 * will be returned.</p>
	 * <p>Examples:<br>
	 * <code>
	 * //get all values in a category
	 * $settings = new settings();
	 * $all_settings = $settings->get();
	 * print_r($all_settings); //shows all settings stored in the settings object
	 *
	 * //get all values in a subcategory
	 * $settings = new settings();
	 * $subcategory_array = $settings->get('switch');
	 * print_r($subcategory_array); //shows the array of switch or null if there are no settings
	 *
	 * //get a specific value
	 * $settings = new settings();
	 * $value = $settings->get('switch', 'sounds', '/usr/share/freeswitch/sounds');
	 * echo "switch sounds directory: {$value}\n";	//shows the value stored in category 'switch'
	 *												//with subcategory 'sounds'. In the case
	 *												//that does not exist, the value of
	 *												//'/usr/share/freeswitch/sounds' is returned
	 *
	 * </code></p>
	 * <p>NOTE:<br>
	 * In the case that the $category and $subcategory is null or is an empty string then the $default_value
	 * is returned instead. This is to account for values that are set to zero and considered valid.
	 * </p>
	 * @param string|null $category
	 * @param string|null $subcategory
	 * @param mixed $default_value
	 * @return mixed setting as string, category/subcategory as array, mixed from the default_value or null
	 */
	public function get(?string  $category = null, ?string $subcategory = null, mixed $default_value = null): mixed {
		//all settings requested
		if (empty($category)) {
			return $this->settings;
		}
		//entire category requested
		elseif (empty($subcategory)) {
			return $this->settings[$category] ?? [];
		}
		//specific setting requested
		if (isset($this->settings[$category][$subcategory]) && $this->settings[$category][$subcategory] !== '') {
			return $this->settings[$category][$subcategory];
		}
		//specific setting requested but it was empty so return default
		else {
			return $default_value;
		}
	}

	/**
	 * Set the default, domain, user, device or device profile settings
	 * @param string $table_prefix table prefix for the settings. This is normally the name of the table without '_settings'
	 * @param string $uuid uuid of the setting or empty string. If set to an empty string then a new uuid will be created.
	 * @param string $category Category of the setting.
	 * @param string $subcategory Subcategory of the setting.
	 * @param string $type (optional) Type of the setting (array, numeric, text, etc). Default is text.
	 * @param string $value (optional) Value to set. Default is empty string.
	 * @param bool $enabled (optional) True or False. Default is True.
	 * @param string $description (optional) Description. Default is empty string.
	 * @depends permissions::add
	 * @depends permissions::delete
	 * @depends database::save
	 */
	public function set(string $table_prefix, string $uuid, string $category, string $subcategory, string $type = 'text', string $value = "", bool $enabled = true, string $description = "") {
		//set the table name
		$table_name = $table_prefix.'_settings';

		//init record as an array
		$record = [];
		if(!empty($this->domain_uuid)) {
			$record[$table_name][0]['domain_uuid'] = $this->domain_uuid;
		}
		if(!empty($this->user_uuid)) {
			$record[$table_name][0]['user_uuid'] = $this->user_uuid;
		}
		if(!empty($this->device_uuid)) {
			$record[$table_name][0]['device_uuid'] = $this->device_uuid;
		}
		if(!empty($this->device_profile_uuid)) {
			$record[$table_name][0]['device_profile_uuid'] = $this->device_profile_uuid;
		}
		//check for new record
		if(!is_uuid($uuid)) {
			$uuid = uuid();
		}
		//build the array
		$record[$table_name][0][$table_prefix.'_setting_uuid'       ] = $uuid;
		$record[$table_name][0][$table_prefix.'_setting_category'   ] = $category;
		$record[$table_name][0][$table_prefix.'_setting_subcategory'] = $subcategory;
		$record[$table_name][0][$table_prefix.'_setting_name'       ] = $type;
		$record[$table_name][0][$table_prefix.'_setting_value'      ] = $value;
		$record[$table_name][0][$table_prefix.'_setting_enabled'    ] = $enabled;
		$record[$table_name][0][$table_prefix.'_setting_description'] = $description;

		//grant temporary permissions
		$p = new permissions;
		$p->add($table_prefix.'_setting_add', 'temp');
		$p->add($table_prefix.'_setting_edit', 'temp');

		//execute insert
		$this->database->app_name = $table_name;
		$this->database->save($record);

		//revoke temporary permissions
		$p->delete($table_prefix.'_setting_add', 'temp');
		$p->delete($table_prefix.'_setting_edit', 'temp');
	}

	/**
	 * Returns the number of categories loaded
	 * @return int The count of categories
	 */
	public function count(): int {
		return count($this->settings);
	}

	/**
	 * Counts the number of elements in the internal settings array
	 * @param array $settings Used internally for counting the elements
	 * @return int Number of total elements in the internal settings array
	 */
	public function count_recursive(array $settings = null): int {
		$count = 0;
		if ($settings === null) {
			$settings = $this->settings;
		}
		foreach ($settings as $element) {
			if (is_array($element)) {
				$count += recursiveCount($element);
			} else {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Set the default settings for all domains
	 */
	private function default_settings() {

		//get the default settings
		$sql = "select * from v_default_settings ";
		$sql .= "where default_setting_enabled = 'true' ";
		if (!empty($this->category)) {
			$sql .= "and default_setting_category = :default_setting_category ";
			$parameters['default_setting_category'] = $this->category;
		}
		$sql .= "order by default_setting_order asc ";
		$result = $this->database->select($sql, $parameters ?? null, 'all');
		if (!empty($result)) {
			foreach ($result as $row) {
				$name = $row['default_setting_name'];
				$category = $row['default_setting_category'];
				$subcategory = $row['default_setting_subcategory'];
				if (empty($subcategory)) {
					if ($name == "array") {
						if (!isset($this->settings[$category]) || !is_array($this->settings[$category])) {
							$this->settings[$category] = array();
						}
						$this->settings[$category][] = $row['default_setting_value'];
					}
					else {
						$this->settings[$category] = $row['default_setting_value'];
					}
				}
				else {
					if ($name == "array") {
						if (!isset($this->settings[$category][$subcategory]) || !is_array($this->settings[$category][$subcategory])) {
							$this->settings[$category][$subcategory] = array();
						}
						$this->settings[$category][$subcategory][] = $row['default_setting_value'];
					}
					else {
						$this->settings[$category][$subcategory] = $row['default_setting_value'];
					}
				}
			}
		}
	}


	/**
	 * set the domain settings
	 */
	private function domain_settings() {

		$sql = "select * from v_domain_settings ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$sql .= "and domain_setting_enabled = 'true' ";
		$parameters['domain_uuid'] = $this->domain_uuid;
		$result = $this->database->select($sql, $parameters, 'all');
		if (!empty($result)) {
			foreach ($result as $row) {
				$name = $row['domain_setting_name'];
				$category = $row['domain_setting_category'];
				$subcategory = $row['domain_setting_subcategory'];
				if (empty($subcategory)) {
					if ($name == "array") {
						if (!isset($this->settings[$category]) || !is_array($this->settings[$category])) {
						    $this->settings[$category] = array();
						}
						$this->settings[$category][] = $row['domain_setting_value'];
					}
					else {
						$this->settings[$category] = $row['domain_setting_value'];
					}
				}
				else {
					if ($name == "array") {
						if (!isset($this->settings[$category][$subcategory]) || !is_array($this->settings[$category][$subcategory])) {
						    $this->settings[$category][$subcategory] = array();
						}
						$this->settings[$category][$subcategory][] = $row['domain_setting_value'];
					}
					else {
						$this->settings[$category][$subcategory] = $row['domain_setting_value'];
					}
				}
			}
		}
	}

	/**
	 * Loads the enabled user's settings based on the user_uuid set in the constructor
	 */
	private function user_settings() {

		$sql = "select * from v_user_settings ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$sql .= "and user_uuid = :user_uuid ";
		$sql .= "and user_setting_enabled = :user_setting_enabled ";
		$sql .= "order by user_setting_order asc ";
		$parameters['domain_uuid'] = $this->domain_uuid;
		$parameters['user_uuid'] = $this->user_uuid;
		$parameters['user_setting_enabled'] = 'true';
		$result = $this->database->select($sql, $parameters, 'all');
		if (is_array($result)) {
			foreach ($result as $row) {
				$category = $row['user_setting_category'];
				$subcategory = $row['user_setting_subcategory'];
				$type = $row['user_setting_name'];
				if (!empty($row['user_setting_value'])) {
					if (empty($subcategory)) {
						if ($type == "array") {
							$this->settings[$category][] = $row['user_setting_value'];
						}
						else {
							$this->settings[$category] = $row['user_setting_value'];
						}
					}
					else {
						if ($type == "array") {
							$this->settings[$category][$subcategory][] = $row['user_setting_value'];
						}
						else {
							$this->settings[$category][$subcategory] = $row['user_setting_value'];
						}
					}
				}
			}
		}
	}

}

?>
