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
 * Portions created by the Initial Developer are Copyright (C) 2008-2023
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * Mark J. Crane <markjcrane@fusionpbx.com>
 */

// includes files
require_once __DIR__ . "/resources/require.php";

/** @var url $url */
global $database, $settings, $user, $url;

// get the domain
$domain_name = $url->get_domain_name();

// add multi-lingual support
$text = new text()->get(null, '/core/authentication');

// if the post is not empty then process the login
if (!empty($_POST)) {

	// Parse the username, password, and domain name from the POST data and URL
	$parts = user::from_post_and_url($url);
	$username = $parts[0] ?? '';
	$password = $parts[1] ?? '';
	$domain_name = $parts[2] ?? '';

	$domain_uuid = domains::fetch_domain_uuid($database, $domain_name);

	if (!empty($username) && !empty($password) && !empty($domain_uuid)) {
		// Attempt to log in the user
		$user = user::login($database, $domain_uuid, $username, $password);
	} else {
		$user = new user($database);
	}

	if (!$user->is_logged_in()) {
		message::add($text['message-invalid_credentials'], 'negative');
	}
}

// if the user is already authenticated then redirect them to the default page
if ($user->is_authenticated()) {
	$url->redirect(PROJECT_PATH . "/core/dashboard/dashboard.php");
}

// initialize a template view
$view = new template(PROJECT_ROOT . '/core/authentication/resources/views/login.tpl');

//pre-process some settings
$theme_favicon                = $settings->get('theme', 'favicon', PROJECT_PATH . '/themes/default/favicon.ico');
$theme_logo                   = $settings->get('theme', 'logo', PROJECT_PATH . '/themes/default/images/logo_login.png');
$theme_login_type             = $settings->get('theme', 'login_brand_type', 'image');
$theme_login_image            = $settings->get('theme', 'login_brand_image', '');
$theme_login_text             = $settings->get('theme', 'login_brand_text', '');
$theme_login_logo_width       = $settings->get('theme', 'login_logo_width', 'auto; max-width: 300px');
$theme_login_logo_height      = $settings->get('theme', 'login_logo_height', 'auto; max-height: 300px');
$theme_message_delay          = 1000 * (float)$settings->get('theme', 'message_delay', 3000);
$background_videos            = $settings->get('theme', 'background_video', []);
$theme_background_video       = (isset($background_videos[0])) ? $background_videos[0] : '';
$login_domain_name_visible    = $settings->get('login', 'domain_name_visible', false);
$login_domain_name            = $settings->get('login', 'domain_name');
$login_destination            = $settings->get('login', 'destination');
$users_unique                 = $settings->get('users', 'unique', '');
$login_password_reset_enabled = $settings->get('login', 'password_reset_enabled', false);

// add translations
$view->assign("login_title", $text['button-login']);
$view->assign("label_username", $text['label-username']);
$view->assign("label_password", $text['label-password']);
$view->assign("label_domain", $text['label-domain']);
$view->assign("button_login", $text['button-login']);

// assign default values to the template
$view->assign("project_path", PROJECT_PATH);
$view->assign("login_destination_url", $login_destination);
$view->assign("login_domain_name_visible", $login_domain_name_visible);
$view->assign("login_domain_names", $domain_name);
$view->assign("login_password_reset_enabled", $login_password_reset_enabled);
$view->assign("favicon", $theme_favicon);
$view->assign("login_logo_width", $theme_login_logo_width);
$view->assign("login_logo_height", $theme_login_logo_height);
$view->assign("login_logo_source", $theme_logo);
$view->assign("message_delay", $theme_message_delay);
$view->assign("background_video", $theme_background_video);
$view->assign("login_password_description", $text['label-password_description']);
$view->assign("button_cancel", $text['button-cancel']);
$view->assign("button_forgot_password", $text['button-forgot_password']);

// assign openid values to the template
if ($settings->get('open_id', 'enabled', false)) {
	$classes = $settings->get('open_id', 'methods', []);
	$banners = [];
	foreach ($classes as $open_id_class) {
		if (class_exists($open_id_class)) {
			$banners[] = [
				'name' => $open_id_class,
				'image' => $open_id_class::get_banner_image($settings),
				'class' => $open_id_class::get_banner_css_class($settings),
				'url' => '/app/open_id/open_id.php?action=' . $open_id_class,
			];
		}
	}
	if (count($banners) > 0) {
		$view->assign('banners', $banners);
	}
}

// assign user to the template
if (!empty($_SESSION['username'])) {
	$view->assign("username", $_SESSION['username']);
}

// messages
$view->assign('messages', message::html(true, '		'));

// show the view
echo $view;

exit;
