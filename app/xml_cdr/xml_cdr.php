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
	Luis Daniel Lucio Quiroz <dlucio@okay.com.mx>
	Tony Fernandez <tfernandez@smartip.ca>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permisions
	if (!permission_exists('xml_cdr_view')) {
		echo "access denied";
		exit;
	}

//set permissions
	$permission = array();
	$permission['xml_cdr_view'] = permission_exists('xml_cdr_view');
	$permission['xml_cdr_search_extension'] = permission_exists('xml_cdr_search_extension');
	$permission['xml_cdr_delete'] = permission_exists('xml_cdr_delete');
	$permission['xml_cdr_domain'] = permission_exists('xml_cdr_domain');
	$permission['xml_cdr_search_call_center_queues'] = permission_exists('xml_cdr_search_call_center_queues');
	$permission['xml_cdr_search_ring_groups'] = permission_exists('xml_cdr_search_ring_groups');
	$permission['xml_cdr_search_ivr_menus'] = permission_exists('xml_cdr_search_ivr_menus');
	$permission['xml_cdr_statistics'] = permission_exists('xml_cdr_statistics');
	$permission['xml_cdr_archive'] = permission_exists('xml_cdr_archive');
	$permission['xml_cdr_all'] = permission_exists('xml_cdr_all');
	$permission['xml_cdr_export'] = permission_exists('xml_cdr_export');
	$permission['xml_cdr_export_csv'] = permission_exists('xml_cdr_export_csv');
	$permission['xml_cdr_export_pdf'] = permission_exists('xml_cdr_export_pdf');
	$permission['xml_cdr_search'] = permission_exists('xml_cdr_search');
	$permission['xml_cdr_search_direction'] = permission_exists('xml_cdr_search_direction');
	$permission['xml_cdr_b_leg'] = permission_exists('xml_cdr_b_leg');
	$permission['xml_cdr_search_status'] = permission_exists('xml_cdr_search_status');
	$permission['xml_cdr_search_caller_id'] = permission_exists('xml_cdr_search_caller_id');
	$permission['xml_cdr_search_start_range'] = permission_exists('xml_cdr_search_start_range');
	$permission['xml_cdr_search_duration'] = permission_exists('xml_cdr_search_duration');
	$permission['xml_cdr_search_caller_destination'] = permission_exists('xml_cdr_search_caller_destination');
	$permission['xml_cdr_search_destination'] = permission_exists('xml_cdr_search_destination');
	$permission['xml_cdr_codecs'] = permission_exists('xml_cdr_codecs');
	$permission['xml_cdr_search_wait'] = permission_exists('xml_cdr_search_wait');
	$permission['xml_cdr_search_tta'] = permission_exists('xml_cdr_search_tta');
	$permission['xml_cdr_search_hangup_cause'] = permission_exists('xml_cdr_search_hangup_cause');
	$permission['xml_cdr_search_recording'] = permission_exists('xml_cdr_search_recording');
	$permission['xml_cdr_search_order'] = permission_exists('xml_cdr_search_order');
	$permission['xml_cdr_extension'] = permission_exists('xml_cdr_extension');
	$permission['xml_cdr_caller_id_name'] = permission_exists('xml_cdr_caller_id_name');
	$permission['xml_cdr_caller_id_number'] = permission_exists('xml_cdr_caller_id_number');
	$permission['xml_cdr_caller_destination'] = permission_exists('xml_cdr_caller_destination');
	$permission['xml_cdr_destination'] = permission_exists('xml_cdr_destination');
	$permission['xml_cdr_start'] = permission_exists('xml_cdr_start');
	$permission['xml_cdr_wait'] = permission_exists('xml_cdr_wait');
	$permission['xml_cdr_tta'] = permission_exists('xml_cdr_tta');
	$permission['xml_cdr_duration'] = permission_exists('xml_cdr_duration');
	$permission['xml_cdr_pdd'] = permission_exists('xml_cdr_pdd');
	$permission['xml_cdr_mos'] = permission_exists('xml_cdr_mos');
	$permission['xml_cdr_hangup_cause'] = permission_exists('xml_cdr_hangup_cause');
	$permission['xml_cdr_custom_fields'] = permission_exists('xml_cdr_custom_fields');
	$permission['xml_cdr_search_advanced'] = permission_exists('xml_cdr_search_advanced');
	$permission['xml_cdr_direction'] = permission_exists('xml_cdr_direction');
	$permission['xml_cdr_recording'] = permission_exists('xml_cdr_recording');
	$permission['xml_cdr_recording_play'] = permission_exists('xml_cdr_recording_play');
	$permission['xml_cdr_recording_download'] = permission_exists('xml_cdr_recording_download');
	$permission['xml_cdr_account_code'] = permission_exists('xml_cdr_account_code');
	$permission['xml_cdr_status'] = permission_exists('xml_cdr_status');
	$permission['xml_cdr_details'] = permission_exists('xml_cdr_details');
	$permission['xml_cdr_lose_race'] = permission_exists('xml_cdr_lose_race');
	$permission['xml_cdr_cc_agent_leg'] = permission_exists('xml_cdr_cc_agent_leg');
	$permission['xml_cdr_cc_side'] = permission_exists('xml_cdr_cc_side');
	$permission['xml_cdr_call_center_queues'] = permission_exists('xml_cdr_call_center_queues');

//add multi-lingual support
	$text = new text()->get();

//set defaults
	$archive_request = false;
	$action = '';
	$xml_cdrs = [];
	$paging_controls_mini = '';
	$paging_controls = null;
	$order_by = "";
	$read_codec = '';
	$write_codec = '';
	if (!isset($_REQUEST['show'])) {
		//set to show only this domain
		$_REQUEST['show'] = 'domain';
	}

//get posted data
	if (!$archive_request && isset($_POST['xml_cdrs']) && is_array($_POST['xml_cdrs'])) {
		$action = $_POST['action'] ?? '';
		$xml_cdrs = $_POST['xml_cdrs'] ?? [];
	}

//process the http post data by action
	if (!$archive_request && $action != '' && count($xml_cdrs) > 0) {
		switch ($action) {
			case 'delete':
				if ($permission['xml_cdr_delete']) {
					$obj = new xml_cdr;
					$obj->delete($xml_cdrs);
				}
				break;
		}

		header('Location: xml_cdr.php');
		exit;
	}

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//get the extensions
	if ($permission['xml_cdr_search_extension']) {
		$sql = "select extension_uuid, extension, number_alias from v_extensions ";
		$sql .= "where domain_uuid = :domain_uuid ";
		if (!$permission['xml_cdr_domain'] && is_array($extension_uuids) && @sizeof($extension_uuids != 0)) {
			$sql .= "and extension_uuid in ('".implode("','",$extension_uuids)."') "; //only show the user their extensions
		}
		$sql .= "order by extension asc, number_alias asc ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$extensions = $database->select($sql, $parameters, 'all');
		unset($parameters);
	}

//get the ring groups
	if ($permission['xml_cdr_search_ring_groups']) {
		$sql = "select ring_group_uuid, ring_group_name, ring_group_extension from v_ring_groups ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$sql .= "and ring_group_enabled = true ";
		$sql .= "order by ring_group_extension asc ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$ring_groups = $database->select($sql, $parameters, 'all');
		unset($parameters);
	}

//get the ivr menus
	if ($permission['xml_cdr_search_ivr_menus']) {
		$sql = "select ivr_menu_uuid, ivr_menu_name, ivr_menu_extension from v_ivr_menus ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$sql .= "and ivr_menu_enabled = true ";
		$sql .= "order by ivr_menu_extension asc ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$ivr_menus = $database->select($sql, $parameters, 'all');
		unset($parameters);
	}

//get the call center queues
	if ($permission['xml_cdr_search_call_center_queues']) {
		$sql = "select call_center_queue_uuid, queue_name, queue_extension from v_call_center_queues ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$sql .= "order by queue_extension asc ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$call_center_queues = $database->select($sql, $parameters, 'all');
		unset($parameters);
	}


