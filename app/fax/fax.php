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
	if (!permission_exists('fax_extension_view')) {
		echo "access denied";
		exit;
	}
	$has_domain_select             = permission_exists('domain_select');
	$has_fax_active_view           = permission_exists('fax_active_view');
	$has_fax_extension_add         = permission_exists('fax_extension_add');
	$has_fax_extension_copy        = permission_exists('fax_extension_copy');
	$has_fax_extension_delete      = permission_exists('fax_extension_delete');
	$has_fax_extension_edit        = permission_exists('fax_extension_edit');
	$has_fax_extension_view_all    = permission_exists('fax_extension_view_all');
	$has_fax_extension_view_domain = permission_exists('fax_extension_view_domain');
	$has_fax_inbox_view            = permission_exists('fax_inbox_view');
	$has_fax_log_view              = permission_exists('fax_log_view');
	$has_fax_queue_view            = permission_exists('fax_queue_view');
	$has_fax_send                  = permission_exists('fax_send');
	$has_fax_sent_view             = permission_exists('fax_sent_view');

//add multi-lingual support
	$text = new text()->get();
	//create the url object
	$url = new url();

//get posted data
	if (!empty($_POST['fax_servers']) && is_array($_POST['fax_servers'])) {
		$action = $_POST['action'];
		$search = $_POST['search'] ?? '';
		$fax_servers = $_POST['fax_servers'];
	}

//process the http post data by action
	if (!empty($action) && !empty($fax_servers) && is_array($fax_servers) && @sizeof($fax_servers) != 0) {
		//dispatch pre-action hook
		app::dispatch_list_pre_action('fax_extension_list_page_hook', $url, $action, $fax_servers);

		switch ($action) {
			case 'copy':
				if ($has_fax_extension_copy) {
					$obj = new fax;
					$obj->copy($fax_servers);
				}
				break;
			case 'delete':
				if ($has_fax_extension_delete) {
					$obj = new fax;
					$obj->delete($fax_servers);
				}
				break;
		}

		//dispatch post-action hook
		app::dispatch_list_post_action('fax_extension_list_page_hook', $url, $action, $fax_servers);

		header('Location: fax.php'.($search != '' ? '?search='.urlencode($search) : ''));
		exit;
	}

//dispatch pre-query hook
	$query_parameters = [];
	app::dispatch_list_pre_query('fax_extension_list_page_hook', $url, $query_parameters);

//get order and order by
	$order_by = $_GET["order_by"] ?? 'fax_name';
	$order = $_GET["order"] ?? 'asc';
	$sort = $order_by == 'fax_extension' ? 'natural' : null;

//add the search and show variables
	$search = $_GET["search"] ?? '';
	$show = $_GET["show"] ?? '';

//get record counts
	if ($show == "all" && $has_fax_extension_view_all) {
		//show all fax extensions
		$sql = "select count(f.fax_uuid) ";
		$sql .= "from v_fax as f ";
		$sql .= "where true ";
	}
	elseif ($has_fax_extension_view_domain || $has_fax_extension_view_all) {
		//show all fax extensions for this domain
		$sql = "select count(f.fax_uuid) ";
		$sql .= "from v_fax as f ";
		$sql .= "where (f.domain_uuid = :domain_uuid or f.domain_uuid is null) ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	else {
		//show only assigned fax extensions
		$sql = "select count(f.fax_uuid) ";
		$sql .= "from v_fax as f, v_fax_users as u ";
		$sql .= "where f.fax_uuid = u.fax_uuid ";
		$sql .= "and f.domain_uuid = :domain_uuid ";
		$sql .= "and u.user_uuid = :user_uuid ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$parameters['user_uuid'] = $_SESSION['user_uuid'];
	}
	if (!empty($search)) {
		$sql .= "and lower(fax_name) like :search ";
		$sql .= "or lower(fax_email) like :search ";
		$sql .= "or lower(fax_extension) like :search ";
		$sql .= "or lower(fax_destination_number) like :search ";
		$sql .= "or lower(fax_caller_id_name) like :search ";
		$sql .= "or lower(fax_caller_id_number) like :search ";
		$sql .= "or lower(fax_forward_number) like :search ";
		$sql .= "or lower(fax_description) like :search ";
		$parameters['search'] = '%'.strtolower($search).'%';
	}
	$num_rows = $database->select($sql, $parameters ?? null, 'column');

