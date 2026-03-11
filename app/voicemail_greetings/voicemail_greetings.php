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

//set the max php execution time
	ini_set('max_execution_time', 7200);

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('voicemail_greeting_view') || (!permission_exists('voicemail_view') && !extension_assigned($_REQUEST["id"]))) {
		echo "access denied";
		return;
	}
	$has_domain_select               = permission_exists('domain_select');
	$has_voicemail_greeting_add      = permission_exists('voicemail_greeting_add');
	$has_voicemail_greeting_delete   = permission_exists('voicemail_greeting_delete');
	$has_voicemail_greeting_download = permission_exists('voicemail_greeting_download');
	$has_voicemail_greeting_edit     = permission_exists('voicemail_greeting_edit');
	$has_voicemail_greeting_play     = permission_exists('voicemail_greeting_play');
	$has_voicemail_greeting_upload   = permission_exists('voicemail_greeting_upload');
	$has_voicemail_greeting_view     = permission_exists('voicemail_greeting_view');

//add multi-lingual support
	$text = new text()->get();

//check for speech app
	$speech_enabled = $settings->get('speech', 'enabled');

//set the defaults
	$sql_file_size = '';

//get the http get values and set them as php variables
	$voicemail_id = $_REQUEST["id"] ?? '';
	$order_by = $_GET["order_by"] ?? '';
	$order = $_GET["order"] ?? '';

//set the back button url
	$_SESSION['back'][$_SERVER['PHP_SELF']] = !empty($_GET['back']) ? urldecode($_GET['back']) : $_SESSION['back'][$_SERVER['PHP_SELF']] ?? '';

//define order by default
	if ($order_by == '') {
		$order_by = "greeting_id";
		$order = "asc";
	}

//used (above) to search the array to determine if an extension is assigned to the user
	/**
	 * Checks if the given extension number is assigned to the user.
	 *
	 * @param string $number The extension number to check.
	 *
	 * @return bool True if the extension number is assigned, False otherwise.
	 */
	function extension_assigned($number) {
		foreach ($_SESSION['user']['extension'] as $row) {
			if ((is_numeric($row['number_alias']) && $row['number_alias'] == $number) || $row['user'] == $number) {
				return true;
			}
		}
		return false;
	}

//get currently selected greeting
	$sql = "select greeting_id from v_voicemails ";
	$sql .= "where domain_uuid = :domain_uuid ";
	$sql .= "and voicemail_id = :voicemail_id ";
	$parameters = [];
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$parameters['voicemail_id'] = $voicemail_id;
	$selected_greeting_id = $database->select($sql, $parameters, 'column');
	unset($sql, $parameters);

//define greeting directory
	$greeting_dir = $settings->get('switch', 'voicemail').'/default/'.$_SESSION['domains'][$domain_uuid]['domain_name'].'/'.$voicemail_id;

