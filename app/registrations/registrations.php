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
	Portions created by the Initial Developer are Copyright (C) 2008-2023
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
	James Rose <james.o.rose@gmail.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!(permission_exists('registration_domain') || permission_exists('registration_all'))) {
		echo "access denied";
		exit;
	}
	$has_registration_all    = permission_exists('registration_all');
	$has_registration_reload = permission_exists('registration_reload');

//add multi-lingual support
	$text = new text()->get();

//get common submitted data
	if (!empty($_REQUEST)) {
		$show = $_REQUEST['show'] ?? null;
		$search = $_REQUEST['search'] ?? '';
		$profile = $_REQUEST['profile'] ?? null;
	}

//define query string array
	$qs['show'] = !empty($show) ? "&show=".urlencode($show) : null;
	$qs['search'] = !empty($search) ? "&search=".urlencode($search) : null;
	$qs['profile'] = !empty($profile) ? "&profile=".urlencode($profile) : null;

//get posted data
	if (!empty($_POST) && is_array($_POST['registrations'])) {
		$action = $_POST['action'];
		$registrations = $_POST['registrations'];
	}

//process posted data
	if (!empty($action) && !empty($registrations) && is_array($registrations) && @sizeof($registrations) != 0) {
		$obj = new registrations;

		switch ($action) {
			case 'unregister':
				$obj->unregister($registrations);
				break;

			case 'provision':
				$obj->provision($registrations);
				break;

			case 'reboot':
				$obj->reboot($registrations);
				break;
		}

		header('Location: registrations.php'.($show || $search || $profile ? '?' : null).$qs['show'].$qs['search'].$qs['profile']);
		exit;
	}

//get the registrations
	$obj = new registrations;
	$obj->show = $show ?? null;
	$registrations = $obj->get($profile ?? null);

//order the array
	$order = new array_order();
	$registrations = $order->sort($registrations, 'sip-auth-realm', 'user');

//get registration count
	$num_rows = 0;
	if (is_array($registrations)) {
		foreach ($registrations as $row) {
			$matches = preg_grep("/".($search ?? '')."/i", $row);
			if ($matches != false) {
				$num_rows++;
			}
		}
	}

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//detect page reload via ajax
	$reload = isset($_GET['reload']) && $has_registration_reload ? true : false;

//define location url
	$location = ($reload ? 'registration_reload.php' : 'registrations.php');

//build the action bar buttons
	$btn_refresh = '';
	if (!$reload) {
		$btn_refresh = button::create(['type'=>'button','label'=>$text['button-refresh'],'icon'=>$settings->get('theme', 'button_icon_refresh'),'link'=>$location.(!empty($qs) ? '?'.$qs['show'].$qs['search'].$qs['profile'] : null)]);
	}
	$btn_unregister = '';
	$btn_provision  = '';
	$btn_reboot     = '';
	if ($registrations) {
		$btn_unregister = button::create(['type'=>'button','label'=>$text['button-unregister'],'title'=>$text['button-unregister'],'icon'=>'user-slash','style'=>'margin-left: 15px;','onclick'=>"modal_open('modal-unregister','btn_unregister');"]);
		$btn_provision  = button::create(['type'=>'button','label'=>$text['button-provision'],'title'=>$text['button-provision'],'icon'=>'fax','onclick'=>"modal_open('modal-provision','btn_provision');"]);
		$btn_reboot     = button::create(['type'=>'button','label'=>$text['button-reboot'],'title'=>$text['button-reboot'],'icon'=>'power-off','onclick'=>"modal_open('modal-reboot','btn_reboot');"]);
	}
	$btn_show_all     = '';
	$btn_show_local   = '';
	$btn_all_profiles = '';
	if ($has_registration_all) {
		if (!empty($show) && $show == 'all') {
			$btn_show_local = button::create(['type'=>'button','label'=>$text['button-show_local'],'icon'=>$settings->get('theme', 'button_icon_all'),'link'=>$location.(($qs['search'] || $qs['profile']) ? '?' : null).$qs['search'].$qs['profile']]);
		}
		else {
			$btn_show_all = button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$settings->get('theme', 'button_icon_all'),'link'=>$location.'?show=all'.(!empty($qs) ? $qs['search'].$qs['profile'] : null)]);
		}
		if (!empty($profile)) {
			$btn_all_profiles = button::create(['type'=>'button','label'=>$text['button-all_profiles'],'icon'=>'network-wired','style'=>'margin-left: 15px;','link'=>$location.(!empty($qs) && ($qs['show'] || $qs['search']) ? '?'.$qs['show'].$qs['search'] : null)]);
		}
	}
	$btn_search = '';
	if (!$reload) {
		$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);
	}

