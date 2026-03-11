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
	require_once "resources/paging.php";

//check permissions
	if (!permission_exists('sip_profile_view')) {
		echo "access denied";
		exit;
	}
	$has_sip_profile_add           = permission_exists('sip_profile_add');
	$has_sip_profile_delete        = permission_exists('sip_profile_delete');
	$has_sip_profile_edit          = permission_exists('sip_profile_edit');
	$has_sofia_global_setting_view = permission_exists('sofia_global_setting_view');

//add multi-lingual support
	$text = new text()->get();
	//create the url object
	$url = new url();

//define the variables
	$action = '';
	$search = '';
	$sip_profiles = '';

//get the http post data
	if (!empty($_POST['sip_profiles'])) {
		$action = $_POST['action'];
		$search = $_POST['search'] ?? '';
		$sip_profiles = $_POST['sip_profiles'];
	}

//process the http post data by action
	if (!empty($action) && !empty($sip_profiles) && @sizeof($sip_profiles) != 0) {
		//dispatch pre-action hook
		app::dispatch_list_pre_action('sip_profile_list_page_hook', $url, $action, $sip_profiles);

		switch ($action) {
			case 'toggle':
				if ($has_sip_profile_edit) {
					$obj = new sip_profiles;
					$obj->toggle($sip_profiles);
				}
				break;
			case 'delete':
				if ($has_sip_profile_delete) {
					$obj = new sip_profiles;
					$obj->delete($sip_profiles);
				}
				break;
		}

		//dispatch post-action hook
		app::dispatch_list_post_action('sip_profile_list_page_hook', $url, $action, $sip_profiles);

		header('Location: sip_profiles.php'.(!empty($search) ? '?search='.urlencode($search) : ''));
		exit;
	}

//dispatch pre-query hook
	$query_parameters = [];
	app::dispatch_list_pre_query('sip_profile_list_page_hook', $url, $query_parameters);

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);

//get order and order by
	if (isset($_GET["order_by"])) {
		$order_by = $_GET["order_by"];
	}
	else {
		$order_by = 'sip_profile_name';
	}
	$order = $_GET["order"] ?? '';

