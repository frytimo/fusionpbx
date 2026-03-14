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

//set default
	$is_included = false;

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (!(permission_exists('follow_me') || permission_exists('call_forward') || permission_exists('do_not_disturb'))) {
		echo "access denied";
		exit;
	}
	$has_call_forward     = permission_exists('call_forward');
	$has_call_forward_all = permission_exists('call_forward_all');
	$has_do_not_disturb   = permission_exists('do_not_disturb');
	$has_domain_select    = permission_exists('domain_select');
	$has_extension_edit   = permission_exists('extension_edit');
	$has_follow_me        = permission_exists('follow_me');

//add multi-lingual support
	$language = new text;
	$text = $language->get($settings->get('domain', 'language', 'en-us'), 'app/call_forward');

	//create the url object
	$url = new url();

//get posted data and set defaults
	$action = $_POST['action'] ?? '';
	$search = $_POST['search'] ?? '';
	$extensions = $_POST['extensions'] ?? [];

//process the http post data by action
	if (!empty($action) && count($extensions) > 0) {
		//dispatch pre-action hook
		app::dispatch_list_pre_action('call_forward_list_page_hook', $url, $action, $extensions);

		switch ($action) {
			case 'toggle_call_forward':
				if ($has_call_forward) {
					$obj = new call_forward;
					$obj->toggle($extensions);
				}
				break;
			case 'toggle_follow_me':
				if ($has_follow_me) {
					$obj = new follow_me;
					$obj->toggle($extensions);
				}
				break;
			case 'toggle_do_not_disturb':
				if ($has_do_not_disturb) {
					$obj = new do_not_disturb;
					$obj->toggle($extensions);
				}
				break;
		}

		//dispatch post-action hook
		app::dispatch_list_post_action('call_forward_list_page_hook', $url, $action, $extensions);

		header('Location: call_forward.php' . ($search != '' ? '?search=' . urlencode($search) : null));
		exit;
	}

//dispatch pre-query hook
	$query_parameters = [];
	app::dispatch_list_pre_query('call_forward_list_page_hook', $url, $query_parameters);

//get order and order by
	$order_by = $_GET["order_by"] ?? 'extension';
	$order = $_GET["order"] ?? 'asc';
	$sort = $order_by == 'extension' ? 'natural' : null;

//get the search
	$search = strtolower($_GET["search"] ?? '');

//set the show variable
	$show = $_GET['show'] ?? '';

//define select count query
	$sql = "select count(*) from v_extensions ";
	if ($show === "all" && $has_call_forward_all) {
		$sql .= "where true ";
	}
	else {
		$sql .= "where domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	if (!empty($search)) {
		$sql .= "and ( ";
		$sql .= "extension like :search ";
		$sql .= "or lower(description) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%' . $search . '%';
	}
	$sql .= "and enabled = 'true' ";
	if (!$has_extension_edit) {
		if (!empty($_SESSION['user']['extension']) && count($_SESSION['user']['extension']) > 0) {
			$sql .= "and (";
			$x = 0;
			foreach ($_SESSION['user']['extension'] as $row) {
				if ($x > 0) {
					$sql .= "or ";
				}
				$sql .= "extension = '" . $row['user'] . "' ";
				$x++;
			}
			$sql .= ")";
		} else {
			//used to hide any results when a user has not been assigned an extension
			$sql .= "and extension = 'disabled' ";
		}
	}
	$num_rows = $database->select($sql, $parameters ?? null, 'column');
	unset($parameters);

