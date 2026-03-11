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
	Portions created by the Initial Developer are Copyright (C) 2023-2025
	the Initial Developer. All Rights Reserved.
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (!permission_exists('fax_queue_view')) {
		echo "access denied";
		exit;
	}

//set defaults
	$domain_uuid = $_SESSION['domain_uuid'] ?? '';
	$user_uuid = $_SESSION['user_uuid'] ?? '';
	$settings = new settings(['database' => $database, 'domain_uuid' => $domain_uuid, 'user_uuid' => $user_uuid]);

//set default permissions
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);
	$permission = [];
	$permission['fax_queue_add'] = permission_exists('fax_queue_add');
	$permission['fax_queue_delete'] = permission_exists('fax_queue_delete');
	$permission['fax_queue_domain'] = permission_exists('fax_queue_domain');
	$permission['fax_queue_all'] = permission_exists('fax_queue_all');
	$permission['fax_queue_edit'] = permission_exists('fax_queue_edit');

//add multi-lingual support
	$text = new text()->get();
	//create the url object
	$url = new url();

//get the http post data
	if (isset($_REQUEST['action'])) {
		$action = $_REQUEST['action'];
	}

//add the search
	if (isset($_REQUEST["search"])) {
		$search = strtolower($_REQUEST["search"]);
	}

//get the fax_queue item checked
	if (isset($_REQUEST['fax_queue'])) {
		$fax_queue = $_REQUEST['fax_queue'];
	}

//set display variables
	$show = $_GET['show'] ?? '';
	$fax_status = $_GET['fax_status'] ?? '';

//process the http post data by action
	if (!empty($action) && !empty($fax_queue) && !empty($fax_queue)) {

		//dispatch pre-action hook
		app::dispatch_list_pre_action('fax_queue_list_page_hook', $url, $action, $fax_queue);

		switch ($action) {
			case 'copy':
				if ($permission['fax_queue_add']) {
					$obj = new fax_queue;
					$obj->copy($fax_queue);
				}
				break;
			case 'resend':
				if ($permission['fax_queue_edit']) {
					$obj = new fax_queue;
					$obj->resend($fax_queue);
				}
				break;
			case 'delete':
				if ($permission['fax_queue_delete']) {
					$obj = new fax_queue;
					$obj->delete($fax_queue);
				}
				break;
		}

		//redirect the user
		//dispatch post-action hook
		app::dispatch_list_post_action('fax_queue_list_page_hook', $url, $action, $fax_queue);

		header('Location: fax_queue.php'.(!empty($search) ? '?search='.urlencode($search) : ''));
		exit;
	}

//dispatch pre-query hook
	$query_parameters = [];
	app::dispatch_list_pre_query('fax_queue_list_page_hook', $url, $query_parameters);

//get order and order by
	$order_by = $_GET["order_by"] ?? null;
	$order = $_GET["order"] ?? null;

//get the count
	$sql = "select count(fax_queue_uuid) ";
	$sql .= "from v_fax_queue as q ";
	$sql .= "LEFT JOIN v_users AS u ON q.insert_user = u.user_uuid ";
	if (!empty($_GET['show']) && $_GET['show'] == "all" && $permission['fax_queue_all']) {
		// show faxes for all domains
		$sql .= "WHERE true ";
	}
	elseif ($permission['fax_queue_domain']) {
		// show faxes for one domain
		$sql .= "WHERE q.domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $domain_uuid;
	}
	else {
		// show only assigned fax extensions
		$sql .= "WHERE q.domain_uuid = :domain_uuid ";
		$sql .= "AND u.user_uuid = :user_uuid ";
		$parameters['domain_uuid'] = $domain_uuid;
		$parameters['user_uuid'] = $user_uuid;
	}

	if (isset($search)) {
		$sql .= "AND (";
		$sql .= "	LOWER(q.hostname) LIKE :search ";
		$sql .= "	OR LOWER(q.fax_caller_id_name) LIKE :search ";
		$sql .= "	OR LOWER(q.fax_caller_id_number) LIKE :search ";
		$sql .= "	OR LOWER(q.fax_number) LIKE :search ";
		$sql .= "	OR LOWER(q.fax_email_address) LIKE :search ";
		$sql .= "	OR LOWER(u.username) LIKE :search ";
		$sql .= "	OR LOWER(q.fax_file) LIKE :search ";
		$sql .= "	OR LOWER(q.fax_status) LIKE :search ";
		$sql .= "	OR LOWER(q.fax_accountcode) LIKE :search ";
		$sql .= ") ";
		$parameters['search'] = '%' . $search . '%';
	}

	if (isset($_GET["fax_status"]) && !empty($_GET["fax_status"])) {
			$sql .= "AND q.fax_status = :fax_status ";
			$parameters['fax_status'] = $_GET["fax_status"];
	}
	$num_rows = $database->select($sql, $parameters ?? null, 'column');
	unset($sql, $parameters);

