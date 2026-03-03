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
  Portions created by the Initial Developer are Copyright (C) 2008-2024
  the Initial Developer. All Rights Reserved.

  Contributor(s):
  Mark J Crane <markjcrane@fusionpbx.com>
  Tim Fry <tim@fusionpbx.com>
 */

/**
 * Auto Loader class
 * Searches for project files when a class is required. Debugging mode can be set using:
 * - export DEBUG=1
 *      OR
 * - debug=true is appended to the url
 */
class auto_loader {

	const CLASSES_KEY = 'autoloader_classes';
	const CLASSES_FILE = 'autoloader_cache.php';
	const INTERFACES_KEY = "autoloader_interfaces";
	const INTERFACES_FILE = "autoloader_interface_cache.php";
	const INHERITANCE_KEY = "autoloader_inheritance";
	const INHERITANCE_FILE = "autoloader_inheritance_cache.php";
	const CACHE_VERSION_KEY = 'autoloader_cache_version';
	const CACHE_VERSION = 3;
	/**
	 * Cache path and file name for classes
	 *
	 * @var string
	 */
	private static $classes_file = null;
	/**
	 * Cache path and file name for interfaces
	 *
	 * @var string
	 */
	private static $interfaces_file = null;
	/**
	 * Cache path and file name for inheritance
	 *
	 * @var string
	 */
	private static $inheritance_file = null;
	private $classes;
	/**
	 * Tracks the APCu extension for caching to RAM drive across requests
	 *
	 * @var bool
	 */
	private $apcu_enabled;
	/**
	 * Maps interfaces to classes
	 *
	 * @var array
	 */
	private $interfaces;
	/**
	 * Maps classes/interfaces to their parent class/interface
	 *
	 * @var array
	 */
	private $inheritance;
	/**
	 * @var array
	 */
	private $traits;

	/**
	 * Initializes the class and sets up caching mechanisms.
	 *
	 * @param bool $disable_cache If true, disables cache usage. Defaults to false.
	 */
	public function __construct($disable_cache = false) {

		//set if we can use RAM cache
		$this->apcu_enabled = function_exists('apcu_enabled') && apcu_enabled();

		//set classes cache location
		if (empty(self::$classes_file)) {
			self::$classes_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::CLASSES_FILE;
		}

		//set interface cache location
		if (empty(self::$interfaces_file)) {
			self::$interfaces_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::INTERFACES_FILE;
		}

		//set inheritance cache location
		if (empty(self::$inheritance_file)) {
			self::$inheritance_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::INHERITANCE_FILE;
		}

		//classes must be loaded before this object is registered
		if ($disable_cache || !$this->load_cache()) {
			//cache miss so load them
			$this->reload_classes();
			//update the cache after loading classes array
			$this->update_cache();
		}
		//register this object to load any unknown classes
		spl_autoload_register([$this, 'loader']);
	}

