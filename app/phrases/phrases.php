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

//check the permission
	if (!permission_exists('phrase_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$text = new text()->get();
	//create the url object
	$url = new url();

//set the defaults
	$sql_search = '';

//add additional variables
	$show = $_GET['show'] ?? '';

//get posted data
	if (!empty($_POST['phrases'])) {
		$action = $_POST['action'];
		$search = $_POST['search'] ?? '';
		$phrases = $_POST['phrases'];
	}

//process the http post data by action
	if (!empty($action) && is_array($phrases)) {
		//dispatch pre-action hook
		app::dispatch_list_pre_action(null, $url, $action, $phrases);

		switch ($action) {
			case 'copy':
				if (permission_exists('phrase_add')) {
					$obj = new phrases;
					$obj->copy($phrases);
					//save_phrases_xml();
				}
				break;
			case 'toggle':
				if (permission_exists('phrase_edit')) {
					$obj = new phrases;
					$obj->toggle($phrases);
					//save_phrases_xml();
				}
				break;
			case 'delete':
				if (permission_exists('phrase_delete')) {
					$obj = new phrases;
					$obj->delete($phrases);
					//save_phrases_xml();
				}
				break;
		}

		//dispatch post-action hook
		app::dispatch_list_post_action(null, $url, $action, $phrases);

		header('Location: phrases.php'.($search != '' ? '?search='.urlencode($search) : ''));
		exit;
	}

//dispatch pre-query hook
	$query_parameters = [];
	app::dispatch_list_pre_query(null, $url, $query_parameters);

//get order and order by
	$order_by = $_GET["order_by"] ?? '';
	$order = $_GET["order"] ?? '';

//add the search term
	$search = strtolower($_GET["search"] ?? '');
	if (!empty($search)) {
		$sql_search = "and (";
		$sql_search .= "lower(phrase_name) like :search ";
		$sql_search .= "or lower(phrase_description) like :search ";
		$sql_search .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}

//get phrases record count
	$sql = "select count(*) from v_phrases ";
	$sql .= "where true ";
	if ($show != "all" || !permission_exists('phrase_all')) {
		$sql .= "and (domain_uuid = :domain_uuid or domain_uuid is null) ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	$sql .= $sql_search;
	$num_rows = $database->select($sql, $parameters ?? null, 'column');

//prepare to page the results
	$rows_per_page = $settings->get('domain', 'paging', 50);
	$param = "&search=".urlencode($search);
	if ($show == "all" && permission_exists('phrase_all')) {
		$param .= "&show=all";
	}
	$page = isset($_GET['page']) ? $_GET['page'] : 0;
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the list
	$sql = "select ";
	$sql .= " phrase_uuid, ";
	$sql .= " domain_uuid, ";
	$sql .= " phrase_name, ";
	$sql .= " phrase_language, ";
	$sql .= " cast(phrase_enabled as text), ";
	$sql .= " phrase_description ";
	$sql .= "from v_phrases ";
	$sql .= "where true ";
	if ($show != "all" || !permission_exists('phrase_all')) {
		$sql .= "and (domain_uuid = :domain_uuid or domain_uuid is null) ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	$sql .= $sql_search;
	$sql .= order_by($order_by, $order, 'phrase_name', 'asc');
	$sql .= limit_offset($rows_per_page, $offset);
	$phrases = $database->select($sql, $parameters ?? null, 'all');
	//dispatch post-query hook
	app::dispatch_list_post_query(null, $url, $phrases);
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//pre-compute permissions
$has_phrase_add    = permission_exists('phrase_add');
$has_phrase_all    = permission_exists('phrase_all');
$has_phrase_delete = permission_exists('phrase_delete');
$has_phrase_edit   = permission_exists('phrase_edit');
$has_domain_select = permission_exists('domain_select');

//build the action bar buttons
$btn_add = '';
if ($has_phrase_add) {
$btn_add = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','link'=>'phrase_edit.php']);
}
$btn_copy = '';
if ($has_phrase_add && $phrases) {
$btn_copy = button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$settings->get('theme', 'button_icon_copy'),'id'=>'btn_copy','name'=>'btn_copy','style'=>'display: none;','onclick'=>"modal_open('modal-copy','btn_copy');"]);
}
$btn_toggle = '';
if ($has_phrase_edit && $phrases) {
$btn_toggle = button::create(['type'=>'button','label'=>$text['button-toggle'],'icon'=>$settings->get('theme', 'button_icon_toggle'),'id'=>'btn_toggle','name'=>'btn_toggle','style'=>'display: none;','onclick'=>"modal_open('modal-toggle','btn_toggle');"]);
}
$btn_delete = '';
if ($has_phrase_delete && $phrases) {
$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
}
$btn_show_all = '';
if ($has_phrase_all && $show !== 'all') {
$btn_show_all = button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$settings->get('theme', 'button_icon_all'),'link'=>'?type=&show=all'.($search != '' ? "&search=".urlencode($search) : null)]);
}
$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);

