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
	Portions created by the Initial Developer are Copyright (C) 2008-2020
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (!permission_exists('module_view')) {
		echo "access denied";
		exit;
	}
	$has_module_add    = permission_exists('module_add');
	$has_module_delete = permission_exists('module_delete');
	$has_module_edit   = permission_exists('module_edit');

//add multi-lingual support
	$text = new text()->get();
	//create the url object
	$url = new url();


//define the variables
	$action = '';
	$search = '';
	$modules = '';

//get posted data
	if (!empty($_POST['modules'])) {
		$modules = $_POST['modules'];
	}
	if (!empty($_POST['action'])) {
		$action = $_POST['action'];
	}
	if (!empty($_POST['search'])) {
		$search = $_POST['search'] ?? '';
	}

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);

//process the http post data by action
	if ($action != '' && is_array($modules) && @sizeof($modules) != 0) {
		//dispatch pre-action hook
		app::dispatch_list_pre_action(null, $url, $action, $modules);

		switch ($action) {
			case 'start':
				//start the modules
				$obj = new modules;
				$obj->start($modules);
				//add a delay so that modules have time to load
				sleep(1);
				break;
			case 'stop':
				//stop the modules
				$obj = new modules;
				$obj->stop($modules);
				break;
			case 'toggle':
				//toggle enables or disables (stops) the modules
				if ($has_module_edit) {
					$obj = new modules;
					$obj->toggle($modules);
				}
				break;
			case 'delete':
				if ($has_module_delete) {
					$obj = new modules;
					$obj->delete($modules);
				}
				break;
		}

		//redirect to display updates
		//dispatch post-action hook
		app::dispatch_list_post_action(null, $url, $action, $modules);

		header('Location: modules.php'.($search != '' ? '?search='.urlencode($search) : ''));
		exit;
	}

//dispatch pre-query hook
	$query_parameters = [];
	app::dispatch_list_pre_query(null, $url, $query_parameters);

//connect to event socket
	$esl = event_socket::create();

//warn if switch not running
	if (!$esl->is_connected()) {
		message::add($text['error-event-socket'], 'negative', 5000);
	}

//use the module class to get the list of modules from the db and add any missing modules
	$module = new modules;
	$module->dir = $settings->get('switch', 'mod');
	$module->get_modules();
	$modules = $module->modules;
	//dispatch post-query hook
	app::dispatch_list_post_query(null, $url, $modules);
	$module_count = count($modules);
	$module->synch();
	$module->xml();
	$msg = $module->msg;

//show the msg
	if ($msg) {
		message::add($msg, 'negative', 5000);
	}

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//build the action bar buttons
	$esl_connected = $esl->is_connected();
	$btn_stop = '';
	$btn_start = '';
	if ($has_module_edit && $modules && $esl_connected) {
		$btn_stop  = button::create(['type'=>'button','label'=>$text['button-stop'],'icon'=>$settings->get('theme', 'button_icon_stop'),'onclick'=>"modal_open('modal-stop','btn_stop');"]);
		$btn_start = button::create(['type'=>'button','label'=>$text['button-start'],'icon'=>$settings->get('theme', 'button_icon_start'),'onclick'=>"modal_open('modal-start','btn_start');"]);
	}
	$btn_refresh = button::create(['type'=>'button','label'=>$text['button-refresh'],'icon'=>$settings->get('theme', 'button_icon_refresh'),'style'=>'margin-right: 15px;','link'=>'modules.php']);
	$btn_add = '';
	if ($has_module_add) {
		$btn_add = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','link'=>'module_edit.php']);
	}
	$btn_toggle = '';
	if ($has_module_edit && $modules) {
		$btn_toggle = button::create(['type'=>'button','label'=>$text['button-toggle'],'icon'=>$settings->get('theme', 'button_icon_toggle'),'id'=>'btn_toggle','name'=>'btn_toggle','style'=>'display: none;','onclick'=>"modal_open('modal-toggle','btn_toggle');"]);
	}
	$btn_delete = '';
	if ($has_module_delete && $modules) {
		$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}

