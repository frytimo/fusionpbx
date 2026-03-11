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
	Portions created by the Initial Developer are Copyright (C) 2018-2024
	the Initial Developer. All Rights Reserved.
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (!permission_exists('stream_view')) {
		echo "access denied";
		exit;
	}
	$has_domain_select = permission_exists('domain_select');
	$has_stream_add    = permission_exists('stream_add');
	$has_stream_all    = permission_exists('stream_all');
	$has_stream_delete = permission_exists('stream_delete');
	$has_stream_edit   = permission_exists('stream_edit');

//add multi-lingual support
	$text = new text()->get();
	//create the url object
	$url = new url();

//set additional variables
	$show = $_GET["show"] ?? '';

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);

//set the defaults
	$search = '';

//get the http post data
	if (!empty($_POST['streams'])) {
		$action = $_POST['action'];
		$search = $_POST['search'] ?? '';
		$streams = $_POST['streams'];
	}

//process the http post data by action
	if (!empty($action)) {
		//dispatch pre-action hook
		app::dispatch_list_pre_action('stream_list_page_hook', $url, $action, $streams);

		switch ($action) {
			case 'copy':
				if ($has_stream_add) {
					$obj = new streams;
					$obj->copy($streams);
				}
				break;
			case 'toggle':
				if ($has_stream_edit) {
					$obj = new streams;
					$obj->toggle($streams);
				}
				break;
			case 'delete':
				if ($has_stream_delete) {
					$obj = new streams;
					$obj->delete($streams);
				}
				break;
		}

		//dispatch post-action hook
		app::dispatch_list_post_action('stream_list_page_hook', $url, $action, $streams);

		header('Location: streams.php'.(!empty($search) ? '?search='.urlencode($search) : ''));
		exit;
	}

//dispatch pre-query hook
	$query_parameters = [];
	app::dispatch_list_pre_query('stream_list_page_hook', $url, $query_parameters);

//get order and order by
	$order_by = $_GET["order_by"] ?? '';
	$order = $_GET["order"] ?? '';

//add the search term
	if (!empty($_GET["search"])) {
		$search = $_GET["search"];
	}

//prepare to page the results
	$sql = "select count(stream_uuid) from v_streams ";
	$sql .= "where true ";
	if (!empty($search)) {
		$sql .= "and (";
		$sql .= "lower(stream_name) like :search ";
		$sql .= "or lower(stream_location) like :search ";
		$sql .= "or lower(stream_enabled) like :search ";
		$sql .= "or lower(stream_description) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.strtolower($search).'%';
	}
	if ($has_stream_all && $show == "all") {
		//show all
	}
	elseif ($has_stream_all) {
		$sql .= "and (domain_uuid = :domain_uuid or domain_uuid is null) ";
		$parameters['domain_uuid'] = $domain_uuid;
	}
	else {
		$sql .= "and domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $domain_uuid;
	}
	$num_rows = $database->select($sql, $parameters ?? null, 'column');
	unset($parameters);

//prepare to page the results
	$rows_per_page = $settings->get('domain', 'paging', 50);
	$param = "&search=".$search;
	$param = ($show == 'all' && $has_stream_all) ? "&show=all" : null;
	$page = isset($_GET['page']) ? $_GET['page'] : 0;
	if (!empty($page)) { $page = 0; $_GET['page'] = 0; }
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the list
	$sql = "select ";
	$sql .= "stream_uuid, domain_uuid, stream_name, stream_location, ";
	$sql .= "cast(stream_enabled as text), stream_description ";
	$sql .= "from v_streams ";
	$sql .= "where true ";
	if (!empty($search)) {
		$sql .= "and (";
		$sql .= "	lower(stream_name) like :search ";
		$sql .= "	or lower(stream_location) like :search ";
		$sql .= "	or lower(cast(stream_enabled as text)) like :search ";
		$sql .= "	or lower(stream_description) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.strtolower($search).'%';
	}
	if ($has_stream_all && $show == "all") {
		//show all
	}
	elseif ($has_stream_all) {
		$sql .= "and (domain_uuid = :domain_uuid or domain_uuid is null) ";
		$parameters['domain_uuid'] = $domain_uuid;
	}
	else {
		$sql .= "and domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $domain_uuid;
	}
	$sql .= order_by($order_by, $order, 'stream_name', 'asc');
	$sql .= limit_offset($rows_per_page, $offset);
	$streams = $database->select($sql, (!empty($parameters) && @sizeof($parameters) != 0 ? $parameters : null), 'all');

	//dispatch post-query hook
	app::dispatch_list_post_query('stream_list_page_hook', $url, $streams);
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//build the action bar buttons
	$btn_add = '';
	if ($has_stream_add) {
		$btn_add = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','link'=>'stream_edit.php']);
	}
	$btn_copy = '';
	if ($has_stream_add && $streams) {
		$btn_copy = button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$settings->get('theme', 'button_icon_copy'),'id'=>'btn_copy','name'=>'btn_copy','style'=>'display: none;','onclick'=>"modal_open('modal-copy','btn_copy');"]);
	}
	$btn_toggle = '';
	if ($has_stream_edit && $streams) {
		$btn_toggle = button::create(['type'=>'button','label'=>$text['button-toggle'],'icon'=>$settings->get('theme', 'button_icon_toggle'),'id'=>'btn_toggle','name'=>'btn_toggle','style'=>'display: none;','onclick'=>"modal_open('modal-toggle','btn_toggle');"]);
	}
	$btn_delete = '';
	if ($has_stream_delete && $streams) {
		$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	$btn_show_all = '';
	if ($has_stream_all && $show !== 'all') {
		$btn_show_all = button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$settings->get('theme', 'button_icon_all'),'link'=>'?show=all']);
	}
	$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search','style'=>(!empty($search) ? 'display: none;' : null)]);
	$btn_reset  = button::create(['label'=>$text['button-reset'],'icon'=>$settings->get('theme', 'button_icon_reset'),'type'=>'button','id'=>'btn_reset','link'=>'streams.php','style'=>($search == '' ? 'display: none;' : null)]);

