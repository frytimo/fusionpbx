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
	Portions created by the Initial Developer are Copyright (C) 2018-2025
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (!permission_exists('conference_control_view')) {
		echo "access denied";
		exit;
	}
	$has_conference_control_add    = permission_exists('conference_control_add');
	$has_conference_control_delete = permission_exists('conference_control_delete');
	$has_conference_control_edit   = permission_exists('conference_control_edit');

//add multi-lingual support
	$text = new text()->get();
	//create the url object
	$url = new url();

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);

//get the http post data
	if (!empty($_POST['conference_controls'])) {
		$action = $_POST['action'];
		$search = $_POST['search'] ?? '';
		$conference_controls = $_POST['conference_controls'];
	}

//process the http post data by action
	if (!empty($action) && !empty($conference_controls)) {
		//dispatch pre-action hook
		app::dispatch_list_pre_action('conference_control_list_page_hook', $url, $action, $conference_controls);

		switch ($action) {
			case 'copy':
				if ($has_conference_control_add) {
					$obj = new conference_controls;
					$obj->copy($conference_controls);
				}
				break;
			case 'toggle':
				if ($has_conference_control_edit) {
					$obj = new conference_controls;
					$obj->toggle($conference_controls);
				}
				break;
			case 'delete':
				if ($has_conference_control_delete) {
					$obj = new conference_controls;
					$obj->delete($conference_controls);
				}
				break;
		}

		//dispatch post-action hook
		app::dispatch_list_post_action('conference_control_list_page_hook', $url, $action, $conference_controls);

		header('Location: conference_controls.php'.(!empty($search) ? '?search='.urlencode($search) : ''));
		exit;
	}

//dispatch pre-query hook
	$query_parameters = [];
	app::dispatch_list_pre_query('conference_control_list_page_hook', $url, $query_parameters);

//get order and order by
	$order_by = $_GET["order_by"] ?? '';
	$order = $_GET["order"] ?? '';

//add the search string
	$search = strtolower($_GET["search"] ?? '');
	if (!empty($search)) {
		$sql_search = "where (";
		$sql_search .= "	lower(control_name) like :search ";
		$sql_search .= "	or lower(control_description) like :search ";
		$sql_search .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}

//get the count
	$sql = "select count(conference_control_uuid) from v_conference_controls ";
	$sql .= $sql_search ?? '';
	$num_rows = $database->select($sql, $parameters ?? null, 'column');
	unset($sql);

//prepare to page the results
	$rows_per_page = $settings->get('domain', 'paging', 50);
	$param = $search ? "&search=".$search : null;
	$page = isset($_GET['page']) ? $_GET['page'] : 0;
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the list
	$sql = "select ";
	$sql .= "conference_control_uuid, ";
	$sql .= "control_name, ";
	$sql .= "cast(control_enabled as text), ";
	$sql .= "control_description ";
	$sql .= "from v_conference_controls ";
	$sql .= $sql_search ?? '';
	$sql .= order_by($order_by, $order, 'control_name', 'asc');
	$sql .= limit_offset($rows_per_page, $offset);
	$conference_controls = $database->select($sql, $parameters ?? null, 'all');
	//dispatch post-query hook
	app::dispatch_list_post_query('conference_control_list_page_hook', $url, $conference_controls);
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//build the action bar buttons
	$btn_add = '';
	if ($has_conference_control_add) {
		$btn_add = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','link'=>'conference_control_edit.php']);
	}
	$btn_copy = '';
	if ($has_conference_control_add && $conference_controls) {
		$btn_copy = button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$settings->get('theme', 'button_icon_copy'),'id'=>'btn_copy','name'=>'btn_copy','style'=>'display: none;','onclick'=>"modal_open('modal-copy','btn_copy');"]);
	}
	$btn_toggle = '';
	if ($has_conference_control_edit && $conference_controls) {
		$btn_toggle = button::create(['type'=>'button','label'=>$text['button-toggle'],'icon'=>$settings->get('theme', 'button_icon_toggle'),'id'=>'btn_toggle','name'=>'btn_toggle','style'=>'display: none;','onclick'=>"modal_open('modal-toggle','btn_toggle');"]);
	}
	$btn_delete = '';
	if ($has_conference_control_delete && $conference_controls) {
		$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);

//build the modals
	$modal_copy = '';
	if ($has_conference_control_add && $conference_controls) {
		$modal_copy = modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
	}
	$modal_toggle = '';
	if ($has_conference_control_edit && $conference_controls) {
		$modal_toggle = modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
	}
	$modal_delete = '';
	if ($has_conference_control_delete && $conference_controls) {
		$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

//build the table header columns
	$th_control_name    = th_order_by('control_name', $text['label-control_name'], $order_by, $order);
	$th_control_enabled = th_order_by('control_enabled', $text['label-control_enabled'], $order_by, $order, null, "class='center shrink'");

//build the row data
	$x = 0;
	if (!empty($conference_controls)) {
		foreach ($conference_controls as &$row) {
			app::dispatch_list_render_row('conference_control_list_page_hook', $url, $row, $x);
			$list_row_url = '';
			if ($has_conference_control_edit) {
				$list_row_url = "conference_control_edit.php?id=".urlencode($row['conference_control_uuid']);
			}
			$row['_list_row_url'] = $list_row_url;
			$row['_enabled_label'] = $text['label-'.$row['control_enabled']];
			$row['_toggle_button'] = '';
			if ($has_conference_control_edit) {
				$row['_toggle_button'] = button::create(['type'=>'submit','class'=>'link','label'=>$text['label-'.$row['control_enabled']],'title'=>$text['button-toggle'],'onclick'=>"list_self_check('checkbox_{$x}'); list_action_set('toggle'); list_form_submit('form_list')"]);
			}
			$row['_edit_button'] = '';
			if ($has_conference_control_edit && $list_row_edit_button) {
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
	$template->assign('text',                         $text);
	$template->assign('num_rows',                     $num_rows);
	$template->assign('rows',                         $conference_controls ?? []);
	$template->assign('search',                       $search);
	$template->assign('paging_controls',              $paging_controls);
	$template->assign('paging_controls_mini',         $paging_controls_mini);
	$template->assign('token',                        $token);
	$template->assign('has_conference_control_add',    $has_conference_control_add);
	$template->assign('has_conference_control_delete', $has_conference_control_delete);
	$template->assign('has_conference_control_edit',   $has_conference_control_edit);
	$template->assign('list_row_edit_button',          $list_row_edit_button);
	$template->assign('btn_add',                       $btn_add);
	$template->assign('btn_copy',                      $btn_copy);
	$template->assign('btn_toggle',                    $btn_toggle);
	$template->assign('btn_delete',                    $btn_delete);
	$template->assign('btn_search',                    $btn_search);
	$template->assign('modal_copy',                    $modal_copy);
	$template->assign('modal_toggle',                  $modal_toggle);
	$template->assign('modal_delete',                  $modal_delete);
	$template->assign('th_control_name',               $th_control_name);
	$template->assign('th_control_enabled',            $th_control_enabled);

//invoke pre-render hook
	app::dispatch_list_pre_render('conference_control_list_page_hook', $url, $template);

//include the header
	$document['title'] = $text['title-conference_controls'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('conference_controls_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('conference_control_list_page_hook', $url, $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";
