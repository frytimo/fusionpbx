<?php

	/**
	 * settings class
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
		 * Called when the object is created.
		 * <p>The key/value pairs for <b>$setting_array</b> can have the values <i>domain_uuid</i>, <i>user_uuid</i>, <i>device_profile_uuid</i>, <i>device_uuid</i>.</p>
		 * @param array setting_array
		 * @depends database::new()
		 */
		public function __construct($setting_array = []) {
			//open a database connection
			$this->database = database::new();

			//set the values from the array
			$this->domain_uuid = $setting_array['domain_uuid'] ?? null;
			$this->user_uuid = $setting_array['user_uuid'] ?? null;
			$this->device_profile_uuid = $setting_array['device_profile_uuid'] ?? null;
			$this->device_uuid = $setting_array['device_uuid'] ?? null;

			//populate the $settings array
			$this->reload();
		}

//		public function __destruct() {
//			if (!is_cli()) {
//				$_SESSION['settings'] = serialize($this);
//			}
//		}

		/**
		 * Sets the domain_uuid in the object and reloads all settings
		 * @param null|string $domain_uuid Set the domain_uuid in the object to reload the settings for that domain uuid
		 * @return $this|string Returns the current domain_uuid that is set in the object when no parameters have been passed. Otherwise returns $this.
		 * @throws InvalidArgumentException
		 */
		public function domain_uuid($domain_uuid = null) {
			if (func_num_args() === 0) {
				return $this->domain_uuid;
			}
			if (is_uuid($domain_uuid)) {
				$this->domain_uuid = $domain_uuid;
				$this->reload();
			} else {
				throw new InvalidArgumentException('domain uuid is not a valid uuid');
			}
			return $this;
		}

		/**
		 * Sets the user_uuid in the object and reloads all settings
		 * @param null|string $user_uuid Set the user_uuid in the object to reload the settings for that domain uuid
		 * @return $this|string Returns the current user_uuid that is set in the object when no parameters have been passed. Otherwise returns $this.
		 * @throws InvalidArgumentException
		 */
		public function user_uuid($user_uuid = null) {
			if (func_num_args() === 0) {
				return $this->user_uuid;
			}
			if (is_uuid($user_uuid)) {
				$this->user_uuid = $user_uuid;
				$this->reload();
			} else {
				throw new InvalidArgumentException('user uuid is not a valid uuid');
			}
			return $this;
		}

		/**
		 * Sets the device_uuid in the object and reloads all settings
		 * @param null|string $device_uuid Set the device_uuid in the object to reload the settings for that domain uuid
		 * @return $this|string Returns the current device_uuid that is set in the object when no parameters have been passed. Otherwise returns $this.
		 * @throws InvalidArgumentException
		 */
		public function device_uuid($device_uuid = null) {
			if (func_num_args() === 0) {
				return $this->device_uuid;
			}
			if (is_uuid($device_uuid)) {
				$this->device_uuid = $device_uuid;
				$this->reload();
			} else {
				throw new InvalidArgumentException('device uuid is not a valid uuid');
			}
			return $this;
		}

		/**
		 * Sets the device_profile_uuid in the object and reloads all settings
		 * @param null|string $device_profile_uuid Set the device_profile_uuid in the object to reload the settings for that domain uuid
		 * @return $this|string Returns the current device_profile_uuid that is set in the object when no parameters have been passed. Otherwise returns $this.
		 * @throws InvalidArgumentException
		 */
		public function device_profile_uuid($device_profile_uuid = null) {
			if (func_num_args() === 0) {
				return $this->device_profile_uuid;
			}
			if (is_uuid($device_profile_uuid)) {
				$this->device_profile_uuid = $device_profile_uuid;
				$this->reload();
			} else {
				throw new InvalidArgumentException('device profile uuid is not a valid uuid');
			}
			return $this;
		}

		/**
		 * get the value
		 * @param string category
		 * @param string subcategory
		 */
		public function get($category = null, $subcategory = null) {
			if (empty($category)) {
				return $this->settings;
			} elseif (empty($subcategory)) {
				return $this->settings[$category] ?? null;
			} else {
				return $this->settings[$category][$subcategory] ?? null;
			}
		}

		/**
		 * set the default, domain, user, device or device profile settings
		 * @param string $table_prefix prefix for the table.
		 * @param string $uuid uuid of the setting if available. If set to an empty string then a new uuid will be created.
		 * @param string $category Category of the setting.
		 * @param string $subcategory Subcategory of the setting.
		 * @param string $type Type of the setting (array, numeric, text, etc)
		 * @param string $value (optional) Value to set. Default is empty string.
		 * @param bool $enabled (optional) True or False. Default is True.
		 * @param string $description (optional) Description. Default is empty string.
		 */
		public function set(string $table_prefix, string $uuid, string $category, string $subcategory, string $type = 'text', string $value = "", bool $enabled = true, string $description = "") {
			//set the table name
			$table_name = $table_prefix . '_settings';

			//init record as an array
			$record = [];
			if (!empty($this->domain_uuid)) {
				$record[$table_name][0]['domain_uuid'] = $this->domain_uuid;
			}
			if (!empty($this->user_uuid)) {
				$record[$table_name][0]['user_uuid'] = $this->user_uuid;
			}
			if (!empty($this->device_uuid)) {
				$record[$table_name][0]['device_uuid'] = $this->device_uuid;
			}
			if (!empty($this->device_profile_uuid)) {
				$record[$table_name][0]['device_profile_uuid'] = $this->device_profile_uuid;
			}
			if (!is_uuid($uuid)) {
				$uuid = uuid();
			}
			//build the array
			$record[$table_name][0][$table_prefix . '_setting_uuid'] = $uuid;
			$record[$table_name][0][$table_prefix . '_setting_category'] = $category;
			$record[$table_name][0][$table_prefix . '_setting_subcategory'] = $subcategory;
			$record[$table_name][0][$table_prefix . '_setting_name'] = $type;
			$record[$table_name][0][$table_prefix . '_setting_value'] = $value;
			$record[$table_name][0][$table_prefix . '_setting_enabled'] = $enabled;
			$record[$table_name][0][$table_prefix . '_setting_description'] = $description;

			//grant temporary permissions
			$p = new permissions;
			$p->add($table_prefix . '_setting_add', 'temp');
			$p->add($table_prefix . '_setting_edit', 'temp');

			//execute insert
			$this->database->app_name = $table_name;
			$this->database->save($record);

			//revoke temporary permissions
			$p->delete($table_prefix . '_setting_add', 'temp');
			$p->delete($table_prefix . '_setting_edit', 'temp');
		}

		/**
		 * set the default settings
		 *
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
							$this->settings[$category][] = $row['default_setting_value'];
						} else {
							$this->settings[$category] = $row['default_setting_value'];
						}
					} else {
						if ($name == "array") {
							$this->settings[$category][$subcategory][] = $row['default_setting_value'];
						} else {
							$this->settings[$category][$subcategory] = $row['default_setting_value'];
						}
					}
				}
			}
			unset($sql, $result, $row);
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
			unset($sql, $parameters);
			if (!empty($result)) {
				foreach ($result as $row) {
					$name = $row['domain_setting_name'];
					$category = $row['domain_setting_category'];
					$subcategory = $row['domain_setting_subcategory'];
					if (empty($subcategory)) {
						if ($name == "array") {
							$this->settings[$category][] = $row['domain_setting_value'];
						} else {
							$this->settings[$category] = $row['domain_setting_value'];
						}
					} else {
						if ($name == "array") {
							$this->settings[$category][$subcategory][] = $row['domain_setting_value'];
						} else {
							$this->settings[$category][$subcategory] = $row['domain_setting_value'];
						}
					}
				}
			}
			unset($result, $row);
		}

		/**
		 * set the user settings
		 */
		private function user_settings() {

			$sql = "select * from v_user_settings ";
			$sql .= "where domain_uuid = :domain_uuid ";
			$sql .= "and user_uuid = :user_uuid ";
			$sql .= " order by user_setting_order asc ";
			$parameters['domain_uuid'] = $this->domain_uuid;
			$parameters['user_uuid'] = $this->user_uuid;
			$result = $this->database->select($sql, $parameters, 'all');
			if (is_array($result)) {
				foreach ($result as $row) {
					if ($row['user_setting_enabled'] == 'true') {
						$name = $row['user_setting_name'];
						$category = $row['user_setting_category'];
						$subcategory = $row['user_setting_subcategory'];
						if (!empty($row['user_setting_value'])) {
							if (empty($subcategory)) {
								if ($name == "array") {
									$this->settings[$category][] = $row['user_setting_value'];
								} else {
									$this->settings[$category] = $row['user_setting_value'];
								}
							} else {
								if ($name == "array") {
									$this->settings[$category][$subcategory][] = $row['user_setting_value'];
								} else {
									$this->settings[$category][$subcategory] = $row['user_setting_value'];
								}
							}
						}
					}
				}
			}
		}

		private function device_settings() {
			$sql = "select";
			$sql .= "   d.device_enabled,";
			$sql .= "   d.device_vendor,";
			$sql .= "   d.device_address,";
			$sql .= "   d.device_profile_uuid,";
			$sql .= "   s.device_setting_subcategory,";
			$sql .= "   s.device_setting_value,";
			$sql .= "   s.device_setting_enabled";
			$sql .= " from v_devices d ";
			$sql .= " left join v_device_settings s ";
			$sql .= "   on d.device_uuid = s.device_uuid ";
			$sql .= " where d.device_uuid = :device_uuid ";
			$sql .= " and d.device_enabled = 'true'";
			$sql .= " and s.device_setting_enabled = 'true'";
			$rows = $this->database->select($sql, ['device_uuid' => $this->device_uuid]);
			if(is_array($rows)) {
				$load_profile_settings = true;
				foreach ($rows as $row) {
					$vendor = $row['device_vendor'] ?? device::get_vendor($row['device_address'] ?? '');
					if ($load_profile_settings && !empty($row['device_profile_uuid'])) {
						$this->device_profile_settings($row['device_profile_uuid'], $vendor);
						//only load the profile settings once
						$load_profile_settings = false;
					}
					$key = $row['device_setting_subcategory'];
					$value = $row['device_setting_value'];
					if (!empty($vendor)) {
						$this->settings[$vendor][$key] = $value;
					}
					$this->settings['provision'][$key] = $value;
				}
			}
		}

		private function reload() {
			//remove old settings
			$this->settings = [];

			//set the default settings
			$this->default_settings();

			//set the domain settings
			if (!empty($this->domain_uuid)) {
				$this->domain_settings();
			}

			//set the user settings
			if (!empty($this->user_uuid)) {
				$this->user_settings();
			}

			//set the device profile settings
			if (!empty($this->device_profile_uuid)) {
				$this->device_profile_settings();
			}

			//set the device settings
			if (!empty($this->device_uuid)) {
				$this->device_settings();
			}
		}

		public static function load() {
			//check if settings are cached for http page load or cli
			if (!is_cli() && isset($_SESSION['settings'])) {
				return unserialize($_SESSION['settings']);
			} else {
				return new settings();
			}
		}

		private function device_profile_settings($device_profile_uuid = "", $vendor = "") {
			$parameters = null;
			$sql = "select profile_setting_name, profile_setting_value ";
			$sql .= "from v_device_profile_settings ";
			$sql .= "where profile_setting_enabled = 'true' ";
			if (!empty($device_profile_uuid)) {
				$sql .= "and device_profile_uuid = :device_profile_uuid ";
				$parameters['device_profile_uuid'] = $device_profile_uuid;
			}
			$rows = $this->database->select($sql, $parameters);
			if (is_array($rows)) {
				foreach ($rows as $row) {
					$key   = $row['profile_setting_name'];
					$value = $row['profile_setting_value'];
					if (!empty($vendor)) {
						$this->settings[$vendor][$key] = $value;
					}
					$this->settings['provision'][$key] = $value;
				}
			}
		}

		public function __serialize(): array {
			return ['settings'=> json_encode($this->settings)
				, 'domain_uuid' => $this->domain_uuid
				, 'user_uuid' => $this->user_uuid
				, 'device_uuid' => $this->device_uuid
				, 'device_profile_uuid' => $this->device_profile_uuid
				, 'category' => $this->category
				];
		}

		public function __unserialize(array $data) {
			//restore object variables
			$this->settings = json_decode($data['settings'], true);
			$this->domain_uuid = $data['domain_uuid'] ?? null;
			$this->user_uuid = $data['user_uuid'] ?? null;
			$this->device_uuid = $data['device_uuid'] ?? null;
			$this->device_profile_uuid = $data['device_profile_uuid'] ?? null;
			$this->category = $data['category'] ?? null;
			//reconnect database
			$this->database = database::new();
		}

	}
