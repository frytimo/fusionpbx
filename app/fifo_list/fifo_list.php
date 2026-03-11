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
	Copyright (C) 2010
	All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/
//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('active_queue_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$text = new text()->get();

//build the template
	$template = new template();
	$template->engine = 'smarty';
	$template->template_dir = __DIR__.'/resources/views';
	$template->cache_dir = sys_get_temp_dir();
	$template->init();

//assign the template variables
	$template->assign('text',        $text);
	$template->assign('request_uri', $_SERVER['REQUEST_URI']);

//invoke pre-render hook
	app::dispatch_list_pre_render('active_queue_list_page_hook', $url, $template);

//include the header
	$document['title'] = $text['title-active_queues'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('fifo_list_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('active_queue_list_page_hook', $url, $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";

