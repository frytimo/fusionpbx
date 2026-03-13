<?php

/*
 * FusionPBX
 * Version: MPL 1.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is FusionPBX
 *
 * The Initial Developer of the Original Code is
 * Mark J Crane <markjcrane@fusionpbx.com>
 * Portions created by the Initial Developer are Copyright (C) 2008-2024
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * Mark J Crane <markjcrane@fusionpbx.com>
 */

/**
 * authentication
 */
class authentication {
	/**
	 * Declare Public variables
	 *
	 * @var mixed
	 */
	public $domain_uuid;

	public $user_uuid;
	public $domain_name;
	public $username;
	public $password;
	public $key;

	/**
	 * Declare Private variables
	 *
	 * @var mixed
	 */
	private $database;

	private $settings;

	/**
	 * Called when the object is created
	 */
	public function __construct(array $setting_array = []) {
		// set the config object
		$config = $setting_array['config'] ?? config::load();

		// set the database connection
		$this->database = $setting_array['database'] ?? database::new(['config' => $config]);

		// set the settings object
		$this->settings = $setting_array['settings'] ?? new settings(['database' => $this->database]);

		// intialize the object
		$this->user_uuid = null;
	}

	/**
	 * validate uses authentication plugins to check if a user is authorized to login
	 *
	 * @return array|false [plugin] => last plugin used to authenticate the user [authorized] => true or false
	 */
	public function validate() {
		// set default return array as null
		$result = null;

		// use a login message when a login attempt fails
		$failed_login_message = null;

		// get the domain_name and domain_uuid
		if (!isset($this->domain_name) || !isset($this->domain_uuid)) {
			$this->get_domain();
		}

		// create a settings object to pass to plugins
		$this->settings = new settings(['database' => $this->database, 'domain_uuid' => $this->domain_uuid]);

		// set the default authentication method to the database
		if (empty($_SESSION['authentication']['methods']) || !is_array($_SESSION['authentication']['methods'])) {
			$_SESSION['authentication']['methods'][] = 'database';
		}

		// set the database as the default plugin
		if (!isset($_SESSION['authentication']['methods'])) {
			$_SESSION['authentication']['methods'][] = 'database';
		}

		// check if contacts app exists
		$contacts_exists = file_exists(dirname(__DIR__, 4) . '/core/contacts/');

		// ensure plugin container exists
		if (!isset($_SESSION['authentication']['plugin']) || !is_array($_SESSION['authentication']['plugin'])) {
			$_SESSION['authentication']['plugin'] = [];
		}

		// attempt remember-me cookie re-authentication before running plugins
		if (empty($_SESSION['authorized']) && isset($_COOKIE['remember'])) {
			$cookie_parts = explode(':', $_COOKIE['remember'], 2);
			if (count($cookie_parts) === 2) {
				[$selector, $validator] = $cookie_parts;
				if (is_uuid($selector)) {
					$sql  = "select ul.domain_uuid, ul.user_uuid, ul.username, ul.remember_validator, d.domain_name ";
					$sql .= "from v_user_logs as ul ";
					$sql .= "join v_domains as d on d.domain_uuid = ul.domain_uuid ";
					$sql .= "where ul.remember_selector = :selector ";
					$sql .= "and ul.result = 'success' ";
					$sql .= "limit 1 ";
					$cookie_row = $this->database->select($sql, ['selector' => $selector], 'row');
					if (!empty($cookie_row) && !empty($cookie_row['remember_validator'])
						&& hash_equals($cookie_row['remember_validator'], hash('sha256', $validator))
					) {
						// rotate the token to limit exposure window
						$new_validator = bin2hex(random_bytes(32));
						$p = permissions::new();
						$p->add('user_log_add', 'temp');
						$this->database->execute(
							"update v_user_logs set remember_validator = :new_hash where remember_selector = :selector ",
							['new_hash' => hash('sha256', $new_validator), 'selector' => $selector]
						);
						$p->delete('user_log_add', 'temp');
						setcookie('remember', $selector . ':' . $new_validator, [
							'expires'  => time() + 60 * 60 * 24 * 30,
							'path'     => '/',
							'httponly' => true,
							'samesite' => 'Lax',
							'secure'   => isset($_SERVER['HTTPS']),
						]);
						$this->domain_uuid = $cookie_row['domain_uuid'];
						$this->user_uuid   = $cookie_row['user_uuid'];
						$this->settings    = new settings(['database' => $this->database, 'domain_uuid' => $this->domain_uuid]);
						$result = [
							'plugin'       => 'database',
							'domain_uuid'  => $cookie_row['domain_uuid'],
							'domain_name'  => $cookie_row['domain_name'],
							'user_uuid'    => $cookie_row['user_uuid'],
							'username'     => $cookie_row['username'],
							'contact_uuid' => null,
							'authorized'   => true,
						];
						self::create_user_session($result, $this->settings);
						$_SESSION['authorized'] = true;
						return $result;
					}
					// invalid or expired token – clear the cookie
					setcookie('remember', '', time() - 3600, '/');
				}
			}
		}

		// use the authentication plugins
		foreach ($_SESSION['authentication']['methods'] as $name) {
			// already processed the plugin move to the next plugin
			if (!empty($_SESSION['authentication']['plugin'][$name]['authorized']) && $_SESSION['authentication']['plugin'][$name]['authorized']) {
				continue;
			}

			// prepare variables
			$class_name = "plugin_" . $name;
			$base = __DIR__ . "/plugins";
			$plugin = $base . "/" . $name . ".php";

			// process the plugin
			if (file_exists($plugin)) {
				// run the plugin
				$object = new $class_name();
				$object->domain_name = $this->domain_name;
				$object->domain_uuid = $this->domain_uuid;
				if ($name == 'database' && isset($this->key)) {
					$object->key = $this->key;
				}
				if ($name == 'database' && isset($this->username)) {
					$object->username = $this->username;
					$object->password = $this->password;
				}
				// initialize the plugin send the authentication object and settings
				$array = $object->$name($this, $this->settings);

				// build a result array
				if (!empty($array) && is_array($array)) {
					$result['plugin'] = $array["plugin"];
					$result['domain_name'] = $array["domain_name"];
					$result['username'] = $array["username"];
					$result['user_uuid'] = $array["user_uuid"];
					$result['contact_uuid'] = $array["contact_uuid"];
					if ($contacts_exists) {
						$result["contact_organization"] = $array["contact_organization"] ?? '';
						$result["contact_name_given"] = $array["contact_name_given"] ?? '';
						$result["contact_name_family"] = $array["contact_name_family"] ?? '';
						$result["contact_image"] = $array["contact_image"] ?? '';
					}
					$result['domain_uuid'] = $array["domain_uuid"];
					$result['authorized'] = $array["authorized"];

					// set the domain_uuid
					$this->domain_uuid = $array["domain_uuid"];

					// set the user_uuid
					$this->user_uuid = $array["user_uuid"];

					// save the result to the authentication plugin
					$_SESSION['authentication']['plugin'][$name] = $result;
				}

				// plugin authorized false
				if (!is_array($result) || empty($result['authorized'])) {
					break;
				}
			}
		}

		// make sure all plugins are in the array
		if (!empty($_SESSION['authentication']['methods'])) {
			foreach ($_SESSION['authentication']['methods'] as $name) {
				if (!isset($_SESSION['authentication']['plugin'][$name]['authorized'])) {
					$_SESSION['authentication']['plugin'][$name]['plugin'] = $name;
					$_SESSION['authentication']['plugin'][$name]['domain_name'] = $_SESSION['domain_name'] ?? null;
					$_SESSION['authentication']['plugin'][$name]['domain_uuid'] = $_SESSION['domain_uuid'] ?? null;
					$_SESSION['authentication']['plugin'][$name]['username'] = $_SESSION['username'] ?? null;
					$_SESSION['authentication']['plugin'][$name]['user_uuid'] = $_SESSION['user_uuid'] ?? null;
					$_SESSION['authentication']['plugin'][$name]['user_email'] = $_SESSION['user_email'] ?? null;
					$_SESSION['authentication']['plugin'][$name]['authorized'] = false;
				}
			}
		}

		// debug information
		// view_array($_SESSION['authentication'], false);

		// set authorized to false if any authentication method failed
		$authorized = false;
		$plugin_name = '';
		if (is_array($_SESSION['authentication']['plugin'])) {
			foreach ($_SESSION['authentication']['plugin'] as $row) {
				$plugin_name = $row['plugin'] ?? '';
				if (!empty($row["authorized"])) {
					$authorized = true;
				} else {
					$authorized = false;
					$failed_login_message = "Authentication plugin '$plugin_name' blocked login attempt";
					break;
				}
			}
		}

		// user is authorized - get user settings, check user cidr
		if ($authorized) {
			// get the cidr restrictions from global, domain, and user default settings
			$this->settings = new settings(['database' => $this->database, 'domain_uuid' => $this->domain_uuid, 'user_uuid' => $this->user_uuid]);
			$cidr_list = $this->settings->get('domain', 'cidr', []);
			if (check_cidr($cidr_list, $_SERVER['REMOTE_ADDR'])) {
				// user passed the cidr check
				self::create_user_session($result, $this->settings);

				// generate a remember-me token if the user opted in
				if (!empty($_SESSION['remember'])) {
					$selector  = uuid();
					$validator = bin2hex(random_bytes(32));
					$_SESSION['authentication']['plugin'][$name]['remember_selector'] = $selector;
					$_SESSION['authentication']['plugin'][$name]['remember_validator'] = hash('sha256', $validator);
					setcookie('remember', $selector . ':' . $validator, [
						'expires'  => time() + 60 * 60 * 24 * 30,
						'path'     => '/',
						'httponly' => true,
						'samesite' => 'Lax',
						'secure'   => isset($_SERVER['HTTPS']),
					]);
					unset($_SESSION['remember']);
				}
			} else {
				// user failed the cidr check - no longer authorized
				$authorized = false;
				$failed_login_message = "CIDR blocked login attempt";
				$_SESSION['authentication']['plugin'][$name]['authorized'] = false;
			}
		}

		// set a session variable to indicate whether or not we are authorized
		$_SESSION['authorized'] = $authorized;

		// add a concise server log entry for denied login attempts to aid troubleshooting
		if (!$authorized) {
			error_log(
				"FusionPBX login denied"
				. " username=" . ($_REQUEST['username'] ?? $_SESSION['username'] ?? 'unknown')
				. " domain=" . ($this->domain_name ?? $_SESSION['domain_name'] ?? 'unknown')
				. " plugin=" . ($plugin_name ?: 'unknown')
				. " reason=" . ($failed_login_message ?? 'not_authorized')
			);
		}

		// log the attempt
		$log_row = $_SESSION['authentication']['plugin'][$name] ?? [
			'plugin' => $name ?? 'unknown',
			'domain_name' => $_SESSION['domain_name'] ?? null,
			'domain_uuid' => $_SESSION['domain_uuid'] ?? null,
			'username' => $_SESSION['username'] ?? null,
			'user_uuid' => $_SESSION['user_uuid'] ?? null,
			'authorized' => $authorized,
		];
		user_logs::add($log_row, $failed_login_message);

		// return the result
		return $result ?? false;
	}

