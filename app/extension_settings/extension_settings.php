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
	Portions created by the Initial Developer are Copyright (C) 2021-2023
	the Initial Developer. All Rights Reserved.
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('extension_setting_view')) {
		echo "access denied";
		exit;
	}
	$has_extension_setting_add    = permission_exists('extension_setting_add');
	$has_extension_setting_all    = permission_exists('extension_setting_all');
	$has_extension_setting_delete = permission_exists('extension_setting_delete');
	$has_extension_setting_edit   = permission_exists('extension_setting_edit');

//add multi-lingual support
	$text = new text()->get();
	//create the url object
	$url = new url();

//set the defaults
	$search = '';
	$paging_controls = '';
	$paging_controls_mini = '';
	$id = '';

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);

//get the http post data
	if (!empty($_POST['extension_settings'])) {
		$action = $_POST['action'];
		$search = $_POST['search'] ?? '';
		$extension_settings = $_POST['extension_settings'];
	}

//action add or update
	if (!empty($_REQUEST["id"]) && is_uuid($_REQUEST["id"])) {
		$extension_uuid = $_REQUEST["id"];
	}

//process the http post data by action
	if (!empty($action) && !empty($extension_settings)) {

		//validate the token
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-invalid_token'],'negative');
			header('Location: extension_settings.php');
			exit;
		}

		//prepare the database object
		$obj = new extension_settings;

		//send the array to the database class
		//dispatch pre-action hook
		app::dispatch_list_pre_action(null, $url, $action, $extension_settings);

		switch ($action) {
			case 'copy':
				if ($has_extension_setting_add) {
					$obj->copy($extension_settings);
				}
				break;
			case 'toggle':
				if ($has_extension_setting_edit) {
					$obj->toggle($extension_settings);
				}
				break;
			case 'delete':
				if ($has_extension_setting_delete) {
					$obj->extension_uuid = $extension_uuid;
					$obj->delete($extension_settings);
				}
				break;
		}

		//redirect the user
			//dispatch post-action hook
			app::dispatch_list_post_action(null, $url, $action, $extension_settings);

		header('Location: extension_settings.php?id='.urlencode($extension_uuid).'&'.($search != '' ? '?search='.urlencode($search) : ''));
		exit;
	}

//dispatch pre-query hook
	$query_parameters = [];
	app::dispatch_list_pre_query(null, $url, $query_parameters);

//get order and order by
	$order_by = $_GET["order_by"] ?? '';
	$order = $_GET["order"] ?? '';

//add the search
	if (isset($_GET["search"])) {
		$search = strtolower($_GET["search"]);
	}

//get the count
	$sql = "select count(extension_setting_uuid) ";
	$sql .= "from v_extension_settings ";
	$sql .= "where extension_uuid = :extension_uuid ";
	if (isset($search)) {
		$sql .= "and (";
		$sql .= "	lower(extension_setting_type) like :search ";
		$sql .= "	or lower(extension_setting_name) like :search ";
		$sql .= "	or lower(extension_setting_description) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	else {
		$sql .= "and (domain_uuid = :domain_uuid or domain_uuid is null) ";
		if (isset($sql_search)) {
			$sql .= "and ".$sql_search;
		}
		$parameters['domain_uuid'] = $domain_uuid;
	}
	$parameters['extension_uuid'] = $extension_uuid;
	$num_rows = $database->select($sql, $parameters, 'column');
	unset($sql, $parameters);

