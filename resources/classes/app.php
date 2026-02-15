<?php

abstract class app {

	private static $applications = null;
	private static $permission_prefix = null;
	private static $list_page = null;
	private static $table = null;
	private static $uuid_prefix = null;
	private static $toggle_field = null;
	private static $toggle_values = null;

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

	protected $has_permission_prefix = false;
	protected $has_list_page = false;
	protected $has_table = false;
	protected $has_uuid_prefix = false;
	protected $has_toggle_field = false;
	protected $has_toggle_values = false;
	protected $has_delete = false;
	protected $has_add = false;
	protected $has_edit = false;

	public function __construct() {
		if (property_exists($this, 'permission_prefix')) {
			$this->has_permission_prefix = true;
		}
		if (property_exists($this, 'list_page')) {
			$this->has_list_page = true;
		}
		if (property_exists($this, 'table')) {
			$this->has_table = true;
		}
		if (property_exists($this, 'uuid_prefix')) {
			$this->has_uuid_prefix = true;
		}
		if (property_exists($this, 'toggle_field')) {
			$this->has_toggle_field = true;
		}
		if (property_exists($this, 'toggle_values')) {
			$this->has_toggle_values = true;
		}
		if ($this->has_permission_prefix) {
			if (permission_exists($this->permission_prefix . 'delete')) {
				$this->has_delete = true;
			}
			if (permission_exists($this->permission_prefix . 'add')) {
				$this->has_add = true;
			}
			if (permission_exists($this->permission_prefix . 'edit')) {
				$this->has_edit = true;
			}
		}
	}

	public function delete(array $records, string $table = '', string $uuid_prefix = '') {
		if (!$this->has_delete || empty($records)) {
			return;
		}

		$checked = [];
		// build the delete array
		foreach ($records as $x => $record) {
			if (!empty($record['checked']) && $record['checked'] == 'true' && is_uuid($record['uuid'])) {
				$checked[$table][$x][$uuid_prefix . 'uuid'] = $record['uuid'];
				$checked[$table][$x]['domain_uuid'] = $this->domain_uuid;
			}
		}

		// always call the before delete method even if there are no checked records to allow for any necessary pre-delete actions to be performed
		$this->before_delete($checked);

		// delete the checked rows
		if (!empty($checked)) {
			// execute delete
			$this->database->delete($checked);
		}

		// always call the after delete method even if there are no checked records to allow for any necessary post-delete actions to be performed
		$this->after_delete($checked);
	}

	abstract protected function on_delete(array &$checked);

	/**
	 * This method is intended to be overridden by child classes that need to perform actions before records are deleted. It receives an array of the records that are checked for deletion and can be used to perform any necessary pre-delete actions before the delete occurs.
	 *
	 * @param array $checked An array of records that are checked for deletion, where each record is an associative array containing the 'uuid' and 'checked' keys.
	 * @return void No return value; this method is intended for performing actions before the delete occurs.
	 */
	protected function before_delete(array &$checked) {}

	/**
	 * This method is intended to be overridden by child classes that need to perform actions after records are deleted. It receives an array of the records that were deleted and can be used to perform any necessary cleanup actions after the delete occurs.
	 *
	 * @param array $records An array of records that were deleted, where each record is an associative array containing the 'uuid' and 'checked' keys.
	 * @return void No return value; this method is intended for performing actions after the delete occurs.
	 */
	protected function after_delete() {}

	/**
	 * Copies one or more records
	 *
	 * @param array $records Array of records to delete, where each record is an associative array containing the 'uuid' and 'checked' keys.
	 */
	public function copy(array $records) {
		if ($this->has_add) {
			$uuids = [];
			// get checked records
			foreach ($records as $x => $record) {
				if (!empty($record['checked']) && $record['checked'] == 'true' && is_uuid($record['uuid'])) {
					$uuids[] = "'" . $record['uuid'] . "'";
				}
			}
			$array = $this->on_copy($uuids);
			if (!empty($array)) {
				$this->database->save($array);
			}
		}
	}

	/**
	 * This method is intended to be overridden by child classes that need to perform actions when records are copied. It should return an array of data to be saved to the database.
	 *
	 * @param array $uuids An array of UUIDs that are being copied. This array can be modified by the child class to perform any necessary copy actions and should return an array of data to be saved to the database.
	 * @return array An array of data to be saved to the database after the copy action is performed.
	 */
	abstract protected function on_copy(array &$uuids);

	/**
	 * This method is intended to be overridden by child classes that need to perform actions before records are copied.
	 *
	 * @param array $uuids An array of UUIDs that are being copied. This array can be modified by the child class to perform any necessary pre-copy actions.
	 * @return void No return value; this method is intended for performing actions before the copy occurs.
	 */
	protected function before_copy(array &$uuids) {}

