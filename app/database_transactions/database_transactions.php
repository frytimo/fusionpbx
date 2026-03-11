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
	Portions created by the Initial Developer are Copyright (C) 2016 - 2023
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (!permission_exists('database_transaction_view')) {
		echo "access denied";
		exit;
	}
	$has_database_transaction_edit = permission_exists('database_transaction_edit');
	$has_domain_select             = permission_exists('domain_select');

//add multi-lingual support
	$text = new text()->get();

//set default values
	$search = '';
	$user_uuid = '';

//get variables used to control the order
	$order_by = $_GET["order_by"] ?? '';
	$order = $_GET["order"] ?? '';

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);
	$button_icon_view = !empty($settings->get('theme', 'button_icon_view')) ? $settings->get('theme', 'button_icon_view') : '';

//add the user filter and search term
	if (!empty($_GET["user_uuid"])) {
		$user_uuid = $_GET['user_uuid'];
	}
	if (!empty($_GET["search"])) {
		$search = strtolower($_GET["search"]);
	}

//prepare to page the results
	$sql = "select count(t.database_transaction_uuid) ";
	$sql .= "from v_database_transactions as t ";
	$sql .= "left outer join v_domains as d using (domain_uuid) ";
	$sql .= "left outer join v_users as u using (user_uuid) ";
	$sql .= "where (t.domain_uuid = :domain_uuid or t.domain_uuid is null) ";
	if (!empty($user_uuid)) {
		$sql .= "and t.user_uuid = :user_uuid ";
		$parameters['user_uuid'] = $user_uuid;
	}
	if (!empty($search)) {
		$sql .= "and (";
		$sql .= "	lower(t.app_name) like :search ";
		$sql .= "	or lower(t.transaction_code) like :search ";
		$sql .= "	or lower(t.transaction_address) like :search ";
		$sql .= "	or lower(t.transaction_type) like :search ";
		$sql .= "	or cast(t.transaction_date as text) like :search ";
		$sql .= "	or lower(t.transaction_old) like :search ";
		$sql .= "	or lower(t.transaction_new) like :search ";
		$sql .= "	or lower(u.username) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	};
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$num_rows = $database->select($sql, $parameters, 'column');
	unset($parameters);

//prepare to page the results
	$rows_per_page = $settings->get('domain', 'paging', 50);
	$param = "search=".$search;
	$page = empty($_GET['page']) ? $page = 0 : $page = $_GET['page'];
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the list
	$sql = "select t.database_transaction_uuid, t.domain_uuid, d.domain_name, u.username, ";
	$sql .= "t.user_uuid, t.app_name, t.app_uuid, t.transaction_code, ";
	$sql .= "t.transaction_address, t.transaction_type, t.transaction_date ";
	$sql .= "from v_database_transactions as t ";
	$sql .= "left outer join v_domains as d using (domain_uuid) ";
	$sql .= "left outer join v_users as u using (user_uuid) ";
	$sql .= "where (t.domain_uuid = :domain_uuid or t.domain_uuid is null) ";
	if (!empty($user_uuid)) {
		$sql .= "and t.user_uuid = :user_uuid ";
		$parameters['user_uuid'] = $user_uuid;
	}
	if (!empty($search)) {
		$sql .= "and (";
		$sql .= "	lower(t.app_name) like :search ";
		$sql .= "	or lower(t.transaction_code) like :search ";
		$sql .= "	or lower(t.transaction_address) like :search ";
		$sql .= "	or lower(t.transaction_type) like :search ";
		$sql .= "	or cast(t.transaction_date as text) like :search ";
		$sql .= "	or lower(t.transaction_old) like :search ";
		$sql .= "	or lower(t.transaction_new) like :search ";
		$sql .= "	or lower(u.username) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$sql .= order_by($order_by, $order, 't.transaction_date', 'desc');
	$sql .= limit_offset($rows_per_page, $offset);
	$transactions = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//get users
	$sql = "select user_uuid, username from v_users ";
	$sql .= "where domain_uuid = :domain_uuid ";
	$sql .= "order by username ";
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$rows = $database->select($sql, $parameters, 'all');
	if (!empty($rows)) {
		foreach ($rows as $row) {
			$users[$row['user_uuid']] = $row['username'];
		}
	}
	unset($sql, $parameters, $rows, $row);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//build the action bar buttons
	$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);

//build the table header columns
	$th_domain_name         = th_order_by('domain_name', $text['label-domain'], $order_by, $order);
	$th_username            = th_order_by('username', $text['label-user_uuid'], $order_by, $order);
	$th_app_name            = th_order_by('app_name', $text['label-app_name'], $order_by, $order);
	$th_transaction_code    = th_order_by('transaction_code', $text['label-transaction_code'], $order_by, $order);
	$th_transaction_address = th_order_by('transaction_address', $text['label-transaction_address'], $order_by, $order);
	$th_transaction_type    = th_order_by('transaction_type', $text['label-transaction_type'], $order_by, $order);
	$th_transaction_date    = th_order_by('transaction_date', $text['label-transaction_date'], $order_by, $order);

//build the row data
	$x = 0;
	foreach ($transactions as &$row) {
		if (empty($row['domain_name'])) { $row['domain_name'] = $text['label-global']; }
		$list_row_url = '';
		if ($has_database_transaction_edit) {
			$list_row_url = "database_transaction_edit.php?id=".urlencode($row['database_transaction_uuid']).(!empty($page) ? "&page=".urlencode($page) : null).(!empty($search) ? "&search=".urlencode($search) : null);
			if ($row['domain_uuid'] != $_SESSION['domain_uuid'] && $has_domain_select) {
				$list_row_url .= '&domain_uuid='.urlencode($row['domain_uuid'] ?? '').'&domain_change=true';
			}
		}
		$row['_list_row_url'] = $list_row_url;
		$row['_edit_button'] = '';
		if ($has_database_transaction_edit && $list_row_edit_button) {
			$row['_edit_button'] = button::create(['type'=>'button','title'=>$text['button-view'],'icon'=>$button_icon_view,'link'=>$list_row_url]);
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
	$template->assign('text',                          $text);
	$template->assign('num_rows',                      $num_rows);
	$template->assign('transactions',                  $transactions ?? []);
	$template->assign('search',                        $search);
	$template->assign('users',                         $users ?? []);
	$template->assign('user_uuid',                     $user_uuid);
	$template->assign('paging_controls',               $paging_controls);
	$template->assign('paging_controls_mini',          $paging_controls_mini);
	$template->assign('token',                         $token);
	$template->assign('has_database_transaction_edit', $has_database_transaction_edit);
	$template->assign('list_row_edit_button',          $list_row_edit_button);
	$template->assign('btn_search',                    $btn_search);
	$template->assign('th_domain_name',                $th_domain_name);
	$template->assign('th_username',                   $th_username);
	$template->assign('th_app_name',                   $th_app_name);
	$template->assign('th_transaction_code',           $th_transaction_code);
	$template->assign('th_transaction_address',        $th_transaction_address);
	$template->assign('th_transaction_type',           $th_transaction_type);
	$template->assign('th_transaction_date',           $th_transaction_date);

//invoke pre-render hook
	app::dispatch_list_pre_render('database_transaction_list_page_hook', $url_paging, $template);

//include the header
	$document['title'] = $text['title-database_transactions'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('database_transactions_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('database_transaction_list_page_hook', $url_paging, $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";
