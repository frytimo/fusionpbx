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

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (!permission_exists('var_view')) {
		echo "access denied";
		exit;
	}
	$has_var_add    = permission_exists('var_add');
	$has_var_delete = permission_exists('var_delete');
	$has_var_edit   = permission_exists('var_edit');

//add multi-lingual support
	$text = new text()->get();
	//create the url object
	$url = new url();

//define the variables
	$action = '';
	$search = '';

//get posted data
	if (!empty($_POST['vars'])) {
		$action = $_POST['action'] ?? '';
		$search = $_POST['search'] ?? '';
		$vars = $_POST['vars'] ?? '';
	}

//process the http post data by action
	if (!empty($action)) {
		//dispatch pre-action hook
		app::dispatch_list_pre_action('var_list_page_hook', $url, $action, $vars);

		switch ($action) {
			case 'copy':
				if ($has_var_add) {
					$obj = new vars;
					$obj->copy($vars);
				}
				break;
			case 'toggle':
				if ($has_var_edit) {
					$obj = new vars;
					$obj->toggle($vars);
				}
				break;
			case 'delete':
				if ($has_var_delete) {
					$obj = new vars;
					$obj->delete($vars);
				}
				break;
		}

		//dispatch post-action hook
		app::dispatch_list_post_action('var_list_page_hook', $url, $action, $vars);

		header('Location: vars.php'.($search != '' ? '?search='.urlencode($search) : ''));
		exit;
	}

//dispatch pre-query hook
	$query_parameters = [];
	app::dispatch_list_pre_query('var_list_page_hook', $url, $query_parameters);

//get order and order by
	$order_by = $_GET["order_by"] ?? '';
	$order = $_GET["order"] ?? '';

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);

//get the count
	$sql = "select count(var_uuid) from v_vars ";
	if (!empty($_GET["search"])) {
		$search = strtolower($_GET["search"]);
		$sql .= "where (";
		$sql .= "	lower(var_category) like :search ";
		$sql .= "	or lower(var_name) like :search ";
		$sql .= "	or lower(var_value) like :search ";
		$sql .= "	or lower(var_hostname) like :search ";
		$sql .= "	or lower(cast(var_enabled as text)) like :search ";
		$sql .= "	or lower(var_description) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$num_rows = $database->select($sql, $parameters ?? null, 'column');

//prepare to page the results
	$rows_per_page = $settings->get('domain', 'paging', 50);
	$param = $search ? "&search=".$search : null;
	$param = $order_by ? "&order_by=".$order_by."&order=".$order : null;
	$page = empty($_GET['page']) ? $page = 0 : $page = $_GET['page'];
	[$paging_controls, $rows_per_page] = paging($num_rows, $param, $rows_per_page);
	[$paging_controls_mini, $rows_per_page] = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the list
	$sql = "select \n";
	$sql .= "var_uuid, \n";
	$sql .= "var_category, \n";
	$sql .= "var_name, \n";
	$sql .= "var_value, \n";
	$sql .= "var_hostname, \n";
	$sql .= "cast(var_enabled as text), \n";
	$sql .= "var_description \n";
	$sql .= "from v_vars ";
	if (!empty($_GET["search"])) {
		$search = strtolower($_GET["search"]);
		$sql .= "where (";
		$sql .= "	lower(var_category) like :search ";
		$sql .= "	or lower(var_name) like :search ";
		$sql .= "	or lower(var_value) like :search ";
		$sql .= "	or lower(var_hostname) like :search ";
		$sql .= "	or lower(cast(var_enabled as text)) like :search ";
		$sql .= "	or lower(var_description) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$sql .= $order_by != '' ? order_by($order_by, $order) : " order by var_category, var_order asc, var_name asc ";
	$sql .= limit_offset($rows_per_page, $offset);
	$vars = $database->select($sql, $parameters ?? null, 'all');
	//dispatch post-query hook
	app::dispatch_list_post_query('var_list_page_hook', $url, $vars);
	unset($sql);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//build action bar buttons
	if ($has_var_add) {
		$btn_add = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','link'=>'var_edit.php']);
	}
	if ($has_var_add && $vars) {
		$btn_copy = button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$settings->get('theme', 'button_icon_copy'),'id'=>'btn_copy','name'=>'btn_copy','style'=>'display: none;','onclick'=>"modal_open('modal-copy','btn_copy');"]);
	}
	if ($has_var_edit && $vars) {
		$btn_toggle = button::create(['type'=>'button','label'=>$text['button-toggle'],'icon'=>$settings->get('theme', 'button_icon_toggle'),'id'=>'btn_toggle','name'=>'btn_toggle','style'=>'display: none;','onclick'=>"modal_open('modal-toggle','btn_toggle');"]);
	}
	if ($has_var_delete && $vars) {
		$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}

