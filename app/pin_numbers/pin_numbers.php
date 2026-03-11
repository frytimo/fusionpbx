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
	Portions created by the Initial Developer are Copyright (C) 2016-2025
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (!permission_exists('pin_number_view')) {
		echo "access denied";
		exit;
	}
	$has_domain_select     = permission_exists('domain_select');
	$has_pin_number_add    = permission_exists('pin_number_add');
	$has_pin_number_delete = permission_exists('pin_number_delete');
	$has_pin_number_edit   = permission_exists('pin_number_edit');

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);

//add multi-lingual support
	$text = new text()->get();
	//create the url object
	$url = new url();

//get posted data
	if (is_array($_POST['pin_numbers'])) {
		$action = $_POST['action'];
		$search = $_POST['search'] ?? '';
		$pin_numbers = $_POST['pin_numbers'];
	}

//process the http post data by action
	if ($action != '' && is_array($pin_numbers) && @sizeof($pin_numbers) != 0) {
		//dispatch pre-action hook
		app::dispatch_list_pre_action('pin_number_list_page_hook', $url, $action, $pin_numbers);

		switch ($action) {
			case 'copy':
				if ($has_pin_number_add) {
					$obj = new pin_numbers;
					$obj->copy($pin_numbers);
				}
				break;
			case 'toggle':
				if ($has_pin_number_edit) {
					$obj = new pin_numbers;
					$obj->toggle($pin_numbers);
				}
				break;
			case 'delete':
				if ($has_pin_number_delete) {
					$obj = new pin_numbers;
					$obj->delete($pin_numbers);
				}
				break;
		}

		//dispatch post-action hook
		app::dispatch_list_post_action('pin_number_list_page_hook', $url, $action, $pin_numbers);

		header('Location: pin_numbers.php'.($search != '' ? '?search='.urlencode($search) : ''));
		exit;
	}

//dispatch pre-query hook
	$query_parameters = [];
	app::dispatch_list_pre_query('pin_number_list_page_hook', $url, $query_parameters);

//get order and order by
	$order_by = $_GET["order_by"];
	$order = $_GET["order"];

//add the search term
	$search = strtolower($_GET["search"]);
	if (!empty($search)) {
		$sql_search = "and (";
		$sql_search .= "lower(pin_number) like :search ";
		$sql_search .= "or lower(accountcode) like :search ";
		$sql_search .= "or lower(description) like :search ";
		$sql_search .= ")";
		$parameters['search'] = '%'.$search.'%';
	}

//prepare to page the results
	$sql = "select count(*) from v_pin_numbers ";
	$sql .= "where domain_uuid = :domain_uuid ";
	$sql .= $sql_search;
	$parameters['domain_uuid'] = $domain_uuid;
	$num_rows = $database->select($sql, $parameters, 'column');

//prepare to page the results
	$rows_per_page = $settings->get('domain', 'paging', 50);
	$param = "&search=".$search;
	$page = is_numeric($_GET['page']) ? $_GET['page'] : 0;
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the list

	$sql = "select domain_uuid, pin_number_uuid, pin_number, accountcode, description, cast(enabled as text) ";
	$sql .= "from v_pin_numbers ";
	$sql .= "where domain_uuid = :domain_uuid ";
	$sql .= $sql_search;
	$sql .= order_by($order_by, $order, 'pin_number', 'asc');
	$sql .= limit_offset($rows_per_page, $offset);
	$pin_numbers = $database->select($sql, $parameters, 'all');
	//dispatch post-query hook
	app::dispatch_list_post_query('pin_number_list_page_hook', $url, $pin_numbers);
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//build the action bar buttons
	$btn_export = button::create(['type'=>'button','label'=>$text['button-export'],'icon'=>$settings->get('theme', 'button_icon_export'),'style'=>'margin-right: 15px;','link'=>'pin_download.php']);
	$btn_add = '';
	if ($has_pin_number_add) {
		$btn_add = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','link'=>'pin_number_edit.php']);
	}
	$btn_copy = '';
	if ($has_pin_number_add && $pin_numbers) {
		$btn_copy = button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$settings->get('theme', 'button_icon_copy'),'name'=>'btn_copy','onclick'=>"modal_open('modal-copy','btn_copy');"]);
	}
	$btn_toggle = '';
	if ($has_pin_number_edit && $pin_numbers) {
		$btn_toggle = button::create(['type'=>'button','label'=>$text['button-toggle'],'icon'=>$settings->get('theme', 'button_icon_toggle'),'name'=>'btn_toggle','onclick'=>"modal_open('modal-toggle','btn_toggle');"]);
	}
	$btn_delete = '';
	if ($has_pin_number_delete && $pin_numbers) {
		$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'name'=>'btn_delete','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search','style'=>($search != '' ? 'display: none;' : null)]);
	$btn_reset  = button::create(['label'=>$text['button-reset'],'icon'=>$settings->get('theme', 'button_icon_reset'),'type'=>'button','id'=>'btn_reset','link'=>'pin_numbers.php','style'=>($search == '' ? 'display: none;' : null)]);