//prepare to page the results
	$rows_per_page = $settings->get('domain', 'paging', 50);
	$param = !empty($search) ? "&search=".$search : null;
	$param = (!empty($_GET['show']) && $_GET['show'] == 'all' && $permission['fax_queue_all']) ? "&show=all" : null;
	$page = !empty($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 0;
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//set the time zone
	$time_zone = $settings->get('domain', 'time_zone', date_default_timezone_get());

//set the time format options: 12h, 24h
	if ($settings->get('domain', 'time_format') == '24h') {
		$time_format = 'HH24:MI:SS';
	}
	else {
		$time_format = 'HH12:MI:SS am';
	}

//get the list
	$sql = "SELECT ";
	$sql .= "d.domain_name, ";
	$sql .= "q.domain_uuid, ";
	$sql .= "q.fax_queue_uuid, ";
	$sql .= "q.fax_uuid, ";
	$sql .= "q.fax_date, ";
	$sql .= "to_char(timezone(:time_zone, q.fax_date), 'DD Mon YYYY') as fax_date_formatted, ";
	$sql .= "to_char(timezone(:time_zone, q.fax_date), '".$time_format."') as fax_time_formatted, ";
	$sql .= "q.hostname, ";
	$sql .= "q.fax_caller_id_name, ";
	$sql .= "q.fax_caller_id_number, ";
	$sql .= "q.fax_number, ";
	$sql .= "q.fax_prefix, ";
	$sql .= "q.fax_email_address, ";
	$sql .= "u.username as insert_user, ";
	$sql .= "q.fax_file, ";
	$sql .= "q.fax_status, ";
	$sql .= "q.fax_retry_date, ";
	$sql .= "to_char(timezone(:time_zone, q.fax_retry_date), 'DD Mon YYYY') as fax_retry_date_formatted, ";
	$sql .= "to_char(timezone(:time_zone, q.fax_retry_date), '".$time_format."') as fax_retry_time_formatted, ";
	$sql .= "q.fax_notify_date, ";
	$sql .= "to_char(timezone(:time_zone, q.fax_notify_date), 'DD Mon YYYY') as fax_notify_date_formatted, ";
	$sql .= "to_char(timezone(:time_zone, q.fax_notify_date), '".$time_format."') as fax_notify_time_formatted, ";
	$sql .= "q.fax_retry_count, ";
	$sql .= "q.fax_accountcode, ";
	$sql .= "q.fax_command ";
	$sql .= "FROM v_fax_queue AS q ";
	$sql .= "LEFT JOIN v_users AS u ON q.insert_user = u.user_uuid ";
	$sql .= "JOIN v_domains AS d ON q.domain_uuid = d.domain_uuid ";

	if (!empty($_GET['show']) && $_GET['show'] == "all" && $permission['fax_queue_all']) {
		// show faxes for all domains
		$sql .= "WHERE true ";
	}
	elseif ($permission['fax_queue_domain']) {
		// show faxes for one domain
		$sql .= "WHERE q.domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $domain_uuid;
	}
	else {
		// show only assigned fax extensions
		$sql .= "WHERE q.domain_uuid = :domain_uuid ";
		$sql .= "AND u.user_uuid = :user_uuid ";
		$parameters['domain_uuid'] = $domain_uuid;
		$parameters['user_uuid'] = $user_uuid;
	}

	if (isset($search)) {
		$sql .= "AND (";
		$sql .= "	LOWER(q.hostname) LIKE :search ";
		$sql .= "	OR LOWER(q.fax_caller_id_name) LIKE :search ";
		$sql .= "	OR LOWER(q.fax_caller_id_number) LIKE :search ";
		$sql .= "	OR LOWER(q.fax_number) LIKE :search ";
		$sql .= "	OR LOWER(q.fax_email_address) LIKE :search ";
		$sql .= "	OR LOWER(u.username) LIKE :search ";
		$sql .= "	OR LOWER(q.fax_file) LIKE :search ";
		$sql .= "	OR LOWER(q.fax_status) LIKE :search ";
		$sql .= "	OR LOWER(q.fax_accountcode) LIKE :search ";
		$sql .= ") ";
		$parameters['search'] = '%' . $search . '%';
	}

	if (isset($_GET["fax_status"]) && !empty($_GET["fax_status"])) {
			$sql .= "AND q.fax_status = :fax_status ";
			$parameters['fax_status'] = $_GET["fax_status"];
	}

	$sql .= order_by($order_by, $order, 'fax_date', 'desc');
	$sql .= limit_offset($rows_per_page, $offset);
	$parameters['time_zone'] = $time_zone;
	$fax_queue = $database->select($sql, $parameters, 'all');
//dispatch post-query hook
app::dispatch_list_post_query('fax_queue_list_page_hook', $url, $fax_queue);
unset($sql, $parameters);

//create token
$object = new token;
$token = $object->create($_SERVER['PHP_SELF']);

//build the action bar buttons
$btn_back = button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$settings->get('theme', 'button_icon_back'),'id'=>'btn_back','style'=>'margin-right: 15px;','link'=>'/app/fax/fax.php']);
$btn_resend = '';
if ($permission['fax_queue_edit'] && $fax_queue) {
$btn_resend = button::create(['type'=>'button','label'=>$text['button-resend'],'icon'=>'fax','id'=>'btn_resend','name'=>'btn_resend','collapse'=>'hide-xs','style'=>'display: none;','class'=>'+revealed','onclick'=>"modal_open('modal-resend','btn_resend');"]);
}
$btn_delete = '';
if ($permission['fax_queue_delete'] && $fax_queue) {
$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display:none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
}
$btn_show_all = '';
if ($permission['fax_queue_all'] && $show !== 'all') {
$btn_show_all = button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$settings->get('theme', 'button_icon_all'),'style'=>'margin-left: 15px;','link'=>'?show=all']);
}
$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);

