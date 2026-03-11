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
	if (!permission_exists('time_condition_view')) {
		echo "access denied";
		exit;
	}
	$has_domain_select          = permission_exists('domain_select');
	$has_time_condition_add     = permission_exists('time_condition_add');
	$has_time_condition_all     = permission_exists('time_condition_all');
	$has_time_condition_context = permission_exists('time_condition_context');
	$has_time_condition_delete  = permission_exists('time_condition_delete');
	$has_time_condition_edit    = permission_exists('time_condition_edit');

//add multi-lingual support
	$text = new text()->get();
	//create the url object
	$url = new url();

//get the http post data
	if (!empty($_POST['time_conditions']) && is_array($_POST['time_conditions'])) {
		$action = $_POST['action'];
		$search = $_POST['search'] ?? '';
		$time_conditions = $_POST['time_conditions'];
	}

//process the http post data by action
	if (!empty($action) && !empty($time_conditions) && is_array($time_conditions) && @sizeof($time_conditions) != 0) {
		//dispatch pre-action hook
		app::dispatch_list_pre_action('time_condition_list_page_hook', $url, $action, $time_conditions);

		switch ($action) {
			case 'copy':
				if ($has_time_condition_add) {
					$obj = new time_conditions;
					$obj->copy($time_conditions);
				}
				break;
			case 'toggle':
				if ($has_time_condition_edit) {
					$obj = new time_conditions;
					$obj->toggle($time_conditions);
				}
				break;
			case 'delete':
				if ($has_time_condition_delete) {
					$obj = new time_conditions;
					$obj->delete($time_conditions);
				}
				break;
		}

		//dispatch post-action hook
		app::dispatch_list_post_action('time_condition_list_page_hook', $url, $action, $time_conditions);

		header('Location: time_conditions.php'.($search != '' ? '?search='.urlencode($search) : ''));
		exit;
	}

//dispatch pre-query hook
	$query_parameters = [];
	app::dispatch_list_pre_query('time_condition_list_page_hook', $url, $query_parameters);

//get order and order by
	$order_by = $_GET["order_by"] ?? 'dialplan_name';
	$order = $_GET["order"] ?? 'asc';
	$sort = $order_by == 'dialplan_number' ? 'natural' : null;

//add the search variable
	$search = $_GET["search"] ?? '';
	$show = $_GET["show"] ?? '';

//get the number of rows in the dialplan
	$sql = "select count(dialplan_uuid) from v_dialplans ";
	if ($show == "all" && $has_time_condition_all) {
		$sql .= "where true ";
	}
	else {
		$sql .= "where (domain_uuid = :domain_uuid or domain_uuid is null) ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	if (!empty($search)) {
		$search = strtolower($search);
		$sql .= "and (";
		$sql .= " 	lower(dialplan_context) like :search ";
		$sql .= " 	or lower(dialplan_name) like :search ";
		$sql .= " 	or lower(dialplan_number) like :search ";
		$sql .= " 	or lower(dialplan_description) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$sql .= "and app_uuid = '4b821450-926b-175a-af93-a03c441818b1' ";
	$sql .= $sql_search ?? null;
	$num_rows = $database->select($sql, $parameters ?? null, 'column');

//prepare to page data
	$rows_per_page = $settings->get('domain', 'paging', 50);
	$param = $search ? "&search=".urlencode($search) : null;
	if (!empty($_GET['show']) && $_GET['show'] == "all" && $has_time_condition_all) {
		$param .= "&show=all";
	}
	$page = !empty($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 0;
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the data
	$sql = str_replace('count(dialplan_uuid)', '*', $sql);
	$sql .= order_by($order_by, $order, null, null, $sort);
	$sql .= limit_offset($rows_per_page, $offset);
	$dialplans = $database->select($sql, $parameters ?? null, 'all');
	//dispatch post-query hook
	app::dispatch_list_post_query('time_condition_list_page_hook', $url, $dialplans);
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//set from session variables
$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);

//build the action bar buttons
$time_conditions = $dialplans ?? [];
$btn_add = '';
if ($has_time_condition_add) {
$btn_add = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','link'=>'time_condition_edit.php']);
}
$btn_copy = '';
if ($has_time_condition_add && $time_conditions) {
$btn_copy = button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$settings->get('theme', 'button_icon_copy'),'id'=>'btn_copy','name'=>'btn_copy','style'=>'display: none;','onclick'=>"modal_open('modal-copy','btn_copy');"]);
}
$btn_toggle = '';
if ($has_time_condition_edit && $time_conditions) {
$btn_toggle = button::create(['type'=>'button','label'=>$text['button-toggle'],'icon'=>$settings->get('theme', 'button_icon_toggle'),'id'=>'btn_toggle','name'=>'btn_toggle','style'=>'display: none;','onclick'=>"modal_open('modal-toggle','btn_toggle');"]);
}
$btn_delete = '';
if ($has_time_condition_delete && $time_conditions) {
$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
}
$btn_show_all = '';
if ($has_time_condition_all && $show !== 'all') {
$btn_show_all = button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$settings->get('theme', 'button_icon_all'),'link'=>'?type=&show=all'.($search != '' ? "&search=".urlencode($search) : null)]);
}
$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);

