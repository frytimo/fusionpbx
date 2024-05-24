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

	public static function initialize(): void {
		self::config();
		if (self::$config->is_empty()) {
			header('Location: /core/install/install.php', true);
			exit();
		}
		self::set_error_reporting_level();
		self::define_project_paths();
	}

	public static function config(): config {
		global $config;
		if (self::$config === null) {
			self::$config = new config();
		}
		return self::$config;
	}

	// set project paths if not already defined
	private static function define_project_paths() {
		// Load the document root
		$doc_root = self::$config->get('document.root', '/var/www/fusionpbx');
		$doc_path = self::$config->get('document.path', '');
		//set the server variables and define project path constant
		if (!empty($doc_path)) {
			if (!defined('PROJECT_PATH')) { define("PROJECT_PATH", $doc_path); }
			if (!defined('PROJECT_ROOT')) { define("PROJECT_ROOT", $doc_root.'/'.$doc_path); }
		}
		else {
			if (!defined('PROJECT_PATH')) { define("PROJECT_PATH", ''); }
			if (!defined('PROJECT_ROOT')) { define("PROJECT_ROOT", $doc_root); }
		}

		// internal definitions to the framework
		$_SERVER["PROJECT_PATH"] = PROJECT_PATH;
		$_SERVER["PROJECT_ROOT"] = PROJECT_ROOT;

		// tell php where the framework is
		$_SERVER["DOCUMENT_ROOT"] = PROJECT_ROOT;

		// have php search for any libraries in the now defined root
		set_include_path(PROJECT_ROOT);
	}

	public static function database(): database {
		global $database;
		if (self::$database === null) {
			self::$database = new database(['config' => self::$config]);
		}
		return self::$database;
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
}
