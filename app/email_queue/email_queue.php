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
	Portions created by the Initial Developer are Copyright (C) 2022-2025
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (!permission_exists('email_queue_view')) {
		echo "access denied";
		exit;
	}
	$has_domain_select      = permission_exists('domain_select');
	$has_email_queue_add    = permission_exists('email_queue_add');
	$has_email_queue_all    = permission_exists('email_queue_all');
	$has_email_queue_delete = permission_exists('email_queue_delete');
	$has_email_queue_edit   = permission_exists('email_queue_edit');

//add multi-lingual support
	$text = new text()->get();

//get the http post data
	if (!empty($_POST['email_queue']) && is_array($_POST['email_queue'])) {
		$action = $_POST['action'];
		$search = $_POST['search'] ?? '';
		$email_queue = $_POST['email_queue'];
	}

//process the http post data by action
	if (!empty($action) && !empty($email_queue) && is_array($email_queue) && @sizeof($email_queue) != 0) {

		//validate the token
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-invalid_token'],'negative');
			header('Location: email_queue.php');
			exit;
		}

		//prepare the array
		$x = 0;
		foreach ($email_queue as $row) {
			//email class queue uuid
			$array[$x]['checked'] = $row['checked'] ?? null;
			$array[$x]['uuid'] = $row['email_queue_uuid'];

			// database class uuid
			//$array['email_queue'][$x]['checked'] = $row['checked'];
			//$array['email_queue'][$x]['email_queue_uuid'] = $row['email_queue_uuid'];
			$x++;
		}

		//send the array to the database class
		switch ($action) {
			case 'resend':
				if ($has_email_queue_edit) {
					$obj = new email_queue;
					$obj->resend($array);
				}
				break;
			case 'delete':
				if ($has_email_queue_delete) {
					$obj = new email_queue;
					$obj->delete($array);
				}
				break;
		}

		//redirect the user
		header('Location: email_queue.php'.($search != '' ? '?search='.urlencode($search) : ''));
		exit;
	}

//get order and order by
	$order_by = $_GET["order_by"] ?? null;
	$order = $_GET["order"] ?? null;

//add the search
	if (isset($_GET["search"])) {
		$search = strtolower($_GET["search"]);
	}

//get the count
	$sql = "select count(email_queue_uuid) ";
	$sql .= "from v_email_queue ";
	$sql .= "where true ";
	if (isset($search)) {
		$sql .= "and (";
		$sql .= "	lower(email_from) like :search ";
		$sql .= "	or lower(email_to) like :search ";
		$sql .= "	or lower(email_subject) like :search ";
		$sql .= "	or lower(email_body) like :search ";
		$sql .= "	or lower(email_status) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	if (isset($_GET["email_status"]) && $_GET["email_status"] != '') {
		$sql .= "and email_status = :email_status ";
		$parameters['email_status'] = $_GET["email_status"];
	}
	//else {
	//	$sql .= "where (domain_uuid = :domain_uuid or domain_uuid is null) ";
	//	$parameters['domain_uuid'] = $domain_uuid;
	//}
	$num_rows = $database->select($sql, $parameters ?? null, 'column');
	unset($sql, $parameters);

//prepare to page the results
	$rows_per_page = $settings->get('domain', 'paging', 50);
	$param = !empty($_GET["email_status"]) ? "&email_status=".urlencode($_GET["email_status"]) : null;
	$param .= !empty($search) ? "&search=".urlencode($search) : null;
	$param .= !empty($_REQUEST['show']) && $_REQUEST['show'] == 'all' && $has_email_queue_all ? "&show=all" : null;
	$page = !empty($_REQUEST['page']) && is_numeric($_REQUEST['page']) ? $_REQUEST['page'] : 0;
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//set the time zone
	$time_zone = $settings->get('domain', 'time_zone', date_default_timezone_get());

//set the time format options: 12h, 24h
	if ($settings->get('domain', 'time_format') == '24h') {
		$time_format = 'HH24:MI:SS';
	}
	else {
		$time_format = 'HH12:MI:SS am';
	}

//get the list
	$sql = "select ";
	$sql .= "email_date, ";
	$sql .= "to_char(timezone(:time_zone, email_date), 'DD Mon YYYY') as email_date_formatted, \n";
	$sql .= "to_char(timezone(:time_zone, email_date), '".$time_format."') as email_time_formatted, \n";
	$sql .= "email_queue_uuid, ";
	$sql .= "hostname, ";
	$sql .= "email_from, ";
	$sql .= "email_to, ";
	$sql .= "email_subject, ";
	$sql .= "substring(email_body, 0, 80) as email_body, ";
	//$sql .= "email_action_before, ";
	$sql .= "email_action_after, ";
	$sql .= "email_status, ";
	$sql .= "email_retry_count ";
	$sql .= "from v_email_queue ";
	$sql .= "where true ";
	if (isset($search)) {
		$sql .= "and (";
		$sql .= "	lower(email_from) like :search ";
		$sql .= "	or lower(email_to) like :search ";
		$sql .= "	or lower(email_subject) like :search ";
		$sql .= "	or lower(email_body) like :search ";
		$sql .= "	or lower(email_status) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	if (isset($_GET["email_status"]) && $_GET["email_status"] != '') {
		$sql .= "and email_status = :email_status ";
		$parameters['email_status'] = $_GET["email_status"];
	}
	$sql .= order_by($order_by, $order, 'email_date', 'desc');
	$sql .= limit_offset($rows_per_page, $offset);
	$parameters['time_zone'] = $time_zone;
	$email_queue = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create('/app/email_queue/email_queue.php');

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);

