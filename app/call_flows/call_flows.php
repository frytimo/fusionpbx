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
	require_once "resources/paging.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('call_flow_view')) {
		echo "access denied";
		exit;
	}
	$has_call_flow_add     = permission_exists('call_flow_add');
	$has_call_flow_all     = permission_exists('call_flow_all');
	$has_call_flow_context = permission_exists('call_flow_context');
	$has_call_flow_delete  = permission_exists('call_flow_delete');
	$has_call_flow_edit    = permission_exists('call_flow_edit');
	$has_domain_select     = permission_exists('domain_select');

//add multi-lingual support
	$text = new text()->get();
	//create the url object
	$url = new url();

//set additional variables
	$show = $_GET["show"] ?? '';

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);

//get search
	$search = $_REQUEST['search'] ?? '';

//get posted data
	if (!empty($_POST['call_flows'])) {
		$action = $_POST['action'];
		$call_flows = $_POST['call_flows'];
		$toggle_field = $_POST['toggle_field'];
	}

//process the http post data by action
	if (!empty($action) && !empty($call_flows)) {
		//dispatch pre-action hook
		app::dispatch_list_pre_action('call_flow_list_page_hook', $url, $action, $call_flows);

		switch ($action) {
			case 'copy':
				if ($has_call_flow_add) {
					$obj = new call_flows;
					$obj->copy($call_flows);
				}
				break;
			case 'toggle':
				if ($has_call_flow_edit) {
					$obj = new call_flows;
					$obj->toggle_field = $toggle_field;
					$obj->toggle($call_flows);
				}
				break;
			case 'delete':
				if ($has_call_flow_delete) {
					$obj = new call_flows;
					$obj->delete($call_flows);
				}
				break;
		}

		//dispatch post-action hook
		app::dispatch_list_post_action('call_flow_list_page_hook', $url, $action, $call_flows);

		header('Location: call_flows.php'.($search != '' ? '?search='.urlencode($search) : ''));
		exit;
	}

//dispatch pre-query hook
	$query_parameters = [];
	app::dispatch_list_pre_query('call_flow_list_page_hook', $url, $query_parameters);

//get variables used to control the order
	$order_by = $_GET["order_by"] ?? '';
	$order = $_GET["order"] ?? '';
	$sort = $order_by == 'call_flow_extension' ? 'natural' : null;

//add the search term
	$search = strtolower($search ?? '');
	if (!empty($search)) {
		$sql_search = "and (";
		$sql_search .= "lower(call_flow_name) like :search ";
		$sql_search .= "or lower(call_flow_extension) like :search ";
		$sql_search .= "or lower(call_flow_feature_code) like :search ";
		$sql_search .= "or lower(call_flow_context) like :search ";
		$sql_search .= "or lower(call_flow_pin_number) like :search ";
		$sql_search .= "or lower(call_flow_label) like :search ";
		$sql_search .= "or lower(call_flow_alternate_label) like :search ";
		$sql_search .= "or lower(call_flow_description) like :search ";
		$sql_search .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}

