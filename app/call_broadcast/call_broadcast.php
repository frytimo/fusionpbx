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
	Portions created by the Initial Developer are Copyright (C) 2008-2024
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (!permission_exists('call_broadcast_view')) {
		echo "access denied";
		exit;
	}
	$has_call_broadcast_add    = permission_exists('call_broadcast_add');
	$has_call_broadcast_all    = permission_exists('call_broadcast_all');
	$has_call_broadcast_delete = permission_exists('call_broadcast_delete');
	$has_call_broadcast_edit   = permission_exists('call_broadcast_edit');
	$has_domain_select         = permission_exists('domain_select');

//add multi-lingual support
	$text = new text()->get();
	//create the url object
	$url = new url();

//set additional variables
	$search = $_GET["search"] ?? '';
	$show = $_GET["show"] ?? '';

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);

//get posted data
	if (!empty($_POST['call_broadcasts'])) {
		$action = $_POST['action'];
		$search = $_POST['search'] ?? '';
		$call_broadcasts = $_POST['call_broadcasts'];
	}

//process the http post data by action
	if (!empty($action) && is_array($call_broadcasts)) {
		//dispatch pre-action hook
		app::dispatch_list_pre_action('call_broadcast_list_page_hook', $url, $action, $call_broadcasts);

		switch ($action) {
			case 'copy':
				if ($has_call_broadcast_add) {
					$obj = new call_broadcast;
					$obj->copy($call_broadcasts);
				}
				break;
			case 'delete':
				if ($has_call_broadcast_delete) {
					$obj = new call_broadcast;
					$obj->delete($call_broadcasts);
				}
				break;
		}

		//dispatch post-action hook
		app::dispatch_list_post_action('call_broadcast_list_page_hook', $url, $action, $call_broadcasts);

		header('Location: call_broadcast.php'.($search != '' ? '?search='.urlencode($search) : ''));
		exit;
	}

//dispatch pre-query hook
	$query_parameters = [];
	app::dispatch_list_pre_query('call_broadcast_list_page_hook', $url, $query_parameters);

//get the http get variables and set them to php variables
	$order_by = $_GET["order_by"] ?? '';
	$order = $_GET["order"] ?? '';

//add the search term
	if (!empty($search)) {
		$search = strtolower($_GET["search"]);
	}

//get the count
	$sql = "select count(*) from v_call_broadcasts ";
	$sql .= "where true ";
	if ($show != "all" || !$has_call_broadcast_all) {
		$sql .= "and (domain_uuid = :domain_uuid or domain_uuid is null) ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	if (!empty($search)) {
		$sql .= "and (";
		$sql .= "	lower(broadcast_name) like :search ";
		$sql .= "	or lower(broadcast_description) like :search ";
		$sql .= "	or lower(broadcast_caller_id_name) like :search ";
		$sql .= "	or lower(broadcast_caller_id_number) like :search ";
		$sql .= "	or lower(broadcast_phone_numbers) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$num_rows = $database->select($sql, $parameters ?? null, 'column');

//prepare the paging
	$param = '';
	$rows_per_page = $settings->get('domain', 'paging', 50);
	if (!empty($search)) {
		$param .= "&search=".urlencode($search);
	}
	if ($show == "all" && $has_call_broadcast_all) {
		$param .= "&show=all";
	}
	$page = $_GET['page'] ?? '';
	if (empty($page)) { $page = 0; $_GET['page'] = 0; }
	list($paging_controls, $rows_per_page) = paging($num_rows, $param ?? null, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param ?? null, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the call broadcasts
	$sql = "select call_broadcast_uuid, domain_uuid, broadcast_name, ";
	$sql .= "broadcast_description, broadcast_start_time, broadcast_timeout, ";
	$sql .= "broadcast_concurrent_limit, recording_uuid, broadcast_caller_id_name, ";
	$sql .= "broadcast_caller_id_number, broadcast_destination_type, broadcast_phone_numbers, ";
	$sql .= "broadcast_avmd, broadcast_destination_data, broadcast_accountcode, broadcast_toll_allow ";
	$sql .= "from v_call_broadcasts ";
	$sql .= "where true ";
	if ($show != "all" || !$has_call_broadcast_all) {
		$sql .= "and (domain_uuid = :domain_uuid or domain_uuid is null) ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	if (!empty($search)) {
		$sql .= "and (";
		$sql .= "	lower(broadcast_name) like :search ";
		$sql .= "	or lower(broadcast_description) like :search ";
		$sql .= "	or lower(broadcast_caller_id_name) like :search ";
		$sql .= "	or lower(broadcast_caller_id_number) like :search ";
		$sql .= "	or lower(broadcast_phone_numbers) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$sql .= order_by($order_by, $order, 'broadcast_name', 'asc');
	$sql .= limit_offset($rows_per_page, $offset);
	$result = $database->select($sql, $parameters ?? null, 'all');
	//dispatch post-query hook
	app::dispatch_list_post_query('call_broadcast_list_page_hook', $url, $result);
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//build the action bar buttons
	$btn_add = '';
	if ($has_call_broadcast_add) {
		$btn_add = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','link'=>'call_broadcast_edit.php']);
	}
	$btn_copy = '';
	if ($has_call_broadcast_add && $result) {
		$btn_copy = button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$settings->get('theme', 'button_icon_copy'),'id'=>'btn_copy','name'=>'btn_copy','style'=>'display: none;','onclick'=>"modal_open('modal-copy','btn_copy');"]);
	}
	$btn_delete = '';
	if ($has_call_broadcast_delete && $result) {
		$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	$btn_show_all = '';
	if ($has_call_broadcast_all && $show !== 'all') {
		$btn_show_all = button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$settings->get('theme', 'button_icon_all'),'link'=>'?type='.urlencode($destination_type ?? '').'&show=all'.(!empty($search) ? "&search=".urlencode($search ?? '') : null)]);
	}
	$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);