//download the greeting
	if (!empty($_GET['a']) && $_GET['a'] == "download" && ($has_voicemail_greeting_play || $has_voicemail_greeting_download)) {
		if ($_GET['type'] == "rec") {
			//get the id
			$voicemail_greeting_uuid = $_GET['uuid'];

			//get voicemail greeting details from db
			$sql = "select greeting_filename, greeting_base64, greeting_id ";
			$sql .= "from v_voicemail_greetings ";
			$sql .= "where domain_uuid = :domain_uuid ";
			$sql .= "and voicemail_greeting_uuid = :voicemail_greeting_uuid ";
			$parameters = [];
			$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
			$parameters['voicemail_greeting_uuid'] = $voicemail_greeting_uuid;
			$row = $database->select($sql, $parameters, 'row');
			if (is_array($row) && @sizeof($row) != 0) {
				$greeting_filename = $row['greeting_filename'];
				$greeting_id = $row['greeting_id'];
				if (!empty($settings->get('voicemail', 'storage_type')) && $settings->get('voicemail', 'storage_type') == 'base64' && $row['greeting_base64'] != '') {
					$greeting_decoded = base64_decode($row['greeting_base64']);
					file_put_contents($greeting_dir.'/'.$greeting_filename, $greeting_decoded);
				}
			}
			unset($sql, $row, $greeting_decoded);
			if (file_exists($greeting_dir.'/'.$greeting_filename)) {

				$fd = fopen($greeting_dir.'/'.$greeting_filename, "rb");
				if (!empty($_GET['t']) && $_GET['t'] == "bin") {
					header("Content-Type: application/force-download");
					header("Content-Type: application/octet-stream");
					header("Content-Type: application/download");
					header("Content-Description: File Transfer");
				}
				else {
					$file_ext = pathinfo($greeting_filename, PATHINFO_EXTENSION);
					switch ($file_ext) {
						case "wav" : header("Content-Type: audio/x-wav"); break;
						case "mp3" : header("Content-Type: audio/mpeg"); break;
						case "ogg" : header("Content-Type: audio/ogg"); break;
					}
				}
				header('Content-Disposition: attachment; filename="'.$greeting_filename.'"');
				header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
				header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
				if (!empty($_GET['t']) && $_GET['t'] == "bin") {
					header("Content-Length: ".filesize($greeting_dir.'/'.$greeting_filename));
				}
				ob_clean();

				//content-range
				if (isset($_SERVER['HTTP_RANGE']) && (empty($_GET['t']) || $_GET['t'] != "bin")) {
					range_download($greeting_dir.'/'.$greeting_filename);
				}

				fpassthru($fd);
			}

			//if base64, remove temp greeting file (if not currently selected greeting)
			if (!empty($settings->get('voicemail', 'storage_type')) && $settings->get('voicemail', 'storage_type') == 'base64' && $row['greeting_base64'] != '') {
				if ($greeting_id != $selected_greeting_id) {
					@unlink($greeting_dir.'/'.$greeting_filename);
				}
			}
		}
		exit;
	}

//greeting limit
	if (!empty($_POST['limit_reached']) && $_POST['limit_reached'] == 'true'){
		message::add($text['message-maximum_voicemail_greetings'].' 9', 'negative');
		header('Location: voicemail_greetings.php?id='.urlencode($voicemail_id));
		exit;
	}

