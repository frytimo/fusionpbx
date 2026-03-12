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
	Portions created by the Initial Developer are Copyright (C) 2008-2019
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes files
	require_once __DIR__ . "/resources/require.php";

//call the logout events before session is destroyed
	$classes = $autoload->get_interface_list('logout_event');
	foreach ($classes as $class) {
		// Call events for OpenID handling
		$class::on_logout_pre_session_destroy($settings);
	}

//use custom logout destination if set otherwise redirect to the index page
	$logout_destination = $settings->get('login', 'logout_destination', PROJECT_PATH.'/');

//clear the remember-me cookie and invalidate the stored token
	if (isset($_COOKIE['remember'])) {
		$cookie_parts = explode(':', $_COOKIE['remember'], 2);
		$selector = $cookie_parts[0] ?? '';
		if (is_uuid($selector)) {
			$p = permissions::new();
			$p->add('user_log_add', 'temp');
			$database->execute(
				"update v_user_logs set remember_selector = null, remember_validator = null where remember_selector = :selector ",
				['selector' => $selector]
			);
			$p->delete('user_log_add', 'temp');
		}
		setcookie('remember', '', time() - 3600, '/');
	}

//destroy session
	session_unset();
	session_destroy();

//call the logout events after session is destroyed
	$classes = $autoload->get_interface_list('logout_event');
	foreach ($classes as $class) {
		$class::on_logout_post_session_destroy($settings);
	}

//redirect the user to the logout page
	header("Location: ".$logout_destination);
	exit;