	/**
	 * Loads the class cache from various sources.
	 *
	 * @return bool True if the cache is loaded successfully, false otherwise.
	 */
	public function load_cache(): bool {
		$this->classes = [];
		$this->interfaces = [];
		$this->inheritance = [];
		$this->traits = [];

		//check APCu cache version
		$apcu_version_valid = false;
		if ($this->apcu_enabled) {
			$cached_version = apcu_fetch(self::CACHE_VERSION_KEY, $version_exists);
			if ($version_exists && $cached_version === self::CACHE_VERSION) {
				$apcu_version_valid = true;
			} else if ($version_exists) {
				//clear stale APCu cache
				apcu_delete(self::CACHE_VERSION_KEY);
				apcu_delete(self::CLASSES_KEY);
				apcu_delete(self::INTERFACES_KEY);
				apcu_delete(self::INHERITANCE_KEY);
			}
		}

		//use apcu when available and version is valid
		if ($this->apcu_enabled && $apcu_version_valid && apcu_exists(self::CLASSES_KEY)) {
			$this->classes = apcu_fetch(self::CLASSES_KEY, $classes_cached);
			$this->interfaces = apcu_fetch(self::INTERFACES_KEY, $interfaces_cached);
			$this->inheritance = apcu_fetch(self::INHERITANCE_KEY, $inheritance_cached);
			//verify we got valid data
			if ($classes_cached && $interfaces_cached && $inheritance_cached && !empty($this->classes)) {
				return true;
			}
		}

		//check file cache version and load if valid
		$file_cache_valid = false;
		if (file_exists(self::$classes_file)) {
			$cached_data = include self::$classes_file;
			//validate structure and version
			if (is_array($cached_data) && isset($cached_data['version']) && $cached_data['version'] === self::CACHE_VERSION) {
				$this->classes = $cached_data['classes'] ?? [];
				$file_cache_valid = true;
			} else {
				//delete stale file cache
				@unlink(self::$classes_file);
			}
		}

		//do the same for interface to class mappings
		if ($file_cache_valid && file_exists(self::$interfaces_file)) {
			$cached_data = include self::$interfaces_file;
			//validate structure and version
			if (is_array($cached_data) && isset($cached_data['version']) && $cached_data['version'] === self::CACHE_VERSION) {
				$this->interfaces = $cached_data['interfaces'] ?? [];
			} else {
				//delete stale file cache
				@unlink(self::$interfaces_file);
				$file_cache_valid = false;
			}
		}

		//do the same for inheritance mappings
		if ($file_cache_valid && file_exists(self::$inheritance_file)) {
			$cached_data = include self::$inheritance_file;
			//validate structure and version
			if (is_array($cached_data) && isset($cached_data['version']) && $cached_data['version'] === self::CACHE_VERSION) {
				$this->inheritance = $cached_data['inheritance'] ?? [];
			} else {
				//delete stale file cache
				@unlink(self::$inheritance_file);
				$file_cache_valid = false;
			}
		}

		//populate apcu cache from file cache if available and valid
		if ($this->apcu_enabled && $file_cache_valid && !empty($this->classes)) {
			apcu_store(self::CACHE_VERSION_KEY, self::CACHE_VERSION);
			apcu_store(self::CLASSES_KEY, $this->classes);
			apcu_store(self::INTERFACES_KEY, $this->interfaces);
			apcu_store(self::INHERITANCE_KEY, $this->inheritance);
		}

		//return true when we have classes and false if the array is still empty
		return ($file_cache_valid && !empty($this->classes) && !empty($this->interfaces));
	}

	/**
	 * Reloads classes and interfaces from the project's resources.
	 *
	 * This method scans all PHP files in the specified locations, parses their contents,
	 * and updates the internal storage of classes and interfaces. It also processes
	 * implementation relationships between classes and interfaces.
	 *
	 * @return void
	 */
	public function reload_classes() {
		//set project path using magic dir constant
		$project_path = dirname(__DIR__, 2);

		//build the array of all locations for classes in specific order
		$search_path = [
			$project_path . '/resources/interfaces/*.php',
			$project_path . '/resources/traits/*.php',
			$project_path . '/resources/classes/*.php',
			$project_path . '/*/*/resources/interfaces/*.php',
			$project_path . '/*/*/resources/traits/*.php',
			$project_path . '/*/*/resources/classes/*.php',
			$project_path . '/core/authentication/resources/classes/plugins/*.php',
		];

		//get all php files for each path
		$files = [];
		foreach ($search_path as $path) {
			$files = array_merge($files, glob($path));
		}

		//reset the current array
		$class_list = [];

		//store the class name (key) and the path (value)
		foreach ($files as $file) {
			$file_content = file_get_contents($file);

			// Remove block comments
			$file_content = preg_replace('/\/\*.*?\*\//s', '', $file_content);
			// Remove single-line comments
			$file_content = preg_replace('/(\/\/|#).*$/m', '', $file_content);

			// Detect the namespace
			$namespace = '';
			if (preg_match('/\bnamespace\s+([^;{]+)[;{]/', $file_content, $namespace_match)) {
				$namespace = trim($namespace_match[1]) . '\\';
			}

			// Regex to capture class, interface, or trait declarations
			// Now captures the extends clause properly as $match[3]
			$pattern = '/\b(class|interface|trait)\s+(\w+)(?:\s+extends\s+(\w+))?(?:\s+implements\s+([^\\{]+))?/';

			if (preg_match_all($pattern, $file_content, $matches, PREG_SET_ORDER)) {
				foreach ($matches as $match) {

					// "class", "interface", or "trait"
					$type = $match[1];

					// The class/interface/trait name
					$name = trim($match[2], " \n\r\t\v\x00\\");

					// Combine the namespace and name
					$full_name = $namespace . $name;

					// Store the class/interface/trait with its file overwriting any existing declaration.
					$this->classes[$full_name] = $file;

					// Track inheritance (what this class/interface extends)
					if (isset($match[3]) && trim($match[3]) !== '') {
						$parent_name = trim($match[3], " \n\r\t\v\x00\\");
						$this->inheritance[$full_name] = $parent_name;
					}

					// If it's a class that implements interfaces, process the implements clause.
					if ($type === 'class' && isset($match[4]) && trim($match[4]) !== '') {
						// Split the interface list by commas.
						$interface_list = explode(',', $match[4]);
						foreach ($interface_list as $interface) {
							$interface_name = trim($interface, " \n\r\t\v\x00\\");
							// Check that it is declared as an array so we can record the classes
							if (empty($this->interfaces[$interface_name])) {
								$this->interfaces[$interface_name] = [];
							}

							// Ensure we don't already have the class recorded
							if (!in_array($full_name, $this->interfaces[$interface_name], true)) {
								// Record the classes that implement interface sorting by namspace and class name
								$this->interfaces[$interface_name][] = $full_name;
							}
						}
					}
				}
			} else {

				//
				// When the file is in the classes|interfaces|traits folder then
				// we must assume it is a valid class as IonCube will encode the
				// class name. So, we use the file name as the class name in the
				// global  namespace and  set it,  checking first  to ensure the
				// basename does not  override an already declared class file in
				// order to mimic previous behaviour.
				//

				// use the basename as the class name
				$class_name = basename($file, '.php');
				if (!isset($this->classes[$class_name])) {
					$this->classes[$class_name] = $file;
				}
			}
		}
	}

