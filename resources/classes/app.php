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
	protected $database;

	/**
	 * Settings object set in the constructor. Must be a settings object and cannot be null.
	 * @var settings Settings Object
	 */
	protected $settings;

	/**
	 * User UUID set in the constructor. This can be passed in through the $settings_array associative array or set in the session global array
	 * @var string
	 */
	protected $user_uuid;

	/**
	 * Domain UUID set in the constructor. This can be passed in through the $settings_array associative array or set in the session global array
	 * @var string
	 */
	protected $domain_uuid;

	protected $has_permission_prefix = false;
	protected $has_list_page = false;
	protected $has_table = false;
	protected $has_uuid_prefix = false;
	protected $has_toggle_field = false;
	protected $has_toggle_values = false;
	protected $has_delete = false;
	protected $has_add = false;
	protected $has_edit = false;

	/**
	 * The app-specific edit hook interface name. When set, the dispatch system will invoke
	 * hooks implementing this interface in addition to the global page_edit_hook hooks.
	 * Example: 'bridge_edit_hook' for bridges.
	 * @var string|null
	 */
	protected $edit_hook_interface = null;

	/**
	 * The app-specific list hook interface name. When set, the dispatch system will invoke
	 * hooks implementing this interface in addition to the global page_hook/list_page_hook hooks.
	 * Example: 'bridge_list_page_hook' for bridges.
	 * @var string|null
	 */
	protected $list_hook_interface = null;

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

	//
	// Hook Dispatch Infrastructure
	//

	/**
	 * Dispatches a hook method to all classes implementing the given interfaces.
	 * Uses a two-tier approach:
	 *  - Global hooks: direct implementers of the generic interface (e.g., page_edit_hook)
	 *  - App-specific hooks: all implementers (including via child interfaces) of the app-specific interface
	 *
	 * @param string      $generic_interface The generic/global interface name (e.g., 'page_edit_hook')
	 * @param string|null $app_interface     The app-specific interface name (e.g., 'bridge_edit_hook'), or null for global-only
	 * @param string      $method            The method name to invoke on each hook class
	 * @param mixed       ...$args           Arguments to pass to the hook method
	 * @return void
	 */
	protected function dispatch_hooks(string $generic_interface, ?string $app_interface, string $method, mixed ...$args): void {
		self::dispatch_hooks_static($generic_interface, $app_interface, $method, ...$args);
	}

	/**
	 * Static version of dispatch_hooks for use from procedural page code (list pages, etc.)
	 *
	 * @param string      $generic_interface The generic/global interface name
	 * @param string|null $app_interface     The app-specific interface name, or null for global-only
	 * @param string      $method            The method name to invoke
	 * @param mixed       ...$args           Arguments to pass to the hook method
	 * @return void
	 */
	public static function dispatch_hooks_static(string $generic_interface, ?string $app_interface, string $method, mixed ...$args): void {
		$autoload = new auto_loader();

		// tier 1: global hooks — only direct implementers of the generic interface
		$global_hooks = $autoload->get_direct_implementers($generic_interface);

		// tier 2: app-specific hooks — full resolution including child interfaces
		$app_hooks = [];
		if (!empty($app_interface)) {
			$app_hooks = $autoload->get_interface_list($app_interface);
		}

		// merge and deduplicate
		$all_hooks = array_unique(array_merge($global_hooks, $app_hooks));

		// invoke the method on each hook class
		foreach ($all_hooks as $class) {
			if (method_exists($class, $method)) {
				$class::$method(...$args);
			}
		}
	}

	/**
	 * Dispatches an edit hook method (for save operations).
	 * Combines global page_edit_hook implementers with app-specific edit hook implementers.
	 *
	 * @param string $method The method to invoke (e.g., 'on_pre_save', 'on_post_save')
	 * @param mixed  ...$args Arguments to pass to the hook method
	 * @return void
	 */
	protected function dispatch_edit_hooks(string $method, mixed ...$args): void {
		$this->dispatch_hooks('page_edit_hook', $this->edit_hook_interface, $method, ...$args);
	}

	/**
	 * Dispatches a list page hook method (for list page operations).
	 * Combines global page_hook implementers with app-specific list hook implementers.
	 *
	 * @param string $method The method to invoke (e.g., 'on_pre_action', 'on_post_action')
	 * @param mixed  ...$args Arguments to pass to the hook method
	 * @return void
	 */
	protected function dispatch_list_hooks(string $method, mixed ...$args): void {
		$this->dispatch_hooks('page_hook', $this->list_hook_interface, $method, ...$args);
	}

	//
	// Static List Page Hook Helpers
	// These are called from procedural list page code (e.g., bridges.php)
	//

	/**
	 * Dispatches the on_pre_action hook for list pages.
	 *
	 * @param string|null $app_interface App-specific list hook interface name, or null for global-only
	 * @param url         $url           The URL object
	 * @param string      $action        The action being performed (passed by reference)
	 * @param array       $items         The items array (passed by reference)
	 * @return void
	 */
	public static function dispatch_list_pre_action(?string $app_interface, url $url, string &$action, array &$items): void {
		self::dispatch_hooks_static('page_hook', $app_interface, 'on_pre_action', $url, $action, $items);
	}

	/**
	 * Dispatches the on_post_action hook for list pages.
	 *
	 * @param string|null $app_interface App-specific list hook interface name, or null for global-only
	 * @param url         $url           The URL object
	 * @param string      $action        The action that was performed
	 * @param array       $items         The items array
	 * @return void
	 */
	public static function dispatch_list_post_action(?string $app_interface, url $url, string $action, array $items): void {
		self::dispatch_hooks_static('page_hook', $app_interface, 'on_post_action', $url, $action, $items);
	}

	/**
	 * Dispatches the on_pre_query hook for list pages.
	 *
	 * @param string|null $app_interface App-specific list hook interface name, or null for global-only
	 * @param url         $url           The URL object
	 * @param array       $parameters    Query parameters (passed by reference)
	 * @return void
	 */
	public static function dispatch_list_pre_query(?string $app_interface, url $url, array &$parameters): void {
		self::dispatch_hooks_static('page_hook', $app_interface, 'on_pre_query', $url, $parameters);
	}

	/**
	 * Dispatches the on_post_query hook for list pages.
	 *
	 * @param string|null $app_interface App-specific list hook interface name, or null for global-only
	 * @param url         $url           The URL object
	 * @param array       $items         The fetched records (passed by reference)
	 * @return void
	 */
	public static function dispatch_list_post_query(?string $app_interface, url $url, array &$items): void {
		self::dispatch_hooks_static('page_hook', $app_interface, 'on_post_query', $url, $items);
	}

	/**
	 * Dispatches the on_pre_render hook for list pages.
	 *
	 * @param string|null $app_interface App-specific list hook interface name, or null for global-only
	 * @param url         $url           The URL object
	 * @param template    $template      The template object
	 * @return void
	 */
	public static function dispatch_list_pre_render(?string $app_interface, url $url, template $template): void {
		self::dispatch_hooks_static('page_hook', $app_interface, 'on_pre_render', $url, $template);
	}

	/**
	 * Dispatches the on_post_render hook for list pages.
	 *
	 * @param string|null $app_interface App-specific list hook interface name, or null for global-only
	 * @param url         $url           The URL object
	 * @param string      $html          The HTML output (passed by reference)
	 * @return void
	 */
	public static function dispatch_list_post_render(?string $app_interface, url $url, string &$html): void {
		self::dispatch_hooks_static('page_hook', $app_interface, 'on_post_render', $url, $html);
	}

	/**
	 * Dispatches the on_render_row hook for list pages.
	 *
	 * @param string|null $app_interface App-specific list hook interface name, or null for global-only
	 * @param url         $url           The URL object
	 * @param array       $row           The row data (passed by reference)
	 * @param int         $row_index     Zero-based row index
	 * @return void
	 */
	public static function dispatch_list_render_row(?string $app_interface, url $url, array &$row, int $row_index): void {
		self::dispatch_hooks_static('list_page_hook', $app_interface, 'on_render_row', $url, $row, $row_index);
	}

	public function delete($records) {
		if (!$this->has_delete || empty($records)) {
			return;
		}

		$checked = [];
		// build the delete array
		foreach ($records as $x => $record) {
			if (!empty($record['checked']) && $record['checked'] == 'true' && is_uuid($record['uuid'])) {
				$checked[$this->table][$x][$this->uuid_prefix . 'uuid'] = $record['uuid'];
				$checked[$this->table][$x]['domain_uuid'] = $this->domain_uuid;
			}
		}

		// dispatch pre-action hooks (action = 'delete')
		$url = new url($_SERVER['PHP_SELF'] ?? '');
		$action = 'delete';
		$this->dispatch_list_hooks('on_pre_action', $url, $action, $checked);

		// always call the before delete method even if there are no checked records to allow for any necessary pre-delete actions to be performed
		$this->before_delete($checked);

		// delete the checked rows
		if (!empty($checked)) {
			// execute delete
			$this->database->delete($checked);
		}

		// always call the after delete method even if there are no checked records to allow for any necessary post-delete actions to be performed
		$this->after_delete($checked);

		// dispatch post-action hooks
		$this->dispatch_list_hooks('on_post_action', $url, $action, $checked);
	}

	protected function on_delete(array &$checked) {}

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

			// dispatch pre-action hooks (action = 'copy')
			$url = new url($_SERVER['PHP_SELF'] ?? '');
			$action = 'copy';
			$this->dispatch_list_hooks('on_pre_action', $url, $action, $uuids);

			// perform any necessary actions before copying in the child class
			$this->before_copy($uuids);

			$array = $this->on_copy($uuids);
			if (!empty($array)) {
				$this->database->save($array);
			}

			// perform any necessary actions after copying in the child class
			$this->after_copy();

			// dispatch post-action hooks
			$this->dispatch_list_hooks('on_post_action', $url, $action, $uuids);
		}
	}

	/**
	 * This method is intended to be overridden by child classes that need to perform actions when records are copied. It should return an array of data to be saved to the database.
	 *
	 * @param array $uuids An array of UUIDs that are being copied. This array can be modified by the child class to perform any necessary copy actions and should return an array of data to be saved to the database.
	 * @return array An array of data to be saved to the database after the copy action is performed.
	 */
	protected function on_copy(array &$uuids) { return []; }

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

		// dispatch pre-action hooks (action = 'toggle')
		$url = new url($_SERVER['PHP_SELF'] ?? '');
		$action = 'toggle';
		$this->dispatch_list_hooks('on_pre_action', $url, $action, $uuids);

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

		// dispatch post-action hooks
		$this->dispatch_list_hooks('on_post_action', $url, $action, $uuids);
	}

	/**
	 * This method is intended to be overridden by child classes that need to perform actions when records are toggled. It should return an array of data to be saved to the database.
	 *
	 * @param array $uuids An array of UUIDs that are being toggled. This array can be modified by the child class to perform any necessary toggle actions and should return an array of data to be saved to the database.
	 * @return array An array of data to be saved to the database after the toggle action is performed.
	 */
	protected function on_toggle(array &$checked) { return []; }

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
	 * Saves a record to the database with pre/post save hook dispatch.
	 * Follows the same template method pattern as delete(), copy(), and toggle().
	 *
	 * @param array    $array The data array to save (in FusionPBX database->save format)
	 * @param url|null $url   Optional URL object for hook context. If null, one is created from $_SERVER.
	 * @return void
	 */
	public function save(array &$array, ?url $url = null): void {
		if (!$this->has_add && !$this->has_edit) {
			return;
		}

		// build url context if not provided
		if ($url === null) {
			$url = new url($_SERVER['PHP_SELF'] ?? '');
		}

		// dispatch pre-save hooks
		$this->dispatch_edit_hooks('on_pre_save', $url, $array);

		// call the before save template method for child class customization
		$this->before_save($array);

		// execute the save
		if (!empty($array)) {
			$this->database->save($array);
		}

		// call the after save template method for child class customization
		$this->after_save($array);

		// dispatch post-save hooks
		$this->dispatch_edit_hooks('on_post_save', $url, $array);
	}

	/**
	 * This method is intended to be overridden by child classes that need to perform actions before a record is saved.
	 *
	 * @param array $array The data array being saved (passed by reference for modification)
	 * @return void
	 */
	protected function before_save(array &$array) {}

	/**
	 * This method is intended to be overridden by child classes that need to perform actions after a record is saved.
	 *
	 * @param array $array The data array that was saved
	 * @return void
	 */
	protected function after_save(array $array) {}

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

		// get the list of installed apps from the app and core directories
		$config_list = array_merge(
			(array) glob(dirname(__DIR__, 2) . "/app/*/app_config.php"),
			(array) glob(dirname(__DIR__, 2) . "/core/*/app_config.php")
		);

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
					if (!empty($apps[0]) && is_array($apps[0])) {
						self::$applications[] = $apps[0];
					}
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