//build the modals
$modal_copy = '';
if ($permission['fax_queue_add'] && $fax_queue) {
$modal_copy = modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
}
$modal_resend = '';
if ($permission['fax_queue_edit'] && $fax_queue) {
$modal_resend = modal::create([
'id'=>'modal-resend',
'title'=>$text['modal_title-resend'],
'message'=>$text['modal_message-resend'],
'actions'=>
button::create(['type'=>'button','label'=>$text['button-cancel'],'icon'=>$settings->get('theme', 'button_icon_cancel'),'collapse'=>'hide-xs','onclick'=>'modal_close();']).
button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','collapse'=>'never','style'=>'float: right;','onclick'=>"modal_close(); list_action_set('resend'); list_form_submit('form_list');"])
]);
}
$modal_delete = '';
if ($permission['fax_queue_delete'] && $fax_queue) {
$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
}

//build the table header columns
$th_domain_name          = '';
if (!empty($show) && $show == 'all' && $permission['fax_queue_all']) {
$th_domain_name = th_order_by('domain_name', $text['label-domain'], $order_by, $order);
}
$th_hostname = '';
if ($permission['fax_queue_all']) {
$th_hostname = th_order_by('hostname', $text['label-hostname'], $order_by, $order, null, "class='hide-md-dn'");
}
$th_fax_caller_id_name   = th_order_by('fax_caller_id_name', $text['label-fax_caller_id_name'], $order_by, $order, null, "class='hide-md-dn'");
$th_fax_caller_id_number = th_order_by('fax_caller_id_number', $text['label-fax_caller_id_number'], $order_by, $order);
$th_fax_number           = th_order_by('fax_number', $text['label-fax_number'], $order_by, $order);
$th_fax_email_address    = th_order_by('fax_email_address', $text['label-fax_email_address'], $order_by, $order);
$th_insert_user          = th_order_by('insert_user', $text['label-insert_user'], $order_by, $order);
$th_fax_status           = th_order_by('fax_status', $text['label-fax_status'], $order_by, $order);
$th_fax_retry_date       = th_order_by('fax_retry_date', $text['label-fax_retry_date'], $order_by, $order);
$th_fax_notify_date      = th_order_by('fax_notify_date', $text['label-fax_notify_date'], $order_by, $order);
$th_fax_retry_count      = th_order_by('fax_retry_count', $text['label-fax_retry_count'], $order_by, $order);

