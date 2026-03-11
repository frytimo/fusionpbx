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
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (permission_exists('voicemail_view')) {
		//access granted
	}
	elseif (permission_exists('voicemail_message_view')) {
		header('Location: /app/voicemails/voicemail_messages.php');
	}
	else {
		echo "access denied";
		exit;
	}
	$has_domain_select                   = permission_exists('domain_select');
	$has_voicemail_add                   = permission_exists('voicemail_add');
	$has_voicemail_all                   = permission_exists('voicemail_all');
	$has_voicemail_delete                = permission_exists('voicemail_delete');
	$has_voicemail_domain                = permission_exists('voicemail_domain');
	$has_voicemail_edit                  = permission_exists('voicemail_edit');
	$has_voicemail_export                = permission_exists('voicemail_export');
	$has_voicemail_greeting_view         = permission_exists('voicemail_greeting_view');
	$has_voicemail_import                = permission_exists('voicemail_import');
	$has_voicemail_message_view          = permission_exists('voicemail_message_view');
	$has_voicemail_transcription_enabled = permission_exists('voicemail_transcription_enabled');

//add multi-lingual support
	$text = new text()->get();
	//create the url object
	$url = new url();

//get the settings
	$settings = new settings(['database' => $database, 'domain_uuid' => $_SESSION['domain_uuid'] ?? '', 'user_uuid' => $_SESSION['user_uuid'] ?? '']);

//get the http post data
	$action = $_POST['action'] ?? '';
	$search = $_POST['search'] ?? '';
	$voicemails = $_POST['voicemails'] ?? [];

//process the http post data by action
	if (!empty($action) && !empty($voicemails)) {
		//dispatch pre-action hook
		app::dispatch_list_pre_action('voicemail_list_page_hook', $url, $action, $voicemails);

		switch ($action) {
			case 'toggle':
				if ($has_voicemail_edit) {
					$obj = new voicemail;
					$obj->voicemail_toggle($voicemails);
				}
				break;
			case 'delete':
				if ($has_voicemail_delete) {
					$obj = new voicemail;
					$obj->voicemail_delete($voicemails);
				}
				break;
		}

		//dispatch post-action hook
		app::dispatch_list_post_action('voicemail_list_page_hook', $url, $action, $voicemails);

		header('Location: voicemails.php'.($search != '' ? '?search='.urlencode($search) : ''));
		exit;
	}

//dispatch pre-query hook
	$query_parameters = [];
	app::dispatch_list_pre_query('voicemail_list_page_hook', $url, $query_parameters);

//set the voicemail uuid array
	if (isset($_SESSION['user']['voicemail'])) {
		foreach ($_SESSION['user']['voicemail'] as $row) {
			if (!empty($row['voicemail_uuid'])) {
				$voicemail_uuids[]['voicemail_uuid'] = $row['voicemail_uuid'];
			}
		}
	}
	else {
		$voicemail = new voicemail;
		$rows = $voicemail->voicemails();
		if (!empty($rows)) {
			foreach ($rows as $row) {
				$voicemail_uuids[]['voicemail_uuid'] = $row['voicemail_uuid'];
			}
		}
		unset($voicemail, $rows, $row);
	}

//get order and order by
	$order_by = $_GET["order_by"] ?? 'voicemail_id';
	$order = $_GET["order"] ?? 'asc';
	$sort = $order_by == 'voicemail_id' ? 'natural' : '';

//set additional variables
	$show = $_GET["show"] ?? '';

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);

//add the search string
	$search = strtolower($_GET["search"] ?? '');
	if (!empty($search)) {
		$sql_search = "and (";
		$sql_search .= "	lower(cast(voicemail_id as text)) like :search ";
		$sql_search .= " 	or lower(voicemail_mail_to) like :search ";
		$sql_search .= " 	or lower(cast(voicemail_local_after_email as text)) like :search ";
		$sql_search .= " 	or lower(cast(voicemail_enabled as text)) like :search ";
		$sql_search .= " 	or lower(voicemail_description) like :search ";
		$sql_search .= ") ";
	}

