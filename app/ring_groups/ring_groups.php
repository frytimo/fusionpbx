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
	Portions created by the Initial Developer are Copyright (C) 2010-2025
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
	James Rose <james.o.rose@gmail.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (!permission_exists('ring_group_view')) {
		echo "access denied";
		exit;
	}
	$has_domain_select     = permission_exists('domain_select');
	$has_ring_group_add    = permission_exists('ring_group_add');
	$has_ring_group_all    = permission_exists('ring_group_all');
	$has_ring_group_delete = permission_exists('ring_group_delete');
	$has_ring_group_domain = permission_exists('ring_group_domain');
	$has_ring_group_edit   = permission_exists('ring_group_edit');

//add multi-lingual support
	$text = new text()->get();
	//create the url object
	$url = new url();

//set additional variables
	$show = $_GET["show"] ?? '';

//set the defaults
	$search = '';

//get posted data
	if (!empty($_POST['ring_groups'])) {
		$action = $_POST['action'];
		$search = $_POST['search'] ?? '';
		$ring_groups = $_POST['ring_groups'];
	}

//get total ring group count from the database, check limit, if defined
	if (!empty($action) && $action == 'copy' && $settings->get('limit', 'ring_groups', '') ?? '') {
		$sql = "select count(ring_group_uuid) from v_ring_groups ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $domain_uuid;
		$total_ring_groups = $database->select($sql, $parameters, 'column');
		unset($sql, $parameters);

		if (is_numeric($settings->get('limit', 'ring_groups', '')) && $total_ring_groups >= $settings->get('limit', 'ring_groups', '')) {
			message::add($text['message-maximum_ring_groups'].' '.$settings->get('limit', 'ring_groups', ''), 'negative');
			header('Location: ring_groups.php');
			exit;
		}
	}

//process the http post data by action
	if (!empty($action) && !empty($ring_groups)) {
		//dispatch pre-action hook
		app::dispatch_list_pre_action('ring_group_list_page_hook', $url, $action, $ring_groups);

		switch ($action) {
			case 'copy':
				$obj = new ring_groups;
				$obj->copy($ring_groups);
				break;
			case 'toggle':
				$obj = new ring_groups;
				$obj->toggle($ring_groups);
				break;
			case 'delete':
				$obj = new ring_groups;
				$obj->delete($ring_groups);
				break;
		}

			//dispatch post-action hook
			app::dispatch_list_post_action('ring_group_list_page_hook', $url, $action, $ring_groups);

		header('Location: ring_groups.php'.($search != '' ? '?search='.urlencode($search) : ''));
		exit;
	}

//dispatch pre-query hook
	$query_parameters = [];
	app::dispatch_list_pre_query('ring_group_list_page_hook', $url, $query_parameters);

//get order and order by
	$order_by = $_GET["order_by"] ?? 'ring_group_name';
	$order = $_GET["order"] ?? 'asc';
	$sort = $order_by == 'ring_group_extension' ? 'natural' : null;

//add the search term
	if (isset($_GET["search"])) {
		$search = strtolower($_GET["search"]);
	}

//get total domain ring group count
	$sql = "select count(ring_group_uuid) from v_ring_groups ";
	$sql .= "where domain_uuid = :domain_uuid ";
	$parameters['domain_uuid'] = $domain_uuid;
	$total_ring_groups = $database->select($sql, $parameters, 'column');
	unset($parameters);