//prepare to page the results
	$sql = "select count(sip_profile_uuid) ";
	$sql .= "from v_sip_profiles ";
	if (!empty($_GET["search"])) {
		$search = strtolower($_GET["search"]);
		$sql .= "where (";
		$sql .= "lower(sip_profile_name) like :search ";
		$sql .= "or lower(sip_profile_hostname) like :search ";
		$sql .= "or lower(sip_profile_description) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$num_rows = $database->select($sql, $parameters ?? null, 'column');

//prepare to page the results
	$rows_per_page = $settings->get('domain', 'paging', 50);
	$param = $search ? "&search=".$search : null;
	$page = !empty($_GET['page']) ? $_GET['page'] : 0;
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the list
	$sql = str_replace('count(sip_profile_uuid)', 'sip_profile_uuid, sip_profile_name, sip_profile_hostname, cast(sip_profile_enabled as text), sip_profile_description', $sql);
	$sql .= order_by($order_by, $order);
	$sql .= limit_offset($rows_per_page, $offset);
	$sip_profiles = $database->select($sql, $parameters ?? null, 'all');
	//dispatch post-query hook
	app::dispatch_list_post_query('sip_profile_list_page_hook', $url, $sip_profiles);
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//build the action bar buttons
	$btn_add = '';
	if ($has_sip_profile_add) {
		$btn_add = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','link'=>'sip_profile_edit.php']);
	}
	$btn_toggle = '';
	if ($has_sip_profile_edit && $sip_profiles) {
		$btn_toggle = button::create(['type'=>'button','label'=>$text['button-toggle'],'icon'=>$settings->get('theme', 'button_icon_toggle'),'id'=>'btn_toggle','name'=>'btn_toggle','style'=>'display: none;','onclick'=>"modal_open('modal-toggle','btn_toggle');"]);
	}
	$btn_delete = '';
	if ($has_sip_profile_delete && $sip_profiles) {
		$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	$btn_settings = '';
	if ($has_sofia_global_setting_view) {
		$btn_settings = button::create(['type'=>'button','label'=>$text['button-settings'],'icon'=>'code','collapse'=>'hide-xs','link'=>'/app/sofia_global_settings/sofia_global_settings.php']);
	}
	$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);

//build the modals
	$modal_toggle = '';
	if ($has_sip_profile_edit && $sip_profiles) {
		$modal_toggle = modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
	}
	$modal_delete = '';
	if ($has_sip_profile_delete && $sip_profiles) {
		$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

//build the table header columns
	$th_sip_profile_name        = th_order_by('sip_profile_name', $text['label-sip_profile_name'], $order_by, $order);
	$th_sip_profile_hostname    = th_order_by('sip_profile_hostname', $text['label-sip_profile_hostname'], $order_by, $order);
	$th_sip_profile_enabled     = th_order_by('sip_profile_enabled', $text['label-sip_profile_enabled'], $order_by, $order, null, "class='center'");
	$th_sip_profile_description = th_order_by('sip_profile_description', $text['label-sip_profile_description'], $order_by, $order, null, "class='hide-sm-dn pct-70'");

//build the row data
	$x = 0;
	foreach ($sip_profiles as &$row) {
		app::dispatch_list_render_row('sip_profile_list_page_hook', $url, $row, $x);
		$list_row_url = '';
		if ($has_sip_profile_edit) {
			$list_row_url = "sip_profile_edit.php?id=".urlencode($row['sip_profile_uuid']);
		}
		$row['_list_row_url']     = $list_row_url;
		$row['_enabled_label']    = $text['label-'.$row['sip_profile_enabled']];
		$row['_toggle_button']    = '';
		$row['_row_toggle_modal'] = '';
		if ($has_sip_profile_edit) {
			$row['_toggle_button']    = button::create(['type'=>'button','class'=>'link','label'=>$text['label-'.$row['sip_profile_enabled']],'title'=>$text['button-toggle'],'id'=>'btn_toggle_enabled','name'=>'btn_toggle_enabled','onclick'=>"list_self_check('checkbox_{$x}'); modal_open('modal-toggle_enabled','btn_toggle_enabled');"]);
			$row['_row_toggle_modal'] = modal::create(['id'=>'modal-toggle_enabled','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle_enabled','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
		}
		$row['_edit_button'] = '';
		if ($has_sip_profile_edit && $list_row_edit_button) {
			$row['_edit_button'] = button::create(['type'=>'button','title'=>$text['button-edit'],'icon'=>$settings->get('theme', 'button_icon_edit'),'link'=>$list_row_url]);
		}
		$x++;
	}
	unset($row);

//build the template
	$template = new template();
	$template->engine = 'smarty';
	$template->template_dir = __DIR__.'/resources/views';
	$template->cache_dir = sys_get_temp_dir();
	$template->init();

//assign the template variables
	$template->assign('text',                       $text);
	$template->assign('num_rows',                   $num_rows);
	$template->assign('sip_profiles',               $sip_profiles ?? []);
	$template->assign('search',                     $search);
	$template->assign('paging_controls',            $paging_controls);
	$template->assign('paging_controls_mini',       $paging_controls_mini);
	$template->assign('token',                      $token);
	$template->assign('has_sip_profile_add',        $has_sip_profile_add);
	$template->assign('has_sip_profile_delete',     $has_sip_profile_delete);
	$template->assign('has_sip_profile_edit',       $has_sip_profile_edit);
	$template->assign('list_row_edit_button',       $list_row_edit_button);
	$template->assign('btn_add',                    $btn_add);
	$template->assign('btn_toggle',                 $btn_toggle);
	$template->assign('btn_delete',                 $btn_delete);
	$template->assign('btn_settings',               $btn_settings);
	$template->assign('btn_search',                 $btn_search);
	$template->assign('modal_toggle',               $modal_toggle);
	$template->assign('modal_delete',               $modal_delete);
	$template->assign('th_sip_profile_name',        $th_sip_profile_name);
	$template->assign('th_sip_profile_hostname',    $th_sip_profile_hostname);
	$template->assign('th_sip_profile_enabled',     $th_sip_profile_enabled);
	$template->assign('th_sip_profile_description', $th_sip_profile_description);

//invoke pre-render hook
	app::dispatch_list_pre_render('sip_profile_list_page_hook', $url, $template);

//include the header
	$document['title'] = $text['title-sip_profiles'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('sip_profiles_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('sip_profile_list_page_hook', $url, $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";


