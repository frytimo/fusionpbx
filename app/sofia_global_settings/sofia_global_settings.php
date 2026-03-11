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
	Portions created by the Initial Developer are Copyright (C) 2018 - 2023
	the Initial Developer. All Rights Reserved.
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (!permission_exists('sofia_global_setting_view')) {
		echo "access denied";
		exit;
	}
	$has_domain_select               = permission_exists('domain_select');
	$has_sofia_global_setting_add    = permission_exists('sofia_global_setting_add');
	$has_sofia_global_setting_delete = permission_exists('sofia_global_setting_delete');
	$has_sofia_global_setting_edit   = permission_exists('sofia_global_setting_edit');

//add multi-lingual support
	$text = new text()->get();
	//create the url object
	$url = new url();

//set the defaults
	$action = '';
	$search = '';

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);

//get the http post data
	if (!empty($_POST['sofia_global_settings'])) {
		$action = $_POST['action'];
		$search = $_POST['search'] ?? '';
		$sofia_global_settings = $_POST['sofia_global_settings'];
	}

//process the http post data by action
	if (!empty($action) && !empty($sofia_global_settings) && @sizeof($sofia_global_settings) != 0) {

		//dispatch pre-action hook
		app::dispatch_list_pre_action('sofia_global_setting_list_page_hook', $url, $action, $sofia_global_settings);

		switch ($action) {
			case 'copy':
				if ($has_sofia_global_setting_add) {
					$obj = new sofia_global_settings;
					$obj->copy($sofia_global_settings);
				}
				break;
			case 'toggle':
				if ($has_sofia_global_setting_edit) {
					$obj = new sofia_global_settings;
					$obj->toggle($sofia_global_settings);
				}
				break;
			case 'delete':
				if ($has_sofia_global_setting_delete) {
					$obj = new sofia_global_settings;
					$obj->delete($sofia_global_settings);
				}
				break;
		}

		//redirect the user
		//dispatch post-action hook
		app::dispatch_list_post_action('sofia_global_setting_list_page_hook', $url, $action, $sofia_global_settings);

		header('Location: sofia_global_settings.php'.($search != '' ? '?search='.urlencode($search) : ''));
		exit;
	}

//dispatch pre-query hook
	$query_parameters = [];
	app::dispatch_list_pre_query('sofia_global_setting_list_page_hook', $url, $query_parameters);

//get order and order by
	$order_by = $_GET["order_by"] ?? '';
	$order = $_GET["order"] ?? '';

//add the search
	if (isset($_GET["search"])) {
		$search = strtolower($_GET["search"]);
	}