//prepare to page the results
	$sql = "select count(voicemail_uuid) from v_voicemails ";
	$sql .= "where true ";
	$parameters = null;
	if ($show != "all" || !$has_voicemail_all) {
		$sql .= "and (domain_uuid = :domain_uuid or domain_uuid is null) ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	if (!$has_voicemail_domain) {
		if (is_array($voicemail_uuids) && sizeof($voicemail_uuids) != 0) {
			$sql .= "and (";
			foreach ($voicemail_uuids as $x => $row) {
				$sql_where_or[] = 'voicemail_uuid = :voicemail_uuid_'.$x;
				$parameters['voicemail_uuid_'.$x] = $row['voicemail_uuid'];
			}
			if (is_array($sql_where_or) && sizeof($sql_where_or) != 0) {
				$sql .= implode(' or ', $sql_where_or);
			}
			$sql .= ")";
		}
		else {
			$sql .= "and voicemail_uuid is null ";
		}
	}
	if (!empty($sql_search)) {
		$sql .= $sql_search;
		$parameters['search'] = '%'.$search.'%';
	}
	$num_rows = $database->select($sql, $parameters, 'column');

//prepare to page the results
	$rows_per_page = $settings->get('domain', 'paging', 50);
	$param = $search ? "&search=".urlencode($search) : null;
	if ($show == "all" && $has_voicemail_all) {
		$param .= "&show=all";
	}
	$page = empty($_GET['page']) ? 0 : $_GET['page'];
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the list
	$sql = "select domain_uuid, voicemail_uuid, voicemail_id, voicemail_mail_to, voicemail_file, ";
	$sql .= "cast(voicemail_local_after_email as text), cast(voicemail_transcription_enabled as text), cast(voicemail_enabled as text), voicemail_description ";
	$sql .= "from v_voicemails ";
	$sql .= "where true ";
	$parameters = null;
	if ($show != "all" || !$has_voicemail_all) {
		$sql .= "and (domain_uuid = :domain_uuid or domain_uuid is null) ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	if (!$has_voicemail_domain) {
		if (is_array($voicemail_uuids) && sizeof($voicemail_uuids) != 0) {
			$sql .= "and (";
			foreach ($voicemail_uuids as $x => $row) {
				$sql_where_or[] = 'voicemail_uuid = :voicemail_uuid_'.$x;
				$parameters['voicemail_uuid_'.$x] = $row['voicemail_uuid'];
			}
			if (is_array($sql_where_or) && sizeof($sql_where_or) != 0) {
				$sql .= implode(' or ', $sql_where_or);
			}
			$sql .= ")";
		}
		else {
			$sql .= "and voicemail_uuid is null ";
		}
	}
	if (!empty($sql_search)) {
		$sql .= $sql_search;
		$parameters['search'] = '%'.$search.'%';
	}
	$sql .= order_by($order_by, $order, null, null, $sort);
	$sql .= limit_offset($rows_per_page, $offset);
	$voicemails = $database->select($sql, $parameters, 'all');
	//dispatch post-query hook
	app::dispatch_list_post_query('voicemail_list_page_hook', $url, $voicemails);
	unset($sql, $parameters);

//get vm count for each mailbox
	if ($has_voicemail_message_view) {
		$parameters = null;
		$sql = "select voicemail_uuid, count(voicemail_uuid) as voicemail_count ";
		$sql .= "from v_voicemail_messages where true ";
		if ($show !== 'all' || !$has_voicemail_all) {
			$sql .= "and domain_uuid = :domain_uuid ";
			$parameters['domain_uuid'] = $domain_uuid;
		}
		$sql .= "group by voicemail_uuid";
		$voicemails_count_tmp = $database->select($sql, $parameters, 'all');

		$voicemails_count = array();
		foreach ($voicemails_count_tmp as $row) {
			$voicemails_count[$row['voicemail_uuid']] = $row['voicemail_count'];
		}
		unset($sql, $parameters, $voicemails_count_tmp);
	}