	/**
	 * Updates the cache by writing the classes and interfaces to files on disk.
	 *
	 * @return bool True if the update was successful, false otherwise
	 */
	public function update_cache(): bool {
		//guard against writing an empty file
		if (empty($this->classes)) {
			return false;
		}

		//update RAM cache when available
		if ($this->apcu_enabled) {
			apcu_store(self::CACHE_VERSION_KEY, self::CACHE_VERSION);
			apcu_store(self::CLASSES_KEY, $this->classes);
			apcu_store(self::INTERFACES_KEY, $this->interfaces);
			apcu_store(self::INHERITANCE_KEY, $this->inheritance);
		}

		//prepare versioned data structure for classes
		$classes_data = [
			'version' => self::CACHE_VERSION,
			'classes' => $this->classes,
		];
		$classes_array = var_export($classes_data, true);

		//put the array in a form that it can be loaded directly to an array
		$class_result = file_put_contents(self::$classes_file, "<?php\n return " . $classes_array . ";\n");
		if ($class_result === false) {
			//file failed to save - send error to syslog when debugging
			$error_array = error_get_last();
			self::log(LOG_WARNING, $error_array['message'] ?? '');
		}

		//prepare versioned data structure for interfaces
		$interfaces_data = [
			'version' => self::CACHE_VERSION,
			'interfaces' => $this->interfaces,
		];
		$interfaces_array = var_export($interfaces_data, true);

		//put the array in a form that it can be loaded directly to an array
		$interface_result = file_put_contents(self::$interfaces_file, "<?php\n return " . $interfaces_array . ";\n");
		if ($interface_result === false) {
			//file failed to save - send error to syslog when debugging
			$error_array = error_get_last();
			self::log(LOG_WARNING, $error_array['message'] ?? '');
		}

		//prepare versioned data structure for inheritance
		$inheritance_data = [
			'version' => self::CACHE_VERSION,
			'inheritance' => $this->inheritance,
		];
		$inheritance_array = var_export($inheritance_data, true);

		//put the array in a form that it can be loaded directly to an array
		$inheritance_result = file_put_contents(self::$inheritance_file, "<?php\n return " . $inheritance_array . ";\n");
		if ($inheritance_result === false) {
			//file failed to save - send error to syslog when debugging
			$error_array = error_get_last();
			self::log(LOG_WARNING, $error_array['message'] ?? '');
		}

		$result = ($class_result && $interface_result && $inheritance_result);

		return $result;
	}

