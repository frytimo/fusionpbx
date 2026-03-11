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
	Portions created by the Initial Developer are Copyright (C) 2008-2025
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('call_active_view')) {
		echo "access denied";
		exit;
	}
	$has_call_active_all       = permission_exists('call_active_all');
	$has_call_active_eavesdrop = permission_exists('call_active_eavesdrop');
	$has_call_active_hangup    = permission_exists('call_active_hangup');

//add multi-lingual support
	$text = new text()->get();

//get the HTTP values and set as variables
	$show = trim($_REQUEST["show"] ?? '');
	if ($show != "all") { $show = ''; }

//load gateways into a session variable
	$sql = "select gateway_uuid, domain_uuid, gateway from v_gateways where enabled = 'true' ";
	$gateways = $database->select($sql, $parameters ?? null, 'all');
	foreach ($gateways as $row) {
		$_SESSION['gateways'][$row['gateway_uuid']] = $row['gateway'];
	}

//create simple array of users own extensions
	unset($_SESSION['user']['extensions']);
	if (is_array($_SESSION['user']['extension'])) {
		foreach ($_SESSION['user']['extension'] as $assigned_extensions) {
			$_SESSION['user']['extensions'][] = $assigned_extensions['user'];
		}
	}

//create token
	$object = new token;
	$token = $object->create('/app/calls_active/calls_active_inc.php');
	$_SESSION['app']['calls_active']['token']['name'] = $token['name'];
	$_SESSION['app']['calls_active']['token']['hash'] = $token['hash'];

//build JS button strings
	$btn_refresh_active = button::create(['type'=>'button','title'=>$text['label-refresh_pause'],'icon'=>'sync-alt fa-spin','onclick'=>'refresh_stop()']);
	$btn_refresh_paused = button::create(['type'=>'button','title'=>$text['label-refresh_enable'],'icon'=>'pause','onclick'=>'refresh_start()']);

//build the action bar buttons
	$btn_hangup = '';
	if ($has_call_active_hangup && ($rows ?? false)) {
		$btn_hangup = button::create(['type'=>'button','label'=>$text['label-hangup'],'icon'=>'phone-slash','id'=>'btn_delete','onclick'=>"refresh_stop(); modal_open('modal-hangup','btn_hangup');"]);
	}
	$btn_show_all = '';
	$btn_back = '';
	if ($has_call_active_all) {
		if ($show == 'all') {
			$btn_back = button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$theme_button_icon_back,'link'=>'calls_active.php','onmouseover'=>'refresh_stop()','onmouseout'=>'refresh_start()']);
		} else {
			$btn_show_all = button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$theme_button_icon_all,'link'=>'calls_active.php?show=all','onmouseover'=>'refresh_stop()','onmouseout'=>'refresh_start()']);
		}
	}

//build the modals
	$modal_hangup = '';
	if ($has_call_active_hangup && ($rows ?? false)) {
		$modal_hangup = modal::create(['id'=>'modal-hangup','type'=>'general','message'=>$text['confirm-hangups'],'actions'=>button::create(['type'=>'button','label'=>$text['label-hangup'],'icon'=>'check','id'=>'btn_hangup','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('hangup'); list_form_submit('form_list');"])]);
	}

//build the eavesdrop controls
	$eavesdrop_controls = '';
	if ($has_call_active_eavesdrop && !empty($user['extensions'])) {
		if (sizeof($user['extensions']) > 1) {
			$eavesdrop_controls .= "<input type='hidden' id='eavesdrop_dest' value=\"".((empty($_REQUEST['eavesdrop_dest'])) ? $user['extension'][0]['destination'] : escape($_REQUEST['eavesdrop_dest']))."\">\n";
			$eavesdrop_controls .= "<i class='fas fa-headphones' style='margin-left: 15px; cursor: help;' title='".escape($text['description-eavesdrop_destination'])."' align='absmiddle'></i>\n";
			$eavesdrop_controls .= "<select class='formfld' style='margin-right: 5px;' align='absmiddle' onchange=\"document.getElementById('eavesdrop_dest').value = this.options[this.selectedIndex].value; refresh_start();\" onfocus='refresh_stop();'>\n";
			if (is_array($user['extensions'])) {
				foreach ($user['extensions'] as $user_extension) {
					$eavesdrop_controls .= "<option value='".escape($user_extension)."' ".(($_REQUEST['eavesdrop_dest'] == $user_extension) ? 'selected' : null).">".escape($user_extension)."</option>\n";
				}
			}
			$eavesdrop_controls .= "</select>\n";
		} else if (sizeof($user['extensions']) == 1) {
			$eavesdrop_controls .= "<input type='hidden' id='eavesdrop_dest' value=\"".escape($user['extension'][0]['destination'])."\">\n";
		}
	}

//build the template
	$template = new template();
	$template->engine = 'smarty';
	$template->template_dir = __DIR__.'/resources/views';
	$template->cache_dir = sys_get_temp_dir();
	$template->init();

//assign the template variables
	$template->assign('text',                  $text);
	$template->assign('num_rows',              $num_rows ?? 0);
	$template->assign('show',                  $show);
	$template->assign('token',                 $token);
	$template->assign('has_call_active_all',       $has_call_active_all);
	$template->assign('has_call_active_eavesdrop', $has_call_active_eavesdrop);
	$template->assign('has_call_active_hangup',    $has_call_active_hangup);
	$template->assign('rows',                  $rows ?? false);
	$template->assign('btn_refresh_active',    $btn_refresh_active);
	$template->assign('btn_refresh_paused',    $btn_refresh_paused);
	$template->assign('btn_hangup',            $btn_hangup);
	$template->assign('btn_show_all',          $btn_show_all);
	$template->assign('btn_back',              $btn_back);
	$template->assign('modal_hangup',          $modal_hangup);
	$template->assign('eavesdrop_controls',    $eavesdrop_controls);
	$template->assign('debug',                 isset($_REQUEST['debug']));
	$template->assign('request_uri',           $_SERVER['REQUEST_URI']);

//invoke pre-render hook
	app::dispatch_list_pre_render('call_active_list_page_hook', $url, $template);

//include the header
	$document['title'] = $text['title'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('calls_active_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('call_active_list_page_hook', $url, $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";
