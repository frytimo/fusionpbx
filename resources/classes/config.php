<?php

/**
 * config class loads configuration from the file system
 * @param string $database_type Type of database
 * @param string $db_type Type of database
 * @param string $database_driver Alias of type
 * @param string $db_driver Alias of type
 * @param string $database_host Host to connect to
 * @param string $db_host Host to connect to
 * @param string $database_path Path of the database if it is file system based
 * @param string $db_path Path of the database if it is file system based
 * @param string $database_file File name of the database if it is file system based
 * @param string $db_file File name of the database if it is file system based
 * @param string $database_port Port to connect to
 * @param string $db_port Port to connect to
 * @param string $database_name Name of the database
 * @param string $db_name Name of the database
 * @param string $database_sslmode SSL Mode to use
 * @param string $db_sslmode SSL Mode to use
 * @param string $database_cert_authority The certificate authority
 * @param string $db_cert_authority The certificate authority
 * @param string $database_secure If the database is using a secure connection
 * @param string $db_secure If the database is using a secure connection
 * @param string $database_username Username credentials to connect with
 * @param string $db_username Username credentials to connect with
 * @param string $database_password Password credentials to connect with
 * @param string $db_password Password credentials to connect with
 * @param string $config_path_and_filename Full path and configuration file currently in use
 * @param string $temp_dir
 * @param string $admin_username
 * @param string $admin_password
 * @param string $domain_name
 * @internal the @param statements are used because they match the magic __get function that allows those to be accessed publicly
 */
final class config {

	/**
	 * file name of the configuration file
	 * @var string
	 */
	private $config_file;

	/**
	 * path of the configuration file
	 */
	private $config_path;

	// The internal array that holds the configuration in the config.conf file
	private $configuration;

	/**
	 * Configuration object used to hold a single instance
	 * @var array
	 */
	public static $config = null;

	/**
	 * Loads the framework configuration file
	 */
	public function __construct(?string $file = '') {

		//initialize configuration array to be an empty array
		$this->configuration = [];

		//check if the config file was found
		if (empty($file)) {
			//locate the conf file
			$file = self::find();
		}

		//remember the fullpath and filename
		$this->config_path = dirname($file);
		$this->config_file = basename($file);

		//load the conf file
		if (file_exists($file)) {
			$this->read();
		}

	}

	/**
	 * Magic method to allow backward compatibility for variables such as db_type.
	 * <p>This will allow using config object with the syntax of:<br>
	 * $config = new config();<br>
	 * $db_type = $config->db_type;<br></p>
	 * <p>Note:<br>
	 * The <i>InvalidArgumentException</i> is thrown if there is no such variable accessed such as:<br>
	 * $config = new config();<br>
	 * $db_function = $config->db_function();
	 * </p>
	 * <p>This is ensure that any invalid code is detected and fixed.</p>
	 * @param string $name Name of the object property
	 * @return string Returns the value as a string
	 */
	public function __get(string $name): string {
		switch($name) {
			case 'database_type':
			case 'db_type':
			case 'db_driver':
				return $this->configuration['database.0.type'] ?? '';
			case 'database_path':
			case 'db_path':
			case 'path':
				return $this->configuration['database.0.path'] ?? '';
			case 'database_host':
			case 'db_host':
				return $this->configuration['database.0.host'] ?? '';
			case 'database_port':
			case 'db_port':
				return $this->configuration['database.0.port'] ?? '';
			case 'database_name':
			case 'db_name':
				return $this->configuration['database.0.name'] ?? '';
			case 'database_sslmode':
			case 'db_sslmode':
				return $this->configuration['database.0.sslmode'] ?? 'prefer';
			case 'database_cert_authority':
			case 'db_cert_authority':
				return $this->configuration['database.0.cert_authority'] ?? '';
			case 'database_secure':
			case 'db_secure':
				return $this->configuration['database.0.secure'] ?? 'false';
			case 'database_username':
			case 'db_username':
			case 'username':
				return $this->configuration['database.0.username'] ?? '';
			case 'database_password':
			case 'db_password':
			case 'password':
				return $this->configuration['database.0.password'] ?? '';
			case 'database_file':
			case 'db_file':
				return $this->configuration['database.0.file'] ?? '';
			case 'config_path':
				return $this->path();
			case 'config_filename':
				return $this->filename();
			case 'config_path_and_filename':
			case 'config_file':
				return $this->path_and_filename();
			case 'temp_dir':
				return $this->configuration['temp.dir'] ?? '';
			case 'admin_username':
				return $this->configuration['init.admin.name'] ?? '';
			case 'admin_password':
				return $this->configuration['init.admin.password'] ?? '';
			case 'domain_name':
				return $this->configuration['init.domain.name'] ?? '';
			default:
				if (property_exists($this, $name)) {
					return $this->{$name};
				}
				elseif (array_key_exists($name, $this->configuration)) {
					return $this->configuration[$name];
				}
		}
		return "";
	}