	/**
	 * Logs a message at the specified level
	 *
	 * @param int    $level   The log level (e.g. E_ERROR)
	 * @param string $message The log message
	 */
	private static function log(int $level, string $message): void {
		if (filter_var($_REQUEST['debug'] ?? false, FILTER_VALIDATE_BOOLEAN) || filter_var(getenv('DEBUG') ?? false, FILTER_VALIDATE_BOOLEAN)) {
			openlog("PHP", LOG_PID | LOG_PERROR, LOG_LOCAL0);
			syslog($level, "[auto_loader] " . $message);
			closelog();
		}
	}

	/**
	 * Main method used to update internal state by clearing cache, reloading classes and updating cache.
	 *
	 * @return void
	 * @see \auto_loader::clear_cache()
	 * @see \auto_loader::reload_classes()
	 * @see \auto_loader::update_cache()
	 */
	public function update() {
		self::clear_cache();
		$this->reload_classes();
		$this->update_cache();
	}

	/**
	 * Clears the cache of stored classes and interfaces.
	 *
	 * @return void
	 */
	public static function clear_cache() {

		//check for apcu cache
		if (function_exists('apcu_enabled') && apcu_enabled()) {
			apcu_delete(self::CACHE_VERSION_KEY);
			apcu_delete(self::CLASSES_KEY);
			apcu_delete(self::INTERFACES_KEY);
			apcu_delete(self::INHERITANCE_KEY);
		}

		//set default file
		if (empty(self::$classes_file)) {
			self::$classes_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::CLASSES_FILE;
		}

		//set file to clear
		$classes_file = self::$classes_file;

		//remove the file when it exists
		if (file_exists($classes_file)) {
			@unlink($classes_file);
			$error_array = error_get_last();
			//send to syslog when debugging with either environment variable or debug in the url
			self::log(LOG_WARNING, $error_array['message'] ?? '');
		}

		if (empty(self::$interfaces_file)) {
			self::$interfaces_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::INTERFACES_FILE;
		}

		//set interfaces file to clear
		$interfaces_file = self::$interfaces_file;

		//remove the file when it exists
		if (file_exists($interfaces_file)) {
			@unlink($interfaces_file);
			$error_array = error_get_last();
			//send to syslog when debugging with either environment variable or debug in the url
			self::log(LOG_WARNING, $error_array['message'] ?? '');
		}

		if (empty(self::$inheritance_file)) {
			self::$inheritance_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::INHERITANCE_FILE;
		}

		//set inheritance file to clear
		$inheritance_file = self::$inheritance_file;

		//remove the file when it exists
		if (file_exists($inheritance_file)) {
			@unlink($inheritance_file);
			$error_array = error_get_last();
			//send to syslog when debugging with either environment variable or debug in the url
			self::log(LOG_WARNING, $error_array['message'] ?? '');
		}
	}

	/**
	 * Returns a list of classes loaded by the auto_loader. If no classes have been loaded an empty array is returned.
	 *
	 * @param string $parent Optional parent class name to filter the list of classes that has the given parent class.
	 *
	 * @return array List of classes loaded by the auto_loader or empty array
	 */
	public function get_class_list(string $parent = ''): array {
		$classes = [];
		//make sure we can return values if no classes have been loaded
		if (!empty($this->classes)) {
			if ($parent !== '') {
				foreach ($this->classes as $class_name => $path) {
					if (is_subclass_of($class_name, $parent)) {
						$classes[$class_name] = $path;
					}
				}
			} else {
				$classes = $this->classes;
			}
		}
		return $classes;
	}

	/**
	 * Returns a list of classes implementing the interface or any interface that extends it
	 *
	 * @param string $interface_name
	 *
	 * @return array
	 */
	public function get_interface_list(string $interface_name): array {
		//make sure we can return values
		if (empty($this->classes) || empty($this->interfaces)) {
			return [];
		}

		//get direct implementers of this interface
		$result = $this->interfaces[$interface_name] ?? [];

		//find all child interfaces (interfaces that extend this interface)
		$child_interfaces = $this->get_child_interfaces($interface_name);

		//for each child interface, get its implementers
		foreach ($child_interfaces as $child_interface) {
			if (!empty($this->interfaces[$child_interface])) {
				$result = array_merge($result, $this->interfaces[$child_interface]);
			}
		}

		//remove duplicates and return
		return array_unique($result);
	}