//prepare to page the results
	$sql = "select count(*) from v_call_flows ";
	$sql .= "where true ";
	if ($show != "all" || !$has_call_flow_all) {
		$sql .= "and (domain_uuid = :domain_uuid or domain_uuid is null) ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	$sql .= $sql_search ?? '';
	$num_rows = $database->select($sql, $parameters ?? null, 'column');

//prepare to page the results
	$rows_per_page = $settings->get('domain', 'paging', 50);
	$param = "&search=".urlencode($search);
	if ($show == "all" && $has_call_flow_all) {
		$param .= "&show=all";
	}
	$page = $_GET['page'] ?? '';
	if (empty($page)) { $page = 0; $_GET['page'] = 0; }
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the list
	$sql = "select ";
	$sql .= "call_flow_uuid, ";
	$sql .= "domain_uuid, ";
	$sql .= "dialplan_uuid, ";
	$sql .= "call_flow_name, ";
	$sql .= "call_flow_extension, ";
	$sql .= "call_flow_feature_code, ";
	$sql .= "call_flow_context, ";
	$sql .= "call_flow_status, ";
	$sql .= "call_flow_pin_number, ";
	$sql .= "call_flow_label, ";
	$sql .= "call_flow_sound, ";
	$sql .= "call_flow_app, ";
	$sql .= "call_flow_data, ";
	$sql .= "call_flow_alternate_label, ";
	$sql .= "call_flow_alternate_sound, ";
	$sql .= "call_flow_alternate_app, ";
	$sql .= "call_flow_alternate_data, ";
	$sql .= "cast(call_flow_enabled as text), ";
	$sql .= "call_flow_description ";
	$sql .= "from v_call_flows ";
	$sql .= "where true ";
	if ($show != "all" || !$has_call_flow_all) {
		$sql .= "and (domain_uuid = :domain_uuid or domain_uuid is null) ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	$sql .= $sql_search ?? '';
	$sql .= order_by($order_by, $order, 'call_flow_name', 'asc', $sort);
	$sql .= limit_offset($rows_per_page, $offset);
	$call_flows = $database->select($sql, $parameters ?? null, 'all');
	//dispatch post-query hook
	app::dispatch_list_post_query('call_flow_list_page_hook', $url, $call_flows);
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//build the action bar buttons
	$btn_add = '';
	if ($has_call_flow_add) {
		$btn_add = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','link'=>'call_flow_edit.php']);
	}
	$btn_copy = '';
	if ($has_call_flow_add && $call_flows) {
		$btn_copy = button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$settings->get('theme', 'button_icon_copy'),'id'=>'btn_copy','name'=>'btn_copy','style'=>'display: none;','onclick'=>"modal_open('modal-copy','btn_copy');"]);
	}
	$btn_toggle = '';
	if ($has_call_flow_edit && $call_flows) {
		$btn_toggle = button::create(['type'=>'button','label'=>$text['button-toggle'],'icon'=>$settings->get('theme', 'button_icon_toggle'),'id'=>'btn_toggle','name'=>'btn_toggle','style'=>'display: none;','onclick'=>"toggle_select(); this.blur();"]);
		$btn_toggle .= "<select class='formfld' style='display: none; width: auto;' id='call_flow_feature' onchange=\"if (this.selectedIndex != 0) { modal_open('modal-toggle','btn_toggle'); }\">";
		$btn_toggle .= "<option value='' selected='selected'>".$text['label-select']."</option>";
		$btn_toggle .= "<option value='call_flow_status'>".$text['label-call_flow_status']."</option>";
		$btn_toggle .= "<option value='call_flow_enabled'>".$text['label-enabled']."</option>";
		$btn_toggle .= "</select>";
	}
	$btn_delete = '';
	if ($has_call_flow_delete && $call_flows) {
		$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	$btn_show_all = '';
	if ($has_call_flow_all && $show !== 'all') {
		$btn_show_all = button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$settings->get('theme', 'button_icon_all'),'link'=>'?type=&show=all'.($search != '' ? "&search=".urlencode($search) : null)]);
	}
	$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);