//build the action bar buttons
	$btn_test = button::create(['label'=>$text['button-test'],'icon'=>'tools','type'=>'button','id'=>'test_button','onclick'=>"$(this).fadeOut(400, function(){ \$('span#form_test').fadeIn(400); \$('#to').trigger('focus'); });"]);
	$btn_send = button::create(['label'=>$text['button-send'],'icon'=>'envelope','type'=>'submit','id'=>'send_button']);
	$btn_resend = '';
	if ($has_email_queue_edit && $email_queue) {
		$btn_resend = button::create(['type'=>'button','label'=>$text['button-resend'],'icon'=>$settings->get('theme', 'button_icon_email'),'id'=>'btn_resend','name'=>'btn_resend','collapse'=>'hide-xs','style'=>'display: none; margin-left: 15px;','class'=>'+revealed','onclick'=>"modal_open('modal-resend','btn_resend');"]);
	}
	$btn_delete = '';
	if ($has_email_queue_delete && $email_queue) {
		$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display:none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);

//build the modals
	$modal_resend = '';
	if ($has_email_queue_edit && $email_queue) {
		$modal_resend = modal::create([
			'id'=>'modal-resend',
			'title'=>$text['modal_title-resend'],
			'message'=>$text['modal_message-resend'],
			'actions'=>
				button::create(['type'=>'button','label'=>$text['button-cancel'],'icon'=>$settings->get('theme', 'button_icon_cancel'),'collapse'=>'hide-xs','onclick'=>'modal_close();']).
				button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','collapse'=>'never','style'=>'float: right;','onclick'=>"modal_close(); list_action_set('resend'); list_form_submit('form_list');"])
			]);
	}
	$modal_delete = '';
	if ($has_email_queue_delete && $email_queue) {
		$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

//build the table header columns
	$th_email_to          = th_order_by('email_to', $text['label-email_to'], $order_by, $order);
	$th_email_subject     = th_order_by('email_subject', $text['label-email_subject'], $order_by, $order);
	$th_email_status      = th_order_by('email_status', $text['label-email_status'], $order_by, $order);
	$th_email_retry_count = th_order_by('email_retry_count', $text['label-email_retry_count'], $order_by, $order);

//build the email status options
	$email_status_filter = $_GET['email_status'] ?? '';
	$email_status_options_html  = "<option value='' selected='selected' disabled hidden>".$text['label-email_status']."...</option>";
	$email_status_options_html .= "<option value=''></option>";
	$email_status_options_html .= "<option value='waiting' ".($email_status_filter == 'waiting' ? "selected='selected'" : null).">".ucwords($text['label-waiting'])."</option>";
	$email_status_options_html .= "<option value='trying' ".($email_status_filter == 'trying' ? "selected='selected'" : null).">".ucwords($text['label-trying'])."</option>";
	$email_status_options_html .= "<option value='sent' ".($email_status_filter == 'sent' ? "selected='selected'" : null).">".ucwords($text['label-sent'])."</option>";
	$email_status_options_html .= "<option value='failed' ".($email_status_filter == 'failed' ? "selected='selected'" : null).">".ucwords($text['label-failed'])."</option>";

//build the row data
	$x = 0;
	foreach ($email_queue as &$row) {
		$list_row_url = '';
		if ($has_email_queue_edit) {
			$list_row_url = "email_queue_edit.php?id=".urlencode($row['email_queue_uuid']);
			if (!empty($row['domain_uuid']) && $row['domain_uuid'] != $_SESSION['domain_uuid'] && $has_domain_select) {
				$list_row_url .= '&domain_uuid='.urlencode($row['domain_uuid']).'&domain_change=true';
			}
		}
		$row['_list_row_url']           = $list_row_url;
		$row['_email_subject_decoded']  = iconv_mime_decode($row['email_subject'] ?? '');
		$row['_email_status_label']     = ucwords($text['label-'.$row['email_status']]);
		$row['_edit_button'] = '';
		if ($has_email_queue_edit && $list_row_edit_button) {
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
	$template->assign('text',                     $text);
	$template->assign('num_rows',                 $num_rows);
	$template->assign('email_queue',              $email_queue ?? []);
	$template->assign('search',                   $search ?? '');
	$template->assign('paging_controls',          $paging_controls);
	$template->assign('paging_controls_mini',     $paging_controls_mini);
	$template->assign('token',                    $token);
	$template->assign('has_email_queue_add',      $has_email_queue_add);
	$template->assign('has_email_queue_edit',     $has_email_queue_edit);
	$template->assign('has_email_queue_delete',   $has_email_queue_delete);
	$template->assign('list_row_edit_button',     $list_row_edit_button);
	$template->assign('is_mobile',               http_user_agent('mobile'));
	$template->assign('btn_test',                $btn_test);
	$template->assign('btn_send',                $btn_send);
	$template->assign('btn_resend',              $btn_resend);
	$template->assign('btn_delete',              $btn_delete);
	$template->assign('btn_search',              $btn_search);
	$template->assign('modal_resend',            $modal_resend);
	$template->assign('modal_delete',            $modal_delete);
	$template->assign('th_email_to',             $th_email_to);
	$template->assign('th_email_subject',        $th_email_subject);
	$template->assign('th_email_status',         $th_email_status);
	$template->assign('th_email_retry_count',    $th_email_retry_count);
	$template->assign('email_status_options_html', $email_status_options_html);

//invoke pre-render hook
	app::dispatch_list_pre_render('email_queue_list_page_hook', $url, $template);

//include the header
	$document['title'] = $text['title-email_queue'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('email_queue_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('email_queue_list_page_hook', $url, $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";

	echo "	#test_result_layer {\n";
	echo "		z-index: 999999;\n";
	echo "		position: absolute;\n";