	public function __set(string $name, $value): void {
		if (empty($name)) {
			throw new \InvalidArgumentException("Config key must not be null");
		}
		switch($name) {
			case 'database_type':
			case 'db_type':
			case 'db_driver':
				$this->configuration['database.0.type'] = $value;
				break;
			case 'database_path':
			case 'db_path':
			case 'path':
				$this->configuration['database.0.path'] = $value;
				break;
			case 'database_host':
			case 'db_host':
				$this->configuration['database.0.host'] = $value;
				break;
			case 'database_port':
			case 'db_port':
				$this->configuration['database.0.port'] = $value;
				break;
			case 'database_name':
			case 'db_name':
				$this->configuration['database.0.name'] = $value;
				break;
			case 'database_sslmode':
			case 'db_sslmode':
				$this->configuration['database.0.sslmode'] = $value;
				break;
			case 'database_cert_authority':
			case 'db_cert_authority':
				$this->configuration['database.0.cert_authority'] = $value;
				break;
			case 'database_secure':
			case 'db_secure':
				$this->configuration['database.0.secure'] = $value;
				break;
			case 'database_username':
			case 'db_username':
			case 'username':
				$this->configuration['database.0.username'] = $value;
				break;
			case 'database_password':
			case 'db_password':
			case 'password':
				$this->configuration['database.0.password'] = $value;
				break;
			case 'database_file':
			case 'db_file':
				$this->configuration['database.0.file'] = $value;
				break;
			case 'config_path':
				if (str_ends_with($value, '/')) {
					$this->config_path = rtrim($value, '/');
				} else {
					$this->config_path = $value;
				}
				break;
			case 'config_file':
			case 'config_filename':
				$this->config_file = $value;
				break;
			case 'config_path_and_filename':
				$this->config_path = dirname($value);
				$this->config_file = basename($value);
				break;
			case 'temp_dir':
				$this->configuration['temp.dir'] = $value;
				break;
			case 'admin_username':
				$this->configuration['init.admin.name'] = $value;
				break;
			case 'admin_password':
				$this->configuration['init.admin.password'] = $value;
				break;
			case 'domain_name':
				$this->configuration['init.domain.name'] = $value;
				break;
			default:
				if (property_exists($this, $name)) {
					$this->{$name} = $value;
				}
				else {
					$this->configuration[$name] = $value;
				}
				break;
		}
	}

	/**
	 * Returns the string representation of the configuration file
	 * @return string configuration
	 */
	public function __toString(): string {
		$string_builder = "";
		foreach ($this->configuration as $key => $value) {
			if ($value !== null) {
				$string_builder .= "$key = '$value'\n";
			} else {
				// allow for comments
				$string_builder .= "\n$key\n";
			}
		}
		return $string_builder;
	}

	public function set(string $key, ?string $value = null) {
		if (!empty($key)) {
			$this->__set($key, $value);
		}
	}

	// loads the config.conf file
	public function read() {

		//check if include is needed
		if (substr($this->path_and_filename(), 0, -4) === '.php') {
			//allow global variables to be set in the old config.php file
			global $db_type, $db_host, $db_port, $db_name, $db_username, $db_password, $db_path;
			global $db_sslmode, $db_secure, $db_cert_authority;

			//load the config.php file
			require_once $this->path_and_filename();

			//convert the old properties to the new standard
			if (isset($db_type)) {
				$this->configuration['database.0.type'] = $db_type;
			} else {
				$this->configuration['database.0.type'] = 'pgsql';
			}
			if (isset($db_path)) {
				$this->configuration['database.0.path'] = $db_path;
			} else {
				$this->configuration['database.0.path'] = '';
			}
			if (isset($db_host)) {
				$this->configuration['database.0.host'] = $db_host;
			}
			if (isset($db_port)) {
				$this->configuration['database.0.port'] = $db_port;
			}
			if (isset($db_name)) {
				$this->configuration['database.0.name'] = $db_name;
			}
			if (isset($db_username)) {
				$this->configuration['database.0.username'] = $db_username;
			}
			if (isset($db_password)) {
				$this->configuration['database.0.password'] = $db_password;
			}
			if (isset($db_sslmode)) {
				$this->configuration['database.0.sslmode'] = $db_sslmode;
			} else {
				$this->configuration['database.0.sslmode'] = 'prefer';
			}
			if (isset($db_secure)) {
				$this->configuration['database.0.secure'] = $db_secure;
			}
			if (isset($db_cert_authority)) {
				$this->configuration['database.0.cert_authority'] = $db_cert_authority;
			}

			//remove from the global namespace
			unset($db_type, $db_host, $db_port, $db_name, $db_username, $db_password, $db_sslmode, $db_secure, $db_cert_authority);
		} 
		else {
			//save the loaded and parsed conf file to the object
			$this->configuration = parse_ini_file($this->path_and_filename());
		}

	}