//prepare paging
	$rows_per_page = $settings->get('domain', 'paging', 50);
	$param = "&search=".urlencode($search);
	if ($show == "all" && $has_fax_extension_view_all) {
		$param .= "&show=all";
	}
	$page = !empty($_GET['page']) ? $_GET['page'] : 0;
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get fax extensions
	if ($show == "all" && $has_fax_extension_view_all) {
		//show all fax extensions
		$sql = "select * from v_fax as f ";
		$sql .= "where true ";
	}
	elseif ($has_fax_extension_view_domain || $has_fax_extension_view_all) {
		//show all fax extensions for this domain
		$sql = "select fax_uuid, domain_uuid, fax_extension, fax_prefix, fax_name, fax_email, fax_description ";
		$sql .= "from v_fax as f ";
		$sql .= "where (f.domain_uuid = :domain_uuid or f.domain_uuid is null) ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	else {
		//show only assigned fax extensions
		$sql = "select f.fax_uuid, fax_extension, fax_prefix, fax_name, fax_email, fax_description ";
		$sql .= "from v_fax as f, v_fax_users as u ";
		$sql .= "where f.fax_uuid = u.fax_uuid ";
		$sql .= "and f.domain_uuid = :domain_uuid ";
		$sql .= "and u.user_uuid = :user_uuid ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$parameters['user_uuid'] = $_SESSION['user_uuid'];
	}
	if (!empty($search)) {
		$sql .= "and lower(fax_name) like :search ";
		$sql .= "or lower(fax_email) like :search ";
		$sql .= "or lower(fax_extension) like :search ";
		$sql .= "or lower(fax_destination_number) like :search ";
		$sql .= "or lower(fax_caller_id_name) like :search ";
		$sql .= "or lower(fax_caller_id_number) like :search ";
		$sql .= "or lower(fax_forward_number) like :search ";
		$sql .= "or lower(fax_description) like :search ";
		$parameters['search'] = '%'.strtolower($search).'%';
	}
	$sql .= order_by($order_by, $order, 'fax_name', 'asc', $sort);
	$sql .= limit_offset($rows_per_page, $offset);
	$result = $database->select($sql, $parameters ?? null, 'all');
	//dispatch post-query hook
	app::dispatch_list_post_query('fax_extension_list_page_hook', $url, $result);
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//check list row edit button
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);

