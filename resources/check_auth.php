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

$user = new user($database);

// check the authentication
if (!$user->is_logged_in()) {
	// redirect to the login page
	$url->redirect('/login.php');
	exit;
}