//get filtered ring group count
	if ($show == "all" && $has_ring_group_all) {
		$sql = "select count(ring_group_uuid) from v_ring_groups ";
		$sql .= "where true ";
	}
	elseif ($has_ring_group_domain || $has_ring_group_all) {
		$sql = "select count(ring_group_uuid)  from v_ring_groups ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $domain_uuid;
	}
	else {
		$sql = "select count(ring_group_uuid) ";
		$sql .= "from v_ring_groups as r, v_ring_group_users as u ";
		$sql .= "where r.domain_uuid = :domain_uuid ";
		$sql .= "and r.ring_group_uuid = u.ring_group_uuid ";
		$sql .= "and u.user_uuid = :user_uuid ";
		$parameters['domain_uuid'] = $domain_uuid;
		$parameters['user_uuid'] = $_SESSION['user_uuid'];
	}
	if (!empty($search)) {
		$sql .= "and (";
		$sql .= "lower(ring_group_name) like :search ";
		$sql .= "or lower(ring_group_extension) like :search ";
		$sql .= "or lower(ring_group_description) like :search ";
		$sql .= "or lower(ring_group_strategy) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$num_rows = $database->select($sql, $parameters, 'column');
	unset($sql, $parameters);

//prepare to page the results
	$rows_per_page = $settings->get('domain', 'paging', 50);
	$param = $search ? "&search=".$search : null;
	$param = ($show == "all" && $has_ring_group_all) ? "&show=all" : null;
	$page = isset($_GET['page']) ? $_GET['page'] : 0;
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the list
	if ($show == "all" && $has_ring_group_all) {
		$sql = "select r.ring_group_uuid, r.domain_uuid, r.ring_group_name, r.ring_group_extension, r.ring_group_strategy, ";
		$sql .= "r.ring_group_forward_destination, r.ring_group_forward_enabled, cast(r.ring_group_enabled as text), r.ring_group_description ";
		$sql .= "from v_ring_groups as r ";
		$sql .= "where true ";
	}
	else if ($has_ring_group_domain || $has_ring_group_all) {
		$sql = "select r.ring_group_uuid, r.domain_uuid, r.ring_group_name, r.ring_group_extension, r.ring_group_strategy, ";
		$sql .= "r.ring_group_forward_destination, r.ring_group_forward_enabled, cast(r.ring_group_enabled as text), r.ring_group_description ";
		$sql .= "from v_ring_groups as r ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $domain_uuid;
	}
	else {
		$sql = "select r.ring_group_uuid, r.domain_uuid, r.ring_group_name, r.ring_group_extension, r.ring_group_strategy, ";
		$sql .= "r.ring_group_forward_destination, r.ring_group_forward_enabled, cast(r.ring_group_enabled as text), r.ring_group_description ";
		$sql .= "from v_ring_groups as r, v_ring_group_users as u ";
		$sql .= "where r.domain_uuid = :domain_uuid ";
		$sql .= "and r.ring_group_uuid = u.ring_group_uuid ";
		$sql .= "and u.user_uuid = :user_uuid ";
		$parameters['domain_uuid'] = $domain_uuid;
		$parameters['user_uuid'] = $_SESSION['user_uuid'];
	}
	if (!empty($search)) {
		$sql .= "and (";
		$sql .= "lower(ring_group_name) like :search ";
		$sql .= "or lower(ring_group_extension) like :search ";
		$sql .= "or lower(ring_group_description) like :search ";
		$sql .= "or lower(ring_group_strategy) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$sql .= order_by($order_by, $order, null, null, $sort);
	$sql .= limit_offset($rows_per_page, $offset);
	$ring_groups = $database->select($sql, $parameters, 'all');
	//dispatch post-query hook
	app::dispatch_list_post_query('ring_group_list_page_hook', $url, $ring_groups);
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);

//build the action bar buttons
	$btn_add = '';
	if ($has_ring_group_add) {
		$btn_add = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','link'=>'ring_group_edit.php']);
	}
	$btn_copy = '';
	if ($has_ring_group_add && $ring_groups) {
		$btn_copy = button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$settings->get('theme', 'button_icon_copy'),'id'=>'btn_copy','name'=>'btn_copy','style'=>'display: none;','onclick'=>"modal_open('modal-copy','btn_copy');"]);
	}
	$btn_toggle = '';
	if ($has_ring_group_edit && $ring_groups) {
		$btn_toggle = button::create(['type'=>'button','label'=>$text['button-toggle'],'icon'=>$settings->get('theme', 'button_icon_toggle'),'id'=>'btn_toggle','name'=>'btn_toggle','style'=>'display: none;','onclick'=>"modal_open('modal-toggle','btn_toggle');"]);
	}
	$btn_delete = '';
	if ($has_ring_group_delete && $ring_groups) {
		$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	$btn_show_all = '';
	if ($has_ring_group_all && $show !== 'all') {
		$btn_show_all = button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$settings->get('theme', 'button_icon_all'),'link'=>'?show=all']);
	}
	$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);