//get vm greeting count for each mailbox
	if ($has_voicemail_greeting_view) {
		$parameters = null;
		$sql = "select voicemail_id, count(greeting_id) as greeting_count ";
		$sql .= "from v_voicemail_greetings where true ";
		if ($show !== 'all' || !$has_voicemail_all) {
			$sql .= "and domain_uuid = :domain_uuid ";
			$parameters['domain_uuid'] = $domain_uuid;
		}
		$sql .= "group by voicemail_id";
		$voicemail_greetings_count_tmp = $database->select($sql, $parameters, 'all');

		$voicemail_greetings_count = array();
		foreach ($voicemail_greetings_count_tmp as $row) {
			$voicemail_greetings_count[$row['voicemail_id']] = $row['greeting_count'];
		}
		unset($sql, $parameters, $voicemail_greetings_count_tmp);
	}

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//build the action bar buttons
$btn_import = '';
if ($has_voicemail_import) {
$btn_import = button::create(['type'=>'button','label'=>$text['button-import'],'icon'=>$settings->get('theme', 'button_icon_import'),'style'=>'','link'=>'voicemail_imports.php']);
}
$btn_export = '';
if ($has_voicemail_export) {
$btn_export = button::create(['type'=>'button','label'=>$text['button-export'],'icon'=>$settings->get('theme', 'button_icon_export'),'style'=>'margin-right: 15px;','link'=>'voicemail_export.php']);
}
$btn_add = '';
if ($has_voicemail_add) {
$btn_add = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','link'=>'voicemail_edit.php']);
}
$btn_toggle = '';
if ($has_voicemail_edit && $voicemails) {
$btn_toggle = button::create(['type'=>'button','label'=>$text['button-toggle'],'icon'=>$settings->get('theme', 'button_icon_toggle'),'id'=>'btn_toggle','name'=>'btn_toggle','style'=>'display: none;','onclick'=>"modal_open('modal-toggle','btn_toggle');"]);
}
$btn_delete = '';
if ($has_voicemail_delete && $voicemails) {
$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
}
$btn_show_all = '';
if ($has_voicemail_all && $show !== 'all') {
$btn_show_all = button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$settings->get('theme', 'button_icon_all'),'link'=>'?type=&show=all'.($search != '' ? "&search=".urlencode($search) : null)]);
}
$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);

//build the modals
$modal_toggle = '';
if ($has_voicemail_edit && $voicemails) {
$modal_toggle = modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
}
$modal_delete = '';
if ($has_voicemail_delete && $voicemails) {
$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
}

//build the table header columns
$th_domain_name = '';
if ($show == 'all' && $has_voicemail_all) {
$th_domain_name = th_order_by('domain_name', $text['label-domain'], $order_by, $order, $param, "class='shrink'");
}
$th_voicemail_id             = th_order_by('voicemail_id', $text['label-voicemail_id'], $order_by, $order, null, "style='width: 1%;'");
$th_voicemail_mail_to        = th_order_by('voicemail_mail_to', $text['label-voicemail_mail_to'], $order_by, $order, null, "class='hide-sm-dn'");
$th_voicemail_file           = th_order_by('voicemail_file', $text['label-voicemail_file_attached'], $order_by, $order, null, "class='center hide-md-dn' style='width: 1%;'");
$th_voicemail_local_after_email = th_order_by('voicemail_local_after_email', $text['label-voicemail_local_after_email'], $order_by, $order, null, "class='center hide-md-dn' style='width: 1%;'");
$show_transcription_col = $has_voicemail_transcription_enabled && $settings->get('transcribe', 'enabled', false) === true;
$th_transcription = '';
if ($show_transcription_col) {
$th_transcription = th_order_by('voicemail_transcription_enabled', $text['label-voicemail_transcription_enabled'], $order_by, $order, null, "class='center' style='width: 1%;'");
}
$th_tools = '';
if ($has_voicemail_message_view || $has_voicemail_greeting_view) {
$th_tools = "<th style='width: 17%;'>".$text['label-tools']."</th>\n";
}
$th_voicemail_enabled     = th_order_by('voicemail_enabled', $text['label-voicemail_enabled'], $order_by, $order, null, "class='center' style='width: 1%;'");
$th_voicemail_description = th_order_by('voicemail_description', $text['label-voicemail_description'], $order_by, $order, null, "class='hide-sm-dn'");

