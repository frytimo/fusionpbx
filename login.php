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

// initialize a template view
$view = new template(PROJECT_ROOT . '/core/authentication/resources/views/login.tpl');

/** @var url $url */
global $database, $settings, $user, $url;

// get the domain
$domain_name = $url->get_domain_name();

// add multi-lingual support
$text = new text()->get(null, '/core/authentication');

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