//get the count
	$sql = "select count(sofia_global_setting_uuid) ";
	$sql .= "from v_sofia_global_settings ";
	if (isset($search)) {
		$sql .= "where (";
		$sql .= "	global_setting_name like :search ";
		$sql .= "	or global_setting_value like :search ";
		$sql .= "	or global_setting_description like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$num_rows = $database->select($sql, $parameters ?? [], 'column');
	unset($sql, $parameters);

//prepare to page the results
	$rows_per_page = $settings->get('domain', 'paging', 50);
	$param = !empty($search) ? "&search=".$search : null;
	$page = !empty($_GET['page']) ? $_GET['page'] : 0;
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the list
	$sql = "select ";
	$sql .= "sofia_global_setting_uuid, ";
	$sql .= "global_setting_name, ";
	$sql .= "global_setting_value, ";
	$sql .= "cast(global_setting_enabled as text), ";
	$sql .= "global_setting_description ";
	$sql .= "from v_sofia_global_settings ";
	if (isset($search)) {
		$sql .= "where (";
		$sql .= "	global_setting_name like :search ";
		$sql .= "	or global_setting_value like :search ";
		$sql .= "	or global_setting_description like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$sql .= order_by($order_by, $order, 'global_setting_name', 'asc');
	$sql .= limit_offset($rows_per_page, $offset);
	$sofia_global_settings = $database->select($sql, $parameters ?? [], 'all');
	//dispatch post-query hook
	app::dispatch_list_post_query('sofia_global_setting_list_page_hook', $url, $sofia_global_settings);
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//build the action bar buttons
	$btn_add = '';
	if ($has_sofia_global_setting_add) {
		$btn_add = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','name'=>'btn_add','link'=>'sofia_global_setting_edit.php']);
	}
	$btn_copy = '';
	if ($has_sofia_global_setting_add && $sofia_global_settings) {
		$btn_copy = button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$settings->get('theme', 'button_icon_copy'),'id'=>'btn_copy','name'=>'btn_copy','style'=>'display:none;','onclick'=>"modal_open('modal-copy','btn_copy');"]);
	}
	$btn_toggle = '';
	if ($has_sofia_global_setting_edit && $sofia_global_settings) {
		$btn_toggle = button::create(['type'=>'button','label'=>$text['button-toggle'],'icon'=>$settings->get('theme', 'button_icon_toggle'),'id'=>'btn_toggle','name'=>'btn_toggle','style'=>'display:none;','onclick'=>"modal_open('modal-toggle','btn_toggle');"]);
	}
	$btn_delete = '';
	if ($has_sofia_global_setting_delete && $sofia_global_settings) {
		$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display:none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search','style'=>($search != '' ? 'display: none;' : null)]);
	$btn_reset  = button::create(['label'=>$text['button-reset'],'icon'=>$settings->get('theme', 'button_icon_reset'),'type'=>'button','id'=>'btn_reset','link'=>'sofia_global_settings.php','style'=>($search == '' ? 'display: none;' : null)]);

//build the modals
	$modal_copy = '';
	if ($has_sofia_global_setting_add && $sofia_global_settings) {
		$modal_copy = modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
	}
	$modal_toggle = '';
	if ($has_sofia_global_setting_edit && $sofia_global_settings) {
		$modal_toggle = modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
	}
	$modal_delete = '';
	if ($has_sofia_global_setting_delete && $sofia_global_settings) {
		$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

//build the table header columns
	$th_global_setting_name    = th_order_by('global_setting_name', $text['label-global_setting_name'], $order_by, $order);
	$th_global_setting_value   = th_order_by('global_setting_value', $text['label-global_setting_value'], $order_by, $order);
	$th_global_setting_enabled = th_order_by('global_setting_enabled', $text['label-global_setting_enabled'], $order_by, $order, null, "class='center'");

//build the row data
	$x = 0;
	foreach ($sofia_global_settings as &$row) {
		app::dispatch_list_render_row('sofia_global_setting_list_page_hook', $url, $row, $x);
		$list_row_url = '';
		if ($has_sofia_global_setting_edit) {
			$list_row_url = "sofia_global_setting_edit.php?id=".urlencode($row['sofia_global_setting_uuid']);
			if (!empty($row['domain_uuid']) && $row['domain_uuid'] != $_SESSION['domain_uuid'] && $has_domain_select) {
				$list_row_url .= '&domain_uuid='.urlencode($row['domain_uuid']).'&domain_change=true';
			}
		}
		$row['_list_row_url']  = $list_row_url;
		$row['_enabled_label'] = $text['label-'.$row['global_setting_enabled']];
		$row['_toggle_button'] = '';
		if ($has_sofia_global_setting_edit) {
			$row['_toggle_button'] = "<input type='hidden' name='number_translations[{$x}][global_setting_enabled]' value='".escape($row['global_setting_enabled'])."' />".button::create(['type'=>'submit','class'=>'link','label'=>$text['label-'.$row['global_setting_enabled']],'title'=>$text['button-toggle'],'onclick'=>"list_self_check('checkbox_{$x}'); list_action_set('toggle'); list_form_submit('form_list')"]);
		}
		$row['_edit_button'] = '';
		if ($has_sofia_global_setting_edit && $list_row_edit_button) {
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
	$template->assign('text',                          $text);
	$template->assign('num_rows',                      $num_rows);
	$template->assign('sofia_global_settings',         $sofia_global_settings ?? []);
	$template->assign('search',                        $search);
	$template->assign('paging_controls',               $paging_controls);
	$template->assign('paging_controls_mini',          $paging_controls_mini);
	$template->assign('token',                         $token);
	$template->assign('has_sofia_global_setting_add',  $has_sofia_global_setting_add);
	$template->assign('has_sofia_global_setting_delete',$has_sofia_global_setting_delete);
	$template->assign('has_sofia_global_setting_edit', $has_sofia_global_setting_edit);
	$template->assign('list_row_edit_button',          $list_row_edit_button);
	$template->assign('btn_add',                       $btn_add);
	$template->assign('btn_copy',                      $btn_copy);
	$template->assign('btn_toggle',                    $btn_toggle);
	$template->assign('btn_delete',                    $btn_delete);
	$template->assign('btn_search',                    $btn_search);
	$template->assign('btn_reset',                     $btn_reset);
	$template->assign('modal_copy',                    $modal_copy);
	$template->assign('modal_toggle',                  $modal_toggle);
	$template->assign('modal_delete',                  $modal_delete);
	$template->assign('th_global_setting_name',        $th_global_setting_name);
	$template->assign('th_global_setting_value',       $th_global_setting_value);
	$template->assign('th_global_setting_enabled',     $th_global_setting_enabled);

//invoke pre-render hook
	app::dispatch_list_pre_render('sofia_global_setting_list_page_hook', $url, $template);

//include the header
	$document['title'] = $text['title-sofia_global_settings'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('sofia_global_settings_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('sofia_global_setting_list_page_hook', $url, $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";