	/**
	 * This method is intended to be overridden by child classes that need to perform actions after records are copied.
	 *
	 * @param array $uuids An array of UUIDs that were copied. This array can be modified by the child class to perform any necessary post-copy actions.
	 * @return void No return value; this method is intended for performing actions after the copy occurs.
	 */
	protected function after_copy() {}

	public function toggle(array $records) {
		if (!$this->has_edit || empty($records)) {
			return;
		}

		$uuids = [];

		// get current toggle state
		foreach ($records as $x => $record) {
			if (!empty($record['checked']) && $record['checked'] == 'true' && is_uuid($record['uuid'])) {
				$uuids[] = "'" . $record['uuid'] . "'";
			}
		}

		// perform any necessary actions before toggling in the child class
		$this->before_toggle($uuids);

		// get the updated toggle state from the child class
		$array = $this->on_toggle($uuids);

		// save the changes
		if (!empty($array)) {
			// save the array
			$this->database->save($array);
		}

		// perform any necessary actions after toggling in the child class
		$this->after_toggle($uuids);
	}

	/**
	 * This method is intended to be overridden by child classes that need to perform actions when records are toggled. It should return an array of data to be saved to the database.
	 *
	 * @param array $uuids An array of UUIDs that are being toggled. This array can be modified by the child class to perform any necessary toggle actions and should return an array of data to be saved to the database.
	 * @return array An array of data to be saved to the database after the toggle action is performed.
	 */
	abstract protected function on_toggle(array &$checked);

	/**
	 * This method is intended to be overridden by child classes that need to perform actions before records are toggled.
	 *
	 * @param array $checked An array of UUIDs that are being toggled. This array can be modified by the child class to perform any necessary pre-toggle actions.
	 * @return void No return value; this method is intended for performing actions before the toggle occurs.
	 */
	protected function before_toggle(array &$checked) {}

	/**
	 * This method is intended to be overridden by child classes that need to perform actions after records are toggled.
	 *
	 * @param array $checked An array of UUIDs that were toggled. This array can be modified by the child class to perform any necessary post-toggle actions.
	 * @return void No return value; this method is intended for performing actions after the toggle occurs.
	 */
	protected function after_toggle() {}

	/**
	 * Retrieves the configuration array for a specified application.
	 *
	 * @param string $app_name The name of the application to retrieve the config for.
	 * @return array|null The configuration array if the application exists, or null if not found.
	 */
	public static function get_app_config(string $app_name): ?array {
		$applications = self::list();
		foreach ($applications as $app) {
			if (isset($app['name']) && strcasecmp($app['name'], $app_name) === 0) {
				return $app;
			}
		}

		// app not found
		return null;
	}

	/**
	 * Retrieves the configuration index for a given application name.
	 *
	 * @param string $app_name The name of the application.
	 * @return int The configuration index associated with the application.
	 */
	public static function get_app_config_index(string $app_name): int {
		foreach (self::list() as $index => $app) {
			if (isset($app['name']) && strcasecmp($app['name'], $app_name) === 0) {
				return $index;
			}
		}

		// app not found
		return -1;
	}

	public static function database_schemas(): array {
		$schema = [];
		$schema_apps = (new auto_loader(true))->get_interface_list('has_database_schema');
		foreach ($schema_apps as $class) {
			$schema[] = $class::get_database_schema();
		}

		return $schema;
	}

	public static function default_settings(): array {
		$settings = [];
		$settings_apps = (new auto_loader(true))->get_interface_list('has_default_settings');
		foreach ($settings_apps as $class) {
			$settings[] = $class::get_default_settings();
		}

		return $settings;
	}

	public static function default_permissions(): array {
		$permissions = [];
		$permissions_apps = (new auto_loader())->get_interface_list('has_default_permissions');
		foreach ($permissions_apps as $class) {
			$permissions[] = $class::get_default_permissions();
		}

		return $permissions;
	}

	public static function default_menus(): array {
		$menus = [];
		$menus_apps = (new auto_loader())->get_interface_list('has_default_menus');
		foreach ($menus_apps as $class) {
			$menus[] = $class::get_default_menus();
		}

		return $menus;
	}

	public static function default_destinations(): array {
		$destinations = [];
		$default_destinations_apps = (new auto_loader())->get_interface_list('has_default_destinations');
		foreach ($default_destinations_apps as $class) {
			$destinations[] = $class::get_default_destinations();
		}

		return $destinations;
	}