	/**
	 * Creates a valid user session in the superglobal $_SESSION.
	 * <p>The $result must be a validated user with the appropriate variables set.<br>
	 * The associative array
	 *
	 * @param array|bool $result   Associative array containing: domain_uuid, domain_name, user_uuid, username. Contact
	 *                             keys can be empty, but should still be present. They include: contact_uuid,
	 *                             contact_name_given, contact_name_family, contact_image.
	 * @param settings   $settings From the settings object
	 *
	 * @return void
	 * @global string    $conf
	 * @global database  $database
	 */
	public static function create_user_session($result = [], $settings = null): void {
		// use the database global
		global $autoload, $database;

		// validate data
		if (empty($result)) {
			return;
		}

		// Required keys
		$required_keys = [
			'domain_uuid' => true,
			'domain_name' => true,
			'user_uuid' => true,
			'username' => true,
		];

		// Any missing required_fields are left in the $diff array.
		// When all keys are present the $diff array will be empty.
		$diff = array_diff_key($required_keys, $result);

		// All required keys must be present in the $result associative array
		if (!empty($diff)) {
			return;
		}

		// Domain and User UUIDs must be valid UUIDs
		if (!is_uuid($result['domain_uuid']) || !is_uuid($result['user_uuid'])) {
			return;
		}

		// If Contact UUID has a value it must be a valid UUID
		if (!empty($result['contact_uuid']) && !is_uuid($result['contact_uuid'])) {
			return;
		}

		$listeners = $autoload->get_interface_list('login_event');
		try {
			foreach ($listeners as $class) {
				$class::on_login_pre_session_create($settings);
			}
		} catch (Exception $e) {
			// Log the failing listener and message because this path previously failed silently.
			error_log("FusionPBX login pre-session denied by " . ($class ?? 'unknown_listener') . ": " . $e->getMessage());
			header("Location: " . PROJECT_PATH . "/login.php?login_error=pre_session_denied");
			exit();
		}

		//
		// All data validated continue to create session
		//

		// Set project root directory
		$project_root = dirname(__DIR__, 4);

		try {
			if (session_status() !== PHP_SESSION_ACTIVE) {
				session_start();
			}
			// Set the core session variables
			$_SESSION["domain_uuid"] = $result["domain_uuid"];
			$_SESSION["domain_name"] = $result["domain_name"];
			$_SESSION["user_uuid"] = $result["user_uuid"];
			$_SESSION["context"] = $result['domain_name'];

			// User session array — populated here from auth plugin result data
			// (contact fields, username, etc.) so that post-session listeners can read it.
			$_SESSION["user"]["domain_uuid"] = $result["domain_uuid"];
			$_SESSION["user"]["domain_name"] = $result["domain_name"];
			$_SESSION["user"]["user_uuid"] = $result["user_uuid"];
			$_SESSION["user"]["username"] = $result["username"];
			$_SESSION["user"]["contact_uuid"] = $result["contact_uuid"] ?? null;

			// Check for contacts
			if (file_exists($project_root . '/core/contacts/')) {
				$_SESSION["user"]["contact_organization"] = $result["contact_organization"] ?? null;
				$_SESSION["user"]["contact_name"] = trim(($result["contact_name_given"] ?? '') . ' ' . ($result["contact_name_family"] ?? ''));
				$_SESSION["user"]["contact_name_given"] = $result["contact_name_given"] ?? null;
				$_SESSION["user"]["contact_name_family"] = $result["contact_name_family"] ?? null;
				$_SESSION["user"]["contact_image"] = !empty($result["contact_image"]) && is_uuid($result["contact_image"]) ? $result["contact_image"] : null;
			}

			// Create a settings object that matches the current user and domain.
			// This is passed to all post-session listeners so they can access user-scoped settings.
			$settings = new settings(['database' => $database, 'domain_uuid' => $result["domain_uuid"], 'user_uuid' => $result["user_uuid"]]);

			// Trigger the post-session event.
			// The user class (and other listeners) are responsible for loading groups,
			// permissions, user settings, extensions, session hash, timezone, etc.
			foreach ($listeners as $class) {
				$class::on_login_post_session_create($settings);
			}

			return;
		} catch (Exception $e) {
		}
	}