//build the modals
	$modal_copy = '';
	if ($has_call_flow_add && $call_flows) {
		$modal_copy = modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
	}
	$modal_toggle = '';
	if ($has_call_flow_edit && $call_flows) {
		$modal_toggle = modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); document.getElementById('toggle_field').value = document.getElementById('call_flow_feature').options[document.getElementById('call_flow_feature').selectedIndex].value; list_action_set('toggle'); list_form_submit('form_list');"])]);
	}
	$modal_delete = '';
	if ($has_call_flow_delete && $call_flows) {
		$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

//build the table header columns
	$th_domain_name = '';
	if ($show == 'all' && $has_call_flow_all) {
		$th_domain_name = th_order_by('domain_name', $text['label-domain'], $order_by, $order, $param, "class='shrink'");
	}
	$th_call_flow_name         = th_order_by('call_flow_name', $text['label-call_flow_name'], $order_by, $order);
	$th_call_flow_extension    = th_order_by('call_flow_extension', $text['label-call_flow_extension'], $order_by, $order);
	$th_call_flow_feature_code = th_order_by('call_flow_feature_code', $text['label-call_flow_feature_code'], $order_by, $order);
	$th_call_flow_status       = th_order_by('call_flow_status', $text['label-call_flow_status'], $order_by, $order);
	$th_call_flow_context      = '';
	if ($has_call_flow_context) {
		$th_call_flow_context = th_order_by('call_flow_context', $text['label-call_flow_context'], $order_by, $order);
	}
	$th_call_flow_enabled      = th_order_by('call_flow_enabled', $text['label-enabled'], $order_by, $order, null, "class='center'");
	$th_call_flow_description  = th_order_by('call_flow_description', $text['label-call_flow_description'], $order_by, $order, null, "class='hide-sm-dn'");

//build the row data
	$x = 0;
	if (!empty($call_flows)) {
		foreach ($call_flows as &$row) {
			app::dispatch_list_render_row('call_flow_list_page_hook', $url, $row, $x);
			$list_row_url = '';
			if ($has_call_flow_edit) {
				$list_row_url = "call_flow_edit.php?id=".urlencode($row['call_flow_uuid']);
				if ($row['domain_uuid'] != $_SESSION['domain_uuid'] && $has_domain_select) {
					$list_row_url .= '&domain_uuid='.urlencode($row['domain_uuid']).'&domain_change=true';
				}
			}
			$row['_list_row_url'] = $list_row_url;
			$status_label = $row['call_flow_status'] != 'false' ? $row['call_flow_label'] : $row['call_flow_alternate_label'];
			$row['_status_label'] = escape($status_label);
			$row['_status_toggle_button'] = '';
			if ($has_call_flow_edit) {
				$row['_status_toggle_button'] = button::create(['type'=>'submit','class'=>'link','label'=>escape($status_label),'title'=>$text['button-toggle'],'onclick'=>"list_self_check('checkbox_{$x}'); list_action_set('toggle'); document.getElementById('toggle_field').value = 'call_flow_status'; list_form_submit('form_list')"]);
			}
			$row['_enabled_label'] = $text['label-'.($row['call_flow_enabled'] == 'true' ? 'true' : 'false')];
			$row['_enabled_toggle_button'] = '';
			if ($has_call_flow_edit) {
				$row['_enabled_toggle_button'] = button::create(['type'=>'submit','class'=>'link','label'=>$text['label-'.($row['call_flow_enabled'] == 'true' ? 'true' : 'false')],'title'=>$text['button-toggle'],'onclick'=>"list_self_check('checkbox_{$x}'); list_action_set('toggle'); document.getElementById('toggle_field').value = 'call_flow_enabled'; list_form_submit('form_list')"]);
			}
			$row['_domain_display'] = '';
			if (!empty($show) && $show == 'all' && $has_call_flow_all) {
				$row['_domain_display'] = !empty($_SESSION['domains'][$row['domain_uuid']]['domain_name']) ? escape($_SESSION['domains'][$row['domain_uuid']]['domain_name']) : $text['label-global'];
			}
			$row['_edit_button'] = '';
			if ($has_call_flow_edit && $list_row_edit_button) {
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
	$template->assign('rows',                    $call_flows ?? []);
	$template->assign('search',                  $search);
	$template->assign('show',                    $show);
	$template->assign('paging_controls',         $paging_controls);
	$template->assign('paging_controls_mini',    $paging_controls_mini);
	$template->assign('token',                   $token);
	$template->assign('has_call_flow_add',        $has_call_flow_add);
	$template->assign('has_call_flow_all',        $has_call_flow_all);
	$template->assign('has_call_flow_context',    $has_call_flow_context);
	$template->assign('has_call_flow_delete',     $has_call_flow_delete);
	$template->assign('has_call_flow_edit',       $has_call_flow_edit);
	$template->assign('list_row_edit_button',     $list_row_edit_button);
	$template->assign('btn_add',                  $btn_add);
	$template->assign('btn_copy',                 $btn_copy);
	$template->assign('btn_toggle',               $btn_toggle);
	$template->assign('btn_delete',               $btn_delete);
	$template->assign('btn_show_all',             $btn_show_all);
	$template->assign('btn_search',               $btn_search);
	$template->assign('modal_copy',               $modal_copy);
	$template->assign('modal_toggle',             $modal_toggle);
	$template->assign('modal_delete',             $modal_delete);
	$template->assign('th_domain_name',           $th_domain_name);
	$template->assign('th_call_flow_name',        $th_call_flow_name);
	$template->assign('th_call_flow_extension',   $th_call_flow_extension);
	$template->assign('th_call_flow_feature_code',$th_call_flow_feature_code);
	$template->assign('th_call_flow_status',      $th_call_flow_status);
	$template->assign('th_call_flow_context',     $th_call_flow_context);
	$template->assign('th_call_flow_enabled',     $th_call_flow_enabled);
	$template->assign('th_call_flow_description', $th_call_flow_description);

//invoke pre-render hook
	app::dispatch_list_pre_render('call_flow_list_page_hook', $url, $template);

//include header
	$document['title'] = $text['title-call_flows'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('call_flows_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('call_flow_list_page_hook', $url, $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";
