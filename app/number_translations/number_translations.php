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
	if (!permission_exists('number_translation_view')) {
		echo "access denied";
		exit;
	}
	$has_number_translation_add    = permission_exists('number_translation_add');
	$has_number_translation_delete = permission_exists('number_translation_delete');
	$has_number_translation_edit   = permission_exists('number_translation_edit');

//add multi-lingual support
	$text = new text()->get();
	//create the url object
	$url = new url();

//set additional variables
	$search = $_GET["search"] ?? '';

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);

//get the http post data
	if (!empty($_POST['number_translations'])) {
		$action = $_POST['action'];
		$search = $_POST['search'] ?? '';
		$number_translations = $_POST['number_translations'];
	}

//process the http post data by action
	if (!empty($action) && !empty($number_translations)) {
		//dispatch pre-action hook
		app::dispatch_list_pre_action(null, $url, $action, $number_translations);

		switch ($action) {
			case 'copy':
				if ($has_number_translation_add) {
					$obj = new number_translations;
					$obj->copy($number_translations);
				}
				break;
			case 'toggle':
				if ($has_number_translation_edit) {
					$obj = new number_translations;
					$obj->toggle($number_translations);
				}
				break;
			case 'delete':
				if ($has_number_translation_delete) {
					$obj = new number_translations;
					$obj->delete($number_translations);
				}
				break;
		}

		//redirect the user
		//dispatch post-action hook
		app::dispatch_list_post_action(null, $url, $action, $number_translations);

		header('Location: number_translations.php'.(!empty($search) ? '?search='.urlencode($search) : ''));
		exit;
	}

//dispatch pre-query hook
	$query_parameters = [];
	app::dispatch_list_pre_query(null, $url, $query_parameters);

//get order and order by
	$order_by = $_GET["order_by"] ?? '';
	$order = $_GET["order"] ?? '';

//add the search
	if (!empty($search)) {
		$search = strtolower($_GET["search"]);
		$parameters['search'] = '%'.$search.'%';
	}

//get the count
	$sql = "select count(number_translation_uuid) ";
	$sql .= "from v_number_translations ";
	if (!empty($search)) {
		$sql .= "where (";
		$sql .= "	lower(number_translation_name) like :search ";
		$sql .= "	or lower(number_translation_description) like :search ";
		$sql .= ") ";
	}
	$num_rows = $database->select($sql, $parameters ?? null, 'column');

//prepare to page the results
	$rows_per_page = $settings->get('domain', 'paging', 50);
	$param = $search ? "&search=".$search : null;
	$page = isset($_GET['page']) ? $_GET['page'] : 0;
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the list
	$sql = "select ";
	$sql .= "number_translation_uuid, ";
	$sql .= "number_translation_name, ";
	$sql .= "cast(number_translation_enabled as text), ";
	$sql .= "number_translation_description ";
	$sql .= "from v_number_translations ";
	if (!empty($search)) {
		$sql .= "where (";
		$sql .= "	lower(number_translation_name) like :search ";
		$sql .= "	or lower(number_translation_description) like :search ";
		$sql .= ") ";
	}
	$sql .= order_by($order_by, $order, 'number_translation_name', 'asc');
	$sql .= limit_offset($rows_per_page, $offset);
	$number_translations = $database->select($sql, $parameters ?? null, 'all');
	//dispatch post-query hook
	app::dispatch_list_post_query(null, $url, $number_translations);
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//build the action bar buttons
$btn_add = '';
if ($has_number_translation_add) {
$btn_add = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','name'=>'btn_add','link'=>'number_translation_edit.php']);
}
$btn_copy = '';
if ($has_number_translation_add && $number_translations) {
$btn_copy = button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$settings->get('theme', 'button_icon_copy'),'id'=>'btn_copy','name'=>'btn_copy','style'=>'display:none;','onclick'=>"modal_open('modal-copy','btn_copy');"]);
}
$btn_toggle = '';
if ($has_number_translation_edit && $number_translations) {
$btn_toggle = button::create(['type'=>'button','label'=>$text['button-toggle'],'icon'=>$settings->get('theme', 'button_icon_toggle'),'id'=>'btn_toggle','name'=>'btn_toggle','style'=>'display:none;','onclick'=>"modal_open('modal-toggle','btn_toggle');"]);
}
$btn_delete = '';
if ($has_number_translation_delete && $number_translations) {
$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display:none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
}
$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);

//build the modals
$modal_copy = '';
if ($has_number_translation_add && $number_translations) {
$modal_copy = modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
}
$modal_toggle = '';
if ($has_number_translation_edit && $number_translations) {
$modal_toggle = modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
}
$modal_delete = '';
if ($has_number_translation_delete && $number_translations) {
$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
}

//build the table header columns
$th_number_translation_name    = th_order_by('number_translation_name', $text['label-number_translation_name'], $order_by, $order);
$th_number_translation_enabled = th_order_by('number_translation_enabled', $text['label-number_translation_enabled'], $order_by, $order, null, "class='center'");

//build the row data
if (!empty($number_translations)) {
$x = 0;
foreach ($number_translations as &$row) {
app::dispatch_list_render_row('number_translation_list_page_hook', $url, $row, $x);
$list_row_url = '';
if ($has_number_translation_edit) {
$list_row_url = "number_translation_edit.php?id=".urlencode($row['number_translation_uuid']);
}
$row['_list_row_url']  = $list_row_url;
$row['_enabled_label'] = $text['label-'.$row['number_translation_enabled']];
$row['_toggle_button'] = '';
if ($has_number_translation_edit) {
$row['_toggle_button'] = button::create(['type'=>'submit','class'=>'link','label'=>$text['label-'.$row['number_translation_enabled']],'title'=>$text['button-toggle'],'onclick'=>"list_self_check('checkbox_{$x}'); list_action_set('toggle'); list_form_submit('form_list')"]);
}
$row['_edit_button'] = '';
if ($has_number_translation_edit && $list_row_edit_button) {
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
$template->assign('text',                            $text);
$template->assign('num_rows',                        $num_rows);
$template->assign('number_translations',             $number_translations ?? []);
$template->assign('search',                          $search);
$template->assign('paging_controls',                 $paging_controls);
$template->assign('paging_controls_mini',            $paging_controls_mini);
$template->assign('token',                           $token);
$template->assign('has_number_translation_add',      $has_number_translation_add);
$template->assign('has_number_translation_delete',   $has_number_translation_delete);
$template->assign('has_number_translation_edit',     $has_number_translation_edit);
$template->assign('list_row_edit_button',            $list_row_edit_button);
$template->assign('btn_add',                         $btn_add);
$template->assign('btn_copy',                        $btn_copy);
$template->assign('btn_toggle',                      $btn_toggle);
$template->assign('btn_delete',                      $btn_delete);
$template->assign('btn_search',                      $btn_search);
$template->assign('modal_copy',                      $modal_copy);
$template->assign('modal_toggle',                    $modal_toggle);
$template->assign('modal_delete',                    $modal_delete);
$template->assign('th_number_translation_name',      $th_number_translation_name);
$template->assign('th_number_translation_enabled',   $th_number_translation_enabled);

//invoke pre-render hook
app::dispatch_list_pre_render('number_translation_list_page_hook', $url, $template);

//include the header
$document['title'] = $text['title-number_translations'];
require_once "resources/header.php";

//render the template
$html = $template->render('number_translations_list.tpl');

//invoke post-render hook
app::dispatch_list_post_render('number_translation_list_page_hook', $url, $html);
echo $html;

//include the footer
require_once "resources/footer.php";