//build the modals
$modal_copy = '';
if ($has_phrase_add && $phrases) {
$modal_copy = modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
}
$modal_toggle = '';
if ($has_phrase_edit && $phrases) {
$modal_toggle = modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
}
$modal_delete = '';
if ($has_phrase_delete && $phrases) {
$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
}

//build the table header columns
$th_domain_name      = '';
if ($show == 'all' && $has_phrase_all) {
$th_domain_name = th_order_by('domain_name', $text['label-domain'], $order_by, $order, $param, "class='shrink'");
}
$th_phrase_name        = th_order_by('phrase_name', $text['label-name'], $order_by, $order);
$th_phrase_language    = th_order_by('phrase_language', $text['label-language'], $order_by, $order);
$th_phrase_enabled     = th_order_by('phrase_enabled', $text['label-enabled'], $order_by, $order, null, "class='center'");
$th_phrase_description = th_order_by('phrase_description', $text['label-description'], $order_by, $order, null, "class='hide-sm-dn' style='min-width: 40%;'");

//build the row data
if (!empty($phrases)) {
$x = 0;
foreach ($phrases as &$row) {
app::dispatch_list_render_row('phrase_list_page_hook', $url, $row, $x);
$list_row_url = '';
if ($has_phrase_edit) {
$list_row_url = "phrase_edit.php?id=".urlencode($row['phrase_uuid']);
if ($row['domain_uuid'] != $_SESSION['domain_uuid'] && $has_domain_select) {
$list_row_url .= '&domain_uuid='.urlencode($row['domain_uuid']).'&domain_change=true';
}
}
$domain_name = '';
if ($show == 'all' && $has_phrase_all) {
$domain_name = !empty($_SESSION['domains'][$row['domain_uuid']]['domain_name'])
? $_SESSION['domains'][$row['domain_uuid']]['domain_name']
: $text['label-global'];
}
$row['_list_row_url']  = $list_row_url;
$row['_domain_name']   = $domain_name;
$row['_enabled_label'] = $text['label-'.$row['phrase_enabled']];
$row['_toggle_button'] = '';
if ($has_phrase_edit) {
$row['_toggle_button'] = button::create(['type'=>'submit','class'=>'link','label'=>$text['label-'.$row['phrase_enabled']],'title'=>$text['button-toggle'],'onclick'=>"list_self_check('checkbox_{$x}'); list_action_set('toggle'); list_form_submit('form_list')"]);
}
$row['_edit_button'] = '';
if ($has_phrase_edit && $list_row_edit_button) {
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
$template->assign('text',                 $text);
$template->assign('num_rows',             $num_rows);
$template->assign('phrases',              $phrases ?? []);
$template->assign('search',               $search);
$template->assign('show',                 $show);
$template->assign('paging_controls',      $paging_controls);
$template->assign('paging_controls_mini', $paging_controls_mini);
$template->assign('token',                $token);
$template->assign('has_phrase_add',       $has_phrase_add);
$template->assign('has_phrase_all',       $has_phrase_all);
$template->assign('has_phrase_delete',    $has_phrase_delete);
$template->assign('has_phrase_edit',      $has_phrase_edit);
$template->assign('list_row_edit_button', $list_row_edit_button);
$template->assign('btn_add',              $btn_add);
$template->assign('btn_copy',             $btn_copy);
$template->assign('btn_toggle',           $btn_toggle);
$template->assign('btn_delete',           $btn_delete);
$template->assign('btn_show_all',         $btn_show_all);
$template->assign('btn_search',           $btn_search);
$template->assign('modal_copy',           $modal_copy);
$template->assign('modal_toggle',         $modal_toggle);
$template->assign('modal_delete',         $modal_delete);
$template->assign('th_domain_name',       $th_domain_name);
$template->assign('th_phrase_name',       $th_phrase_name);
$template->assign('th_phrase_language',   $th_phrase_language);
$template->assign('th_phrase_enabled',    $th_phrase_enabled);
$template->assign('th_phrase_description',$th_phrase_description);

//invoke pre-render hook
app::dispatch_list_pre_render('phrase_list_page_hook', $url, $template);

//include the header
$document['title'] = $text['title-phrases'];
require_once "resources/header.php";

//render the template
$html = $template->render('phrases_list.tpl');

//invoke post-render hook
app::dispatch_list_post_render('phrase_list_page_hook', $url, $html);
echo $html;

//include the footer
require_once "resources/footer.php";