	/**
	 * Returns only classes that directly implement the given interface,
	 * without including classes that implement child/extended interfaces.
	 * This is used for global hook dispatch where only direct implementers
	 * of a generic interface (e.g., page_edit_hook) should be invoked,
	 * while app-specific hooks use get_interface_list() for full resolution.
	 *
	 * @param string $interface_name The interface to find direct implementers for
	 *
	 * @return array List of class names that directly implement the interface
	 */
	public function get_direct_implementers(string $interface_name): array {
		if (empty($this->classes) || empty($this->interfaces)) {
			return [];
		}
		return $this->interfaces[$interface_name] ?? [];
	}

	/**
	 * Recursively finds all interfaces that extend the given interface
	 *
	 * @param string $interface_name The interface to find children for
	 * @param array $visited Track visited interfaces to avoid infinite loops
	 *
	 * @return array List of child interface names
	 */
	private function get_child_interfaces(string $interface_name, array &$visited = []): array {
		$children = [];

		// Mark as visited to prevent infinite recursion
		if (in_array($interface_name, $visited, true)) {
			return [];
		}
		$visited[] = $interface_name;

		// Find all interfaces that extend this interface
		foreach ($this->inheritance as $class_name => $parent_name) {
			if ($parent_name === $interface_name) {
				// Record this as a child
				$children[] = $class_name;

				// Recursively find children of this child
				$children = array_merge($children, $this->get_child_interfaces($class_name, $visited));
			}
		}

		return $children;
	}

	/**
	 * Returns a list of all user defined interfaces that have been registered.
	 *
	 * @return array
	 */
	public function get_interfaces(): array {
		if (!empty($this->interfaces)) {
			return $this->interfaces;
		}
		return [];
	}

	/**
	 * The loader is set to private because only the PHP engine should be calling this method
	 *
	 * @param string $class_name The class name that needs to be loaded
	 *
	 * @return bool True if the class is loaded or false when the class is not found
	 * @access private
	 */
	private function loader($class_name): bool {

		//sanitize the class name (preserve backslashes for namespaces)
		$class_name = preg_replace('/[^a-zA-Z0-9_\\\\]/', '', $class_name);

		//find the path using the class_name as the key in the classes array
		if (isset($this->classes[$class_name])) {
			//include the class or interface
			$result = @include_once $this->classes[$class_name];

			//check for edge case where the file was deleted after cache creation
			if ($result === false) {
				//send to syslog when debugging
				self::log(LOG_ERR, "class '$class_name' registered but include failed (file deleted?). Removed from cache.");

				//remove the class from the array
				unset($this->classes[$class_name]);

				//update the cache with new classes
				$this->update_cache();

				//return failure
				return false;
			}

			//return success
			return true;
		}

		//Smarty has it's own autoloader so reject the request
		if ($class_name === 'Smarty_Autoloader') {
			return false;
		}

		//cache miss
		self::log(LOG_WARNING, "class '$class_name' not found in cache");

		//set project path using magic dir constant
		$project_path = dirname(__DIR__, 2);

		//build the search path array
		$search_path[] = glob($project_path . "/resources/interfaces/" . $class_name . ".php");
		$search_path[] = glob($project_path . "/resources/traits/" . $class_name . ".php");
		$search_path[] = glob($project_path . "/resources/classes/" . $class_name . ".php");
		$search_path[] = glob($project_path . "/*/*/resources/interfaces/" . $class_name . ".php");
		$search_path[] = glob($project_path . "/*/*/resources/traits/" . $class_name . ".php");
		$search_path[] = glob($project_path . "/*/*/resources/classes/" . $class_name . ".php");

		//fix class names in the plugins directory prefixed with 'plugin_'
		if (str_starts_with($class_name, 'plugin_')) {
			$class_name = substr($class_name, 7);
		}
		$search_path[] = glob($project_path . "/core/authentication/resources/classes/plugins/" . $class_name . ".php");

		//collapse all entries to only the matched entry
		$matches = array_filter($search_path);
		if (!empty($matches)) {
			$path = array_pop($matches)[0];

			//include the class, interface, or trait
			include_once $path;

			//inject the class in to the array
			$this->classes[$class_name] = $path;

			//update the cache with new classes
			$this->update_cache();

			//return boolean
			return true;
		}

		//send to syslog when debugging
		self::log(LOG_ERR, "class '$class_name' not found name");

		//return boolean
		return false;
	}
}