//xml cdr include
	$rows_per_page = $settings->get('domain', 'paging', 50);
	require_once "xml_cdr_inc.php";

//build the javascript for send_cmd
	$send_cmd_script_html = "<script type=\"text/javascript\">\n";
	$send_cmd_script_html .= "\tfunction send_cmd(url) {\n";
	$send_cmd_script_html .= "\t\tif (window.XMLHttpRequest) { // code for IE7+, Firefox, Chrome, Opera, Safari\n";
	$send_cmd_script_html .= "\t\t\txmlhttp=new XMLHttpRequest();\n";
	$send_cmd_script_html .= "\t\t}\n";
	$send_cmd_script_html .= "\t\telse {// code for IE6, IE5\n";
	$send_cmd_script_html .= "\t\t\txmlhttp=new ActiveXObject(\"Microsoft.XMLHTTP\");\n";
	$send_cmd_script_html .= "\t\t}\n";
	$send_cmd_script_html .= "\t\txmlhttp.open(\"GET\",url,true);\n";
	$send_cmd_script_html .= "\t\txmlhttp.send(null);\n";
	$send_cmd_script_html .= "\t\tdocument.getElementById('cmd_reponse').innerHTML=xmlhttp.responseText;\n";
	$send_cmd_script_html .= "\t}\n";
	$send_cmd_script_html .= "</script>\n";

//build the javascript for toggle_select
	$toggle_select_script_html = "<script language='javascript' type='text/javascript'>";
	$toggle_select_script_html .= "\tvar fade_speed = 400;";
	$toggle_select_script_html .= "\tfunction toggle_select(select_id) {";
	$toggle_select_script_html .= "\t\t$('#'+select_id).fadeToggle(fade_speed, function() {";
	$toggle_select_script_html .= "\t\t\tdocument.getElementById(select_id).selectedIndex = 0;";
	$toggle_select_script_html .= "\t\t\tdocument.getElementById(select_id).focus();";
	$toggle_select_script_html .= "\t\t});";
	$toggle_select_script_html .= "\t}";
	$toggle_select_script_html .= "</script>";

