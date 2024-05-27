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
  Portions created by the Initial Developer are Copyright (C) 2008-2018
  the Initial Developer. All Rights Reserved.

  Contributor(s):
  Mark J Crane <markjcrane@fusionpbx.com>
  Tim Fry <tim@fusionpbx.com>
 */

/**
 * Description of framework
 */
final class framework {

	private static $config = null;
	private static $database = null;
	private static $permissions = null;
	private static $installing = false;

	// set project paths if not already defined
	private static function define_project_paths() {
		// Load the document root
		$doc_root = self::$config->get('document.root', '/var/www/fusionpbx');
		$doc_path = self::$config->get('document.path', '');
		//set the server variables and define project path constant
		if (!empty($doc_path)) {
			if (!defined('PROJECT_PATH')) {
				define("PROJECT_PATH", $doc_path);
			}
			if (!defined('PROJECT_ROOT')) {
				define("PROJECT_ROOT", $doc_root . '/' . $doc_path);
			}
		} else {
			if (!defined('PROJECT_PATH')) {
				define("PROJECT_PATH", '');
			}
			if (!defined('PROJECT_ROOT')) {
				define("PROJECT_ROOT", $doc_root);
			}
		}

		// internal definitions to the framework
		$_SERVER["PROJECT_PATH"] = PROJECT_PATH;
		$_SERVER["PROJECT_ROOT"] = PROJECT_ROOT;

		// tell php where the framework is
		$_SERVER["DOCUMENT_ROOT"] = PROJECT_ROOT;

		// have php search for any libraries in the now defined root
		set_include_path(PROJECT_ROOT);
	}

	private static function run_installer() {
		self::$installing = true;

		//set the project root
		$project_root = dirname(__DIR__, 2);

		//set the max execution time to 1 hour
		ini_set('max_execution_time', 3600);

		//set debug to true or false
		$debug = false;

		//set the default domain_uuid
		$domain_uuid = uuid();

		//add the menu uuid
		$menu_uuid = 'b4750c3f-2a86-b00d-b7d0-345c14eca286';

		//set the default time zone
		date_default_timezone_set('UTC');

		//create the installer to get the default config based on OS
		$config = install::get_default_config();

		//set the domain name in the config
		$domain_array = explode(":", $_SERVER["HTTP_HOST"]);
		$config->domain_name = $domain_array[0];
		unset($domain_array);

		//check for debugging
		if ($debug || gethostname() === 'php') {
			$config->db_host = 'db';
			$config->set("#development environment");
			$config->set("switch.event_socket.host", 'fs');
			$config->set('init.domain.name', 'localhost');
			$config->set('init.admin.name', 'admin');
			$config->set('init.admin.password', 'password');
//			$config->set('error.reporting', 'all');
		}

		//re-initialize the framework with the default config settings
		framework::initialize($config);

		//create tables that are created in the install.sh script
		//start the session before text object stores values in session
		session_start();

		//temp directory
		$_SESSION['server']['temp']['dir'] = $config->temp_dir;

		//set a default template
		$_SESSION['domain']['template']['name'] = 'default';
		$_SESSION['theme']['menu_brand_image']['text'] = PROJECT_PATH . '/themes/default/images/logo.png';
		$_SESSION['theme']['menu_brand_type']['text'] = 'image';

		//set a default step if not already set
		if (empty($_REQUEST['step'])) {
			$_REQUEST['step'] = '1';
		}

		//add multi-lingual support
		$language = new text;
		$text = $language->get();

		//initialize a template object
		$view = new template();
		$view->set_template_dir($project_root . '/core/install/resources/views/');
		$view->set_cache_dir($config->temp_dir);
		$view->init();

		//assign default values to the template
		$view->assign("admin_username", $config->admin_username);
		$view->assign("admin_password", $config->admin_password);
		$view->assign("domain_name", $config->domain_name);
		$view->assign("database_host", $config->db_host);
		$view->assign("database_port", $config->db_port);
		$view->assign("database_name", $config->db_name);
		$view->assign("database_username", $config->db_username);
		$view->assign("database_password", $config->db_password);

		//add translations
		foreach ($text as $key => $value) {
			$view->assign(str_replace("-", "_", $key), $text[$key]);
			//$view->assign("label_username", $text['label-username']);
			//$view->assign("label_password", $text['label-password']);
			//$view->assign("button_back", $text['button-back']);
		}

		foreach ($_POST as $key => $value) {
			switch ($key) {
				case 'admin_username':
				case 'admin_password':
				case 'domain_name':
				case 'database_host':
				case 'database_port':
				case 'database_name':
				case 'database_username':
				case 'database_password':
					$_SESSION['install'][$key] = $value;
			}
		}

		if ($_REQUEST["step"] == "1") {
			$content = $view->render('configuration.tpl');
		}
		if ($_REQUEST["step"] == "2") {
			$content = $view->render('database.tpl');
		}

		if (!empty($content)) {
			$view->assign("content", $content);
			echo $view->render('template.tpl');
		}

		if ($_REQUEST['step'] == "install") {
			$install = new install($config);

			foreach ($_SESSION['install'] as $key => $value) {
				switch ($key) {
					case 'admin_username':
					case 'admin_password':
					case 'domain_name':
					case 'database_host':
					case 'database_port':
					case 'database_name':
					case 'database_username':
					case 'database_password':
						$install->{$key} = $value;
				}
			}

			//update the framework to use the settings from the install pages
			framework::initialize($config);

			//write the new configuration file
			install::write_config($config);

			//schema
			$install->run_update_schema();

			//create a UUID for the domain
			$domain_uuid = $install->domain_uuid();
			$domain_name = $install->domain_name();

			//set the session domain id and name
			$_SESSION['domain_uuid'] = $domain_uuid;
			$_SESSION['domain_name'] = $domain_name;

			//create super admin
			$install->run_update_superadmin($domain_uuid);

			//app defaults
			$install->run_update_domains();

			//menus
			$install->run_update_menu();

			//permissions
			$install->run_update_permissions();

			header("Location: /logout.php");
		}
	}