//prepare the paging
	$rows_per_page = !empty($settings->get('domain', 'paging')) ? $settings->get('domain', 'paging') : 50;

	if ($search) {
		$params[] = "search=" . $search;
	}
	if ($order_by) {
		$params[] = "order_by=" . $order_by;
	}
	if ($order) {
		$params[] = "order=" . $order;
	}
	if ($show == "all" && $has_call_forward_all) {
		$params[] = "show=all";
	}
	$param = !empty($params) ? implode('&', $params) : '';
	unset($params);
	$page = $_GET['page'] ?? '';
	if (empty($page)) {
		$page = 0;
		$_GET['page'] = 0;
	}
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the list
	$sql = "select * from v_extensions ";
	if ($show == "all" && $has_call_forward_all) {
		$sql .= "where true ";
	}
	else {
		$sql .= "where domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	if (!empty($search)) {
		$sql .= "and ( ";
		$sql .= "extension like :search ";
		$sql .= "or lower(description) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%' . $search . '%';
	}
	$sql .= "and enabled = 'true' ";
	if (!$has_extension_edit) {
		if (!empty($_SESSION['user']['extension']) && count($_SESSION['user']['extension']) > 0) {
			$sql .= "and (";
			$x = 0;
			foreach ($_SESSION['user']['extension'] as $row) {
				if ($x > 0) {
					$sql .= "or ";
				}
				$sql .= "extension = '" . $row['user'] . "' ";
				$x++;
			}
			$sql .= ")";
		}
		else {
			//used to hide any results when a user has not been assigned an extension
			$sql .= "and extension = 'disabled' ";
		}
	}
	$sql .= order_by($order_by, $order, 'extension', 'asc', $sort);
	$sql .= limit_offset($rows_per_page, $offset);
	$extensions = $database->select($sql, $parameters ?? null, 'all');
	//dispatch post-query hook
	app::dispatch_list_post_query('call_forward_list_page_hook', $url, $extensions);
	unset($parameters);

	//if there are no extensions then set to empty array
	if ($extensions === false) {
		$extensions = [];
	}

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//build the action bar buttons
	$btn_call_forward = '';
	if (count($extensions) > 0 && $has_call_forward) {
		$btn_call_forward = button::create(['type' => 'button', 'label' => $text['label-call_forward'], 'icon' => $settings->get('theme', 'button_icon_toggle'), 'collapse' => false, 'name' => 'btn_toggle_cfwd', 'onclick' => "list_action_set('toggle_call_forward'); modal_open('modal-toggle','btn_toggle');"]);
	}
	$btn_follow_me = '';
	if (count($extensions) > 0 && $has_follow_me) {
		$btn_follow_me = button::create(['type' => 'button', 'label' => $text['label-follow_me'], 'icon' => $settings->get('theme', 'button_icon_toggle'), 'collapse' => false, 'name' => 'btn_toggle_follow', 'onclick' => "list_action_set('toggle_follow_me'); modal_open('modal-toggle','btn_toggle');"]);
	}
	$btn_dnd = '';
	if (count($extensions) > 0 && $has_do_not_disturb) {
		$btn_dnd = button::create(['type' => 'button', 'label' => $text['label-dnd'], 'icon' => $settings->get('theme', 'button_icon_toggle'), 'collapse' => false, 'name' => 'btn_toggle_dnd', 'onclick' => "list_action_set('toggle_do_not_disturb'); modal_open('modal-toggle','btn_toggle');"]);
	}
	$btn_show_all = '';
	if ($show !== 'all' && $has_call_forward_all) {
		$btn_show_all = button::create(['type' => 'button', 'label' => $text['button-show_all'], 'icon' => $settings->get('theme', 'button_icon_all'), 'link' => '?show=all' . (!empty($param) ? '&'.$param : null)]);
	}
	$btn_view_all = '';
	if ($is_included && $num_rows > 10) {
		$btn_view_all = button::create(['type' => 'button', 'label' => $text['button-view_all'], 'icon' => 'diagram-project', 'collapse' => false, 'link' => PROJECT_PATH . '/app/call_forward/call_forward.php']);
	}
	$btn_search = button::create(['label' => $text['button-search'], 'icon' => $settings->get('theme', 'button_icon_search'), 'type' => 'submit', 'id' => 'btn_search']);

//build the modals
	$modal_toggle = '';
	if (count($extensions) > 0) {
		$modal_toggle = modal::create(['id' => 'modal-toggle', 'type' => 'toggle', 'actions' => button::create(['type' => 'button', 'label' => $text['button-continue'], 'icon' => 'check', 'id' => 'btn_toggle', 'style' => 'float: right; margin-left: 15px;', 'collapse' => 'never', 'onclick' => "modal_close(); list_form_submit('form_list');"])]);
	}

//build the table header columns
	$th_domain_name = '';
	if (!$is_included && $show == 'all' && $has_call_forward_all) {
		$th_domain_name = "<th>".$text['label-domain']."</th>";
	}
	$th_extension    = th_order_by('extension', $text['label-extension'], $order_by, $order);
	$th_call_forward = '';
	if ($has_call_forward) {
		$th_call_forward = "<th>".$text['label-call_forward']."</th>";
	}
	$th_follow_me = '';
	if ($has_follow_me) {
		$th_follow_me = "<th>".$text['label-follow_me']."</th>";
	}
	$th_dnd = '';
	if ($has_do_not_disturb) {
		$th_dnd = "<th>".$text['label-dnd']."</th>";
	}