//build the modals
	$modal_unregister = '';
	$modal_provision  = '';
	$modal_reboot     = '';
	if ($registrations) {
		$modal_unregister = modal::create(['id'=>'modal-unregister','type'=>'general','message'=>$text['confirm-unregister'],'actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_unregister','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('unregister'); list_form_submit('form_list');"])]);
		$modal_provision  = modal::create(['id'=>'modal-provision','type'=>'general','message'=>$text['confirm-provision'],'actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_provision','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('provision'); list_form_submit('form_list');"])]);
		$modal_reboot     = modal::create(['id'=>'modal-reboot','type'=>'general','message'=>$text['confirm-reboot'],'actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_reboot','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('reboot'); list_form_submit('form_list');"])]);
	}

//filter and decorate the registration rows
	$filtered_registrations = [];
	$x = 0;
	if (is_array($registrations) && @sizeof($registrations) != 0) {
		foreach ($registrations as $orig_row) {
			$matches = preg_grep('/'.($search ?? '').'/i', $orig_row);
			if ($matches != false) {
				$row = $orig_row;
				$_user_parts = explode('@', $row['user']);
				if (isset($_user_parts[1]) && $_user_parts[1] == $_SESSION['domains'][$_SESSION['domain_uuid']]['domain_name']) {
					$row['_user_html'] = "<span class='hide-sm-dn'>".escape($row['user'])."</span><span class='hide-md-up cursor-help' title='".escape($row['user'])."'>".escape($_user_parts[0])."</span>";
				}
				else {
					$row['_user_html'] = escape($row['user']);
				}
				$_status_patterns = ['/(\d{4})-(\d{2})-(\d{2})/', '/(\d{2}):(\d{2}):(\d{2})/', '/unknown/', '/exp\(/', '/\(/', '/\)/', '/\s+/'];
				$row['_status'] = preg_replace($_status_patterns, ' ', $row['status']);
				$_contact_parts = explode('"', $row['contact'] ?? '');
				$row['_contact_display'] = escape($_contact_parts[1] ?? '');
				$row['_lan_ip_url']      = urlencode($row['lan-ip'] ?? '');
				$row['_network_ip_url']  = urlencode($row['network-ip'] ?? '');
				$_row_tools = '';
				if ($settings->get('registrations', 'list_row_button_unregister', false)) {
					$_row_tools .= button::create(['type'=>'submit','title'=>$text['button-unregister'],'icon'=>'user-slash fa-fw','style'=>'margin-left: 2px; margin-right: 0;','onclick'=>"list_self_check('checkbox_{$x}'); list_action_set('unregister'); list_form_submit('form_list')"]);
				}
				if ($settings->get('registrations', 'list_row_button_provision', false)) {
					$_row_tools .= button::create(['type'=>'submit','title'=>$text['button-provision'],'icon'=>'fax fa-fw','style'=>'margin-left: 2px; margin-right: 0;','onclick'=>"list_self_check('checkbox_{$x}'); list_action_set('provision'); list_form_submit('form_list')"]);
				}
				if ($settings->get('registrations', 'list_row_button_reboot', false)) {
					$_row_tools .= button::create(['type'=>'submit','title'=>$text['button-reboot'],'icon'=>'power-off fa-fw','style'=>'margin-left: 2px; margin-right: 0;','onclick'=>"list_self_check('checkbox_{$x}'); list_action_set('reboot'); list_form_submit('form_list')"]);
				}
				$row['_tools_html'] = $_row_tools;
				$row['_index']      = $x;
				$filtered_registrations[] = $row;
				$x++;
			}
		}
	}
	unset($registrations, $row);

//build the template
	$template = new template();
	$template->engine       = 'smarty';
	$template->template_dir = __DIR__.'/resources/views';
	$template->cache_dir    = sys_get_temp_dir();
	$template->init();

//assign the template variables
	$template->assign('text',                 $text);
	$template->assign('num_rows',             $num_rows);
	$template->assign('registrations',        $filtered_registrations);
	$template->assign('search',               $search ?? '');
	$template->assign('show',                 $show ?? '');
	$template->assign('profile',              $profile ?? '');
	$template->assign('reload',               $reload);
	$template->assign('location',             $location);
	$template->assign('paging_controls',      $paging_controls ?? '');
	$template->assign('token',                $token);
	$template->assign('has_registration_all', $has_registration_all);
	$template->assign('btn_refresh',          $btn_refresh);
	$template->assign('btn_unregister',       $btn_unregister);
	$template->assign('btn_provision',        $btn_provision);
	$template->assign('btn_reboot',           $btn_reboot);
	$template->assign('btn_show_all',         $btn_show_all);
	$template->assign('btn_show_local',       $btn_show_local);
	$template->assign('btn_all_profiles',     $btn_all_profiles);
	$template->assign('btn_search',           $btn_search);
	$template->assign('modal_unregister',     $modal_unregister);
	$template->assign('modal_provision',      $modal_provision);
	$template->assign('modal_reboot',         $modal_reboot);

//include the header
	if (!$reload) {
		$document['title'] = $text['header-registrations'];
		require_once "resources/header.php";
	}

//render the template
	$html = $template->render('registrations_list.tpl');
	echo $html;

//include the footer
	if (!$reload) {
		require_once "resources/footer.php";
	}