//build the action bar buttons
	$btn_add = '';
	if ($has_fax_extension_add) {
		$btn_add = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','link'=>'fax_edit.php']);
	}
	$btn_copy = '';
	if ($has_fax_extension_copy && $result) {
		$btn_copy = button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$settings->get('theme', 'button_icon_copy'),'id'=>'btn_copy','name'=>'btn_copy','style'=>'display: none;','onclick'=>"modal_open('modal-copy','btn_copy');"]);
	}
	$btn_delete = '';
	if ($has_fax_extension_delete && $result) {
		$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	$btn_show_all = '';
	if ($has_fax_extension_view_all && $show !== 'all') {
		$btn_show_all = button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$settings->get('theme', 'button_icon_all'),'link'=>'?type=&show=all'.(!empty($search) ? '&search='.urlencode($search) : '')]);
	}
	$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search','style'=>(!empty($search) ? 'display: none;' : null)]);
	$btn_reset  = button::create(['label'=>$text['button-reset'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'button','id'=>'btn_reset','link'=>'fax.php'.(!empty($_GET['show']) && $_GET['show'] == 'all' ? '?show=all' : null),'style'=>(empty($search) ? 'display: none;' : null)]);

//build the modals
	$modal_copy = '';
	if ($has_fax_extension_copy && $result) {
		$modal_copy = modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
	}
	$modal_delete = '';
	if ($has_fax_extension_delete && $result) {
		$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

//build table header columns
	$th_domain_name = '';
	if ($has_fax_extension_view_all && $show == 'all') {
		$th_domain_name = th_order_by('domain_name', $text['label-domain'], $order_by, $order);
	}
	$th_fax_name        = th_order_by('fax_name', $text['label-name'], $order_by, $order);
	$th_fax_extension   = th_order_by('fax_extension', $text['label-extension'], $order_by, $order);
	$th_fax_email       = th_order_by('fax_email', $text['label-email'], $order_by, $order);
	$th_fax_description = th_order_by('fax_description', $text['label-description'], $order_by, $order, null, "class='hide-sm-dn'");

//check fax active file availability
	$fax_active_enabled = file_exists(__DIR__ . '/fax_active.php')
		&& !empty($settings->get('fax', 'send_mode'))
		&& $settings->get('fax', 'send_mode') == 'queue';

//build the row data
	if (is_array($result) && @sizeof($result) != 0) {
		$x = 0;
		foreach ($result as &$row) {
			app::dispatch_list_render_row('fax_extension_list_page_hook', $url, $row, $x);
			$list_row_url = '';
			if ($has_fax_extension_edit) {
				$list_row_url = "fax_edit.php?id=".urlencode($row['fax_uuid']);
				if ($row['domain_uuid'] != $_SESSION['domain_uuid'] && $has_domain_select) {
					$list_row_url .= '&domain_uuid='.urlencode($row['domain_uuid']).'&domain_change=true';
				}
			}
			$row['_list_row_url'] = $list_row_url;
			$row['_edit_button']  = '';
			if ($has_fax_extension_edit && $list_row_edit_button) {
				$row['_edit_button'] = button::create(['type'=>'button','title'=>$text['button-edit'],'icon'=>$settings->get('theme', 'button_icon_edit'),'link'=>$list_row_url]);
			}
			$row['_fax_email']   = str_replace("\\", '', $row['fax_email'] ?? '');
			$row['_domain_name'] = '';
			if ($show == 'all' && $has_fax_extension_view_all) {
				$row['_domain_name'] = $_SESSION['domains'][$row['domain_uuid']]['domain_name'] ?? '';
			}
			$tools_html = '';
			if ($has_fax_send) {
				$tools_html .= "<a href='fax_send.php?id=".urlencode($row['fax_uuid'])."'>".$text['label-new']."</a>&nbsp;&nbsp;";
			}
			if ($has_fax_inbox_view) {
				if (!empty($row['fax_email_inbound_subject_tag'])) {
					$inbox_file = 'fax_files_remote.php';
					$inbox_box  = rawurlencode($row['fax_email_connection_mailbox'] ?? '');
				}
				else {
					$inbox_file = 'fax_files.php';
					$inbox_box  = 'inbox';
				}
				$tools_html .= "<a href='".$inbox_file."?order_by=fax_date&order=desc&id=".urlencode($row['fax_uuid'])."&box=".$inbox_box."'>".$text['label-inbox']."</a>&nbsp;&nbsp;";
			}
			if ($has_fax_sent_view) {
				$tools_html .= "<a href='fax_files.php?order_by=fax_date&order=desc&id=".urlencode($row['fax_uuid'])."&box=sent'>".$text['label-sent']."</a>&nbsp;&nbsp;";
			}
			if ($has_fax_log_view) {
				$tools_html .= "<a href='fax_logs.php?id=".urlencode($row['fax_uuid'])."'>".$text['label-log']."</a>&nbsp;&nbsp;";
			}
			if ($fax_active_enabled && $has_fax_active_view) {
				$tools_html .= "<a href='fax_active.php?id=".urlencode($row['fax_uuid'])."'>".$text['label-active']."</a>&nbsp;&nbsp;";
			}
			if ($has_fax_queue_view) {
				$tools_html .= "<a href='/app/fax_queue/fax_queue.php'>".$text['label-queue']."</a>&nbsp;&nbsp;";
			}
			$row['_tools_html'] = $tools_html;
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
	$template->assign('text',                          $text);
	$template->assign('num_rows',                      $num_rows);
	$template->assign('result',                        $result ?? []);
	$template->assign('search',                        $search);
	$template->assign('show',                          $show);
	$template->assign('paging_controls',               $paging_controls);
	$template->assign('paging_controls_mini',          $paging_controls_mini);
	$template->assign('token',                         $token);
	$template->assign('has_domain_select',             $has_domain_select);
	$template->assign('has_fax_active_view',           $has_fax_active_view);
	$template->assign('has_fax_extension_add',         $has_fax_extension_add);
	$template->assign('has_fax_extension_copy',        $has_fax_extension_copy);
	$template->assign('has_fax_extension_delete',      $has_fax_extension_delete);
	$template->assign('has_fax_extension_edit',        $has_fax_extension_edit);
	$template->assign('has_fax_extension_view_all',    $has_fax_extension_view_all);
	$template->assign('has_fax_inbox_view',            $has_fax_inbox_view);
	$template->assign('has_fax_log_view',              $has_fax_log_view);
	$template->assign('has_fax_queue_view',            $has_fax_queue_view);
	$template->assign('has_fax_send',                  $has_fax_send);
	$template->assign('has_fax_sent_view',             $has_fax_sent_view);
	$template->assign('list_row_edit_button',          $list_row_edit_button);
	$template->assign('btn_add',                       $btn_add);
	$template->assign('btn_copy',                      $btn_copy);
	$template->assign('btn_delete',                    $btn_delete);
	$template->assign('btn_show_all',                  $btn_show_all);
	$template->assign('btn_search',                    $btn_search);
	$template->assign('btn_reset',                     $btn_reset);
	$template->assign('modal_copy',                    $modal_copy);
	$template->assign('modal_delete',                  $modal_delete);
	$template->assign('th_domain_name',                $th_domain_name);
	$template->assign('th_fax_name',                   $th_fax_name);
	$template->assign('th_fax_extension',              $th_fax_extension);
	$template->assign('th_fax_email',                  $th_fax_email);
	$template->assign('th_fax_description',            $th_fax_description);

//invoke pre-render hook
	app::dispatch_list_pre_render('fax_extension_list_page_hook', $url, $template);

//include the header
	$document['title'] = $text['title-fax'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('fax_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('fax_extension_list_page_hook', $url, $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";

?>

