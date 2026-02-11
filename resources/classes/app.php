<?php

class app {

	private static $applications = null;

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