//build the modals
$modal_copy = '';
if ($has_time_condition_add && $time_conditions) {
$modal_copy = modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
}
$modal_toggle = '';
if ($has_time_condition_edit && $time_conditions) {
$modal_toggle = modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
}
$modal_delete = '';
if ($has_time_condition_delete && $time_conditions) {
$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','name'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
}

//build the table header columns
$th_domain_name = '';
if ($show == 'all' && $has_time_condition_all) {
$th_domain_name = th_order_by('domain_name', $text['label-domain'], $order_by, $order, $param, "class='shrink'");
}
$th_dialplan_name        = th_order_by('dialplan_name', $text['label-name'], $order_by, $order, null, null, ($search != '' ? "search=".$search : null));
$th_dialplan_number      = th_order_by('dialplan_number', $text['label-number'], $order_by, $order, null, null, ($search != '' ? "search=".$search : null));
$th_dialplan_context     = '';
if ($has_time_condition_context) {
$th_dialplan_context = th_order_by('dialplan_context', $text['label-context'], $order_by, $order, null, null, ($search != '' ? "search=".$search : null));
}
$th_dialplan_order       = th_order_by('dialplan_order', $text['label-order'], $order_by, $order, null, "class='center'", ($search != '' ? "search=".$search : null));
$th_dialplan_enabled     = th_order_by('dialplan_enabled', $text['label-enabled'], $order_by, $order, null, "class='center'", ($search != '' ? "search=".$search : null));
$th_dialplan_description = th_order_by('dialplan_description', $text['label-description'], $order_by, $order, null, "class='hide-sm-dn'", ($search != '' ? "search=".$search : null));

//build the row data
$x = 0;
foreach ($time_conditions as &$row) {
app::dispatch_list_render_row('time_condition_list_page_hook', $url, $row, $x);
$list_row_url = '';
if ($has_time_condition_edit) {
$list_row_url = "time_condition_edit.php?id=".urlencode($row['dialplan_uuid']);
if ($row['domain_uuid'] != $_SESSION['domain_uuid'] && $has_domain_select) {
$list_row_url .= '&domain_uuid='.urlencode($row['domain_uuid']).'&domain_change=true';
}
}
$row['_list_row_url']  = $list_row_url;
$row['_enabled_label'] = $text['label-'.($row['dialplan_enabled'] ? 'true' : 'false')];
$row['_domain_name']   = !empty($_SESSION['domains'][$row['domain_uuid']]['domain_name']) ? $_SESSION['domains'][$row['domain_uuid']]['domain_name'] : $text['label-global'];
$row['_toggle_button'] = '';
if ($has_time_condition_edit) {
$row['_toggle_button'] = button::create(['type'=>'submit','class'=>'link','label'=>$text['label-'.($row['dialplan_enabled'] ? 'true' : 'false')],'title'=>$text['button-toggle'],'onclick'=>"list_self_check('checkbox_{$x}'); list_action_set('toggle'); list_form_submit('form_list')"]);
}
$row['_edit_button'] = '';
if ($has_time_condition_edit && $list_row_edit_button) {
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
$template->assign('time_conditions',           $time_conditions ?? []);
$template->assign('search',                    $search);
$template->assign('show',                      $show);
$template->assign('paging_controls',           $paging_controls);
$template->assign('paging_controls_mini',      $paging_controls_mini);
$template->assign('token',                     $token);
$template->assign('has_time_condition_add',    $has_time_condition_add);
$template->assign('has_time_condition_all',    $has_time_condition_all);
$template->assign('has_time_condition_context',$has_time_condition_context);
$template->assign('has_time_condition_delete', $has_time_condition_delete);
$template->assign('has_time_condition_edit',   $has_time_condition_edit);
$template->assign('list_row_edit_button',      $list_row_edit_button);
$template->assign('btn_add',                   $btn_add);
$template->assign('btn_copy',                  $btn_copy);
$template->assign('btn_toggle',                $btn_toggle);
$template->assign('btn_delete',                $btn_delete);
$template->assign('btn_show_all',              $btn_show_all);
$template->assign('btn_search',                $btn_search);
$template->assign('modal_copy',                $modal_copy);
$template->assign('modal_toggle',              $modal_toggle);
$template->assign('modal_delete',              $modal_delete);
$template->assign('th_domain_name',            $th_domain_name);
$template->assign('th_dialplan_name',          $th_dialplan_name);
$template->assign('th_dialplan_number',        $th_dialplan_number);
$template->assign('th_dialplan_context',       $th_dialplan_context);
$template->assign('th_dialplan_order',         $th_dialplan_order);
$template->assign('th_dialplan_enabled',       $th_dialplan_enabled);
$template->assign('th_dialplan_description',   $th_dialplan_description);

//invoke pre-render hook
app::dispatch_list_pre_render('time_condition_list_page_hook', $url, $template);

//include the header
$document['title'] = $text['title-time_conditions'];
require_once "resources/header.php";

//render the template
$html = $template->render('time_conditions_list.tpl');

//invoke post-render hook
app::dispatch_list_post_render('time_condition_list_page_hook', $url, $html);
echo $html;

//include the footer
require_once "resources/footer.php";
