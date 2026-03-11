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
	Portions created by the Initial Developer are Copyright (C) 2018-2024
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (!permission_exists('bridge_view')) {
		echo "access denied";
		exit;
	}
	$has_bridge_add    = permission_exists('bridge_add');
	$has_bridge_all    = permission_exists('bridge_all');
	$has_bridge_delete = permission_exists('bridge_delete');
	$has_bridge_edit   = permission_exists('bridge_edit');
	$has_bridge_import = permission_exists('bridge_import');
	$has_domain_select = permission_exists('domain_select');

//add multi-lingual support
	$text = new text()->get();

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);

//get request data from url object
	$show = $url_paging->get('show', '');
	$order = $url_paging->get('order', '');
	$action = $url_paging->get('action', '');
	$search = $url_paging->get('search', '');
	$order_by = $url_paging->get('order_by', '');

//get bridges from the url for post processing
	$bridges = $url_paging->get('bridges', []);

//invoke pre-action hook
	if (!empty($action) && !empty($bridges)) {
		app::dispatch_list_pre_action('bridge_list_page_hook', $url_paging, $action, $bridges);
	}

//process the http post data by action
	if (!empty($action) && !empty($bridges)) {
		switch ($action) {
			case 'copy':
				if ($has_bridge_add) {
					$obj = new bridges;
					$obj->copy($bridges);
				}
				break;
			case 'toggle':
				if ($has_bridge_edit) {
					$obj = new bridges;
					$obj->toggle($bridges);
				}
				break;
			case 'delete':
				if ($has_bridge_delete) {
					$obj = new bridges;
					$obj->delete($bridges);
				}
				break;
		}

		//invoke post-action hook
		app::dispatch_list_post_action('bridge_list_page_hook', $url_paging, $action, $bridges);

		header('Location: bridges.php'.(!empty($search) ? '?search='.urlencode($search) : ''));
		exit;
	}

//invoke pre-query hook
	$query_parameters = [];
	app::dispatch_list_pre_query('bridge_list_page_hook', $url_paging, $query_parameters);

//get the count
	$num_rows = bridges::count($url_paging);
	$url_paging->set_total_rows($num_rows);

//prepare to page the results
	$paging_controls = url_paging::html_paging_controls($url_paging);
	$paging_controls_mini = url_paging::html_paging_mini_controls($url_paging);

//get the list
	$bridges = bridges::fetch($url_paging);

//invoke post-fetch hook
	app::dispatch_list_post_query('bridge_list_page_hook', $url_paging, $bridges);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//build the action bar buttons
	$btn_import = '';
	if ($has_bridge_import) {
		$btn_import = button::create(['type'=>'button','label'=>$text['button-import'],'icon'=>$settings->get('theme', 'button_icon_import'),'style'=>'margin-right: 15px;','link'=>'bridge_imports.php']);
	}
	$btn_add = '';
	if ($has_bridge_add) {
		$btn_add = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','link'=>'bridge_edit.php']);
	}
	$btn_copy = '';
	if ($has_bridge_add && $bridges) {
		$btn_copy = button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$settings->get('theme', 'button_icon_copy'),'id'=>'btn_copy','name'=>'btn_copy','style'=>'display: none;','onclick'=>"modal_open('modal-copy','btn_copy');"]);
	}
	$btn_toggle = '';
	if ($has_bridge_edit && $bridges) {
		$btn_toggle = button::create(['type'=>'button','label'=>$text['button-toggle'],'icon'=>$settings->get('theme', 'button_icon_toggle'),'id'=>'btn_toggle','name'=>'btn_toggle','style'=>'display: none;','onclick'=>"modal_open('modal-toggle','btn_toggle');"]);
	}
	$btn_delete = '';
	if ($has_bridge_delete && $bridges) {
		$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	$btn_show_all = '';
	if ($has_bridge_all && $show !== 'all') {
		$btn_show_all = button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$settings->get('theme', 'button_icon_all'),'link'=>'?show=all']);
	}
	$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);