//build the modals
	$modal_copy = '';
	if ($has_pin_number_add && $pin_numbers) {
		$modal_copy = modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
	}
	$modal_toggle = '';
	if ($has_pin_number_edit && $pin_numbers) {
		$modal_toggle = modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
	}
	$modal_delete = '';
	if ($has_pin_number_delete && $pin_numbers) {
		$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

//build the table header columns
	$th_pin_number  = th_order_by('pin_number', $text['label-pin_number'], $order_by, $order);
	$th_accountcode = th_order_by('accountcode', $text['label-accountcode'], $order_by, $order);
	$th_enabled     = th_order_by('enabled', $text['label-enabled'], $order_by, $order, null, "class='center'");
	$th_description = th_order_by('description', $text['label-description'], $order_by, $order, null, "class='hide-sm-dn'");

//build the row data
	$x = 0;
	foreach ($pin_numbers as &$row) {
		app::dispatch_list_render_row('pin_number_list_page_hook', $url, $row, $x);
		$list_row_url = '';
		if ($has_pin_number_edit) {
			$list_row_url = "pin_number_edit.php?id=".urlencode($row['pin_number_uuid']);
			if ($row['domain_uuid'] != $_SESSION['domain_uuid'] && $has_domain_select) {
				$list_row_url .= '&domain_uuid='.urlencode($row['domain_uuid']).'&domain_change=true';
			}
		}
		$row['_list_row_url']  = $list_row_url;
		$row['_enabled_label'] = $text['label-'.$row['enabled']];
		$row['_toggle_button'] = '';
		if ($has_pin_number_edit) {
			$row['_toggle_button'] = button::create(['type'=>'submit','class'=>'link','label'=>$text['label-'.$row['enabled']],'title'=>$text['button-toggle'],'onclick'=>"list_self_check('checkbox_{$x}'); list_action_set('toggle'); list_form_submit('form_list')"]);
		}
		$row['_edit_button'] = '';
		if ($has_pin_number_edit && $list_row_edit_button) {
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
	$template->assign('pin_numbers',           $pin_numbers ?? []);
	$template->assign('search',                $search);
	$template->assign('paging_controls',       $paging_controls);
	$template->assign('paging_controls_mini',  $paging_controls_mini);
	$template->assign('token',                 $token);
	$template->assign('has_pin_number_add',    $has_pin_number_add);
	$template->assign('has_pin_number_delete', $has_pin_number_delete);
	$template->assign('has_pin_number_edit',   $has_pin_number_edit);
	$template->assign('list_row_edit_button',  $list_row_edit_button);
	$template->assign('btn_export',            $btn_export);
	$template->assign('btn_add',               $btn_add);
	$template->assign('btn_copy',              $btn_copy);
	$template->assign('btn_toggle',            $btn_toggle);
	$template->assign('btn_delete',            $btn_delete);
	$template->assign('btn_search',            $btn_search);
	$template->assign('btn_reset',             $btn_reset);
	$template->assign('modal_copy',            $modal_copy);
	$template->assign('modal_toggle',          $modal_toggle);
	$template->assign('modal_delete',          $modal_delete);
	$template->assign('th_pin_number',         $th_pin_number);
	$template->assign('th_accountcode',        $th_accountcode);
	$template->assign('th_enabled',            $th_enabled);
	$template->assign('th_description',        $th_description);

//invoke pre-render hook
	app::dispatch_list_pre_render('pin_number_list_page_hook', $url, $template);

//include the header
	$document['title'] = $text['title-pin_numbers'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('pin_numbers_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('pin_number_list_page_hook', $url, $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";