//build modals
	if ($has_var_add && $vars) {
		$modal_copy = modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
	}
	if ($has_var_edit && $vars) {
		$modal_toggle = modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
	}
	if ($has_var_delete && $vars) {
		$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

//build column headings
	$th_var_name = th_order_by('var_name', $text['label-name'], $order_by, $order, null, "class='pct-30'");
	$th_var_value = th_order_by('var_value', $text['label-value'], $order_by, $order, null, "class='pct-40'");
	$th_var_hostname = th_order_by('var_hostname', $text['label-hostname'], $order_by, $order, null, "class='hide-sm-dn'");
	$th_var_enabled = th_order_by('var_enabled', $text['label-enabled'], $order_by, $order, null, "class='center'");

//pre-render rows
	$previous_category = '';
	foreach ($vars as $x => &$row) {
		//dispatch render-row hook
		app::dispatch_list_render_row('var_list_page_hook', $url, $row, $x);
		//compute category modifier
		$modifier = strtolower(trim($row['var_category']));
		$modifier = str_replace('/', '', $modifier);
		$modifier = str_replace('  ', ' ', $modifier);
		$modifier = str_replace(' ', '_', $modifier);
		$modifier = str_replace(':', '', $modifier);
		$row['_show_category_header'] = ($previous_category != $row['var_category']);
		$row['_category_needs_br'] = ($previous_category != '');
		$row['_category_modifier'] = $modifier;
		//build row url
		$list_row_url = '';
		if ($has_var_edit) {
			$list_row_url = 'var_edit.php?id='.urlencode($row['var_uuid']);
		}
		$row['_list_row_url'] = $list_row_url;
		//build enabled label
		$row['_enabled_label'] = $text['label-'.($row['var_enabled'] ?? 'false')];
		//build toggle button
		if ($has_var_edit) {
			$row['_toggle_button'] = button::create(['type'=>'submit','class'=>'link','label'=>$text['label-'.($row['var_enabled'] ?? 'false')],'title'=>$text['button-toggle'],'onclick'=>"list_self_check('checkbox_".$x."'); list_action_set('toggle'); list_form_submit('form_list')"]);
		}
		//build edit button
		if ($has_var_edit && $list_row_edit_button) {
			$row['_edit_button'] = button::create(['type'=>'button','title'=>$text['button-edit'],'icon'=>$settings->get('theme', 'button_icon_edit'),'link'=>$list_row_url]);
		}
		$previous_category = $row['var_category'];
	}
	unset($row);

//set up template
	$template = new template;
	$template->engine = 'smarty';
	$template->template_dir = __DIR__.'/resources/views';
	$template->cache_dir = sys_get_temp_dir();
	$template->init();
	$template->assign('text', $text);
	$template->assign('vars', $vars);
	$template->assign('search', $search);
	$template->assign('token', $token);
	$template->assign('paging_controls', $paging_controls);
	$template->assign('paging_controls_mini', $paging_controls_mini);
	$template->assign('num_rows', $num_rows);
	$template->assign('btn_add', $btn_add ?? '');
	$template->assign('btn_copy', $btn_copy ?? '');
	$template->assign('btn_toggle', $btn_toggle ?? '');
	$template->assign('btn_delete', $btn_delete ?? '');
	$template->assign('btn_search', button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']));
	$template->assign('modal_copy', $modal_copy ?? '');
	$template->assign('modal_toggle', $modal_toggle ?? '');
	$template->assign('modal_delete', $modal_delete ?? '');
	$template->assign('th_var_name', $th_var_name);
	$template->assign('th_var_value', $th_var_value);
	$template->assign('th_var_hostname', $th_var_hostname);
	$template->assign('th_var_enabled', $th_var_enabled);
	$template->assign('has_var_add', $has_var_add);
	$template->assign('has_var_edit', $has_var_edit);
	$template->assign('has_var_delete', $has_var_delete);
	$template->assign('list_row_edit_button', $list_row_edit_button);

//dispatch pre-render hook
	app::dispatch_list_pre_render('var_list_page_hook', $url, $template);

//include the header
	$document['title'] = $text['title-variables'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('vars_list.tpl');

//dispatch post-render hook
	app::dispatch_list_post_render('var_list_page_hook', $url, $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";