//build the modals
	$modal_copy = '';
	if ($has_bridge_add && $bridges) {
		$modal_copy = modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
	}
	$modal_toggle = '';
	if ($has_bridge_edit && $bridges) {
		$modal_toggle = modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
	}
	$modal_delete = '';
	if ($has_bridge_delete && $bridges) {
		$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

//build the table header columns
	$th_domain_name = '';
	if (!empty($show) && $show == 'all' && $has_bridge_all) {
		$th_domain_name = th_order_by('domain_name', $text['label-domain'], $order_by, $order);
	}
	$th_bridge_name        = th_order_by('bridge_name', $text['label-bridge_name'], $order_by, $order);
	$th_bridge_destination = th_order_by('bridge_destination', $text['label-bridge_destination'], $order_by, $order);
	$th_bridge_enabled     = th_order_by('bridge_enabled', $text['label-bridge_enabled'], $order_by, $order, null, "class='center'");

//build the row data
	$x = 0;
	foreach ($bridges as &$row) {
		app::dispatch_list_render_row('bridge_list_page_hook', $url_paging, $row, $x);
		$list_row_url = '';
		if ($has_bridge_edit) {
			$list_row_url = "bridge_edit.php?id=".urlencode($row['bridge_uuid']);
			if ($row['domain_uuid'] != $_SESSION['domain_uuid'] && $has_domain_select) {
				$list_row_url .= '&domain_uuid='.urlencode($row['domain_uuid']).'&domain_change=true';
			}
		}
		$row['_list_row_url']  = $list_row_url;
		$row['_enabled_label'] = $text['label-'.$row['bridge_enabled']];
		$row['_toggle_button'] = '';
		if ($has_bridge_edit) {
			$row['_toggle_button'] = button::create(['type'=>'submit','class'=>'link','label'=>$text['label-'.$row['bridge_enabled']],'title'=>$text['button-toggle'],'onclick'=>"list_self_check('checkbox_{$x}'); list_action_set('toggle'); list_form_submit('form_list')"]);
		}
		$row['_edit_button'] = '';
		if ($has_bridge_edit && $list_row_edit_button) {
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
	$template->assign('text',                   $text);
	$template->assign('num_rows',               $num_rows);
	$template->assign('bridges',                $bridges ?? []);
	$template->assign('search',                 $search);
	$template->assign('show',                   $show);
	$template->assign('paging_controls',        $paging_controls);
	$template->assign('paging_controls_mini',   $paging_controls_mini);
	$template->assign('token',                  $token);
	$template->assign('has_bridge_add',         $has_bridge_add);
	$template->assign('has_bridge_all',         $has_bridge_all);
	$template->assign('has_bridge_delete',      $has_bridge_delete);
	$template->assign('has_bridge_edit',        $has_bridge_edit);
	$template->assign('has_bridge_import',      $has_bridge_import);
	$template->assign('list_row_edit_button',   $list_row_edit_button);
	$template->assign('btn_import',             $btn_import);
	$template->assign('btn_add',                $btn_add);
	$template->assign('btn_copy',               $btn_copy);
	$template->assign('btn_toggle',             $btn_toggle);
	$template->assign('btn_delete',             $btn_delete);
	$template->assign('btn_show_all',           $btn_show_all);
	$template->assign('btn_search',             $btn_search);
	$template->assign('modal_copy',             $modal_copy);
	$template->assign('modal_toggle',           $modal_toggle);
	$template->assign('modal_delete',           $modal_delete);
	$template->assign('th_domain_name',         $th_domain_name);
	$template->assign('th_bridge_name',         $th_bridge_name);
	$template->assign('th_bridge_destination',  $th_bridge_destination);
	$template->assign('th_bridge_enabled',      $th_bridge_enabled);

//invoke pre-render hook
	app::dispatch_list_pre_render('bridge_list_page_hook', $url_paging, $template);

//include the header
	$document['title'] = $text['title-bridges'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('bridges_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('bridge_list_page_hook', $url_paging, $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";