//upload the greeting
	if (!empty($_POST['a']) && $_POST['a'] == "upload" && $has_voicemail_greeting_upload
		&& $_POST['type'] == 'rec' && is_uploaded_file($_FILES['file']['tmp_name'])) {

		//validate the token
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-invalid_token'],'negative');
			header('Location: voicemail_greetings.php?id='.urlencode($voicemail_id));
			exit;
		}

		//get the file extension
		$file_ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
		$file_name = $_FILES['file']['name'];

		//define the allowed file extensions
		$allowed_extensions = ['wav', 'mp3', 'ogg'];

		//check file extension
		if (in_array($file_ext, $allowed_extensions)) {

			//find the next available greeting id starting at 1
			$greeting_id = 1;
			for ($i = 1; $i <= 9; $i++) {
				$found_existing_file = false;

				//check for wav, mp3, and ogg files with the current greeting id
				foreach ($allowed_extensions as $extension) {
					$potential_file_name = "greeting_{$i}.{$extension}";
					if (file_exists($greeting_dir . '/' . $potential_file_name)) {
						$found_existing_file = true;
						break;
					}
				}

				if (!$found_existing_file) {
					//found an available greeting id
					$greeting_id = $i;
					break;
				}
			}

			//set the greeting file name
			$greeting_file_name = "greeting_{$greeting_id}.{$file_ext}";

			//move the uploaded greeting
			if (!empty($greeting_dir) && !file_exists($greeting_dir)) {
				mkdir($greeting_dir, 0770, false);
			}
			move_uploaded_file($_FILES['file']['tmp_name'], $greeting_dir.'/'.$greeting_file_name);

			//set newly uploaded greeting as active greeting for voicemail box
			$sql = "update v_voicemails ";
			$sql .= "set greeting_id = :greeting_id ";
			$sql .= "where domain_uuid = :domain_uuid ";
			$sql .= "and voicemail_id = :voicemail_id ";
			$parameters = [];
			$parameters['greeting_id'] = $greeting_id;
			$parameters['domain_uuid'] = $domain_uuid;
			$parameters['voicemail_id'] = $voicemail_id;
			$database->execute($sql, $parameters);
			unset($sql, $parameters);

			//build insert array
			$x = 0;
			$array['voicemail_greetings'][$x]['voicemail_greeting_uuid'] = uuid();
			$array['voicemail_greetings'][$x]['domain_uuid'] = $domain_uuid;
			$array['voicemail_greetings'][$x]['voicemail_id'] = $voicemail_id;
			$array['voicemail_greetings'][$x]['greeting_id'] = $greeting_id;
			$array['voicemail_greetings'][$x]['greeting_name'] = $text['label-greeting'].' '.$greeting_id;
			$array['voicemail_greetings'][$x]['greeting_filename'] = $greeting_file_name;
			$array['voicemail_greetings'][$x]['greeting_description'] = '';
			if (!empty($settings->get('voicemail', 'storage_type')) && $settings->get('voicemail', 'storage_type') == 'base64') {
				$array['voicemail_greetings'][$x]['greeting_base64'] = base64_encode(file_get_contents($greeting_dir.'/'.$file_name));
			}

			//save the array
			if (is_array($array) && @sizeof($array) != 0) {

				//grant temporary permissions
				$p = permissions::new();
				$p->add('voicemail_greeting_add', 'temp');
				$p->add('voicemail_greeting_edit', 'temp');

				//execute inserts/updates
				$database->save($array);
				unset($array);

				//revoke temporary permissions
				$p->delete('voicemail_greeting_add', 'temp');
				$p->delete('voicemail_greeting_edit', 'temp');
			}
			else {
				echo __line__;
			}

			//set message
			message::add($text['message-uploaded'].": ".$_FILES['file']['name']);

		}

		//set the file name to be inserted as the greeting description
		$greeting_description = base64_encode($_FILES['file']['name']);
		header("Location: voicemail_greetings.php?id=".urlencode($voicemail_id)."&order_by=".urlencode($order_by)."&order=".urlencode($order)."&gd=".$greeting_description);
		exit;
	}

//check the permission
	if (!$has_voicemail_greeting_view) {
		echo "access denied";
		exit;
	}

//set the greeting
	if (!empty($_REQUEST['action']) && $_REQUEST['action'] == "set") {
		//save the greeting_id to a variable
		$greeting_id = $_REQUEST['greeting_id'];

		//set the greeting_id
		$sql = "update v_voicemails ";
		$sql .= "set greeting_id = :greeting_id ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$sql .= "and voicemail_id = :voicemail_id ";
		$parameters = [];
		$parameters['greeting_id'] = $greeting_id;
		$parameters['domain_uuid'] = $domain_uuid;
		$parameters['voicemail_id'] = $voicemail_id;
		$database->execute($sql, $parameters);
		unset($sql, $parameters);

		//set message
		message::add($text['message-greeting_selected']);

		//redirect
		header("Location: voicemail_greetings.php?id=".$voicemail_id."&order_by=".$order_by."&order=".$order);
		exit;
	}

//get the http post data
	if (!empty($_POST['voicemail_greetings'])) {
		$action = $_POST['action'];
		$voicemail_id = $_POST['voicemail_id'];
		$voicemail_greetings = $_POST['voicemail_greetings'];
	}

//process the http post data by action
	if (!empty($action) && !empty($voicemail_greetings)) {
		switch ($action) {
			case 'delete':
				if ($has_voicemail_greeting_delete) {
					$obj = new voicemail_greetings;
					$obj->voicemail_id = $voicemail_id;
					$obj->delete($voicemail_greetings);
				}
				break;
		}

		header('Location: voicemail_greetings.php?id='.urlencode($voicemail_id).'&back='.urlencode(PROJECT_PATH.'/app/voicemails/voicemails.php'));
		exit;
	}

