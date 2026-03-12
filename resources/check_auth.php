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

// includes files
require_once __DIR__ . "/require.php";

// add multi-lingual support
$language = new text;
$text = $language->get(null, 'resources');

// start the session
if (function_exists('session_start')) {
	if (!isset($_SESSION)) {
		session_start();
	}
}

// check the authentication and required session context
if (empty($_SESSION['authorized']) || empty($_SESSION['user_uuid']) || empty($_SESSION['domain_uuid'])) {
	// attempt remember-me cookie re-authentication before redirecting to login
	if (isset($_COOKIE['remember'])) {
		(new authentication())->validate();
	}
	if (empty($_SESSION['authorized']) || empty($_SESSION['user_uuid']) || empty($_SESSION['domain_uuid'])) {
		$url->redirect('/login.php');
		exit;
	}
// set the domains session if not already set
if (!isset($_SESSION['domains'])) {
	$domain = new domains();
	$domain->session();
	$domain->set();
}

}

// build the session server array to validate the session fingerprint
global $conf;
if (!isset($conf['session.validate'])) {
	$conf['session.validate'][] = 'HTTP_USER_AGENT';
} elseif (!is_array($conf['session.validate'])) {
	$conf['session.validate'] = [$conf['session.validate']];
}

$server_array = [];
foreach ($conf['session.validate'] as $name) {
	$server_array[$name] = $_SERVER[$name] ?? '';
}

$calculated_hash = hash('sha256', implode($server_array));

// destroy and redirect when the session fingerprint no longer matches
if (empty($_SESSION['user_hash']) || !hash_equals((string) $_SESSION['user_hash'], $calculated_hash)) {
	error_log(
		"FusionPBX session validation failed for user " . ($_SESSION['user']['username'] ?? 'unknown') .
		" on domain " . ($_SESSION['domain_name'] ?? 'unknown') .
		" using keys [" . implode(',', $conf['session.validate']) . "]"
	);
	session_unset();
	session_destroy();
	$url->redirect('/login.php?login_error=session_validation_failed');
	exit;
}