	public static function is_install_active(): bool {
		return self::$installing;
	}

	public static function initialize(?config $config = null): void {
		//load common global functions
		require_once dirname(__DIR__) . '/functions.php';

		//locate and load the config
		if ($config === null) {
			self::config();
		} else {
			self::$config = $config;
		}

		//redirect to installer if needed
		if (self::$config->is_empty()) {
			self::run_installer();
			exit();
		}

		//initialize framework objects
		self::database();
		self::permissions();

		//set reporting level
		self::set_error_reporting_level();

		//define project paths
		self::define_project_paths();

		self::$installing = false;

	}

	public static function config(): config {
		if (self::$config === null) {
			self::$config = new config();
		}
		return self::$config;
	}

	public static function database(): database {
		global $database;
		if (self::$database === null) {
			self::$database = new database(self::config());
		}
		return self::$database;
	}

	public static function permissions(): permissions {
		global $permissions;
		if (self::$permissions === null) {
			self::$permissions = new permissions(self::database());
		}
		return self::$permissions;
	}

	public static function set_error_reporting_level() {
		//set the error reporting
		ini_set('display_errors', '1');

		switch (self::$config->get('error.reporting', 'user')) {
			case 'user':
				error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING ^ E_DEPRECATED);
				break;
			case 'dev':
				error_reporting(E_ALL ^ E_NOTICE);
				break;
			case 'all':
				error_reporting(E_ALL);
				break;
			default:
				error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING ^ E_DEPRECATED);
		}
	}

	/**
	 * Returns the UUID of the current session domain
	 * @return string
	 */
	public static function domain_uuid(): string {
		if (!empty($_SESSION['domain_uuid'])) {
			return $_SESSION['domain_uuid'];
		}
	}

	/**
	 * Returns the domain name of the current session domain
	 * @return string
	 */
	public static function domain_name(): string {
		if (!empty($_SESSION['domain_name'])) {
			return $_SESSION['domain_name'];
		}
		//actively set the current domain name if the domain uuid is available
		if (!empty(self::domain_uuid()) && self::$database !== null) {
			$sql = "select domain_name from v_domains where domain_uuid = :domain_uuid";
			$params = [];
			$params['domain_uuid'] = self::domain_uuid();
			$domain_name = self::$database->select($sql, $params, 'column');
			if (!empty($domain_name)) {
				if (session_status() === PHP_SESSION_ACTIVE) {
					$_SESSION['domain_name'] = $domain_name;
				}
				return $domain_name;
			}
		}
		return "";
	}
}