	public static function default_queues(): array {
		$queues = [];
		$default_queues_apps = (new auto_loader())->get_interface_list('has_default_queues');
		foreach ($default_queues_apps as $class) {
			$queues[] = $class::get_default_queues();
		}

		return $queues;
	}

	public static function default_all(): array {
		$all = [];
		$default_all_apps = (new auto_loader())->get_interface_list('has_default_all');
		foreach ($default_all_apps as $class) {
			$all[] = $class::get_default_all();
		}

		return $all;
	}

	/**
	 * Retrieves configuration indexes for the specified application names.
	 *
	 * This static method accepts one or more application names as strings and returns
	 * an array containing the corresponding configuration indexes. If no app names are
	 * provided, it may return indexes for all available applications or an empty array,
	 * depending on implementation.
	 *
	 * @param string ...$app_name One or more application names to retrieve indexes for.
	 * @return array An array of configuration indexes keyed by application name or similar structure.
	 */
	public static function get_app_config_indexes(string ...$app_name): array {
		$matches = [];
		foreach (self::list() as $index => $app) {
			if (isset($app['name'])) {
				foreach ($app_name as $name) {
					if (strcasecmp($app['name'], $name) === 0) {
						$matches[$name] = $index;
					}
				}
			}
		}

		// app not found
		return $matches;
	}

	public static function get_app_config_destinations(): array {
		$destinations = [];
		foreach (self::list() as $apps) {
			if (!empty($apps['destinations'])) {
				foreach ($apps['destinations'] as $destination) {
					$singular_name = database::singular($destination['name']);
					$destination['name_singular'] = $singular_name;
					$destination['permission'] = $singular_name . '_destinations';
					$destination['has_permission'] = permission_exists($destination['permission']);
					$destinations[] = $destination;
				}
			}
		}

		return $destinations;
	}

	public static function get_app_config_all(): array {
		$all = [];
		$all['destinations'] = self::default_destinations();
		$all['queues'] = self::default_queues();
		$all['menus'] = self::default_menus();
		$all['permissions'] = self::default_permissions();
		$all['settings'] = self::default_settings();
		$all['db'] = self::database_schemas();
		return $all;
	}

	/**
	 * Loads the app_config.php files from the core and mod directories and returns an array of applications.
	 *
	 * This method uses glob to find all app_config.php files in the core and mod directories, includes each file,
	 * and collects the application configurations defined in those files into an array. The method assumes that
	 * each included app_config.php file defines an $apps array, and it aggregates these configurations into a single
	 * array of applications.
	 *
	 * @return array An array of applications loaded from the app_config.php files.
	 * @deprecated 5.5.0 This method is deprecated and may be removed in a future release. Use auto_loader to load app configurations instead.
	 */
	public static function list(): array {
		if (self::$applications !== null) {
			return self::$applications;
		}
		// Isolates the apps array to prevent unintended side effects from including app_config.php files.
		self::$applications = [];

		// get the list of installed apps from the core and mod directories
		$config_list = glob(dirname(__DIR__, 2) . "/*/*/app_config.php");

		//
		// $x is used for compatibility with the old code to build a global array of "$apps"
		//
		$x = 0;

		//
		// Loop through the list of app_config.php files and test for syntax errors before
		// including the file to build the applications array. If there are syntax errors
		// or if the file fails to load then we log an error to syslog and skip loading
		// that file.
		//
		foreach ($config_list as $config_path) {
			// Reset the $apps array to isolate all apps
			$apps = [];

			// Reset output and exit code variables for each file to test for syntax errors
			$output = [];
			$exit_code = 0;

			//
			// Use a try and catch block to catch any exceptions that may be thrown.
			// NOTE: Not all errors will be caught by this block.
			//
			try {
				// Include the file and merge its contents into the applications array.
				$result = @include ($config_path);
				if ($result === false) {
					// No syntax errors and nothing thrown but still failed to include
					syslog(LOG_ERR, "Failed to include file $config_path");
				} else {
					// Successful include so assign the individual $apps array from the included file to the applications array
					self::$applications[] = $apps[0];
				}
			} catch (Throwable $e) {
				// Exception thrown while trying to include the file. Log the error message and skip loading that file.
				syslog(LOG_ERR, "Exception thrown while trying to include '$config_path': {$e->getMessage()} on line {$e->getLine()}\n");
				continue;
			}
		}

		$app_list = (new auto_loader(true))->get_interface_list('has_app_config');
		foreach ($app_list as $class) {
			$app = $class::get_app_config();
			if ($app !== null) {
				self::$applications[] = $app;
			}
		}

		return self::$applications;
	}
}
