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
 * Portions created by the Initial Developer are Copyright (C) 2008-2025
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * Mark J Crane <markjcrane@fusionpbx.com>
 */

if (version_compare(PHP_VERSION, '8.4', '<')) {
	die('PHP 8.4 or higher is required');
}

if (!defined('STDIN') && (!function_exists('apcu_enabled') || !apcu_enabled())) {
	die('APCu extension is required and must be enabled');
}

if (!defined('PROJECT_ROOT')) {
	define('PROJECT_ROOT', dirname(__DIR__));
}

// class auto loader
if (!class_exists('auto_loader')) {
	$enable_cache = getenv('DISABLE_CACHE') !== 'true';
	require_once __DIR__ . "/classes/auto_loader.php";
	$autoload = new auto_loader($enable_cache);
}

// Use a global url instance that parses all requests and provides utility functions for working with URLs.
global $url;
$url = new url();

// load config file
global $config;
$config = config::load();

// config.conf file not found re-direct the request to the install
if ($config->is_empty()) {
	$url::redirect('/core/install/install.php');
}

// include global functions
require_once __DIR__ . "/functions.php";

// connect to the database
global $database;
$database = database::new(['config' => $config]);

// security headers
if (!is_cli() && session_status() === PHP_SESSION_NONE) {
	header("X-Frame-Options: SAMEORIGIN");
	header("Content-Security-Policy: frame-ancestors 'self';");
	header("X-Content-Type-Options: nosniff");
	header("Referrer-Policy: strict-origin-when-cross-origin");
	// header("Strict-Transport-Security: max-age=63072000; includeSubDomains; preload");
}

// start the session if not using the command line
global $no_session;
if (!is_cli() && empty($no_session) && session_status() === PHP_SESSION_NONE) {
	ini_set('session.cookie_httponly', !isset($conf['session.cookie_httponly']) ? 'true' : (!empty($config->get('session.cookie_httponly')) ? 'true' : 'false'));
	ini_set('session.cookie_secure', !isset($conf['session.cookie_secure']) ? 'true' : (!empty($config->get('session.cookie_secure')) ? 'true' : 'false'));
	ini_set('session.cookie_samesite', $config->get('session.cookie_samesite', 'Lax'));
	session_start();
}

$domain_uuid = $_SESSION['domain_uuid'] ?? '';
$user_uuid = $_SESSION['user_uuid'] ?? '';

// load settings
global $settings;
$settings = new settings(['database' => $database, 'domain_uuid' => $domain_uuid, 'user_uuid' => $user_uuid]);

// check if the cidr range is valid
global $no_cidr;
if (!is_cli() && empty($no_cidr)) {
	require_once __DIR__ . '/cidr.php';
}

// include switch functions when available
if (file_exists(__DIR__ . '/switch.php')) {
	require_once __DIR__ . '/switch.php';
}

// change language on the fly - for translate tool (if available)
// if (!is_cli() && isset($_REQUEST['view_lang_code']) && ($_REQUEST['view_lang_code']) != '') {
//	$_SESSION['domain']['language']['en-us'] = $_REQUEST['view_lang_code'];
// }

// change the domain
if (!empty($_GET["domain_uuid"]) && is_uuid($_GET["domain_uuid"]) && !empty($_GET["domain_change"]) && $_GET["domain_change"] == "true" && permission_exists('domain_select')) {
	// include domains
	if (file_exists(dirname(__DIR__, 1) . "/app/domains/app_config.php") && !permission_exists('domain_all')) {
		include_once "app/domains/domains.php";
	}

	// update the domain session variables
	$domain_uuid = $_GET["domain_uuid"];
	$_SESSION["previous_domain_uuid"] = $_SESSION['domain_uuid'];
	$_SESSION['domain_uuid'] = $domain_uuid;

	// get the domain details
	$sql = "select * from v_domains ";
	$sql .= "order by domain_name asc ";
	$domains = $database->select($sql, null, 'all');
	if (!empty($domains)) {
		foreach ($domains as $row) {
			$_SESSION['domains'][$row['domain_uuid']] = $row;
		}
	}
	unset($sql, $domains);

	// update the domain session variables
	$_SESSION["domain_name"] = $_SESSION['domains'][$domain_uuid]['domain_name'];
	$_SESSION["context"] = $_SESSION["domain_name"];

	// clear the extension array so that it is regenerated for the selected domain
	unset($_SESSION['extension_array']);

	// set the setting arrays
	$domain = new domains();
	$domain->set();
}