//build the row data
	$x = 0;
	if (!empty($extensions)) {
		foreach ($extensions as &$row) {
			app::dispatch_list_render_row('call_forward_list_page_hook', $url, $row, $x);
			$list_row_url = PROJECT_PATH . "/app/call_forward/call_forward_edit.php?id=".$row['extension_uuid'];
			if ($row['domain_uuid'] != $_SESSION['domain_uuid'] && $has_domain_select) {
				$list_row_url .= '&domain_uuid='.urlencode($row['domain_uuid']).'&domain_change=true';
			}
			$row['_list_row_url'] = $list_row_url;
			$row['_forward_all_display'] = boolean_value($row['forward_all_enabled']) ? escape(format_phone($row['forward_all_destination'])) : '';
			$row['_follow_me_display'] = '';
			if ($has_follow_me && boolean_value($row['follow_me_enabled']) && is_uuid($row['follow_me_uuid'])) {
				$sql = "select count(*) from v_follow_me_destinations ";
				$sql .= "where follow_me_uuid = :follow_me_uuid ";
				$sql .= "and domain_uuid = :domain_uuid ";
				$fm_params['follow_me_uuid'] = $row['follow_me_uuid'];
				$fm_params['domain_uuid'] = $_SESSION['domain_uuid'];
				$follow_me_destination_count = $database->select($sql, $fm_params, 'column');
				unset($sql, $fm_params);
				if ($follow_me_destination_count) {
					$row['_follow_me_display'] = $text['label-enabled'] . ' (' . $follow_me_destination_count . ')';
				}
			}
			$row['_dnd_display'] = boolean_value($row['do_not_disturb']) ? $text['label-enabled'] : '';
			$row['_domain_display'] = '';
			if (!empty($show) && $show == 'all' && $has_call_forward_all) {
				$row['_domain_display'] = !empty($_SESSION['domains'][$row['domain_uuid']]['domain_name']) ? escape($_SESSION['domains'][$row['domain_uuid']]['domain_name']) : $text['label-global'];
			}
			$row['_edit_button'] = '';
			if ($list_row_edit_button) {
				$row['_edit_button'] = button::create(['type' => 'button', 'title' => $text['button-edit'], 'icon' => $settings->get('theme', 'button_icon_edit'), 'link' => $list_row_url]);
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
	$template->assign('text',                 $text);
	$template->assign('num_rows',             $num_rows);
	$template->assign('rows',                 $extensions ?? []);
	$template->assign('search',               $search);
	$template->assign('show',                 $show);
	$template->assign('paging_controls',      $paging_controls);
	$template->assign('paging_controls_mini', $paging_controls_mini);
	$template->assign('token',                $token);
	$template->assign('is_included',          $is_included);
	$template->assign('has_call_forward',     $has_call_forward);
	$template->assign('has_call_forward_all', $has_call_forward_all);
	$template->assign('has_do_not_disturb',   $has_do_not_disturb);
	$template->assign('has_follow_me',        $has_follow_me);
	$template->assign('list_row_edit_button', $list_row_edit_button);
	$template->assign('btn_call_forward',     $btn_call_forward);
	$template->assign('btn_follow_me',        $btn_follow_me);
	$template->assign('btn_dnd',              $btn_dnd);
	$template->assign('btn_show_all',         $btn_show_all);
	$template->assign('btn_view_all',         $btn_view_all);
	$template->assign('btn_search',           $btn_search);
	$template->assign('modal_toggle',         $modal_toggle);
	$template->assign('th_domain_name',       $th_domain_name);
	$template->assign('th_extension',         $th_extension);
	$template->assign('th_call_forward',      $th_call_forward);
	$template->assign('th_follow_me',         $th_follow_me);
	$template->assign('th_dnd',               $th_dnd);

//invoke pre-render hook
	app::dispatch_list_pre_render('call_forward_list_page_hook', $url, $template);

//include header
	if (!$is_included) {
		$document['title'] = $text['title-call_forward'];
	}
	require_once "resources/header.php";

//set the back button
	$_SESSION['call_forward_back'] = $_SERVER['PHP_SELF'];

//render the template
	$html = $template->render('call_forward_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('call_forward_list_page_hook', $url, $html);
	echo $html;

	if (!$is_included) {
		require_once "resources/footer.php";
	}