//get the greetings list
	if (!empty($settings->get('voicemail', 'storage_type')) && $settings->get('voicemail', 'storage_type') == 'base64') {
		switch ($database->type) {
			case 'pgsql': $sql_file_size = ", length(decode(greeting_base64,'base64')) as greeting_size "; break;
			case 'mysql': $sql_file_size = ", length(from_base64(greeting_base64)) as greeting_size "; break;
		}
	}
	$sql = "select * ".$sql_file_size." from v_voicemail_greetings ";
	$sql .= "where domain_uuid = :domain_uuid ";
	$sql .= "and voicemail_id = :voicemail_id ";
	$sql .= order_by($order_by, $order);
	$parameters = [];
	$parameters['domain_uuid'] = $domain_uuid;
	$parameters['voicemail_id'] = $voicemail_id;
	$greetings = $database->select($sql, $parameters, 'all');
	$num_rows = is_array($greetings) ? @sizeof($greetings) : 0;
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//compute storage type flag
	$storage_is_base64 = (!empty($settings->get('voicemail', 'storage_type')) && $settings->get('voicemail', 'storage_type') == 'base64');

//set list row edit button preference
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);

//build action bar buttons
	$btn_back = button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$settings->get('theme', 'button_icon_back'),'id'=>'btn_back','link'=>$_SESSION['back'][$_SERVER['PHP_SELF']]]);
	$btn_add = '';
	if ($has_voicemail_greeting_add && is_array($greetings) && $speech_enabled == 'true') {
		$btn_add = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','style'=>'margin-left: 15px;','link'=>'voicemail_greeting_edit.php?voicemail_id='.urlencode($voicemail_id)]);
	}
	$btn_upload_form = '';
	$_ml = !empty($btn_add);
	if ($has_voicemail_greeting_upload && is_array($greetings) && @sizeof($greetings) < 9) {
		$_ul_btn  = button::create(['type'=>'button','label'=>$text['button-upload'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_upload','style'=>(!$_ml ? 'margin-left: 15px;' : null),'onclick'=>"$(this).fadeOut(250, function(){ \$('span#form_upload').fadeIn(250); document.getElementById('ulfile').click(); });"]);
		$_can_btn = button::create(['label'=>$text['button-cancel'],'icon'=>$settings->get('theme', 'button_icon_cancel'),'type'=>'button','id'=>'btn_upload_cancel','style'=>'margin-left: 15px;','onclick'=>"\$('span#form_upload').fadeOut(250, function(){ document.getElementById('form_upload').reset(); \$('#btn_upload').fadeIn(250) });"]);
		$_sub_btn = button::create(['type'=>'submit','label'=>$text['button-upload'],'icon'=>$settings->get('theme', 'button_icon_upload')]);
		$btn_upload_form  = "<form id='form_upload' class='inline' method='post' enctype='multipart/form-data'>\n";
		$btn_upload_form .= "<input name='a' type='hidden' value='upload'>\n";
		$btn_upload_form .= "<input type='hidden' name='id' value='".escape($voicemail_id)."'>\n";
		$btn_upload_form .= "<input type='hidden' name='type' value='rec'>\n";
		$btn_upload_form .= "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";
		$btn_upload_form .= $_ul_btn."\n";
		$btn_upload_form .= "<span id='form_upload' style='display: none;'>\n";
		$btn_upload_form .= $_can_btn."\n";
		$btn_upload_form .= "	<input type='text' class='txt' style='width: 100px; cursor: pointer;' id='filename' placeholder='Select...' onclick=\"document.getElementById('ulfile').click(); this.blur();\" onfocus='this.blur();'>\n";
		$btn_upload_form .= "	<input type='file' id='ulfile' name='file' style='display: none;' accept='.wav,.mp3,.ogg' onchange=\"document.getElementById('filename').value = this.files.item(0).name; check_file_type(this);\">\n";
		$btn_upload_form .= $_sub_btn."\n";
		$btn_upload_form .= "</span>\n";
		$btn_upload_form .= "</form>\n";
		$_ml = true;
		unset($_ul_btn, $_can_btn, $_sub_btn);
	} elseif ($has_voicemail_greeting_upload && is_array($greetings) && @sizeof($greetings) >= 9) {
		$_ul_btn = button::create(['type'=>'submit','label'=>$text['button-upload'],'icon'=>$settings->get('theme', 'button_icon_add')]);
		$btn_upload_form  = "<form class='inline' method='post'>\n";
		$btn_upload_form .= "<input type='hidden' name='limit_reached' value='true'>\n";
		$btn_upload_form .= $_ul_btn."\n";
		$btn_upload_form .= "</form>\n";
		$_ml = true;
		unset($_ul_btn);
	}
	$btn_delete = '';
	if ($has_voicemail_greeting_delete && $greetings) {
		$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;'.(!$_ml ? ' margin-left: 15px;' : null),'onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	unset($_ml);

//build modal
	$modal_delete = '';
	if ($has_voicemail_greeting_delete && $greetings) {
		$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

//build table headers
	$th_greeting_id       = th_order_by('greeting_id',          $text['label-number'],      $order_by, $order, null, "class='center shrink'",   "id=".urlencode($voicemail_id));
	$th_greeting_name     = th_order_by('greeting_name',         $text['label-name'],        $order_by, $order, null, null,                        "id=".urlencode($voicemail_id));
	$th_greeting_filename = '';
	if (!$storage_is_base64) {
		$th_greeting_filename = th_order_by('greeting_filename', $text['label-filename'],    $order_by, $order, null, "class='hide-sm-dn'",       "id=".urlencode($voicemail_id));
	}
	$th_description       = th_order_by('greeting_description',  $text['label-description'], $order_by, $order, null, "class='hide-sm-dn pct-25'", "id=".urlencode($voicemail_id));

//compute col count for progress bar colspan
	$col_count = 0;
	if ($has_voicemail_greeting_delete) { $col_count++; }
	$col_count++; //selected radio
	$col_count++; //greeting_id
	$col_count++; //greeting_name
	if (!$storage_is_base64) { $col_count++; } //filename
	if ($has_voicemail_greeting_play || $has_voicemail_greeting_download) { $col_count++; } //tools
	$col_count++; //size
	if (!$storage_is_base64) { $col_count++; } //uploaded
	$col_count++; //description
	if ($has_voicemail_greeting_edit && $list_row_edit_button) { $col_count++; } //edit button

//build row data
	if (is_array($greetings)) {
		date_default_timezone_set($settings->get('domain', 'time_zone', date_default_timezone_get()));
		$time_format = ($settings->get('domain', 'time_format') == '24h') ? 'H:i:s' : 'h:i:s a';
		$x = 0;
		foreach ($greetings as &$row) {
			app::dispatch_list_render_row('voicemail_greeting_list_page_hook', null, $row, $x);
			$list_row_url = '';
			if ($has_voicemail_greeting_edit) {
				$list_row_url = "voicemail_greeting_edit.php?id=".urlencode($row['voicemail_greeting_uuid'])."&voicemail_id=".urlencode($voicemail_id);
				if ($row['domain_uuid'] != $_SESSION['domain_uuid'] && $has_domain_select) {
					$list_row_url .= '&domain_uuid='.urlencode($row['domain_uuid']).'&domain_change=true';
				}
			}
			$row['_list_row_url']  = $list_row_url;
			$row['_radio_button']  = "<input type='radio' onclick=\"window.location='".PROJECT_PATH."/app/voicemail_greetings/voicemail_greetings.php?id=".escape($voicemail_id)."&greeting_id=".escape($row['greeting_id'])."&action=set&order_by=".$order_by."&order=".$order."';\" name='greeting_id' value='".escape($row['greeting_id'])."' ".(($row['greeting_id'] == $selected_greeting_id) ? "checked='checked'" : '')." style='display: block; width: 20px; height: auto; margin: auto calc(50% - 10px);'>";
			$row['_progress_bar_html'] = '';
			if ($has_voicemail_greeting_play) {
				$_uuid_e = escape($row['voicemail_greeting_uuid']);
				$row['_progress_bar_html']  = "<tr class='list-row' id='recording_progress_bar_{$_uuid_e}' onclick=\"recording_seek(event,'{$_uuid_e}')\" style='display: none;'><td id='playback_progress_bar_background_{$_uuid_e}' class='playback_progress_bar_background' style='padding: 0; border: none;' colspan='{$col_count}'><span class='playback_progress_bar' id='recording_progress_{$_uuid_e}'></span></td></tr>\n";
				$row['_progress_bar_html'] .= "<tr class='list-row' style='display: none;'><td></td></tr>\n";
			}
			$row['_tools_html'] = '';
			if ($has_voicemail_greeting_play || $has_voicemail_greeting_download) {
				$_tools = '';
				if ($has_voicemail_greeting_play) {
					$_uuid_e       = escape($row['voicemail_greeting_uuid']);
					$_file_ext     = pathinfo(strtolower($row['greeting_filename']), PATHINFO_EXTENSION);
					$_audio_types  = ['wav'=>'audio/wav','mp3'=>'audio/mpeg','ogg'=>'audio/ogg'];
					$_audio_type   = $_audio_types[$_file_ext] ?? 'audio/wav';
					$_tools .= "<audio id='recording_audio_{$_uuid_e}' style='display: none;' preload='none' ontimeupdate=\"update_progress('{$_uuid_e}')\" onended=\"recording_reset('{$_uuid_e}');\" src=\"voicemail_greetings.php?id=".escape($voicemail_id)."&a=download&type=rec&uuid={$_uuid_e}\" type='{$_audio_type}'></audio>";
					$_tools .= button::create(['type'=>'button','title'=>$text['label-play'].' / '.$text['label-pause'],'icon'=>$settings->get('theme', 'button_icon_play'),'id'=>'recording_button_'.$_uuid_e,'onclick'=>"recording_play('{$_uuid_e}','".escape($voicemail_id)."|{$_uuid_e}')"]);
				}
				if ($has_voicemail_greeting_download) {
					$_tools .= button::create(['type'=>'button','title'=>$text['label-download'],'icon'=>$settings->get('theme', 'button_icon_download'),'link'=>"voicemail_greetings.php?a=download&type=rec&t=bin&id=".urlencode($voicemail_id)."&uuid=".escape($row['voicemail_greeting_uuid'])]);
				}
				$row['_tools_html'] = $_tools;
				unset($_tools, $_uuid_e, $_file_ext, $_audio_types, $_audio_type);
			}
			if ($storage_is_base64) {
				$row['_file_size'] = byte_convert($row['greeting_size']);
				$row['_file_date'] = '';
			} else {
				if (file_exists($greeting_dir.'/'.$row['greeting_filename'])) {
					$row['_file_size'] = byte_convert(filesize($greeting_dir.'/'.$row['greeting_filename']));
					$row['_file_date'] = date("M d, Y ".$time_format, filemtime($greeting_dir.'/'.$row['greeting_filename']));
				} else {
					$row['_file_size'] = '0';
					$row['_file_date'] = '';
				}
			}
			$row['_edit_button'] = '';
			if ($has_voicemail_greeting_edit && $list_row_edit_button) {
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
	$template->assign('text',                              $text);
	$template->assign('num_rows',                          $num_rows);
	$template->assign('greetings',                         $greetings ?? []);
	$template->assign('voicemail_id',                      $voicemail_id);
	$template->assign('order_by',                          $order_by);
	$template->assign('order',                             $order);
	$template->assign('token',                             $token);
	$template->assign('storage_is_base64',                 $storage_is_base64);
	$template->assign('has_voicemail_greeting_delete',     $has_voicemail_greeting_delete);
	$template->assign('has_voicemail_greeting_download',   $has_voicemail_greeting_download);
	$template->assign('has_voicemail_greeting_edit',       $has_voicemail_greeting_edit);
	$template->assign('has_voicemail_greeting_play',       $has_voicemail_greeting_play);
	$template->assign('list_row_edit_button',              $list_row_edit_button);
	$template->assign('btn_back',                          $btn_back);
	$template->assign('btn_add',                           $btn_add);
	$template->assign('btn_upload_form',                   $btn_upload_form);
	$template->assign('btn_delete',                        $btn_delete);
	$template->assign('modal_delete',                      $modal_delete);
	$template->assign('th_greeting_id',                    $th_greeting_id);
	$template->assign('th_greeting_name',                  $th_greeting_name);
	$template->assign('th_greeting_filename',              $th_greeting_filename);
	$template->assign('th_description',                    $th_description);

//invoke pre render hook
	app::dispatch_list_pre_render('voicemail_greeting_list_page_hook', null, $template);

//include the header
	$document['title'] = $text['title'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('voicemail_greetings_list.tpl');

//invoke post render hook
	app::dispatch_list_post_render('voicemail_greeting_list_page_hook', null, $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";

//define the download function (helps safari play audio sources)
	/**
	 * Handles a range download request for the given file.
	 *
	 * @param string $file The path to the file being downloaded.
	 */
	function range_download($file) {
		$fp = @fopen($file, 'rb');

		$size   = filesize($file); // File size
		$length = $size;           // Content length
		$start  = 0;               // Start byte
		$end    = $size - 1;       // End byte
		// Now that we've gotten so far without errors we send the accept range header
		/* At the moment we only support single ranges.
		* Multiple ranges requires some more work to ensure it works correctly
		* and comply with the spesifications: http://www.w3.org/Protocols/rfc2616/rfc2616-sec19.html#sec19.2
		*
		* Multirange support annouces itself with:
		* header('Accept-Ranges: bytes');
		*
		* Multirange content must be sent with multipart/byteranges mediatype,
		* (mediatype = mimetype)
		* as well as a boundry header to indicate the various chunks of data.
		*/
		header("Accept-Ranges: 0-$length");
		// header('Accept-Ranges: bytes');
		// multipart/byteranges
		// http://www.w3.org/Protocols/rfc2616/rfc2616-sec19.html#sec19.2
		if (isset($_SERVER['HTTP_RANGE'])) {

			$c_start = $start;
			$c_end   = $end;
			// Extract the range string
			[, $range] = explode('=', $_SERVER['HTTP_RANGE'], 2);
			// Make sure the client hasn't sent us a multibyte range
			if (strpos($range, ',') !== false) {
				// (?) Shoud this be issued here, or should the first
				// range be used? Or should the header be ignored and
				// we output the whole content?
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				header("Content-Range: bytes $start-$end/$size");
				// (?) Echo some info to the client?
				exit;
			}
			// If the range starts with an '-' we start from the beginning
			// If not, we forward the file pointer
			// And make sure to get the end byte if spesified
			if (!empty($range0) && $range0 == '-') {
				// The n-number of the last bytes is requested
				$c_start = $size - substr($range, 1);
			}
			else {
				$range  = explode('-', $range);
				$c_start = $range[0];
				$c_end   = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
			}
			/* Check the range and make sure it's treated according to the specs.
			* http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html
			*/
			// End bytes can not be larger than $end.
			$c_end = ($c_end > $end) ? $end : $c_end;
			// Validate the requested range and return an error if it's not correct.
			if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {

				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				header("Content-Range: bytes $start-$end/$size");
				// (?) Echo some info to the client?
				exit;
			}
			$start  = $c_start;
			$end    = $c_end;
			$length = $end - $start + 1; // Calculate new content length
			fseek($fp, $start);
			header('HTTP/1.1 206 Partial Content');
		}
		// Notify the client the byte range we'll be outputting
		header("Content-Range: bytes $start-$end/$size");
		header("Content-Length: $length");

		// Start buffered download
		$buffer = 1024 * 8;
		while(!feof($fp) && ($p = ftell($fp)) <= $end) {
			if ($p + $buffer > $end) {
				// In case we're only outputtin a chunk, make sure we don't
				// read past the length
				$buffer = $end - $p + 1;
			}
			set_time_limit(0); // Reset time limit for big files
			echo fread($fp, $buffer);
			flush(); // Free up memory. Otherwise large files will trigger PHP's memory limit.
		}

		fclose($fp);
	}
