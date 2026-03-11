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
	Portions created by the Initial Developer are Copyright (C) 2008-2026
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>

	The original Call Block was written by Gerrit Visser <gerrit308@gmail.com>
	All of it has been rewritten over years.
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (!permission_exists('call_block_view')) {
		echo "access denied";
		exit;
	}
	$has_call_block_add       = permission_exists('call_block_add');
	$has_call_block_all       = permission_exists('call_block_all');
	$has_call_block_delete    = permission_exists('call_block_delete');
	$has_call_block_domain    = permission_exists('call_block_domain');
	$has_call_block_edit      = permission_exists('call_block_edit');
	$has_call_block_extension = permission_exists('call_block_extension');
	$has_domain_all           = permission_exists('domain_all');
	$has_domain_select        = permission_exists('domain_select');

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
	if (!empty($_POST['call_blocks'])) {
		$action = $_POST['action'];
		$search = $_POST['search'] ?? '';
		$call_blocks = $_POST['call_blocks'];
	}

//process the http post data by action
	if (!empty($action) && !empty($call_blocks)) {
		//dispatch pre-action hook
		app::dispatch_list_pre_action('call_block_list_page_hook', $url, $action, $call_blocks);

		switch ($action) {
			case 'copy':
				if ($has_call_block_add) {
					$obj = new call_block;
					$obj->copy($call_blocks);
				}
				break;
			case 'toggle':
				if ($has_call_block_edit) {
					$obj = new call_block;
					$obj->toggle($call_blocks);
				}
				break;
			case 'delete':
				if ($has_call_block_delete) {
					$obj = new call_block;
					$obj->delete($call_blocks);
				}
				break;
		}

		//dispatch post-action hook
		app::dispatch_list_post_action('call_block_list_page_hook', $url, $action, $call_blocks);

		header('Location: call_block.php'.($search != '' ? '?search='.urlencode($search) : ''));
		exit;
	}

//dispatch pre-query hook
	$query_parameters = [];
	app::dispatch_list_pre_query('call_block_list_page_hook', $url, $query_parameters);

//get variables used to control the order
	$order_by = $_GET["order_by"] ?? '';
	$order = $_GET["order"] ?? '';

//add the search term
	if (!empty($_GET["search"])) {
		$search = strtolower($_GET["search"]);
	}