	/**
	 * Find the path to the config.conf file
	 * @var string $config_path - full path to the config.php file
	 */
	public static function find(): string {
		//define the file variable
		$file = "";

		//find the file
		if (file_exists("/etc/fusionpbx/config.conf")) {
			$file = "/etc/fusionpbx/config.conf";
		}
		elseif (file_exists("/usr/local/etc/fusionpbx/config.conf")) {
			$file = "/usr/local/etc/fusionpbx/config.conf";
		}
		elseif (file_exists("/etc/fusionpbx/config.php")) {
			$file = "/etc/fusionpbx/config.php";
		}
		elseif (file_exists("/usr/local/etc/fusionpbx/config.php")) {
			$file = "/usr/local/etc/fusionpbx/config.php";
		}
		elseif (file_exists(dirname(__DIR__, 2) . "/resources/config.php")) {
			//use the current web directory to find it as a last resort
			$file = "/var/www/fusionpbx/resources/config.php";
		}
		return $file;
	}

	/**
	 * Get a configuration value using a key in the configuration file
	 * @param string|null $key Match key on the left hand side of the '=' in the config file. If $key is null the default value is returned
	 * @param string $default_value if no matching key is found, then this value will be returned
	 * @return string returns a value in the config.conf file or an empty string
	 */
	public function get(string $key, string $default_value = ''): string {
		if (!empty($this->__get($key))) {
			return $this->__get($key);
		}
		return $default_value;
	}

	/**
	 * Returns the config path or an empty string
	 * @return string
	 */
	public function path(): string {
		return $this->config_path;
	}

	/**
	 * Returns the file name only of the configuration file
	 * @return string
	 */
	public function filename(): string {
		return $this->config_file;
	}

	/**
	 * Returns the path and the file name
	 * @return string
	 */
	public function path_and_filename(): string {
		return $this->config_path . '/' . $this->config_file;
	}

	/**
	 * Returns if the config class has a loaded configuration or not
	 * @return bool True if configuration has loaded and false if it is empty
	 */
	public function is_empty(): bool {
		return count($this->configuration) === 0;
	}

	/**
	 * Returns the array of configuration settings
	 * @return array
	 */
	public function configuration(): array {
		return $this->configuration;
	}

	/**
	 * Ensures the configuration file is loaded only once
	 * @return config
	 */
	public static function load(?string $file = ''): config {
		if (self::$config === null) {
			self::$config = new config($file);
		}
		return self::$config;
	}
}

/*
//Examples:
//~~~~~~~~
$config = new config;
echo "Config path: " . $config->path() . "\n";
echo "Config file: " . $config->filename() . "\n";
echo "Full path and filename: " . $config->path_and_filename() . "\n";

// show old style configuration options
echo "db_type: ".$config->db_type."\n";
echo "db_name: ".$config->db_name."\n";
echo "db_username: ".$config->db_username."\n";
echo "db_password: ".$config->db_password."\n";
echo "db_host: ".$config->db_host."\n";
echo "db_path: ".$config->db_path."\n";
echo "db_port: ".$config->db_port."\n";

// use current style configuration options even on old config.php
echo "database.0.type: " . $config->get('database.0.type') . "\n";

// use a default value
echo "admin.name: " . $config->value('admin.name', 'admin') . "\n";

// get all configuration options by printing the object
echo "config settings: " . $config . "\n";

// save the configuration options to a file
file_put_contents('/etc/fusionpbx/config.conf', $config);

// get all configuration options as an array
var_dump($config->configuration());

//*/
