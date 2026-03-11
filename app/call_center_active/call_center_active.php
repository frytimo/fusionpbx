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
	Portions created by the Initial Developer are Copyright (C) 2008-2026
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('call_center_active_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$text = new text()->get();

//get the queue_name and set it as a variable
	$queue_name = $_GET['queue_name'];
	$name = $_GET['name'] ?? null;

//get a new session array
	unset($_SESSION['queues']);
	unset($_SESSION['agents']);

//determine refresh rate
	$refresh_default = 1500;
	$refresh = is_numeric($settings->get('call_center', 'refresh')) ? $settings->get('call_center', 'refresh') : $refresh_default;
	if ($refresh >= 0.5 && $refresh <= 120) {
		$refresh = $refresh * 1000;
	} else if ($refresh < 0.5 || ($refresh > 120 && $refresh < 500)) {
		$refresh = $refresh_default;
	}

//get the agent status filter
	$agent_status = $_GET['agent_status'] ?? '';

//build the template
	$template = new template();
	$template->engine = 'smarty';
	$template->template_dir = __DIR__.'/resources/views';
	$template->cache_dir = sys_get_temp_dir();
	$template->init();

//assign the template variables
	$template->assign('text',         $text);
	$template->assign('queue_name',   $queue_name);
	$template->assign('name',         $name);
	$template->assign('agent_status', $agent_status);
	$template->assign('refresh',      $refresh);
	$template->assign('request_uri',  $_SERVER['REQUEST_URI']);

//invoke pre-render hook
	app::dispatch_list_pre_render('call_center_active_list_page_hook', null, $template);

//include the header
	$document['title'] = $text['title-call_center_queue_activity'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('call_center_active_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('call_center_active_list_page_hook', null, $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";