//get the count
	$sql = "select count(*) from view_call_block ";
	$sql .= "where true ";
	if ($show == "all" && $has_call_block_all) {
		//show all records across all domains
	}
	else {
		$sql .= "and ( ";
		$sql .= "	domain_uuid = :domain_uuid ";
		if ($has_call_block_domain) {
			$sql .= "	or domain_uuid is null ";
		}
		$sql .= ") ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	if (!$has_call_block_extension && !empty($_SESSION['user']['extension'])) {
		$sql .= "and extension_uuid in (";
		$x = 0;
		foreach ($_SESSION['user']['extension'] as $field) {
			if (is_uuid($field['extension_uuid'])) {
				$sql .= ($x == 0) ? "'".$field['extension_uuid']."'" : ",'".$field['extension_uuid']."'";
			}
			$x++;
		}
		$sql .= ") ";
	}
	if (!empty($search)) {
		$sql .= "and (";
		$sql .= " lower(call_block_name) like :search ";
		$sql .= " or lower(call_block_direction) like :search ";
		$sql .= " or lower(call_block_number) like :search ";
		$sql .= " or lower(call_block_app) like :search ";
		$sql .= " or lower(call_block_data) like :search ";
		$sql .= " or lower(call_block_description) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$num_rows = $database->select($sql, $parameters ?? null, 'column');
	unset($parameters);

//prepare to page the results
	$rows_per_page = $settings->get('domain', 'paging', 50);
	$param = "&search=".$search;
	if ($show == "all" && $has_call_block_all) {
		$param .= "&show=all";
	}
	$page = $_GET['page'] ?? '';
	if (empty($page)) { $page = 0; $_GET['page'] = 0; }
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
	$sql = "select domain_uuid, call_block_uuid, call_block_direction, extension_uuid, call_block_name, ";
	$sql .= " call_block_country_code, call_block_number, extension, number_alias, call_block_count, ";
	$sql .= " call_block_app, call_block_data, ";
	$sql .= " to_char(timezone(:time_zone, insert_date), 'DD Mon YYYY') as date_formatted, \n";
	$sql .= " to_char(timezone(:time_zone, insert_date), '".$time_format."') as time_formatted, \n";
	$sql .= " cast(call_block_enabled as text), call_block_description, insert_date, insert_user, update_date, update_user ";
	$sql .= "from view_call_block ";
	$sql .= "where true ";
	if ($show == "all" && $has_call_block_all) {
		//$sql .= "and (domain_uuid = :domain_uuid or domain_uuid is null) ";
		//$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	else {
		$sql .= "and ( ";
		$sql .= "	domain_uuid = :domain_uuid ";
		if ($has_call_block_domain) {
			$sql .= "	or domain_uuid is null ";
		}
		$sql .= ") ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	if (!$has_call_block_extension && !empty($_SESSION['user']['extension']) && count($_SESSION['user']['extension']) > 0) {
		$sql .= "and extension_uuid in (";
		$x = 0;
		foreach ($_SESSION['user']['extension'] as $field) {
			if (is_uuid($field['extension_uuid'])) {
				$sql .= ($x == 0) ? "'".$field['extension_uuid']."'" : ",'".$field['extension_uuid']."'";
			}
			$x++;
		}
		$sql .= ") ";
	}
	if (!empty($search)) {
		$sql .= "and (";
		$sql .= " lower(call_block_name) like :search ";
		$sql .= " or lower(call_block_direction) like :search ";
		$sql .= " or lower(call_block_number) like :search ";
		$sql .= " or lower(call_block_app) like :search ";
		$sql .= " or lower(call_block_data) like :search ";
		$sql .= " or lower(call_block_description) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$sql .= order_by($order_by, $order, ['domain_uuid','call_block_country_code','call_block_number']);
	$sql .= limit_offset($rows_per_page, $offset);
	$parameters['time_zone'] = $time_zone;
	$result = $database->select($sql, $parameters ?? null, 'all');
	//dispatch post-query hook
	app::dispatch_list_post_query('call_block_list_page_hook', $url, $result);
	unset($sql, $parameters);

//determine if any global
	$global_call_blocks = false;
	if ($has_call_block_domain && !empty($result) && is_array($result) && @sizeof($result) != 0) {
		foreach ($result as $row) {
			if (!is_uuid($row['domain_uuid'])) { $global_call_blocks = true; break; }
		}
	}

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//build the action bar buttons
	$btn_add = '';
	if ($has_call_block_add) {
		$btn_add = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','link'=>'call_block_edit.php']);
	}
	$btn_copy = '';
	if ($has_call_block_add && $result) {
		$btn_copy = button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$settings->get('theme', 'button_icon_copy'),'id'=>'btn_copy','name'=>'btn_copy','style'=>'display: none;','onclick'=>"modal_open('modal-copy','btn_copy');"]);
	}
	$btn_toggle = '';
	if ($has_call_block_edit && $result) {
		$btn_toggle = button::create(['type'=>'button','label'=>$text['button-toggle'],'icon'=>$settings->get('theme', 'button_icon_toggle'),'id'=>'btn_toggle','name'=>'btn_toggle','style'=>'display: none;','onclick'=>"modal_open('modal-toggle','btn_toggle');"]);
	}
	$btn_delete = '';
	if ($has_call_block_delete && $result) {
		$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	$btn_show_all = '';
	if ($has_call_block_all && $show !== 'all') {
		$btn_show_all = button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$settings->get('theme', 'button_icon_all'),'link'=>'?type='.urlencode($destination_type ?? '').'&show=all'.($search != '' ? "&search=".urlencode($search ?? '') : null)]);
	}
	$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);