//build the modals
	$modal_copy = '';
	if ($has_stream_add && $streams) {
		$modal_copy = modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
	}
	$modal_toggle = '';
	if ($has_stream_edit && $streams) {
		$modal_toggle = modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
	}
	$modal_delete = '';
	if ($has_stream_delete && $streams) {
		$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

//build the table header columns
	$th_domain_name      = '';
	if ($show == 'all' && $has_stream_all) {
		$th_domain_name = th_order_by('domain_name', $text['label-domain'], $order_by, $order);
	}
	$th_stream_name        = th_order_by('stream_name', $text['label-stream_name'], $order_by, $order);
	$th_stream_enabled     = th_order_by('stream_enabled', $text['label-stream_enabled'], $order_by, $order, null, "class='center'");
	$th_stream_description = th_order_by('stream_description', $text['label-stream_description'], $order_by, $order, null, "class='hide-sm-dn'");

//build the row data
	$x = 0;
	foreach ($streams as &$row) {
		app::dispatch_list_render_row('stream_list_page_hook', $url, $row, $x);
		$list_row_url = '';
		if ($has_stream_edit) {
			$list_row_url = "stream_edit.php?id=".urlencode($row['stream_uuid']);
			if (!empty($row['domain_uuid']) && $row['domain_uuid'] != $_SESSION['domain_uuid'] && $has_domain_select) {
				$list_row_url .= '&domain_uuid='.urlencode($row['domain_uuid']).'&domain_change=true';
			}
		}
		$row['_list_row_url']  = $list_row_url;
		$row['_enabled_label'] = $text['label-'.$row['stream_enabled']];
		$audio_html = '';
		if (!empty($row['stream_location'])) {
			$location_parts = explode('://', $row['stream_location']);
			$http_protocol = ($location_parts[0] == "shout") ? 'http' : 'https';
			$audio_html = "<audio src='".htmlspecialchars($http_protocol."://".($location_parts[1] ?? ''), ENT_QUOTES)."' controls='controls' />";
		}
		$row['_audio_html'] = $audio_html;
		$row['_domain_name'] = '';
		if (!empty($_SESSION['domains'][$row['domain_uuid']]['domain_name'])) {
			$row['_domain_name'] = escape($_SESSION['domains'][$row['domain_uuid']]['domain_name']);
		} else {
			$row['_domain_name'] = $text['label-global'];
		}
		$row['_toggle_button'] = '';
		if ($has_stream_edit) {
			$row['_toggle_button'] = button::create(['type'=>'submit','class'=>'link','label'=>$text['label-'.$row['stream_enabled']],'title'=>$text['button-toggle'],'onclick'=>"list_self_check('checkbox_{$x}'); list_action_set('toggle'); list_form_submit('form_list')"]);
		}
		$row['_edit_button'] = '';
		if ($has_stream_edit && $list_row_edit_button) {
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
	$template->assign('text',                 $text);
	$template->assign('num_rows',             $num_rows);
	$template->assign('streams',              $streams ?? []);
	$template->assign('search',               $search);
	$template->assign('show',                 $show);
	$template->assign('paging_controls',      $paging_controls);
	$template->assign('paging_controls_mini', $paging_controls_mini);
	$template->assign('token',                $token);
	$template->assign('has_stream_add',       $has_stream_add);
	$template->assign('has_stream_all',       $has_stream_all);
	$template->assign('has_stream_delete',    $has_stream_delete);
	$template->assign('has_stream_edit',      $has_stream_edit);
	$template->assign('list_row_edit_button', $list_row_edit_button);
	$template->assign('btn_add',              $btn_add);
	$template->assign('btn_copy',             $btn_copy);
	$template->assign('btn_toggle',           $btn_toggle);
	$template->assign('btn_delete',           $btn_delete);
	$template->assign('btn_show_all',         $btn_show_all);
	$template->assign('btn_search',           $btn_search);
	$template->assign('btn_reset',            $btn_reset);
	$template->assign('modal_copy',           $modal_copy);
	$template->assign('modal_toggle',         $modal_toggle);
	$template->assign('modal_delete',         $modal_delete);
	$template->assign('th_domain_name',       $th_domain_name);
	$template->assign('th_stream_name',       $th_stream_name);
	$template->assign('th_stream_enabled',    $th_stream_enabled);
	$template->assign('th_stream_description',$th_stream_description);

//invoke pre-render hook
	app::dispatch_list_pre_render('stream_list_page_hook', $url, $template);

//include the header
	$document['title'] = $text['title-streams'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('streams_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('stream_list_page_hook', $url, $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";

?>