	/**
	 * get_domain used to get the domain name from the URL or username and then sets both domain_name and domain_uuid
	 */
	public function get_domain() {
		// get the domain from the url
		$this->domain_name = $_SERVER["HTTP_HOST"];

		// get the domain name from the http value
		if (!empty($_REQUEST["domain_name"])) {
			$this->domain_name = $_REQUEST["domain_name"];
		}

		// remote port number from the domain name
		$domain_array = explode(":", $this->domain_name);
		if (count($domain_array) > 1) {
			$this->domain_name = $domain_array[0];
		}

		// if the username
		if (!empty($_REQUEST["username"])) {
			$_SESSION['username'] = $_REQUEST["username"];
		}

		// set a default value for unqiue
		$_SESSION["users"]["unique"]["text"] = $this->settings->get('users', 'unique', '');

		// ensure domains are available during authentication before looping over them
		if (!isset($_SESSION['domains']) || !is_array($_SESSION['domains'])) {
			$domain = new domains(['database' => $this->database]);
			$domain->session();
		}

		// get the domain name from the username
		if (!empty($_SESSION['username']) && $this->settings->get('users', 'unique', '') != "global") {
			$username_array = explode("@", $_SESSION['username']);
			if (count($username_array) > 1) {
				// get the domain name
				$domain_name = $username_array[count($username_array) - 1];

				// check if the domain from the username exists
				$domain_exists = false;
				foreach ($_SESSION['domains'] as $row) {
					if (lower_case($row['domain_name']) == lower_case($domain_name)) {
						$this->domain_uuid = $row['domain_uuid'];
						$domain_exists = true;
						break;
					}
				}

				// if the domain exists then set domain_name and update the username
				if ($domain_exists) {
					$this->domain_name = $domain_name;
					$this->username = substr($_SESSION['username'], 0, -(strlen($domain_name) + 1));
					// $_SESSION['domain_name'] = $domain_name;
					$_SESSION['username'] = $this->username;
					$_SESSION['domain_uuid'] = $this->domain_uuid;
				}

				// unset the domain name variable
				unset($domain_name);
			}
		}

		// get the domain uuid and domain settings
		if (isset($this->domain_name) && !isset($this->domain_uuid)) {
			foreach ($_SESSION['domains'] as $row) {
				if (lower_case($row['domain_name']) == lower_case($this->domain_name)) {
					$this->domain_uuid = $row['domain_uuid'];
					$_SESSION['domain_uuid'] = $row['domain_uuid'];
					break;
				}
			}
		}

		// set the setting arrays
		$obj = new domains(['database' => $this->database]);
		$obj->set();

		// set the domain settings
		if (!empty($this->domain_name) && !empty($_SESSION["domain_uuid"])) {
			$_SESSION['domain_name'] = $this->domain_name;
			$_SESSION['domain_parent_uuid'] = $_SESSION["domain_uuid"];
		}

		// set the domain name
		return $this->domain_name;
	}
}

/*
 * $auth = new authentication;
 * $auth->username = "user";
 * $auth->password = "password";
 * $auth->domain_name = "sip.fusionpbx.com";
 * $response = $auth->validate();
 * print_r($response);
 */
