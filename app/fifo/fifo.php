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
	if (!permission_exists('fifo_view')) {
		echo "access denied";
		exit;
	}
	$has_domain_select = permission_exists('domain_select');
	$has_fifo_add      = permission_exists('fifo_add');
	$has_fifo_all      = permission_exists('fifo_all');
	$has_fifo_delete   = permission_exists('fifo_delete');
	$has_fifo_edit     = permission_exists('fifo_edit');

//add multi-lingual support
	$text = new text()->get();
	//create the url object
	$url = new url();

//add the settings object
	$settings = new settings(["domain_uuid" => $_SESSION['domain_uuid'], "user_uuid" => $_SESSION['user_uuid']]);

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', 'false');

//get the http post data
	if (!empty($_POST['fifo']) && is_array($_POST['fifo'])) {
		$action = $_POST['action'];
		$search = $_POST['search'] ?? '';
		$fifo = $_POST['fifo'];
	}

//process the http post data by action
	if (!empty($action) && !empty($fifo) && is_array($fifo) && @sizeof($fifo) != 0) {

		//validate the token
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-invalid_token'],'negative');
			header('Location: fifo.php');
			exit;
		}

		//send the array to the database class
		//dispatch pre-action hook
		app::dispatch_list_pre_action('fifo_list_page_hook', $url, $action, $fifo);

		switch ($action) {
// 			case 'copy':
// 				if ($has_fifo_add) {
// 					$obj = new fifo;
// 					$obj->copy($fifo);
// 				}
// 				break;
			case 'toggle':
				if ($has_fifo_edit) {
					$obj = new fifo;
					$obj->toggle($fifo);
				}
				break;
			case 'delete':
				if ($has_fifo_delete) {
					$obj = new fifo;
					$obj->delete($fifo);
				}
				break;
		}

		//redirect the user
			//dispatch post-action hook
			app::dispatch_list_post_action('fifo_list_page_hook', $url, $action, $fifo);

		header('Location: fifo.php'.($search != '' ? '?search='.urlencode($search) : ''));
		exit;
	}

//dispatch pre-query hook
	$query_parameters = [];
	app::dispatch_list_pre_query('fifo_list_page_hook', $url, $query_parameters);

//get order and order by
	$order_by = $_GET["order_by"] ?? null;
	$order = $_GET["order"] ?? null;

//define the variables
	$search = '';
	$show = '';
	$list_row_url = '';

//add the search variable
	if (!empty($_GET["search"])) {
		$search = strtolower($_GET["search"]);
	}

//add the show variable
	if (!empty($_GET["show"])) {
		$show = $_GET["show"];
	}

