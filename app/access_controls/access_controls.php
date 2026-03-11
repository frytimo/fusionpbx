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
	Portions created by the Initial Developer are Copyright (C) 2018-2025
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('access_control_view')) {
		echo "access denied";
		exit;
	}
	$has_access_control_add    = permission_exists('access_control_add');
	$has_access_control_delete = permission_exists('access_control_delete');
	$has_access_control_edit   = permission_exists('access_control_edit');
	$has_access_control_view   = permission_exists('access_control_view');
	$has_domain_select         = permission_exists('domain_select');

//add multi-lingual support
	$text = new text()->get();
	//create the url object
	$url = new url();

//define variable
	$search = '';

//add the settings object
	$settings = new settings(["domain_uuid" => $_SESSION['domain_uuid'], "user_uuid" => $_SESSION['user_uuid']]);

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', 'false');

//get the http post data
	if (!empty($_POST['access_controls'])) {
		$action = $_POST['action'] ?? '';
		$search = $_POST['search'] ?? '';
		$access_controls = $_POST['access_controls'];
	}

//process the http post data by action
	if (!empty($action) && !empty($access_controls) && count($access_controls) > 0) {
		//dispatch pre-action hook
		app::dispatch_list_pre_action(null, $url, $action, $access_controls);

		switch ($action) {
			case 'copy':
				if ($has_access_control_add) {
					$obj = new access_controls;
					$obj->copy($access_controls);
				}
				break;
			case 'delete':
				if ($has_access_control_delete) {
					$obj = new access_controls;
					$obj->delete($access_controls);
				}
				break;
		}

		//redirect the user
		//dispatch post-action hook
		app::dispatch_list_post_action(null, $url, $action, $access_controls);

		header('Location: access_controls.php'.(!empty($search) ? '?search='.urlencode($search) : ''));
		exit;
	}

//dispatch pre-query hook
	$query_parameters = [];
	app::dispatch_list_pre_query(null, $url, $query_parameters);

//get order and order by
	$order_by = $_GET["order_by"] ?? '';
	$order = $_GET["order"] ?? '';

//add the search
	if (isset($_GET["search"])) {
		$search = strtolower($_GET["search"]);
		$parameters['search'] = '%'.$search.'%';
	}

//get the count
	$sql = "select count(access_control_uuid) ";
	$sql .= "from v_access_controls ";
	if (!empty($search)) {
		$sql .= "where (";
		$sql .= "	lower(access_control_name) like :search ";
		$sql .= "	or lower(access_control_default) like :search ";
		$sql .= "	or lower(access_control_description) like :search ";
		$sql .= ") ";
	}
	$num_rows = $database->select($sql, $parameters ?? null, 'column');

//get the list
	$sql = "select ";
	$sql .= "access_control_uuid, ";
	$sql .= "access_control_name, ";
	$sql .= "access_control_default, ";
	$sql .= "access_control_description ";
	$sql .= "from v_access_controls ";
	if (!empty($search)) {
		$sql .= "where (";
		$sql .= "	lower(access_control_name) like :search ";
		$sql .= "	or lower(access_control_default) like :search ";
		$sql .= "	or lower(access_control_description) like :search ";
		$sql .= ") ";
	}
	$sql .= order_by($order_by, $order, 'access_control_name', 'asc');
	$access_controls = $database->select($sql, $parameters ?? null, 'all');
	//dispatch post-query hook
	app::dispatch_list_post_query(null, $url, $access_controls);
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//build the action bar buttons
	$btn_reload = button::create(['label'=>$text['button-reload'],'icon'=>$settings->get('theme', 'button_icon_reload'),'type'=>'button','id'=>'button_reload','link'=>'access_controls_reload.php'.(!empty($search) ? '?search='.urlencode($search) : ''),'style'=>'margin-right: 15px;']);
	$btn_add = '';
	if ($has_access_control_add) {
		$btn_add = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','name'=>'btn_add','link'=>'access_control_edit.php']);
	}
	$btn_copy = '';
	if ($has_access_control_add && $access_controls) {
		$btn_copy = button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$settings->get('theme', 'button_icon_copy'),'id'=>'btn_copy','name'=>'btn_copy','style'=>'display:none;','onclick'=>"modal_open('modal-copy','btn_copy');"]);
	}
	$btn_delete = '';
	if ($has_access_control_delete && $access_controls) {
		$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display:none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);

//build the modals
	$modal_copy = '';
	if ($has_access_control_add && $access_controls) {
		$modal_copy = modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
	}
	$modal_delete = '';
	if ($has_access_control_delete && $access_controls) {
		$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

//build the table header columns
	$th_access_control_name    = th_order_by('access_control_name', $text['label-access_control_name'], $order_by, $order);
	$th_access_control_default = th_order_by('access_control_default', $text['label-access_control_default'], $order_by, $order);

//build the row data
	$x = 0;
	foreach ($access_controls as &$row) {
		app::dispatch_list_render_row('access_control_list_page_hook', $url, $row, $x);
		$list_row_url = '';
		if ($has_access_control_view) {
			$list_row_url = "access_control_edit.php?id=".urlencode($row['access_control_uuid']);
			if (!empty($row['domain_uuid']) && $row['domain_uuid'] != $_SESSION['domain_uuid'] && $has_domain_select) {
				$list_row_url .= '&domain_uuid='.urlencode($row['domain_uuid']).'&domain_change=true';
			}
		}
		$row['_list_row_url'] = $list_row_url;
		$row['_edit_button'] = '';
		if ($has_access_control_edit && $list_row_edit_button == 'true') {
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
	$template->assign('text',                      $text);
	$template->assign('num_rows',                  $num_rows);
	$template->assign('access_controls',           $access_controls ?? []);
	$template->assign('search',                    $search);
	$template->assign('token',                     $token);
	$template->assign('has_access_control_add',    $has_access_control_add);
	$template->assign('has_access_control_delete', $has_access_control_delete);
	$template->assign('has_access_control_edit',   $has_access_control_edit);
	$template->assign('list_row_edit_button',      $list_row_edit_button);
	$template->assign('btn_reload',                $btn_reload);
	$template->assign('btn_add',                   $btn_add);
	$template->assign('btn_copy',                  $btn_copy);
	$template->assign('btn_delete',                $btn_delete);
	$template->assign('btn_search',                $btn_search);
	$template->assign('modal_copy',                $modal_copy);
	$template->assign('modal_delete',              $modal_delete);
	$template->assign('th_access_control_name',    $th_access_control_name);
	$template->assign('th_access_control_default', $th_access_control_default);

//invoke pre-render hook
	app::dispatch_list_pre_render('access_control_list_page_hook', $url, $template);

//include the header
	$document['title'] = $text['title-access_controls'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('access_controls_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('access_control_list_page_hook', $url, $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";