//build the row data
$x = 0;
foreach ($voicemails as &$row) {
app::dispatch_list_render_row('voicemail_list_page_hook', $url, $row, $x);
$list_row_url = '';
if ($has_voicemail_edit) {
$list_row_url = "voicemail_edit.php?id=".urlencode($row['voicemail_uuid']);
if ($row['domain_uuid'] != $_SESSION['domain_uuid'] && $has_domain_select) {
$list_row_url .= '&domain_uuid='.urlencode($row['domain_uuid']).'&domain_change=true';
}
}
$row['_list_row_url']             = $list_row_url;
$row['_domain_name']              = !empty($_SESSION['domains'][$row['domain_uuid']]['domain_name']) ? $_SESSION['domains'][$row['domain_uuid']]['domain_name'] : $text['label-global'];
$row['_file_attached_label']      = ($row['voicemail_file'] == 'attach') ? $text['label-true'] : $text['label-false'];
$row['_local_after_email_display']= ucwords($row['voicemail_local_after_email'] ?? '');
$row['_transcription_display']    = ucwords($row['voicemail_transcription_enabled'] ?? '');
$row['_enabled_label']            = $text['label-'.$row['voicemail_enabled']];
$tools = '';
if ($has_voicemail_greeting_view) {
$tools .= "<a href='".PROJECT_PATH."/app/voicemail_greetings/voicemail_greetings.php?id=".escape($row['voicemail_id'])."&back=".urlencode($_SERVER["REQUEST_URI"])."' style='margin-right: 15px;'>".$text['label-greetings']." (".($voicemail_greetings_count[$row['voicemail_id']] ?? 0).")</a>\n";
}
if ($has_voicemail_message_view) {
$tmp = array_key_exists($row['voicemail_uuid'], $voicemails_count ?? []) ? " (".$voicemails_count[$row['voicemail_uuid']].")" : " (0)";
$tools .= "<a href='voicemail_messages.php?id=".escape($row['voicemail_uuid'])."&back=".urlencode($_SERVER["REQUEST_URI"])."'>".$text['label-messages'].$tmp."</a>\n";
}
$row['_tools_html']    = $tools;
$row['_toggle_button'] = '';
if ($has_voicemail_edit) {
$row['_toggle_button'] = button::create(['type'=>'submit','class'=>'link','label'=>$text['label-'.$row['voicemail_enabled']],'title'=>$text['button-toggle'],'onclick'=>"list_self_check('checkbox_{$x}'); list_action_set('toggle'); list_form_submit('form_list')"]);
}
$row['_edit_button'] = '';
if ($has_voicemail_edit && $list_row_edit_button) {
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
$template->assign('text',                      $text);
$template->assign('num_rows',                  $num_rows);
$template->assign('voicemails',                $voicemails ?? []);
$template->assign('search',                    $search);
$template->assign('show',                      $show);
$template->assign('paging_controls',           $paging_controls);
$template->assign('paging_controls_mini',      $paging_controls_mini);
$template->assign('token',                     $token);
$template->assign('has_voicemail_add',         $has_voicemail_add);
$template->assign('has_voicemail_all',         $has_voicemail_all);
$template->assign('has_voicemail_delete',      $has_voicemail_delete);
$template->assign('has_voicemail_edit',        $has_voicemail_edit);
$template->assign('has_voicemail_import',      $has_voicemail_import);
$template->assign('has_voicemail_export',      $has_voicemail_export);
$template->assign('has_voicemail_greeting_view', $has_voicemail_greeting_view);
$template->assign('has_voicemail_message_view',  $has_voicemail_message_view);
$template->assign('list_row_edit_button',      $list_row_edit_button);
$template->assign('show_transcription_col',    $show_transcription_col);
$template->assign('btn_import',                $btn_import);
$template->assign('btn_export',                $btn_export);
$template->assign('btn_add',                   $btn_add);
$template->assign('btn_toggle',                $btn_toggle);
$template->assign('btn_delete',                $btn_delete);
$template->assign('btn_show_all',              $btn_show_all);
$template->assign('btn_search',                $btn_search);
$template->assign('modal_toggle',              $modal_toggle);
$template->assign('modal_delete',              $modal_delete);
$template->assign('th_domain_name',            $th_domain_name);
$template->assign('th_voicemail_id',           $th_voicemail_id);
$template->assign('th_voicemail_mail_to',      $th_voicemail_mail_to);
$template->assign('th_voicemail_file',         $th_voicemail_file);
$template->assign('th_voicemail_local_after_email', $th_voicemail_local_after_email);
$template->assign('th_transcription',          $th_transcription);
$template->assign('th_tools',                  $th_tools);
$template->assign('th_voicemail_enabled',      $th_voicemail_enabled);
$template->assign('th_voicemail_description',  $th_voicemail_description);

//invoke pre-render hook
app::dispatch_list_pre_render('voicemail_list_page_hook', $url, $template);

//include the header
$document['title'] = $text['title-voicemails'];
require_once "resources/header.php";

//render the template
$html = $template->render('voicemails_list.tpl');

//invoke post-render hook
app::dispatch_list_post_render('voicemail_list_page_hook', $url, $html);
echo $html;

//include the footer
require_once "resources/footer.php";