//build the modals
	$modal_stop = '';
	$modal_start = '';
	if ($has_module_edit && !empty($modules) && $esl_connected) {
		$modal_stop  = modal::create(['id'=>'modal-stop','type'=>'general','message'=>$text['confirm-stop_modules'],'actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_stop','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('stop'); list_form_submit('form_list');"])]);
		$modal_start = modal::create(['id'=>'modal-start','type'=>'general','message'=>$text['confirm-start_modules'],'actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_start','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('start'); list_form_submit('form_list');"])]);
	}
	$modal_toggle = '';
	if ($has_module_edit && $modules) {
		$modal_toggle = modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
	}
	$modal_delete = '';
	if ($has_module_delete && $modules) {
		$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

//build the row data
	if (is_array($modules)) {
		foreach ($modules as $x => &$row) {
			app::dispatch_list_render_row('module_list_page_hook', $url, $row, $x);
			$list_row_url = '';
			if ($has_module_edit) {
				$list_row_url = "module_edit.php?id=".urlencode($row['module_uuid']);
			}
			$modifier = strtolower(trim($row['module_category']));
			$modifier = str_replace('/', '', $modifier);
			$modifier = str_replace('  ', ' ', $modifier);
			$modifier = str_replace(' ', '_', $modifier);
			$row['_list_row_url'] = $list_row_url;
			$row['_modifier']     = $modifier;
			if ($esl_connected) {
				if ($module->active($row['module_name'])) {
					$row['_status_html']   = $text['label-running'];
					$row['_action_button'] = '';
					if ($has_module_edit) {
						$row['_action_button'] = button::create(['type'=>'submit','class'=>'link','label'=>$text['label-stop'],'title'=>$text['button-stop'],'onclick'=>"list_self_check('checkbox_{$x}'); list_action_set('stop'); list_form_submit('form_list')"]);
					}
				} else {
					$row['_status_html']   = $row['module_enabled'] === true ? "<strong style='color: red;'>".$text['label-stopped']."</strong>" : $text['label-stopped'];
					$row['_action_button'] = '';
					if ($has_module_edit) {
						$row['_action_button'] = button::create(['type'=>'submit','class'=>'link','label'=>$text['label-start'],'title'=>$text['button-start'],'onclick'=>"list_self_check('checkbox_{$x}'); list_action_set('start'); list_form_submit('form_list')"]);
					}
				}
			} else {
				$row['_status_html']   = $text['label-unknown'];
				$row['_action_button'] = '';
			}
			$row['_enabled_label'] = $text['label-'.($row['module_enabled'] ? 'true' : 'false')];
			$row['_toggle_button'] = '';
			if ($has_module_edit) {
				$row['_toggle_button'] = button::create(['type'=>'submit','class'=>'link','label'=>$text['label-'.($row['module_enabled'] ? 'true' : 'false')],'title'=>$text['button-toggle'],'onclick'=>"list_self_check('checkbox_{$x}'); list_action_set('toggle'); list_form_submit('form_list')"]);
			}
			$row['_edit_button'] = '';
			if ($has_module_edit && $list_row_edit_button) {
				$row['_edit_button'] = button::create(['type'=>'button','title'=>$text['button-edit'],'icon'=>$settings->get('theme', 'button_icon_edit'),'link'=>$list_row_url]);
			}
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
	$template->assign('module_count',         $module_count);
	$template->assign('modules',              $modules ?? []);
	$template->assign('search',               $search);
	$template->assign('token',                $token);
	$template->assign('has_module_add',       $has_module_add);
	$template->assign('has_module_delete',    $has_module_delete);
	$template->assign('has_module_edit',      $has_module_edit);
	$template->assign('esl_connected',        $esl_connected);
	$template->assign('list_row_edit_button', $list_row_edit_button);
	$template->assign('btn_stop',             $btn_stop);
	$template->assign('btn_start',            $btn_start);
	$template->assign('btn_refresh',          $btn_refresh);
	$template->assign('btn_add',              $btn_add);
	$template->assign('btn_toggle',           $btn_toggle);
	$template->assign('btn_delete',           $btn_delete);
	$template->assign('modal_stop',           $modal_stop);
	$template->assign('modal_start',          $modal_start);
	$template->assign('modal_toggle',         $modal_toggle);
	$template->assign('modal_delete',         $modal_delete);

//invoke pre-render hook
	app::dispatch_list_pre_render('module_list_page_hook', $url, $template);

//include the header
	$document['title'] = $text['title-modules'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('modules_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('module_list_page_hook', $url, $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";
