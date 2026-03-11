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
	Mark J. Crane <markjcrane@fusionpbx.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (!permission_exists('ivr_menu_view')) {
		echo "access denied";
		exit;
	}
	$has_domain_select   = permission_exists('domain_select');
	$has_ivr_menu_add    = permission_exists('ivr_menu_add');
	$has_ivr_menu_all    = permission_exists('ivr_menu_all');
	$has_ivr_menu_delete = permission_exists('ivr_menu_delete');
	$has_ivr_menu_edit   = permission_exists('ivr_menu_edit');

//add multi-lingual support
	$text = new text()->get();
	//create the url object
	$url = new url();

//define defaults
	$action = '';
	$search = '';
	$ivr_menus = '';

//get posted data
	if (!empty($_POST['ivr_menus'])) {
		$action = $_POST['action'];
		$search = $_POST['search'] ?? '';
		$ivr_menus = $_POST['ivr_menus'];
	}

//get total ivr menu count from the database, check limit, if defined
	if (!empty($settings->get('limit', 'ivr_menus'))) {
		$sql = "select count(*) as num_rows from v_ivr_menus where domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $domain_uuid;
		$total_ivr_menus = $database->select($sql, $parameters, 'column');
		unset($sql, $parameters);

		if ($action == 'copy' && $total_ivr_menus >= $settings->get('limit', 'ivr_menus')) {
			message::add($text['message-maximum_ivr_menus'].' '.$settings->get('limit', 'ivr_menus'), 'negative');
			header('Location: ivr_menus.php');
			exit;
		}
	}

//process the http post data by action
	if (!empty($action) && is_array($ivr_menus) && @sizeof($ivr_menus) != 0) {
		//dispatch pre-action hook
		app::dispatch_list_pre_action('ivr_menu_list_page_hook', $url, $action, $ivr_menus);

		switch ($action) {
			case 'copy':
				if ($has_ivr_menu_add) {
					$obj = new ivr_menu;
					$obj->copy($ivr_menus);
				}
				break;
			case 'toggle':
				if ($has_ivr_menu_edit) {
					$obj = new ivr_menu;
					$obj->toggle($ivr_menus);
				}
				break;
			case 'delete':
				if ($has_ivr_menu_delete) {
					$obj = new ivr_menu;
					$obj->delete($ivr_menus);
				}
				break;
		}

			//dispatch post-action hook
			app::dispatch_list_post_action('ivr_menu_list_page_hook', $url, $action, $ivr_menus);

		header('Location: ivr_menus.php'.(!empty($search) ? '?search='.urlencode($search) : ''));
		exit;
	}

//dispatch pre-query hook
	$query_parameters = [];
	app::dispatch_list_pre_query('ivr_menu_list_page_hook', $url, $query_parameters);

//get order and order by
	$order_by = $_GET["order_by"] ?? '';
	$order = $_GET["order"] ?? '';
	$sort = $order_by == 'ivr_menu_extension' ? 'natural' : null;

//add the search variable
	$search = $_GET["search"] ?? '';
	$show = $_GET["show"] ?? '';

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);

//prepare to page the results
	$sql = "select count(*) from v_ivr_menus ";
	if ($show == "all" && $has_ivr_menu_all) {
		$sql .= "where true ";
	}
	else {
		$sql .= "where (domain_uuid = :domain_uuid or domain_uuid is null) ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	if (!empty($search)) {
		$search = strtolower($search);
		$sql .= "and (";
		$sql .= "	lower(ivr_menu_name) like :search ";
		$sql .= "	or lower(ivr_menu_extension) like :search ";
		$sql .= "	or lower(ivr_menu_description) like :search ";
		$sql .= ")";
		$parameters['search'] = '%'.$search.'%';
	}
	$num_rows = $database->select($sql, $parameters ?? [], 'column');

//prepare to page the results
	$rows_per_page = $settings->get('domain', 'paging', 50);
	$param = "&search=".urlencode($search);
	if ($show == "all" && $has_ivr_menu_all) {
		$param .= "&show=all";
	}
	$page = !empty($_GET['page']) ? $_GET['page'] : 0;
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the list
	$sql = "select ";
	$sql .= "ivr_menu_uuid, ";
	$sql .= "domain_uuid, ";
	$sql .= "ivr_menu_name, ";
	$sql .= "ivr_menu_extension, ";
	$sql .= "cast(ivr_menu_enabled as text), ";
	$sql .= "ivr_menu_description ";
	$sql .= "from v_ivr_menus ";
	if ($show == "all" && $has_ivr_menu_all) {
		$sql .= "where true ";
	}
	else {
		$sql .= "where (domain_uuid = :domain_uuid or domain_uuid is null) ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	if (!empty($search)) {
		$search = strtolower($search);
		$sql .= "and (";
		$sql .= "	lower(ivr_menu_name) like :search ";
		$sql .= "	or lower(ivr_menu_extension) like :search ";
		$sql .= "	or lower(ivr_menu_description) like :search ";
		$sql .= ")";
		$parameters['search'] = '%'.$search.'%';
	}
	$sql .= order_by($order_by, $order, 'ivr_menu_name', 'asc', $sort);
	$sql .= limit_offset($rows_per_page, $offset);
	$ivr_menus = $database->select($sql, $parameters ?? [], 'all');
	//dispatch post-query hook
	app::dispatch_list_post_query('ivr_menu_list_page_hook', $url, $ivr_menus);
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//build the action bar buttons
$btn_add = '';
if ($has_ivr_menu_add) {
$btn_add = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','link'=>'ivr_menu_edit.php']);
}
$btn_copy = '';
if ($has_ivr_menu_add) {
$btn_copy = button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$settings->get('theme', 'button_icon_copy'),'id'=>'btn_copy','name'=>'btn_copy','style'=>'display: none;','onclick'=>"modal_open('modal-copy','btn_copy');"]);
}
$btn_toggle = '';
if ($has_ivr_menu_edit && $ivr_menus) {
$btn_toggle = button::create(['type'=>'button','label'=>$text['button-toggle'],'icon'=>$settings->get('theme', 'button_icon_toggle'),'id'=>'btn_toggle','name'=>'btn_toggle','style'=>'display: none;','onclick'=>"modal_open('modal-toggle','btn_toggle');"]);
}
$btn_delete = '';
if ($has_ivr_menu_delete && $ivr_menus) {
$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
}
$btn_show_all = '';
if ($has_ivr_menu_all && $show !== 'all') {
$btn_show_all = button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$settings->get('theme', 'button_icon_all'),'link'=>'?type=&show=all'.($search != '' ? "&search=".urlencode($search) : null)]);
}
$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);