//get the list
	$sql = "select ";
	//$sql .= "d.domain_name, ";
	$sql .= "extension_setting_uuid, ";
	$sql .= "extension_setting_type, ";
	$sql .= "extension_setting_name, ";
	$sql .= "extension_setting_value, ";
	$sql .= "cast(extension_setting_enabled as text), ";
	$sql .= "extension_setting_description ";
	$sql .= "from v_extension_settings as e ";
	//$sql .= ",v_domains as d ";
	$sql .= "where extension_uuid = :extension_uuid ";
	$sql .= "and (e.domain_uuid = :domain_uuid or e.domain_uuid is null) ";
	//$sql .= "and d.domain_uuid = e.domain_uuid ";
	if (isset($_GET["search"])) {
		$sql .= "and (";
		$sql .= "	lower(extension_setting_type) like :search ";
		$sql .= "	or lower(extension_setting_name) like :search ";
		$sql .= "	or lower(extension_setting_description) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}

	$sql .= order_by($order_by, $order, 'extension_setting_type', 'asc');
	$sql .= limit_offset($rows_per_page ?? null, $offset ?? null);
	$parameters['extension_uuid'] = $extension_uuid;
	$parameters['domain_uuid'] = $domain_uuid;
	$extension_settings = $database->select($sql, $parameters, 'all');
	//dispatch post-query hook
	app::dispatch_list_post_query(null, $url, $extension_settings);
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//build the action bar buttons
	$btn_back = button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$settings->get('theme', 'button_icon_back'),'id'=>'btn_add','name'=>'btn_add','style'=>'margin-right: 15px;','link'=>'/app/extensions/extension_edit.php?id='.$extension_uuid]);
	$btn_add = '';
	if ($has_extension_setting_add) {
		$btn_add = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','name'=>'btn_add','link'=>'extension_setting_edit.php?extension_uuid='.$extension_uuid]);
	}
	$btn_copy = '';
	if ($has_extension_setting_add && $extension_settings) {
		$btn_copy = button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$settings->get('theme', 'button_icon_copy'),'id'=>'btn_copy','name'=>'btn_copy','style'=>'display:none;','onclick'=>"modal_open('modal-copy','btn_copy');"]);
	}
	$btn_toggle = '';
	if ($has_extension_setting_edit && $extension_settings) {
		$btn_toggle = button::create(['type'=>'button','label'=>$text['button-toggle'],'icon'=>$settings->get('theme', 'button_icon_toggle'),'id'=>'btn_toggle','name'=>'btn_toggle','style'=>'display:none;','onclick'=>"modal_open('modal-toggle','btn_toggle');"]);
	}
	$btn_delete = '';
	if ($has_extension_setting_delete && $extension_settings) {
		$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display:none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search','style'=>(!empty($search) ? 'display: none;' : null)]);
	$btn_reset = button::create(['label'=>$text['button-reset'],'icon'=>$settings->get('theme', 'button_icon_reset'),'type'=>'button','id'=>'btn_reset','link'=>'extension_settings.php?id='.$extension_uuid,'style'=>(empty($search) ? 'display: none;' : null)]);

//build the modals
	$modal_copy = '';
	if ($has_extension_setting_add && $extension_settings) {
		$modal_copy = modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
	}
	$modal_toggle = '';
	if ($has_extension_setting_edit && $extension_settings) {
		$modal_toggle = modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
	}
	$modal_delete = '';
	if ($has_extension_setting_delete && $extension_settings) {
		$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

//build the row data
	$previous_extension_setting_type = '';
	$x = 0;
	foreach ($extension_settings as &$row) {
		app::dispatch_list_render_row('extension_setting_list_page_hook', $url, $row, $x);
		$extension_setting_type_lower = strtolower($row['extension_setting_type']);
		$label = ucwords(str_replace(['-', '_'], ' ', $row['extension_setting_type']));
		$row['_show_type_header'] = ($previous_extension_setting_type !== $row['extension_setting_type']);
		$row['_extension_setting_type_lower'] = $extension_setting_type_lower;
		$row['_label_extension_setting_type'] = $label;
		$list_row_url = '';
		if ($has_extension_setting_edit) {
			$list_row_url = "extension_setting_edit.php?id=".urlencode($row['extension_setting_uuid'])."&extension_uuid=".urlencode($extension_uuid);
		}
		$row['_list_row_url'] = $list_row_url;
		$row['_toggle_button'] = '';
		if ($has_extension_setting_edit) {
			$row['_toggle_button'] = button::create(['type'=>'submit','class'=>'link','label'=>$text['label-'.$row['extension_setting_enabled']],'title'=>$text['button-toggle'],'onclick'=>"list_self_check('checkbox_{$x}'); list_action_set('toggle'); list_form_submit('form_list')"]);
		}
		$row['_enabled_label'] = $text['label-'.$row['extension_setting_enabled']];
		$row['_edit_button'] = '';
		if ($has_extension_setting_edit && $list_row_edit_button) {
			$row['_edit_button'] = button::create(['type'=>'button','title'=>$text['button-edit'],'icon'=>$settings->get('theme', 'button_icon_edit'),'link'=>$list_row_url]);
		}
		$previous_extension_setting_type = $row['extension_setting_type'];
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
	$template->assign('text',                         $text);
	$template->assign('num_rows',                     $num_rows);
	$template->assign('extension_settings',           $extension_settings ?? []);
	$template->assign('extension_uuid',               $extension_uuid);
	$template->assign('search',                       $search);
	$template->assign('paging_controls',              $paging_controls);
	$template->assign('paging_controls_mini',         $paging_controls_mini);
	$template->assign('token',                        $token);
	$template->assign('has_extension_setting_add',    $has_extension_setting_add);
	$template->assign('has_extension_setting_delete', $has_extension_setting_delete);
	$template->assign('has_extension_setting_edit',   $has_extension_setting_edit);
	$template->assign('list_row_edit_button',         $list_row_edit_button);
	$template->assign('btn_back',                     $btn_back);
	$template->assign('btn_add',                      $btn_add);
	$template->assign('btn_copy',                     $btn_copy);
	$template->assign('btn_toggle',                   $btn_toggle);
	$template->assign('btn_delete',                   $btn_delete);
	$template->assign('btn_search',                   $btn_search);
	$template->assign('btn_reset',                    $btn_reset);
	$template->assign('modal_copy',                   $modal_copy);
	$template->assign('modal_toggle',                 $modal_toggle);
	$template->assign('modal_delete',                 $modal_delete);

//invoke pre-render hook
	app::dispatch_list_pre_render('extension_setting_list_page_hook', $url, $template);

//include the header
	$document['title'] = $text['title-extension_settings'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('extension_settings_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('extension_setting_list_page_hook', $url, $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";