//build the modals
	$modal_copy = '';
	if ($has_ring_group_add && $ring_groups) {
		$modal_copy = modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
	}
	$modal_toggle = '';
	if ($has_ring_group_edit && $ring_groups) {
		$modal_toggle = modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
	}
	$modal_delete = '';
	if ($has_ring_group_delete && $ring_groups) {
		$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

//build the table header columns
	$th_domain_name = '';
	if ($show == 'all' && $has_ring_group_all) {
		$th_domain_name = th_order_by('domain_name', $text['label-domain'], $order_by, $order);
	}
	$th_ring_group_name        = th_order_by('ring_group_name', $text['label-name'], $order_by, $order);
	$th_ring_group_extension   = th_order_by('ring_group_extension', $text['label-extension'], $order_by, $order);
	$th_ring_group_strategy    = th_order_by('ring_group_strategy', $text['label-strategy'], $order_by, $order);
	$th_ring_group_forward     = th_order_by('ring_group_forward_enabled', $text['label-forwarding'], $order_by, $order);
	$th_ring_group_enabled     = th_order_by('ring_group_enabled', $text['label-enabled'], $order_by, $order, null, "class='center'");
	$th_ring_group_description = th_order_by('ring_group_description', $text['header-description'], $order_by, $order, null, "class='hide-sm-dn'");

//build the row data
	$x = 0;
	foreach ($ring_groups as &$row) {
		app::dispatch_list_render_row('ring_group_list_page_hook', $url, $row, $x);
		$list_row_url = '';
		if ($has_ring_group_edit) {
			$list_row_url = "ring_group_edit.php?id=".urlencode($row['ring_group_uuid']);
			if ($row['domain_uuid'] != $_SESSION['domain_uuid'] && $has_domain_select) {
				$list_row_url .= '&domain_uuid='.urlencode($row['domain_uuid']).'&domain_change=true';
			}
		}
		$row['_list_row_url']       = $list_row_url;
		$row['_enabled_label']      = $text['label-'.$row['ring_group_enabled']];
		$row['_strategy_label']     = $text['option-'.$row['ring_group_strategy']] ?? '';
		$row['_forward_display']    = ($row['ring_group_forward_enabled'] === true && !empty($row['ring_group_forward_destination'])) ? format_phone($row['ring_group_forward_destination']) : '';
		$row['_domain_name']        = $_SESSION['domains'][$row['domain_uuid']]['domain_name'] ?? '';
		$row['_toggle_button']      = '';
		if ($has_ring_group_edit) {
			$row['_toggle_button'] = button::create(['type'=>'submit','class'=>'link','label'=>$text['label-'.$row['ring_group_enabled']],'title'=>$text['button-toggle'],'onclick'=>"list_self_check('checkbox_{$x}'); list_action_set('toggle'); list_form_submit('form_list')"]);
		}
		$row['_edit_button'] = '';
		if ($has_ring_group_edit && $list_row_edit_button) {
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
	$template->assign('ring_groups',               $ring_groups ?? []);
	$template->assign('search',                    $search);
	$template->assign('show',                      $show);
	$template->assign('paging_controls',           $paging_controls);
	$template->assign('paging_controls_mini',      $paging_controls_mini);
	$template->assign('token',                     $token);
	$template->assign('has_ring_group_add',        $has_ring_group_add);
	$template->assign('has_ring_group_all',        $has_ring_group_all);
	$template->assign('has_ring_group_delete',     $has_ring_group_delete);
	$template->assign('has_ring_group_edit',       $has_ring_group_edit);
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
	$template->assign('th_ring_group_name',        $th_ring_group_name);
	$template->assign('th_ring_group_extension',   $th_ring_group_extension);
	$template->assign('th_ring_group_strategy',    $th_ring_group_strategy);
	$template->assign('th_ring_group_forward',     $th_ring_group_forward);
	$template->assign('th_ring_group_enabled',     $th_ring_group_enabled);
	$template->assign('th_ring_group_description', $th_ring_group_description);

//invoke pre-render hook
	app::dispatch_list_pre_render('ring_group_list_page_hook', $url, $template);

//include the header
	$document['title'] = $text['title-ring_groups'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('ring_groups_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('ring_group_list_page_hook', $url, $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";