//build the row data
$x = 0;
foreach ($fax_queue ?? [] as &$row) {
app::dispatch_list_render_row('fax_queue_list_page_hook', $url, $row, $x);
$list_row_url = '';
if ($permission['fax_queue_edit']) {
$list_row_url = "fax_queue_edit.php?id=".urlencode($row['fax_queue_uuid']);
if ($row['domain_uuid'] != $_SESSION['domain_uuid'] && permission_exists('domain_select')) {
$list_row_url .= '&domain_uuid='.urlencode($row['domain_uuid']).'&domain_change=true';
}
}
$row['_list_row_url']      = $list_row_url;
$row['_status_label']      = ucwords($text['label-'.$row['fax_status']]);
$row['_fax_email_address'] = str_replace(',', ' ', $row['fax_email_address'] ?? '');
$row['_edit_button']       = '';
if ($permission['fax_queue_edit'] && $list_row_edit_button) {
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
$template->assign('fax_queue',                  $fax_queue ?? []);
$template->assign('search',                     $search ?? '');
$template->assign('show',                       $show);
$template->assign('fax_status',                 $fax_status);
$template->assign('paging_controls',            $paging_controls);
$template->assign('paging_controls_mini',       $paging_controls_mini);
$template->assign('token',                      $token);
$template->assign('permission',                 $permission);
$template->assign('list_row_edit_button',       $list_row_edit_button);
$template->assign('btn_back',                   $btn_back);
$template->assign('btn_resend',                 $btn_resend);
$template->assign('btn_delete',                 $btn_delete);
$template->assign('btn_show_all',               $btn_show_all);
$template->assign('btn_search',                 $btn_search);
$template->assign('modal_copy',                 $modal_copy);
$template->assign('modal_resend',               $modal_resend);
$template->assign('modal_delete',               $modal_delete);
$template->assign('th_domain_name',             $th_domain_name);
$template->assign('th_hostname',                $th_hostname);
$template->assign('th_fax_caller_id_name',      $th_fax_caller_id_name);
$template->assign('th_fax_caller_id_number',    $th_fax_caller_id_number);
$template->assign('th_fax_number',              $th_fax_number);
$template->assign('th_fax_email_address',       $th_fax_email_address);
$template->assign('th_insert_user',             $th_insert_user);
$template->assign('th_fax_status',              $th_fax_status);
$template->assign('th_fax_retry_date',          $th_fax_retry_date);
$template->assign('th_fax_notify_date',         $th_fax_notify_date);
$template->assign('th_fax_retry_count',         $th_fax_retry_count);

//invoke pre-render hook
app::dispatch_list_pre_render('fax_queue_list_page_hook', $url, $template);

//include the header
$document['title'] = $text['title-fax_queue'];
require_once "resources/header.php";

//render the template
$html = $template->render('fax_queue_list.tpl');

//invoke post-render hook
app::dispatch_list_post_render('fax_queue_list_page_hook', $url, $html);
echo $html;

//include the footer
require_once "resources/footer.php";