//build the modals
$modal_copy = '';
if ($has_ivr_menu_add && $ivr_menus) {
$modal_copy = modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
}
$modal_toggle = '';
if ($has_ivr_menu_edit && $ivr_menus) {
$modal_toggle = modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
}
$modal_delete = '';
if ($has_ivr_menu_delete && $ivr_menus) {
$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
}

//build the table header columns
$th_domain_name = '';
if ($show == 'all' && $has_ivr_menu_all) {
$th_domain_name = th_order_by('domain_name', $text['label-domain'], $order_by, $order, $param, "class='shrink'");
}
$th_ivr_menu_name        = th_order_by('ivr_menu_name', $text['label-name'], $order_by, $order);
$th_ivr_menu_extension   = th_order_by('ivr_menu_extension', $text['label-extension'], $order_by, $order);
$th_ivr_menu_enabled     = th_order_by('ivr_menu_enabled', $text['label-enabled'], $order_by, $order, null, "class='center'");
$th_ivr_menu_description = th_order_by('ivr_menu_description', $text['label-description'], $order_by, $order, null, "class='hide-sm-dn'");

//build the row data
$x = 0;
foreach ($ivr_menus as &$row) {
app::dispatch_list_render_row('ivr_menu_list_page_hook', $url, $row, $x);
$list_row_url = '';
if ($has_ivr_menu_edit) {
$list_row_url = "ivr_menu_edit.php?id=".urlencode($row['ivr_menu_uuid']);
if ($row['domain_uuid'] != $_SESSION['domain_uuid'] && $has_domain_select) {
$list_row_url .= '&domain_uuid='.urlencode($row['domain_uuid']).'&domain_change=true';
}
}
$row['_list_row_url']  = $list_row_url;
$row['_enabled_label'] = $text['label-'.$row['ivr_menu_enabled']];
$row['_domain_name']   = !empty($_SESSION['domains'][$row['domain_uuid']]['domain_name']) ? $_SESSION['domains'][$row['domain_uuid']]['domain_name'] : $text['label-global'];
$row['_toggle_button'] = '';
if ($has_ivr_menu_edit) {
$row['_toggle_button'] = button::create(['type'=>'submit','class'=>'link','label'=>$text['label-'.$row['ivr_menu_enabled']],'title'=>$text['button-toggle'],'onclick'=>"list_self_check('checkbox_{$x}'); list_action_set('toggle'); list_form_submit('form_list')"]);
}
$row['_edit_button'] = '';
if ($has_ivr_menu_edit && $list_row_edit_button) {
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
$template->assign('text',                   $text);
$template->assign('num_rows',               $num_rows);
$template->assign('ivr_menus',              $ivr_menus ?? []);
$template->assign('search',                 $search);
$template->assign('show',                   $show);
$template->assign('paging_controls',        $paging_controls);
$template->assign('paging_controls_mini',   $paging_controls_mini);
$template->assign('token',                  $token);
$template->assign('has_ivr_menu_add',       $has_ivr_menu_add);
$template->assign('has_ivr_menu_all',       $has_ivr_menu_all);
$template->assign('has_ivr_menu_delete',    $has_ivr_menu_delete);
$template->assign('has_ivr_menu_edit',      $has_ivr_menu_edit);
$template->assign('list_row_edit_button',   $list_row_edit_button);
$template->assign('btn_add',                $btn_add);
$template->assign('btn_copy',               $btn_copy);
$template->assign('btn_toggle',             $btn_toggle);
$template->assign('btn_delete',             $btn_delete);
$template->assign('btn_show_all',           $btn_show_all);
$template->assign('btn_search',             $btn_search);
$template->assign('modal_copy',             $modal_copy);
$template->assign('modal_toggle',           $modal_toggle);
$template->assign('modal_delete',           $modal_delete);
$template->assign('th_domain_name',         $th_domain_name);
$template->assign('th_ivr_menu_name',       $th_ivr_menu_name);
$template->assign('th_ivr_menu_extension',  $th_ivr_menu_extension);
$template->assign('th_ivr_menu_enabled',    $th_ivr_menu_enabled);
$template->assign('th_ivr_menu_description',$th_ivr_menu_description);

//invoke pre-render hook
app::dispatch_list_pre_render('ivr_menu_list_page_hook', $url, $template);

//include the header
$document['title'] = $text['title-ivr_menus'];
require_once "resources/header.php";

//render the template
$html = $template->render('ivr_menus_list.tpl');

//invoke post-render hook
app::dispatch_list_post_render('ivr_menu_list_page_hook', $url, $html);
echo $html;

//include the footer
require_once "resources/footer.php";