//get the count
	$sql = "select count(fifo_uuid) ";
	$sql .= "from v_fifo ";
	if ($has_fifo_all && $show == 'all') {
		$sql .= "where true ";
	}
	else {
		$sql .= "where domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	if (!empty($search)) {
		$sql .= "and ( ";
		$sql .= "	lower(fifo_name) like :search ";
		$sql .= "	or lower(fifo_extension) like :search ";
		$sql .= "	or lower(fifo_description) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$num_rows = $database->select($sql, $parameters ?? null, 'column');
	unset($sql, $parameters);

//prepare to page the results
	$rows_per_page = $settings->get('domain', 'paging', 50);
	$param = !empty($search) ? "&search=".$search : null;
	$param .= (!empty($_GET['page']) && $show == 'all' && $has_fifo_all) ? "&show=all" : null;
	$page = !empty($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 0;
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the list
	$sql = "select ";
	$sql .= "fifo_uuid, ";
	$sql .= "fifo_name, ";
	$sql .= "fifo_extension, ";
	$sql .= "fifo_agent_status, ";
	$sql .= "fifo_agent_queue, ";
	$sql .= "fifo_music, ";
	$sql .= "u.domain_uuid, ";
	$sql .= "d.domain_name, ";
	$sql .= "fifo_order, ";
	$sql .= "cast(fifo_enabled as text), ";
	$sql .= "fifo_description ";
	$sql .= "from v_fifo as u, v_domains as d ";
	if ($has_fifo_all && $show == 'all') {
		$sql .= "where true ";
	}
	else {
		$sql .= "where u.domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	if (!empty($search)) {
		$sql .= "and ( ";
		$sql .= "	lower(fifo_name) like :search ";
		$sql .= "	or lower(fifo_extension) like :search ";
		$sql .= "	or lower(fifo_description) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$sql .= "and u.domain_uuid = d.domain_uuid ";
	$sql .= order_by($order_by, $order, '', '');
	$sql .= limit_offset($rows_per_page, $offset);
	$fifo = $database->select($sql, $parameters ?? null, 'all');
	//dispatch post-query hook
	app::dispatch_list_post_query('fifo_list_page_hook', $url, $fifo);
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//build the action bar buttons
	$btn_add = '';
	if ($has_fifo_add) {
		$btn_add = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','name'=>'btn_add','link'=>'fifo_edit.php']);
	}
	$btn_toggle = '';
	if ($has_fifo_edit && $fifo) {
		$btn_toggle = button::create(['type'=>'button','label'=>$text['button-toggle'],'icon'=>$settings->get('theme', 'button_icon_toggle'),'id'=>'btn_toggle','name'=>'btn_toggle','style'=>'display: none;','onclick'=>"modal_open('modal-toggle','btn_toggle');"]);
	}
	$btn_delete = '';
	if ($has_fifo_delete && $fifo) {
		$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	$btn_show_all = '';
	if ($has_fifo_all && $show !== 'all') {
		$btn_show_all = button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$settings->get('theme', 'button_icon_all'),'link'=>'?show=all&search='.$search]);
	}
	$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);

//build the modals
	$modal_toggle = '';
	if ($has_fifo_edit && $fifo) {
		$modal_toggle = modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
	}
	$modal_delete = '';
	if ($has_fifo_delete && $fifo) {
		$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

//build the table header columns
	$th_domain_name = '';
	if (!empty($show) && $show == 'all' && $has_fifo_all) {
		$th_domain_name = th_order_by('domain_name', $text['label-domain'], $order_by, $order);
	}
	$th_fifo_name         = th_order_by('fifo_name', $text['label-fifo_name'], $order_by, $order);
	$th_fifo_extension    = th_order_by('fifo_extension', $text['label-fifo_extension'], $order_by, $order);
	$th_fifo_agent_status = th_order_by('fifo_agent_status', $text['label-fifo_agent_status'], $order_by, $order);
	$th_fifo_agent_queue  = th_order_by('fifo_agent_queue', $text['label-fifo_agent_queue'], $order_by, $order);
	$th_fifo_order        = th_order_by('fifo_order', $text['label-fifo_order'], $order_by, $order);
	$th_fifo_enabled      = th_order_by('fifo_enabled', $text['label-enabled'], $order_by, $order, null, "class='center'");

//build the row data
	$fifo = $fifo ?? [];
	$x = 0;
	foreach ($fifo as &$row) {
		app::dispatch_list_render_row('fifo_list_page_hook', $url, $row, $x);
		$list_row_url = '';
		if ($has_fifo_edit) {
			$list_row_url = "fifo_edit.php?id=".urlencode($row['fifo_uuid']);
			if ($row['domain_uuid'] != $_SESSION['domain_uuid'] && $has_domain_select) {
				$list_row_url .= '&domain_uuid='.urlencode($row['domain_uuid']).'&domain_change=true';
			}
		}
		$row['_list_row_url']  = $list_row_url;
		$row['_enabled_label'] = $text['label-'.$row['fifo_enabled']];
		$row['_toggle_button'] = '';
		if ($has_fifo_edit) {
			$row['_toggle_button'] = button::create(['type'=>'submit','class'=>'link','label'=>$text['label-'.$row['fifo_enabled']],'title'=>$text['button-toggle'],'onclick'=>"list_self_check('checkbox_{$x}'); list_action_set('toggle'); list_form_submit('form_list')"]);
		}
		$row['_edit_button'] = '';
		if ($has_fifo_edit && $list_row_edit_button == 'true') {
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
	$template->assign('text',                  $text);
	$template->assign('num_rows',              $num_rows);
	$template->assign('fifo',                  $fifo);
	$template->assign('search',                $search);
	$template->assign('show',                  $show);
	$template->assign('paging_controls',       $paging_controls);
	$template->assign('paging_controls_mini',  $paging_controls_mini);
	$template->assign('token',                 $token);
	$template->assign('has_fifo_add',          $has_fifo_add);
	$template->assign('has_fifo_all',          $has_fifo_all);
	$template->assign('has_fifo_delete',       $has_fifo_delete);
	$template->assign('has_fifo_edit',         $has_fifo_edit);
	$template->assign('list_row_edit_button',  $list_row_edit_button);
	$template->assign('btn_add',               $btn_add);
	$template->assign('btn_toggle',            $btn_toggle);
	$template->assign('btn_delete',            $btn_delete);
	$template->assign('btn_show_all',          $btn_show_all);
	$template->assign('btn_search',            $btn_search);
	$template->assign('modal_toggle',          $modal_toggle);
	$template->assign('modal_delete',          $modal_delete);
	$template->assign('th_domain_name',        $th_domain_name);
	$template->assign('th_fifo_name',          $th_fifo_name);
	$template->assign('th_fifo_extension',     $th_fifo_extension);
	$template->assign('th_fifo_agent_status',  $th_fifo_agent_status);
	$template->assign('th_fifo_agent_queue',   $th_fifo_agent_queue);
	$template->assign('th_fifo_order',         $th_fifo_order);
	$template->assign('th_fifo_enabled',       $th_fifo_enabled);

//invoke pre-render hook
	app::dispatch_list_pre_render('fifo_list_page_hook', $url, $template);

//include the header
	$document['title'] = $text['title-fifos'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('fifo_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('fifo_list_page_hook', $url, $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";