//build the action bar export form hidden fields
	$export_form_fields_html = '';
	if ($archive_request) {
		$export_form_fields_html .= "<input type='hidden' name='archive_request' value='true'>\n";
	}
	$export_form_fields_html .= "<input type='hidden' name='cdr_id' value='".escape($cdr_id ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='direction' value='".escape($direction ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='caller_id_name' value='".escape($caller_id_name ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='start_stamp_begin' value='".escape($start_stamp_begin ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='start_stamp_end' value='".escape($start_stamp_end ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='hangup_cause' value='".escape($hangup_cause ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='status' value='".escape($status ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='caller_id_number' value='".escape($caller_id_number ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='caller_destination' value='".escape($caller_destination ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='extension_uuid' value='".escape($extension_uuid ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='destination_number' value='".escape($destination_number ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='context' value='".escape($context ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='answer_stamp_begin' value='".escape($answer_stamp_begin ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='answer_stamp_end' value='".escape($answer_stamp_end ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='end_stamp_begin' value='".escape($end_stamp_begin ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='end_stamp_end' value='".escape($end_stamp_end ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='start_epoch' value='".escape($start_epoch ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='stop_epoch' value='".escape($stop_epoch ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='duration' value='".escape($duration ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='duration_min' value='".escape($duration_min ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='duration_max' value='".escape($duration_max ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='billsec' value='".escape($billsec ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='xml_cdr_uuid' value='".escape($xml_cdr_uuid ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='bleg_uuid' value='".escape($bleg_uuid ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='accountcode' value='".escape($accountcode ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='read_codec' value='".escape($read_codec ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='write_codec' value='".escape($write_codec ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='remote_media_ip' value='".escape($remote_media_ip ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='network_addr' value='".escape($network_addr ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='bridge_uuid' value='".escape($bridge_uuid ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='leg' value='".escape($leg ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='wait_min' value='".escape($wait_min ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='wait_max' value='".escape($wait_max ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='tta_min' value='".escape($tta_min ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='tta_max' value='".escape($tta_max ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='call_center_queue_uuid' value='".escape($call_center_queue_uuid ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='ring_group_uuid' value='".escape($ring_group_uuid ?? '')."'>\n";
	$export_form_fields_html .= "<input type='hidden' name='recording' value='".escape($recording ?? '')."'>\n";
	if ($permission['xml_cdr_all'] && $_REQUEST['show'] == 'all') {
		$export_form_fields_html .= "<input type='hidden' name='show' value='all'>\n";
	}
	if (isset($_SESSION['cdr']['field']) && is_array($_SESSION['cdr']['field'])) {
		foreach ($_SESSION['cdr']['field'] as $field) {
			$array = explode(",", $field);
			$field_name = $array[count($array) - 1];
			if (isset($_REQUEST[$field_name])) {
				$export_form_fields_html .= "<input type='hidden' name='".escape($field_name)."' value='".escape($$field_name)."'>\n";
			}
		}
	}
	if (isset($order_by)) {
		$export_form_fields_html .= "<input type='hidden' name='order_by' value='".escape($order_by)."'>\n";
		$export_form_fields_html .= "<input type='hidden' name='order' value='".escape($order)."'>\n";
	}

//build the action bar
	$action_bar_html = "<div class='action_bar' id='action_bar'>\n";
	$action_bar_html .= "	<div class='heading'>";
	if ($archive_request) {
		$action_bar_html .= "<b>".$text['title-call_detail_records_archive']."</b>";
	}
	else {
		$action_bar_html .= "<b>".$text['title-call_detail_records']."</b>";
	}
	$action_bar_html .= "</div>\n";
	$action_bar_html .= "	<div class='actions'>\n";
	if (!$archive_request) {
		if ($permission['xml_cdr_statistics']) {
			$action_bar_html .= button::create(['type'=>'button','label'=>$text['button-statistics'],'icon'=>'chart-area','link'=>'xml_cdr_statistics.php']);
		}
		if ($permission['xml_cdr_archive']) {
			$action_bar_html .= button::create(['type'=>'button','label'=>$text['button-archive'],'icon'=>'archive','link'=>'xml_cdr_archive.php'.($_REQUEST['show'] == 'all' ? '?show=all' : null)]);
		}
	}
	$action_bar_html .= "<form id='frm_export' class='inline' method='post' action='xml_cdr_export.php'>\n";
	$action_bar_html .= $export_form_fields_html;
	if ($archive_request) {
		$action_bar_html .= button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$settings->get('theme', 'button_icon_back'),'link'=>'xml_cdr.php']);
	}
	$action_bar_html .= button::create(['type'=>'button','label'=>$text['button-refresh'],'icon'=>'sync-alt','style'=>'margin-left: 15px;','onclick'=>'location.reload(true);']);
	if (isset($_GET['status']) && $_GET['status'] != 'missed') {
		$action_bar_html .= button::create(['type'=>'button','label'=>$text['button-missed'],'icon'=>'phone-slash','link'=>'?status=missed']);
	}
	if ($permission['xml_cdr_export']) {
		$action_bar_html .= button::create(['type'=>'button','label'=>$text['button-export'],'icon'=>$settings->get('theme', 'button_icon_export'),'onclick'=>"toggle_select('export_format'); this.blur();"]);
		$action_bar_html .= "<select class='formfld' style='display: none; width: auto;' name='export_format' id='export_format' onchange=\"display_message('".$text['message-preparing_download']."'); toggle_select('export_format'); document.getElementById('frm_export').submit();\">";
		$action_bar_html .= "	<option value='' disabled='disabled' selected='selected'>".$text['label-format']."</option>";
		if ($permission['xml_cdr_export_csv']) {
			$action_bar_html .= "	<option value='csv'>CSV</option>";
		}
		if ($permission['xml_cdr_export_pdf']) {
			$action_bar_html .= "	<option value='pdf'>PDF</option>";
		}
		$action_bar_html .= "</select>";
	}
	if (!$archive_request && $permission['xml_cdr_delete']) {
		$action_bar_html .= button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	if ($permission['xml_cdr_all'] && $_REQUEST['show'] !== 'all') {
		$action_bar_html .= button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$settings->get('theme', 'button_icon_all'),'link'=>'?show=all']);
	}
	if ($paging_controls_mini != '') {
		$action_bar_html .= "<span style='margin-left: 15px;'>".$paging_controls_mini."</span>";
	}
	$action_bar_html .= "</form>\n";
	$action_bar_html .= "	</div>\n";
	$action_bar_html .= "	<div style='clear: both;'></div>\n";
	$action_bar_html .= "</div>\n";

//build the delete modal
	$modal_delete_html = '';
	if (!$archive_request && $permission['xml_cdr_delete']) {
		$modal_delete_html = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

//build the search form
	$search_form_html = '';
	if ($permission['xml_cdr_search']) {
		$search_form_html .= "<form name='frm' id='frm' method='get'>\n";
		$search_form_html .= "<div class='card'>\n";
		$search_form_html .= "<div class='form_grid'>\n";

		if ($permission['xml_cdr_search_direction']) {
			$search_form_html .= "	<div class='form_set'>\n";
			$search_form_html .= "		<div class='label'>\n";
			$search_form_html .= "			".$text['label-direction']."\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "		<div class='field'>\n";
			$search_form_html .= "			<select name='direction' class='formfld'>\n";
			$search_form_html .= "				<option value=''></option>\n";
			$search_form_html .= "				<option value='inbound' ".(($direction == "inbound") ? "selected='selected'" : null).">".$text['label-inbound']."</option>\n";
			$search_form_html .= "				<option value='outbound' ".(($direction == "outbound") ? "selected='selected'" : null).">".$text['label-outbound']."</option>\n";
			$search_form_html .= "				<option value='local' ".(($direction == "local") ? "selected='selected'" : null).">".$text['label-local']."</option>\n";
			$search_form_html .= "			</select>\n";
			if ($permission['xml_cdr_b_leg']){
				$search_form_html .= "		<select name='leg' class='formfld'>\n";
				$search_form_html .= "			<option value=''></option>\n";
				$search_form_html .= "			<option value='a' ".(!empty($_REQUEST["leg"]) && $leg == 'a' ? "selected='selected'" : null).">a-leg</option>\n";
				$search_form_html .= "			<option value='b' ".($leg == 'b' ? "selected='selected'" : null).">b-leg</option>\n";
				$search_form_html .= "		</select>\n";
			}
			$search_form_html .= "		</div>\n";
			$search_form_html .= "	</div>\n";
		}
		if ($permission['xml_cdr_search_status']) {
			$search_form_html .= "	<div class='form_set'>\n";
			$search_form_html .= "		<div class='label'>\n";
			$search_form_html .= "			".$text['label-status']."\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "		<div class='field'>\n";
			$search_form_html .= "			<select name='status' class='formfld'>\n";
			$search_form_html .= "				<option value=''></option>\n";
			$search_form_html .= "				<option value='answered' ".(($status == 'answered') ? 'selected' : null).">".$text['label-answered']."</option>\n";
			$search_form_html .= "				<option value='no_answer' ".(($status == 'no_answer') ? 'selected' : null).">".$text['label-no_answer']."</option>\n";
			$search_form_html .= "				<option value='busy' ".(($status == 'busy') ? 'selected' : null).">".$text['label-busy']."</option>\n";
			$search_form_html .= "				<option value='missed' ".(($status == 'missed') ? 'selected' : null).">".$text['label-missed']."</option>\n";
			$search_form_html .= "				<option value='voicemail' ".(($status == 'voicemail') ? 'selected' : null).">".$text['label-voicemail']."</option>\n";
			$search_form_html .= "				<option value='cancelled' ".(($status == 'cancelled') ? 'selected' : null).">".$text['label-cancelled']."</option>\n";
			$search_form_html .= "				<option value='failed' ".(($status == 'failed') ? 'selected' : null).">".$text['label-failed']."</option>\n";
			$search_form_html .= "			</select>\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "	</div>\n";
		}
		if ($permission['xml_cdr_search_extension']) {
			$search_form_html .= "	<div class='form_set'>\n";
			$search_form_html .= "		<div class='label'>\n";
			$search_form_html .= "			".$text['label-extension']."\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "		<div class='field'>\n";
			$search_form_html .= "			<select class='formfld' name='extension_uuid' id='extension_uuid'>\n";
			$search_form_html .= "				<option value=''></option>";
			if (is_array($extensions) && @sizeof($extensions) != 0) {
				foreach ($extensions as $row) {
					$selected = ($row['extension_uuid'] == $extension_uuid) ? "selected" : null;
					$search_form_html .= "		<option value='".escape($row['extension_uuid'])."' ".escape($selected).">".((is_numeric($row['extension'])) ? escape($row['extension']) : escape($row['number_alias'])." (".escape($row['extension']).")")."</option>";
				}
			}
			$search_form_html .= "			</select>\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "	</div>\n";
			unset($sql, $parameters, $extensions, $row, $selected);
		}
		if ($permission['xml_cdr_search_caller_id']) {
			$search_form_html .= "	<div class='form_set'>\n";
			$search_form_html .= "		<div class='label'>\n";
			$search_form_html .= "			".$text['label-caller_id']."\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "		<div class='field no-wrap'>\n";
			$search_form_html .= "			<input type='text' class='formfld' name='caller_id_name' style='min-width: 115px; width: 115px;' placeholder=\"".$text['label-name']."\" value='".escape($caller_id_name)."'>\n";
			$search_form_html .= "			<input type='text' class='formfld' name='caller_id_number' style='min-width: 115px; width: 115px;' placeholder=\"".$text['label-number']."\" value='".escape($caller_id_number)."'>\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "	</div>\n";
		}
		if ($permission['xml_cdr_search_start_range']) {
			$search_form_html .= "	<div class='form_set'>\n";
			$search_form_html .= "		<div class='label'>\n";
			$search_form_html .= "			".$text['label-start_range']."\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "		<div class='field no-wrap'>\n";
			$search_form_html .= "			<input type='text' class='formfld datetimepicker' data-toggle='datetimepicker' data-target='#start_stamp_begin' onblur=\"$(this).datetimepicker('hide');\" style='".($settings->get('domain', 'time_format') == '24h' ? 'min-width: 115px; width: 115px;' : 'min-width: 130px; width: 130px;')."' name='start_stamp_begin' id='start_stamp_begin' placeholder='".$text['label-from']."' value='".escape($start_stamp_begin)."' autocomplete='off'>\n";
			$search_form_html .= "			<input type='text' class='formfld datetimepicker' data-toggle='datetimepicker' data-target='#start_stamp_end' onblur=\"$(this).datetimepicker('hide');\" style='".($settings->get('domain', 'time_format') == '24h' ? 'min-width: 115px; width: 115px;' : 'min-width: 130px; width: 130px;')."' name='start_stamp_end' id='start_stamp_end' placeholder='".$text['label-to']."' value='".escape($start_stamp_end)."' autocomplete='off'>\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "	</div>\n";
		}
		if ($permission['xml_cdr_search_duration']) {
			$search_form_html .= "	<div class='form_set'>\n";
			$search_form_html .= "		<div class='label'>\n";
			$search_form_html .= "			".$text['label-duration']." (".$text['label-seconds'].")\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "		<div class='field no-wrap'>\n";
			$search_form_html .= "			<input type='text' class='formfld' style='min-width: 75px; width: 75px;' name='duration_min' value='".escape($duration_min)."' placeholder=\"".$text['label-minimum']."\">\n";
			$search_form_html .= "			<input type='text' class='formfld' style='min-width: 75px; width: 75px;' name='duration_max' value='".escape($duration_max)."' placeholder=\"".$text['label-maximum']."\">\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "	</div>\n";
		}
		if ($permission['xml_cdr_search_caller_destination']) {
			$search_form_html .= "	<div class='form_set'>\n";
			$search_form_html .= "		<div class='label'>\n";
			$search_form_html .= "			".$text['label-caller_destination']."\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "		<div class='field'>\n";
			$search_form_html .= "			<input type='text' class='formfld' name='caller_destination' value='".escape($caller_destination)."'>\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "	</div>\n";
		}
		if ($permission['xml_cdr_search_destination']) {
			$search_form_html .= "	<div class='form_set'>\n";
			$search_form_html .= "		<div class='label'>\n";
			$search_form_html .= "			".$text['label-destination']."\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "		<div class='field'>\n";
			$search_form_html .= "			<input type='text' class='formfld' name='destination_number' id='destination_number' value='".escape($destination_number ?? '')."'>\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "	</div>\n";
		}
		if ($permission['xml_cdr_codecs']) {
			$search_form_html .= "	<div class='form_set'>\n";
			$search_form_html .= "		<div class='label'>\n";
			$search_form_html .= "			".$text['label-codecs']."\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "		<div class='field no-wrap'>\n";
			$search_form_html .= "			<input type='text' class='formfld' style='min-width: 115px; width: 115px;' name='read_codec' id='read_codec' value='".escape($read_codec)."' placeholder=\"".$text['label-codec_read']."\">\n";
			$search_form_html .= "			<input type='text' class='formfld' style='min-width: 115px; width: 115px;' name='write_codec' id='write_codec' value='".escape($write_codec)."' placeholder=\"".$text['label-codec_write']."\">\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "	</div>\n";
		}
		if ($permission['xml_cdr_search_wait']) {
			$search_form_html .= "	<div class='form_set'>\n";
			$search_form_html .= "		<div class='label'>\n";
			$search_form_html .= "			".$text['label-wait']." (".$text['label-seconds'].")\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "		<div class='field no-wrap'>\n";
			$search_form_html .= "			<input type='text' class='formfld' style='min-width: 75px; width: 75px;' name='wait_min' id='wait_min' value='".escape($wait_min)."' placeholder=\"".$text['label-minimum']."\">\n";
			$search_form_html .= "			<input type='text' class='formfld' style='min-width: 75px; width: 75px;' name='wait_max' id='wait_max' value='".escape($wait_max)."' placeholder=\"".$text['label-maximum']."\">\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "	</div>\n";
		}
		if ($permission['xml_cdr_search_tta']) {
			$search_form_html .= "	<div class='form_set'>\n";
			$search_form_html .= "		<div class='label'>\n";
			$search_form_html .= "			".$text['label-tta']." (".$text['label-seconds'].")\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "		<div class='field no-wrap'>\n";
			$search_form_html .= "			<input type='text' class='formfld' style='min-width: 75px; width: 75px;' name='tta_min' id='tta_min' value='".escape($tta_min)."' placeholder=\"".$text['label-minimum']."\">\n";
			$search_form_html .= "			<input type='text' class='formfld' style='min-width: 75px; width: 75px;' name='tta_max' id='tta_max' value='".escape($tta_max)."' placeholder=\"".$text['label-maximum']."\">\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "	</div>\n";
		}
		if ($permission['xml_cdr_search_hangup_cause']) {
			$search_form_html .= "	<div class='form_set'>\n";
			$search_form_html .= "		<div class='label'>\n";
			$search_form_html .= "			".$text['label-hangup_cause']."\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "		<div class='field'>\n";
			$search_form_html .= "			<select name='hangup_cause' class='formfld'>\n";
			$search_form_html .= "				<option value=''></option>\n";
			$cdr_status_options = array(
				'NORMAL_CLEARING',
				'ORIGINATOR_CANCEL',
				'BLIND_TRANSFER',
				'LOSE_RACE',
				'NO_ANSWER',
				'NORMAL_UNSPECIFIED',
				'NO_USER_RESPONSE',
				'NO_ROUTE_DESTINATION',
				'SUBSCRIBER_ABSENT',
				'NORMAL_TEMPORARY_FAILURE',
				'ATTENDED_TRANSFER',
				'PICKED_OFF',
				'USER_BUSY',
				'CALL_REJECTED',
				'INVALID_NUMBER_FORMAT',
				'NETWORK_OUT_OF_ORDER',
				'DESTINATION_OUT_OF_ORDER',
				'RECOVERY_ON_TIMER_EXPIRE',
				'MANAGER_REQUEST',
				'MEDIA_TIMEOUT',
				'UNALLOCATED_NUMBER',
				'NONE',
				'EXCHANGE_ROUTING_ERROR',
				'ALLOTTED_TIMEOUT',
				'CHAN_NOT_IMPLEMENTED',
				'INCOMPATIBLE_DESTINATION',
				'USER_NOT_REGISTERED',
				'SYSTEM_SHUTDOWN',
				'MANDATORY_IE_MISSING',
				'REQUESTED_CHAN_UNAVAIL'
			);
			sort($cdr_status_options);
			foreach ($cdr_status_options as $cdr_status) {
				$selected = ($hangup_cause == $cdr_status) ? "selected='selected'" : null;
				$cdr_status_label = ucwords(strtolower(str_replace("_", " ", $cdr_status)));
				$search_form_html .= "			<option value='".escape($cdr_status)."' ".escape($selected).">".escape($cdr_status_label)."</option>\n";
			}
			$search_form_html .= "			</select>\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "	</div>\n";
		}
		if ($permission['xml_cdr_search_recording']) {
			$search_form_html .= "	<div class='form_set'>\n";
			$search_form_html .= "		<div class='label'>\n";
			$search_form_html .= "			".$text['label-recording']."\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "		<div class='field'>\n";
			$search_form_html .= "			<select name='recording' class='formfld'>\n";
			$search_form_html .= "				<option value=''></option>\n";
			$search_form_html .= "				<option value='true' ".($recording == 'true' ? "selected='selected'" : null).">".$text['label-true']."</option>\n";
			$search_form_html .= "				<option value='false' ".($recording == 'false' ? "selected='selected'" : null).">".$text['label-false']."</option>\n";
			$search_form_html .= "			</select>\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "	</div>\n";
		}
		if ($permission['xml_cdr_search_order']) {
			$search_form_html .= "	<div class='form_set'>\n";
			$search_form_html .= "		<div class='label'>\n";
			$search_form_html .= "			".$text['label-order']."\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "		<div class='field no-wrap'>\n";
			$search_form_html .= "			<select name='order_by' class='formfld'>\n";
			if ($permission['xml_cdr_extension']) {
				$search_form_html .= "			<option value='extension' ".($order_by == 'extension' ? "selected='selected'" : null).">".$text['label-extension']."</option>\n";
			}
			if ($permission['xml_cdr_all']) {
				$search_form_html .= "			<option value='domain_name' ".($order_by == 'domain_name' ? "selected='selected'" : null).">".$text['label-domain']."</option>\n";
			}
			if ($permission['xml_cdr_caller_id_name']) {
				$search_form_html .= "			<option value='caller_id_name' ".($order_by == 'caller_id_name' ? "selected='selected'" : null).">".$text['label-caller_id_name']."</option>\n";
			}
			if ($permission['xml_cdr_caller_id_number']) {
				$search_form_html .= "			<option value='caller_id_number' ".($order_by == 'caller_id_number' ? "selected='selected'" : null).">".$text['label-caller_id_number']."</option>\n";
			}
			if ($permission['xml_cdr_caller_destination']) {
				$search_form_html .= "			<option value='caller_destination' ".($order_by == 'caller_destination' ? "selected='selected'" : null).">".$text['label-caller_destination']."</option>\n";
			}
			if ($permission['xml_cdr_destination']) {
				$search_form_html .= "			<option value='destination_number' ".($order_by == 'destination_number' ? "selected='selected'" : null).">".$text['label-destination']."</option>\n";
			}
			if ($permission['xml_cdr_start']) {
				$search_form_html .= "			<option value='start_stamp' ".($order_by == 'start_stamp' || $order_by == '' ? "selected='selected'" : null).">".$text['label-start']."</option>\n";
			}
			if ($permission['xml_cdr_wait']) {
				$search_form_html .= "			<option value='wait' ".($order_by == 'wait' ? "selected='selected'" : null).">".$text['label-wait']."</option>\n";
			}
			if ($permission['xml_cdr_tta']) {
				$search_form_html .= "			<option value='tta' ".($order_by == 'tta' ? "selected='selected'" : null).">".$text['label-tta']."</option>\n";
			}
			if ($permission['xml_cdr_duration']) {
				$search_form_html .= "			<option value='duration' ".($order_by == 'duration' ? "selected='selected'" : null).">".$text['label-duration']."</option>\n";
			}
			if ($permission['xml_cdr_pdd']) {
				$search_form_html .= "			<option value='pdd_ms' ".($order_by == 'pdd_ms' ? "selected='selected'" : null).">".$text['label-pdd']."</option>\n";
			}
			if ($permission['xml_cdr_mos']) {
				$search_form_html .= "			<option value='rtp_audio_in_mos' ".($order_by == 'rtp_audio_in_mos' ? "selected='selected'" : null).">".$text['label-mos']."</option>\n";
			}
			if ($permission['xml_cdr_hangup_cause']) {
				$search_form_html .= "			<option value='hangup_cause' ".($order_by == 'desc' ? "selected='selected'" : null).">".$text['label-hangup_cause']."</option>\n";
			}
			if ($permission['xml_cdr_custom_fields']) {
				if (!empty($_SESSION['cdr']['field']) && is_array($_SESSION['cdr']['field'])) {
					$search_form_html .= "			<option value='' disabled='disabled'></option>\n";
					$search_form_html .= "			<optgroup label=\"".$text['label-custom_cdr_fields']."\">\n";
					foreach ($_SESSION['cdr']['field'] as $field) {
						$array = explode(",", $field);
						$field_name = end($array);
						$field_label = ucwords(str_replace("_", " ", $field_name));
						$field_label = str_replace("Sip", "SIP", $field_label);
						if ($field_name != "destination_number") {
							$search_form_html .= "		<option value='".$field_name."' ".($order_by == $field_name ? "selected='selected'" : null).">".$field_label."</option>\n";
						}
					}
					$search_form_html .= "			</optgroup>\n";
				}
			}
			$search_form_html .= "			</select>\n";
			$search_form_html .= "			<select name='order' class='formfld'>\n";
			$search_form_html .= "				<option value='desc' ".($order == 'desc' ? "selected='selected'" : null).">".$text['label-descending']."</option>\n";
			$search_form_html .= "				<option value='asc' ".($order == 'asc' ? "selected='selected'" : null).">".$text['label-ascending']."</option>\n";
			$search_form_html .= "			</select>\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "	</div>\n";

			if ($permission['xml_cdr_search_call_center_queues'] && is_array($call_center_queues) && @sizeof($call_center_queues) != 0) {
				$search_form_html .= "	<div class='form_set'>\n";
				$search_form_html .= "		<div class='label'>\n";
				$search_form_html .= "			".$text['label-call_center_queue']."\n";
				$search_form_html .= "		</div>\n";
				$search_form_html .= "		<div class='field'>\n";
				$search_form_html .= "			<select class='formfld' name='call_center_queue_uuid' id='call_center_queue_uuid'>\n";
				$search_form_html .= "				<option value=''></option>";
				foreach ($call_center_queues as $row) {
					$selected = ($row['call_center_queue_uuid'] == $call_center_queue_uuid) ? "selected" : null;
					$search_form_html .= "		<option value='".escape($row['call_center_queue_uuid'])."' ".escape($selected).">".((is_numeric($row['queue_extension'])) ? escape($row['queue_extension']." (".$row['queue_name'].")") : escape($row['queue_extension'])." (".escape($row['queue_extension']).")")."</option>";
				}
				$search_form_html .= "			</select>\n";
				$search_form_html .= "		</div>\n";
				$search_form_html .= "	</div>\n";
				unset($call_center_queues, $row, $selected);
			}

			if ($permission['xml_cdr_search_ring_groups'] && is_array($ring_groups) && @sizeof($ring_groups) != 0) {
				$search_form_html .= "	<div class='form_set'>\n";
				$search_form_html .= "		<div class='label'>\n";
				$search_form_html .= "			".$text['label-ring_group']."\n";
				$search_form_html .= "		</div>\n";
				$search_form_html .= "		<div class='field'>\n";
				$search_form_html .= "			<select class='formfld' name='ring_group_uuid' id='ring_group_uuid'>\n";
				$search_form_html .= "				<option value=''></option>";
				foreach ($ring_groups as $row) {
					$selected = ($row['ring_group_uuid'] == $ring_group_uuid) ? "selected" : null;
					$search_form_html .= "		<option value='".escape($row['ring_group_uuid'])."' ".escape($selected).">".((is_numeric($row['ring_group_extension'])) ? escape($row['ring_group_extension']." (".$row['ring_group_name'].")") : escape($row['ring_group_extension'])." (".escape($row['ring_group_extension']).")")."</option>";
				}
				$search_form_html .= "			</select>\n";
				$search_form_html .= "		</div>\n";
				$search_form_html .= "	</div>\n";
				unset($ring_groups, $row, $selected);
			}
		}

		if ($permission['xml_cdr_search_ivr_menus'] && is_array($ivr_menus) && @sizeof($ivr_menus) != 0) {
			$search_form_html .= "	<div class='form_set'>\n";
			$search_form_html .= "		<div class='label'>\n";
			$search_form_html .= "			".$text['label-ivr_menu']."\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "		<div class='field'>\n";
			$search_form_html .= "			<select class='formfld' name='ivr_menu_uuid' id='ivr_menu_uuid'>\n";
			$search_form_html .= "				<option value=''></option>";
			foreach ($ivr_menus as $row) {
				$selected = ($row['ivr_menu_uuid'] == $ivr_menu_uuid) ? "selected" : null;
				$search_form_html .= "		<option value='".escape($row['ivr_menu_uuid'])."' ".escape($selected).">".((is_numeric($row['ivr_menu_extension'])) ? escape($row['ivr_menu_extension']." (".$row['ivr_menu_name'].")") : escape($row['ivr_menu_extension'])." (".escape($row['ivr_menu_extension']).")")."</option>";
			}
			$search_form_html .= "			</select>\n";
			$search_form_html .= "		</div>\n";
			$search_form_html .= "	</div>\n";
			unset($ivr_menus, $row, $selected);
		}

		$search_form_html .= "</div>\n";

		button::$collapse = false;
		$search_form_html .= "<div style='display: flex; justify-content: flex-end; padding-top: 15px; margin-left: 20px; white-space: nowrap;'>";
		if ($permission['xml_cdr_all'] && $_REQUEST['show'] == 'all') {
			$search_form_html .= "<input type='hidden' name='show' value='all'>\n";
		}
		if (!$archive_request && $permission['xml_cdr_search_advanced']) {
			$search_form_html .= button::create(['type'=>'button','label'=>$text['button-advanced_search'],'icon'=>'tools','link'=>"xml_cdr_search.php".($_REQUEST['show'] == 'all' ? '?show=all' : null),'style'=>'margin-right: 15px;']);
		}
		$search_form_html .= button::create(['label'=>$text['button-reset'],'icon'=>$settings->get('theme', 'button_icon_reset'),'type'=>'button','link'=>($archive_request ? 'xml_cdr_archive.php' : 'xml_cdr.php')]);
		$search_form_html .= button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_save','name'=>'submit']);
		$search_form_html .= "</div>\n";
		$search_form_html .= "</div>\n";
		$search_form_html .= "<br />\n";
		$search_form_html .= "</form>";
	}

//build the column overflow style
	$col_overflow_style_html = "<style>\n";
	if ($settings->get('cdr', 'column_overflow', 'hidden') == 'scroll') {
		$col_overflow_style_html .= ".hide-sm-dn, .hide-md-dn, .hide-lg-dn {\n";
		$col_overflow_style_html .= "	all: revert;\n";
		$col_overflow_style_html .= "}\n";
		$col_overflow_style_html .= "div.card {\n";
		$col_overflow_style_html .= "	overflow-x: scroll;\n";
		$col_overflow_style_html .= "}\n";
	}
	else {
		$col_overflow_style_html .= "div.card {\n";
		$col_overflow_style_html .= "	overflow-x: hidden;\n";
		$col_overflow_style_html .= "}\n";
	}
	$col_overflow_style_html .= "</style>\n";

//build the column headers
	$col_count = 0;
	$col_headers_html = "<tr class='list-header'>\n";
	if (!$archive_request && $permission['xml_cdr_delete']) {
		$col_headers_html .= "	<th class='checkbox'>\n";
		$col_headers_html .= "		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);' ".(empty($result) ? "style='visibility: hidden;'" : null).">\n";
		$col_headers_html .= "	</th>\n";
		$col_count++;
	}
	if ($permission['xml_cdr_direction']) {
		$col_headers_html .= "<th class='shrink'>&nbsp;</th>\n";
		$col_count++;
	}
	if ($permission['xml_cdr_extension']) {
		$col_headers_html .= "<th class='hide-sm-dn shrink'>".$text['label-extension']."</th>\n";
		$col_count++;
	}
	if ($permission['xml_cdr_all'] && $_REQUEST['show'] == "all") {
		$col_headers_html .= "<th class='hide-md-dn'>".$text['label-domain']."</th>\n";
		$col_count++;
	}
	if ($permission['xml_cdr_caller_id_name']) {
		$col_headers_html .= "<th class='hide-md-dn' style='min-width: 90px;'>".$text['label-caller_id_name']."</th>\n";
		$col_count++;
	}
	if ($permission['xml_cdr_caller_id_number']) {
		$col_headers_html .= "<th>".$text['label-caller_id_number']."</th>\n";
		$col_count++;
	}
	if ($permission['xml_cdr_caller_destination']) {
		$col_headers_html .= "<th class='no-wrap'>".$text['label-caller_destination']."</th>\n";
		$col_count++;
	}
	if ($permission['xml_cdr_destination']) {
		$col_headers_html .= "<th class='hide-md-dn shrink'>".$text['label-destination']."</th>\n";
		$col_count++;
	}
	if ($permission['xml_cdr_recording'] && ($permission['xml_cdr_recording_play'] || $permission['xml_cdr_recording_download'])) {
		$col_headers_html .= "<th class='center'>".$text['label-recording']."</th>\n";
		$col_count++;
	}
	if ($permission['xml_cdr_account_code']) {
		$col_headers_html .= "<th class='left hide-md-dn'>".$text['label-accountcode']."</th>\n";
		$col_count++;
	}
	if ($permission['xml_cdr_custom_fields']) {
		if (isset($_SESSION['cdr']['field']) && is_array($_SESSION['cdr']['field']) && @sizeof($_SESSION['cdr']['field'])) {
			foreach ($_SESSION['cdr']['field'] as $field) {
				$array = explode(",", $field);
				$field_name = end($array);
				$field_label = ucwords(str_replace("_", " ", $field_name));
				$field_label = str_replace("Sip", "SIP", $field_label);
				if ($field_name != "destination_number") {
					$col_headers_html .= "<th class='right'>".$field_label."</th>\n";
					$col_count++;
				}
			}
		}
	}
	if ($permission['xml_cdr_start']) {
		$col_headers_html .= "<th class='center shrink hide-sm-dn'>".$text['label-date']."</th>\n";
		$col_headers_html .= "<th class='center shrink hide-lg-dn'>".$text['label-time']."</th>\n";
		$col_count += 2;
	}
	if ($permission['xml_cdr_codecs']) {
		$col_headers_html .= "<th class='center shrink hide-lg-dn'>".$text['label-codecs']."</th>\n";
		$col_count++;
	}
	if ($permission['xml_cdr_wait']) {
		$col_headers_html .= "<th class='right hide-lg-dn'>".$text['label-wait']."</th>\n";
		$col_count++;
	}
	if ($permission['xml_cdr_tta']) {
		$col_headers_html .= "<th class='right hide-lg-dn' title=\"".$text['description-tta']."\">".$text['label-tta']."</th>\n";
		$col_count++;
	}
	if ($permission['xml_cdr_pdd']) {
		$col_headers_html .= "<th class='right hide-lg-dn' title=\"".$text['description-pdd']."\">".$text['label-pdd']."</th>\n";
		$col_count++;
	}
	if ($permission['xml_cdr_mos']) {
		$col_headers_html .= "<th class='center hide-lg-dn' title=\"".$text['description-mos']."\">".$text['label-mos']."</th>\n";
		$col_count++;
	}
	if ($permission['xml_cdr_duration']) {
		$col_headers_html .= "<th class='center hide-sm-dn'>".$text['label-duration']."</th>\n";
		$col_count++;
	}
	if ($permission['xml_cdr_status']) {
		$col_headers_html .= "<th class='shrink'>".$text['label-status']."</th>\n";
		$col_count++;
	}
	if ($permission['xml_cdr_hangup_cause']) {
		$col_headers_html .= "<th class='hide-sm-dn shrink'>".$text['label-hangup_cause']."</th>\n";
		$col_count++;
	}
	if ($permission['xml_cdr_details']) {
		$col_headers_html .= "<td class='action-button'>&nbsp;</td>\n";
	}
	$col_headers_html .= "</tr>\n";

//build the rows
	$rendered_rows = [];
	if (is_array($result)) {

	//determine if theme images exist
		$theme_image_path = dirname(__DIR__, 2)."/themes/".$settings->get('domain', 'template', 'default')."/images/";
		$theme_cdr_images_exist = (
			file_exists($theme_image_path."icon_cdr_inbound_answered.png") &&
			file_exists($theme_image_path."icon_cdr_inbound_no_answer.png") &&
			file_exists($theme_image_path."icon_cdr_inbound_voicemail.png") &&
			file_exists($theme_image_path."icon_cdr_inbound_missed.png") &&
			file_exists($theme_image_path."icon_cdr_inbound_cancelled.png") &&
			file_exists($theme_image_path."icon_cdr_inbound_busy.png") &&
			file_exists($theme_image_path."icon_cdr_inbound_failed.png") &&
			file_exists($theme_image_path."icon_cdr_outbound_answered.png") &&
			file_exists($theme_image_path."icon_cdr_outbound_no_answer.png") &&
			file_exists($theme_image_path."icon_cdr_outbound_cancelled.png") &&
			file_exists($theme_image_path."icon_cdr_outbound_busy.png") &&
			file_exists($theme_image_path."icon_cdr_outbound_failed.png") &&
			file_exists($theme_image_path."icon_cdr_local_answered.png") &&
			file_exists($theme_image_path."icon_cdr_local_no_answer.png") &&
			file_exists($theme_image_path."icon_cdr_local_voicemail.png") &&
			file_exists($theme_image_path."icon_cdr_local_cancelled.png") &&
			file_exists($theme_image_path."icon_cdr_local_busy.png") &&
			file_exists($theme_image_path."icon_cdr_local_failed.png")
			) ? true : false;

	//simplify the variables
		$outbound_caller_id_name = $_SESSION['user']['extension'][0]['outbound_caller_id_name'] ?? '';
		$outbound_caller_id_number = $_SESSION['user']['extension'][0]['outbound_caller_id_number'] ?? '';
		$user_extension = $_SESSION['user']['extension'][0]['user'] ?? '';

	//loop through the results
		$x = 0;
		foreach ($result as $index => $row) {

		//set the status
			$status = $row['status'];
			if (empty($row['status'])) {
				$failed_array = array(
					"CALL_REJECTED",
					"CHAN_NOT_IMPLEMENTED",
					"DESTINATION_OUT_OF_ORDER",
					"EXCHANGE_ROUTING_ERROR",
					"INCOMPATIBLE_DESTINATION",
					"INVALID_NUMBER_FORMAT",
					"MANDATORY_IE_MISSING",
					"NETWORK_OUT_OF_ORDER",
					"NORMAL_TEMPORARY_FAILURE",
					"NORMAL_UNSPECIFIED",
					"NO_ROUTE_DESTINATION",
					"RECOVERY_ON_TIMER_EXPIRE",
					"REQUESTED_CHAN_UNAVAIL",
					"SUBSCRIBER_ABSENT",
					"SYSTEM_SHUTDOWN",
					"UNALLOCATED_NUMBER"
				);
				if ($row['billsec'] > 0) {
					$status = 'answered';
				}
				if ($row['hangup_cause'] == 'NO_ANSWER') {
					$status = 'no_answer';
				}
				if ($row['missed_call']) {
					$status = 'missed';
				}
				if (substr($row['destination_number'], 0, 3) == '*99') {
					$status = 'voicemail';
				}
				if ($row['hangup_cause'] == 'ORIGINATOR_CANCEL') {
					$status = 'cancelled';
				}
				if ($row['hangup_cause'] == 'USER_BUSY') {
					$status = 'busy';
				}
				if (in_array($row['hangup_cause'], $failed_array)) {
					$status = 'failed';
				}
			}

		//clear previous variables
			unset($record_path, $record_name);

		//get the hangup cause
			$hangup_cause = $row['hangup_cause'];
			$hangup_cause = str_replace("_", " ", $hangup_cause);
			$hangup_cause = strtolower($hangup_cause);
			$hangup_cause = ucwords($hangup_cause);

		//get the duration if null use 0
			$duration = $row['duration'] ?? 0;

		//determine recording properties
			if (!empty($row['record_path']) && !empty($row['record_name']) && $permission['xml_cdr_recording'] && ($permission['xml_cdr_recording_play'] || $permission['xml_cdr_recording_download'])) {
				$record_path = $row['record_path'];
				$record_name = $row['record_name'];
				$record_extension = pathinfo($record_name, PATHINFO_EXTENSION);
				switch ($record_extension) {
					case "wav" : $record_type = "audio/wav"; break;
					case "mp3" : $record_type = "audio/mpeg"; break;
					case "ogg" : $record_type = "audio/ogg"; break;
				}
			}

		//set an empty content variable
			$content = '';

		//recording playback
			if ($permission['xml_cdr_recording_play']) {
				$content .= "<tr class='list-row' id='recording_progress_bar_".$row['xml_cdr_uuid']."' style='display: none;' onclick=\"recording_seek(event,'".escape($row['xml_cdr_uuid'])."')\"><td id='playback_progress_bar_background_".escape($row['xml_cdr_uuid'])."' class='playback_progress_bar_background' colspan='".$col_count."'><span class='playback_progress_bar' id='recording_progress_".$row['xml_cdr_uuid']."'></span></td></tr>\n";
				$content .= "<tr class='list-row' style='display: none;'><td></td></tr>\n";
			}
			$list_row_url = '';
			if ($permission['xml_cdr_details']) {
				$list_row_url = "xml_cdr_details.php?id=".urlencode($row['xml_cdr_uuid']).($_REQUEST['show'] ? "&show=all" : null);
				if ($row['domain_uuid'] != $_SESSION['domain_uuid'] && permission_exists('domain_select')) {
					$list_row_url .= '&domain_uuid='.urlencode($row['domain_uuid']).'&domain_change=true';
				}
			}
			$content .= "<tr class='list-row' href='".$list_row_url."'>\n";
			if (!$archive_request && $permission['xml_cdr_delete']) {
				$content .= "	<td class='checkbox middle'>\n";
				$content .= "		<input type='checkbox' name='xml_cdrs[$x][checked]' id='checkbox_".$x."' value='true' onclick=\"checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }\">\n";
				$content .= "		<input type='hidden' name='xml_cdrs[$x][uuid]' value='".escape($row['xml_cdr_uuid'])."' />\n";
				$content .= "	</td>\n";
			}

		//determine call result and appropriate icon
			if ($permission['xml_cdr_direction']) {
				$content .= "<td class='middle'>\n";
				if ($theme_cdr_images_exist) {
					if (!empty($row['direction'])) {
						$image_name = "icon_cdr_" . $row['direction'] . "_" . $status;
						if ($row['leg'] == 'b') {
							$image_name .= '_b';
						}
						$image_name .= ".png";
						if (file_exists($theme_image_path.$image_name)) {
							$content .= "<img src='".PROJECT_PATH."/themes/".$settings->get('domain', 'template', 'default')."/images/".escape($image_name)."' width='16' style='border: none; cursor: help;' title='".$text['label-'.$row['direction']].": ".$text['label-'.$status]. ($row['leg']=='b'?'(b)':'') . "'>\n";
						}
						else { $content .= "&nbsp;"; }
					}
				}
				else { $content .= "&nbsp;"; }
				$content .= "</td>\n";
			}
		//extension
			if ($permission['xml_cdr_extension']) {
				$content .= "	<td class='middle hide-sm-dn no-wrap'>".$row['extension']." ".escape($row['extension_name'])."</td>\n";
			}
		//domain name
			if ($permission['xml_cdr_all'] && $_REQUEST['show'] == "all") {
				$content .= "	<td class='middle'>".$row['domain_name']."</td>\n";
			}
		//caller id name
			if ($permission['xml_cdr_caller_id_name']) {
				$content .= "	<td class='middle overflow hide-md-dn' title=\"".escape($row['caller_id_name'])."\">".escape($row['caller_id_name'])."</td>\n";
			}
		//source
			if ($permission['xml_cdr_caller_id_number']) {
				$content .= "	<td class='middle no-link no-wrap'>";
				$content .= "		<a href=\"javascript:void(0)\" onclick=\"send_cmd('".PROJECT_PATH."/app/click_to_call/click_to_call.php?src_cid_name=".urlencode(escape($row['caller_id_name']))."&src_cid_number=".urlencode(escape($row['caller_id_number']))."&dest_cid_name=".urlencode($outbound_caller_id_name)."&dest_cid_number=".urlencode($outbound_caller_id_number)."&src=".urlencode($user_extension)."&dest=".urlencode(escape($row['caller_id_number']))."&rec=false&ringback=us-ring&auto_answer=true');\">\n";
				if (is_numeric($row['caller_id_number'])) {
					$content .= "		".escape(format_phone(substr($row['caller_id_number'], 0, 20))).' ';
				}
				else {
					$content .= "		".escape(substr($row['caller_id_number'], 0, 20)).' ';
				}
				$content .= "		</a>";
				$content .= "	</td>\n";
			}
		//caller destination
			if ($permission['xml_cdr_caller_destination']) {
				$content .= "	<td class='middle no-link no-wrap'>";
				$content .= "		<a href=\"javascript:void(0)\" onclick=\"send_cmd('".PROJECT_PATH."/app/click_to_call/click_to_call.php?src_cid_name=".urlencode(escape($row['caller_id_name']))."&src_cid_number=".urlencode(escape($row['caller_id_number']))."&dest_cid_name=".urlencode($outbound_caller_id_name)."&dest_cid_number=".urlencode($outbound_caller_id_number)."&src=".urlencode($user_extension)."&dest=".urlencode(escape($row['caller_destination']))."&rec=false&ringback=us-ring&auto_answer=true');\">\n";
				if (is_numeric($row['caller_destination'])) {
					$content .= "		".escape(format_phone(substr($row['caller_destination'], 0, 20))).' ';
				}
				else {
					$content .= "		".escape(substr($row['caller_destination'] ?? '', 0, 20)).' ';
				}
				$content .= "		</a>";
				$content .= "	</td>\n";
			}
		//destination
			if ($permission['xml_cdr_destination']) {
				$content .= "	<td class='hide-md-dn middle no-link no-wrap'>";
				$content .= "		<a href=\"javascript:void(0)\" onclick=\"send_cmd('".PROJECT_PATH."/app/click_to_call/click_to_call.php?src_cid_name=".urlencode(escape($row['destination_number']))."&src_cid_number=".urlencode(escape($row['destination_number']))."&dest_cid_name=".urlencode($outbound_caller_id_name)."&dest_cid_number=".urlencode($outbound_caller_id_number)."&src=".urlencode($user_extension)."&dest=".urlencode(escape($row['destination_number']))."&rec=false&ringback=us-ring&auto_answer=true');\">\n";
				if (is_numeric($row['destination_number'])) {
					$content .= escape(format_phone(substr($row['destination_number'], 0, 20)))."\n";
				}
				else {
					$content .= escape(substr($row['destination_number'], 0, 20))."\n";
				}
				$content .= "		</a>\n";
				$content .= "	</td>\n";
			}
		//recording
			if ($permission['xml_cdr_recording'] && ($permission['xml_cdr_recording_play'] || $permission['xml_cdr_recording_download'])) {
				if (!empty($record_path) || !empty($record_name)) {
					$content .= "	<td class='middle button center no-link no-wrap'>";
					if ($permission['xml_cdr_recording_play']) {
						$content .= 	"<audio id='recording_audio_".escape($row['xml_cdr_uuid'])."' style='display: none;' preload='none' ontimeupdate=\"update_progress('".escape($row['xml_cdr_uuid'])."')\" onended=\"recording_reset('".escape($row['xml_cdr_uuid'])."');\" src=\"download.php?id=".escape($row['xml_cdr_uuid'])."\" type='".escape($record_type)."'></audio>";
						$content .= button::create(['type'=>'button','title'=>$text['label-play'].' / '.$text['label-pause'],'icon'=>$settings->get('theme', 'button_icon_play'),'id'=>'recording_button_'.escape($row['xml_cdr_uuid']),'onclick'=>"recording_play('".escape($row['xml_cdr_uuid'])."')"]);
					}
					if ($permission['xml_cdr_recording_download']) {
						$content .= button::create(['type'=>'button','title'=>$text['label-download'],'icon'=>$settings->get('theme', 'button_icon_download'),'link'=>"download.php?id=".urlencode($row['xml_cdr_uuid'])."&t=bin"]);
					}
					$content .= 	"</td>\n";
				}
				else {
					$content .= "	<td>&nbsp;</td>\n";
				}
			}
		//account code
			if ($permission['xml_cdr_account_code']) {
				$content .= "	<td class='middle hide-md-dn no-wrap '>";
				$content .= 		$row['accountcode'];
				$content .= "	</td>\n";
			}
		//custom cdr fields
			if ($permission['xml_cdr_custom_fields']) {
				if (!empty($_SESSION['cdr']['field']) && is_array($_SESSION['cdr']['field'])) {
					foreach ($_SESSION['cdr']['field'] as $field) {
						$array = explode(",", $field);
						$field_name = $array[count($array) - 1];
						if ($field_name != "destination_number") {
							$content .= "	<td class='middle center no-wrap'>".escape($row[$field_name])."</td>\n";
						}
					}
				}
			}
		//start
			if ($permission['xml_cdr_start']) {
				$content .= "	<td class='middle right no-wrap hide-sm-dn'>".$row['start_date_formatted']."</td>\n";
				$content .= "	<td class='middle right no-wrap hide-lg-dn'>".$row['start_time_formatted']."</td>\n";
			}
		//codec
			if ($permission['xml_cdr_codecs']) {
				$content .= "	<td class='middle right hide-lg-dn no-wrap'>".($row['read_codec'] ?? '').' / '.($row['write_codec'] ?? '')."</td>\n";
			}
		//wait - total time caller waited
			if ($permission['xml_cdr_wait']) {
				$content .= "	<td class='middle right hide-lg-dn'>".(!empty($row['wait']) && $row['wait'] >= 0 ? gmdate("i:s", $row['wait']) : "&nbsp;")."</td>\n";
			}
		//tta (time to answer)
			if ($permission['xml_cdr_tta']) {
				$content .= "	<td class='middle right hide-lg-dn'>".(!empty($row['tta']) && $row['tta'] >= 0 ? $row['tta'] : "&nbsp;")."</td>\n";
			}
		//pdd (post dial delay)
			if ($permission['xml_cdr_pdd']) {
				$content .= "	<td class='middle right hide-lg-dn'>".number_format(escape($row['pdd_ms'])/1000,2)."</td>\n";
			}
		//mos (mean opinion score)
			if ($permission['xml_cdr_mos']) {
				if(!empty($row['rtp_audio_in_mos']) && is_numeric($row['rtp_audio_in_mos'])) {
					$title = " title='".$text['label-mos_score-'.round($row['rtp_audio_in_mos'])]."'";
					$value = $row['rtp_audio_in_mos'];
				}
				$content .= "	<td class='middle center hide-lg-dn' ".($title ?? '').">".($value ?? '')."</td>\n";
			}
		//duration
			if ($permission['xml_cdr_duration']) {
				$content .= "	<td class='middle center hide-sm-dn'>".gmdate("G:i:s", $duration)."</td>\n";
			}
		//call result/status
			if ($permission['xml_cdr_status']) {
				$content .= "	<td class='middle no-wrap'><a href='".$list_row_url."'>".escape($text['label-'.$status] ?? '')."</a></td>\n";
			}
		//hangup cause
			if ($permission['xml_cdr_hangup_cause']) {
				$content .= "	<td class='middle no-wrap hide-sm-dn'><a href='".$list_row_url."'>".escape($hangup_cause)."</a></td>\n";
			}
			$content .= "</tr>\n";

		//show the leg b only to those with the permission
			if ($row['leg'] == 'a') {
				$rendered_rows[] = $content;
			}
			else if ($row['leg'] == 'b' && $permission['xml_cdr_b_leg']) {
				$rendered_rows[] = $content;
			}
			unset($content);

			$x++;
		}
		unset($sql, $result, $row_count);
	}

//build the template
	$template = new template();
	$template->engine = 'smarty';
	$template->template_dir = __DIR__.'/resources/views';
	$template->cache_dir = sys_get_temp_dir();
	$template->init();

//assign the template variables
	$template->assign('text',                      $text);
	$template->assign('send_cmd_script_html',       $send_cmd_script_html);
	$template->assign('toggle_select_script_html',  $toggle_select_script_html);
	$template->assign('action_bar_html',            $action_bar_html);
	$template->assign('modal_delete_html',          $modal_delete_html);
	$template->assign('search_form_html',           $search_form_html);
	$template->assign('col_overflow_style_html',    $col_overflow_style_html);
	$template->assign('col_headers_html',           $col_headers_html);
	$template->assign('rendered_rows',              $rendered_rows);
	$template->assign('paging_controls',            $paging_controls);
	$template->assign('token',                      $token);

//invoke pre-render hook
	app::dispatch_list_pre_render('xml_cdr_list_page_hook', 'xml_cdr.php', $template);

//include the header
	if ($archive_request) {
		$document['title'] = $text['title-call_detail_records_archive'];
	}
	else {
		$document['title'] = $text['title-call_detail_records'];
	}
	require_once "resources/header.php";

//render the template
	$html = $template->render('xml_cdr_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('xml_cdr_list_page_hook', 'xml_cdr.php', $html);
	echo $html;

//store last search/sort query parameters in session
	$_SESSION['xml_cdr']['last_query'] = $_SERVER["QUERY_STRING"];

//include the footer
	require_once "resources/footer.php";