//build the modals
	$modal_copy = '';
	if ($has_call_block_add && $result) {
		$modal_copy = modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
	}
	$modal_toggle = '';
	if ($has_call_block_edit && $result) {
		$modal_toggle = modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
	}
	$modal_delete = '';
	if ($has_call_block_delete && $result) {
		$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

//build the table header columns
	$th_domain_name = '';
	if (!empty($show) && $show == 'all' && $has_domain_all) {
		$th_domain_name = th_order_by('domain_name', $text['label-domain'], $order_by, $order);
	} elseif ($has_call_block_domain && $global_call_blocks) {
		$th_domain_name = th_order_by('domain_uuid', $text['label-domain'], $order_by, $order, null, "style='width: 1%;' class='center'");
	}
	$th_direction    = th_order_by('call_block_direction', $text['label-direction'], $order_by, $order, null, "style='width: 1%;' class='center'");
	$th_extension    = th_order_by('extension', $text['label-extension'], $order_by, $order, null, "class='center'");
	$th_name         = th_order_by('call_block_name', $text['label-caller_id_name'], $order_by, $order);
	$th_country_code = th_order_by('call_block_country_code', $text['label-country_code'], $order_by, $order);
	$th_number       = th_order_by('call_block_number', $text['label-number'], $order_by, $order);
	$th_count        = th_order_by('call_block_count', $text['label-count'], $order_by, $order, '', "class='center hide-sm-dn'");
	$th_action       = th_order_by('call_block_action', $text['label-action'], $order_by, $order);
	$th_enabled      = th_order_by('call_block_enabled', $text['label-enabled'], $order_by, $order, null, "class='center'");
	$th_date         = th_order_by('insert_date', $text['label-date-added'], $order_by, $order, null, "class='shrink no-wrap'");

//build the row data
	$x = 0;
	$template_dir = $settings->get('domain', 'template', 'default');
	if (!empty($result)) {
		foreach ($result as &$row) {
			app::dispatch_list_render_row('call_block_list_page_hook', $url, $row, $x);
			$list_row_url = '';
			if ($has_call_block_edit) {
				$list_row_url = "call_block_edit.php?id=".urlencode($row['call_block_uuid']);
				if ($row['domain_uuid'] != $_SESSION['domain_uuid'] && $has_domain_select) {
					$list_row_url .= '&domain_uuid='.urlencode($row['domain_uuid'] ?? '').'&domain_change=true';
				}
			}
			$row['_list_row_url'] = $list_row_url;
			$row['_direction_image'] = '';
			switch ($row['call_block_direction']) {
				case 'inbound':
					$row['_direction_image'] = "<img src='/themes/".$template_dir."/images/icon_cdr_inbound_answered.png' style='border: none;' title='".$text['label-inbound']."'>";
					break;
				case 'outbound':
					$row['_direction_image'] = "<img src='/themes/".$template_dir."/images/icon_cdr_outbound_answered.png' style='border: none;' title='".$text['label-outbound']."'>";
					break;
			}
			$row['_extension_display'] = !empty($row['extension']) ? escape($row['extension']) : $text['label-all'];
			$row['_number_formatted']  = escape(format_phone($row['call_block_number']));
			$row['_action_display']    = $text['label-'.$row['call_block_app']]." ".escape($row['call_block_data']);
			$row['_enabled_label'] = $text['label-'.$row['call_block_enabled']];
			$row['_toggle_button'] = '';
			if ($has_call_block_edit) {
				$row['_toggle_button'] = button::create(['type'=>'submit','class'=>'link','label'=>$text['label-'.$row['call_block_enabled']],'title'=>$text['button-toggle'],'onclick'=>"list_self_check('checkbox_{$x}'); list_action_set('toggle'); list_form_submit('form_list')"]);
			}
			$row['_edit_button'] = '';
			if ($has_call_block_edit && $list_row_edit_button) {
				$row['_edit_button'] = button::create(['type'=>'button','title'=>$text['button-edit'],'icon'=>$settings->get('theme', 'button_icon_edit'),'link'=>$list_row_url]);
			}
			$row['_domain_cell'] = '';
			if (!empty($show) && $show == 'all' && $has_domain_all) {
				if (!empty($row['domain_uuid']) && is_uuid($row['domain_uuid'])) {
					$row['_domain_cell'] = "<td>".escape($_SESSION['domains'][$row['domain_uuid']]['domain_name'])."</td>";
				} else {
					$row['_domain_cell'] = "<td>".$text['label-global']."</td>";
				}
			} elseif ($global_call_blocks) {
				if ($has_call_block_domain && !is_uuid($row['domain_uuid'])) {
					$row['_domain_cell'] = "<td>".$text['label-global']."</td>";
				} else {
					$row['_domain_cell'] = "<td class='overflow'>".escape($_SESSION['domains'][$row['domain_uuid']]['domain_name'])."</td>";
				}
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
	$template->assign('rows',                 $result ?? []);
	$template->assign('search',               $search);
	$template->assign('show',                 $show);
	$template->assign('paging_controls',      $paging_controls);
	$template->assign('paging_controls_mini', $paging_controls_mini);
	$template->assign('token',                $token);
	$template->assign('has_call_block_add',    $has_call_block_add);
	$template->assign('has_call_block_all',    $has_call_block_all);
	$template->assign('has_call_block_delete', $has_call_block_delete);
	$template->assign('has_call_block_domain', $has_call_block_domain);
	$template->assign('has_call_block_edit',   $has_call_block_edit);
	$template->assign('has_domain_all',        $has_domain_all);
	$template->assign('list_row_edit_button',  $list_row_edit_button);
	$template->assign('global_call_blocks',    $global_call_blocks);
	$template->assign('btn_add',               $btn_add);
	$template->assign('btn_copy',              $btn_copy);
	$template->assign('btn_toggle',            $btn_toggle);
	$template->assign('btn_delete',            $btn_delete);
	$template->assign('btn_show_all',          $btn_show_all);
	$template->assign('btn_search',            $btn_search);
	$template->assign('modal_copy',            $modal_copy);
	$template->assign('modal_toggle',          $modal_toggle);
	$template->assign('modal_delete',          $modal_delete);
	$template->assign('th_domain_name',        $th_domain_name);
	$template->assign('th_direction',          $th_direction);
	$template->assign('th_extension',          $th_extension);
	$template->assign('th_name',               $th_name);
	$template->assign('th_country_code',       $th_country_code);
	$template->assign('th_number',             $th_number);
	$template->assign('th_count',              $th_count);
	$template->assign('th_action',             $th_action);
	$template->assign('th_enabled',            $th_enabled);
	$template->assign('th_date',               $th_date);

//invoke pre-render hook
	app::dispatch_list_pre_render('call_block_list_page_hook', $url, $template);

//include the header
	$document['title'] = $text['title-call_block'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('call_block_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('call_block_list_page_hook', $url, $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";