//build the modals
	$modal_copy = '';
	if ($has_call_broadcast_add && $result) {
		$modal_copy = modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
	}
	$modal_delete = '';
	if ($has_call_broadcast_delete && $result) {
		$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

//build the table header columns
	$th_domain_name = '';
	if ($show == 'all' && $has_call_broadcast_all) {
		$th_domain_name = th_order_by('domain_name', $text['label-domain'], $order_by, $order, $param, "class='shrink'");
	}
	$th_broadcast_name        = th_order_by('broadcast_name', $text['label-name'], $order_by, $order);
	$th_broadcast_limit       = th_order_by('broadcast_concurrent_limit', $text['label-concurrent-limit'], $order_by, $order);
	$th_broadcast_start_time  = th_order_by('broadcast_start_time', $text['label-start_time'], $order_by, $order);
	$th_broadcast_description = th_order_by('broadcast_description', $text['label-description'], $order_by, $order);

//build the row data
	$x = 0;
	if (!empty($result)) {
		foreach ($result as &$row) {
			app::dispatch_list_render_row('call_broadcast_list_page_hook', $url, $row, $x);
			$list_row_url = '';
			if ($has_call_broadcast_edit) {
				$list_row_url = "call_broadcast_edit.php?id=".urlencode($row['call_broadcast_uuid']);
				if ($row['domain_uuid'] != $_SESSION['domain_uuid'] && $has_domain_select) {
					$list_row_url .= '&domain_uuid='.urlencode($row['domain_uuid']).'&domain_change=true';
				}
			}
			$row['_list_row_url'] = $list_row_url;
			$row['_domain_display'] = '';
			if ($show == 'all' && $has_call_broadcast_all) {
				$row['_domain_display'] = !empty($_SESSION['domains'][$row['domain_uuid']]['domain_name']) ? escape($_SESSION['domains'][$row['domain_uuid']]['domain_name']) : $text['label-global'];
			}
			$broadcast_start_reference = !empty($row['update_date']) ?: !empty($row['insert_date']);
			$row['_start_time_display'] = '';
			if ($row['broadcast_start_time'] && $broadcast_start_reference) {
				$row['_start_time_display'] = escape(date('Y-m-d H:i', strtotime($broadcast_start_reference) + $row['broadcast_start_time']));
			}
			$row['_edit_button'] = '';
			if ($has_call_broadcast_edit && $list_row_edit_button) {
				$row['_edit_button'] = button::create(['type'=>'button','title'=>$text['button-edit'],'icon'=>$settings->get('theme', 'button_icon_edit'),'link'=>$list_row_url]);
			}
			$x++;
		}
		unset($row);
	}

//build the template
	$template = new template();
	$template->engine = 'smarty';
	$template->template_dir = __DIR__.'/resources/views';
	$template->cache_dir = sys_get_temp_dir();
	$template->init();

//assign the template variables
	$template->assign('text',                    $text);
	$template->assign('num_rows',                $num_rows);
	$template->assign('rows',                    $result ?? []);
	$template->assign('search',                  $search);
	$template->assign('show',                    $show);
	$template->assign('paging_controls',         $paging_controls);
	$template->assign('paging_controls_mini',    $paging_controls_mini);
	$template->assign('token',                   $token);
	$template->assign('has_call_broadcast_add',    $has_call_broadcast_add);
	$template->assign('has_call_broadcast_all',    $has_call_broadcast_all);
	$template->assign('has_call_broadcast_delete', $has_call_broadcast_delete);
	$template->assign('has_call_broadcast_edit',   $has_call_broadcast_edit);
	$template->assign('list_row_edit_button',      $list_row_edit_button);
	$template->assign('btn_add',                   $btn_add);
	$template->assign('btn_copy',                  $btn_copy);
	$template->assign('btn_delete',                $btn_delete);
	$template->assign('btn_show_all',              $btn_show_all);
	$template->assign('btn_search',                $btn_search);
	$template->assign('modal_copy',                $modal_copy);
	$template->assign('modal_delete',              $modal_delete);
	$template->assign('th_domain_name',            $th_domain_name);
	$template->assign('th_broadcast_name',         $th_broadcast_name);
	$template->assign('th_broadcast_limit',        $th_broadcast_limit);
	$template->assign('th_broadcast_start_time',   $th_broadcast_start_time);
	$template->assign('th_broadcast_description',  $th_broadcast_description);

//invoke pre-render hook
	app::dispatch_list_pre_render('call_broadcast_list_page_hook', $url, $template);

//include the header
	$document['title'] = $text['title-call_broadcast'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('call_broadcast_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('call_broadcast_list_page_hook', $url, $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";
