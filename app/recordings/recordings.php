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
	James Rose <james.o.rose@gmail.com>
*/

//set the max php execution time
	ini_set('max_execution_time', 7200);

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//add multi-lingual support
	$text = new text()->get();

//get the session settings
	$domain_uuid = $_SESSION['domain_uuid'];
	$domain_name = $_SESSION['domain_name'];
	$user_uuid = $_SESSION['user_uuid'];
	$domains =  $_SESSION['domains'];

//initialize the settings object
	$settings = new settings(["domain_uuid" => $domain_uuid, "user_uuid" => $user_uuid]);

//get the settings
	$switch_recordings = $settings->get('switch', 'recordings');
	$time_zone = $settings->get('domain', 'time_zone', date_default_timezone_get());
	$speech_enabled = $settings->get('speech', 'enabled', false);
	$recording_storage_type = $settings->get('recordings','storage_type');
	$recording_password = $settings->get('recordings','recording_password');
	$domain_paging = $settings->get('domain','paging', 100);
	$theme_button_icon_edit = $settings->get('theme','button_icon_edit');
	$theme_button_icon_add = $settings->get('theme','button_icon_add');
	$theme_button_icon_upload = $settings->get('theme','button_icon_upload');
	$theme_button_icon_cancel = $settings->get('theme','button_icon_cancel');
	$theme_button_icon_delete = $settings->get('theme','button_icon_delete');
	$theme_button_icon_all = $settings->get('theme','button_icon_all');
	$theme_button_icon_search = $settings->get('theme','button_icon_search');
	$theme_list_row_edit_button = $settings->get('theme','list_row_edit_button');
	$theme_button_icon_download = $settings->get('theme','button_icon_download');
	$theme_button_icon_play = $settings->get('theme','button_icon_play');
	$theme_button_icon_reset = $settings->get('theme','button_icon_reset');

//set additional variables
	$action = $_REQUEST["action"] ?? '';
	$search = $_REQUEST["search"] ?? '';
	$show = $_GET['show'] ?? '';

//download the recording
	if ($action == "download" && (permission_exists('recording_play') || permission_exists('recording_download'))) {
		if ($_GET['type'] == "rec") {
			//set the path for the directory
				$path = $switch_recordings."/".$domain_name;

			//if from recordings, get recording details from db
				$recording_uuid = $_GET['id']; //recordings
				if ($recording_uuid != '') {
					$sql = "select recording_filename, recording_base64 ";
					$sql .= "from v_recordings ";
					$sql .= "where domain_uuid = :domain_uuid ";
					$sql .= "and recording_uuid = :recording_uuid ";
					$parameters['domain_uuid'] = $domain_uuid;
					$parameters['recording_uuid'] = $recording_uuid;
					$row = $database->select($sql, $parameters, 'row');
					if (is_array($row) && @sizeof($row) != 0) {
						$recording_filename = $row['recording_filename'];
						if ($recording_storage_type == 'base64') {
							$recording_decoded = base64_decode($row['recording_base64']);
							file_put_contents($path.'/'.$recording_filename, $recording_decoded);
						}
					}
					unset($sql, $parameters, $row, $recording_decoded);
				} elseif ($_GET['filename']) {
					$recording_filename = $_GET['filename'];
				}

			// build full path
				if (substr($recording_filename,0,1) == '/'){
					$full_recording_path = $path.$recording_filename;
				}
				else {
					$full_recording_path = $path.'/'.$recording_filename;
				}

			//send the headers and then the data stream
				if (file_exists($full_recording_path)) {

					$fd = fopen($full_recording_path, "rb");
					if (!empty($_GET['t']) && $_GET['t'] == "bin") {
						header("Content-Type: application/force-download");
						header("Content-Type: application/octet-stream");
						header("Content-Type: application/download");
						header("Content-Description: File Transfer");
					}
					else {
						$file_ext = pathinfo($recording_filename, PATHINFO_EXTENSION);
						switch ($file_ext) {
							case "wav" : header("Content-Type: audio/x-wav"); break;
							case "mp3" : header("Content-Type: audio/mpeg"); break;
							case "ogg" : header("Content-Type: audio/ogg"); break;
						}
					}
					header('Content-Disposition: attachment; filename="'.$recording_filename.'"');
					header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
					header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
					if (!empty($_GET['t'] ) && $_GET['t'] == "bin") {
						header("Content-Length: ".filesize($full_recording_path));
					}
					ob_clean();

					//content-range
					if (isset($_SERVER['HTTP_RANGE']) && (empty($_GET['t']) || $_GET['t'] != "bin"))  {
						range_download($full_recording_path);
					}

					fpassthru($fd);
				}
		}
		exit;
	}

//upload the recording
	if ($action == "upload" && permission_exists('recording_upload') && is_uploaded_file($_FILES['file']['tmp_name'])) {

		//remove special characters
			$recording_filename = str_replace(" ", "_", $_FILES['file']['name']);
			$recording_filename = str_replace("'", "", $recording_filename);

		//make sure the destination directory exists
			if (!is_dir($switch_recordings.'/'.$domain_name)) {
				mkdir($switch_recordings.'/'.$domain_name, 0770, false);
			}

		//move the uploaded files
			$result = move_uploaded_file($_FILES['file']['tmp_name'], $switch_recordings.'/'.$domain_name.'/'.$recording_filename);

		//clear the destinations session array
			if (isset($_SESSION['destinations']['array'])) {
				unset($_SESSION['destinations']['array']);
			}

		//set the message
			message::add($text['message-uploaded'].": ".htmlentities($recording_filename));

		//set the file name to be inserted as the recording description
			$recording_description = $_FILES['file']['name'];
			header("Location: recordings.php?rd=".urlencode($recording_description));
			exit;
	}

//check the permission
	if (!permission_exists('recording_view')) {
		echo "access denied";
		exit;
	}

//get existing recordings
	$sql = "select recording_uuid, recording_filename, recording_base64 ";
	$sql .= "from v_recordings ";
	$sql .= "where domain_uuid = :domain_uuid ";
	$parameters['domain_uuid'] = $domain_uuid;
	$result = $database->select($sql, $parameters, 'all');
	if (is_array($result) && @sizeof($result) != 0) {
		foreach ($result as $row) {
			$array_recordings[$row['recording_uuid']] = $row['recording_filename'];
			$array_base64_exists[$row['recording_uuid']] = ($row['recording_base64'] != '') ? true : false;
			//if not base64, convert back to local files and remove base64 from db
			if ($recording_storage_type != 'base64' && $row['recording_base64'] != '') {
				if (!file_exists($switch_recordings.'/'.$domain_name.'/'.$row['recording_filename'])) {
					$recording_decoded = base64_decode($row['recording_base64']);
					file_put_contents($switch_recordings.'/'.$domain_name.'/'.$row['recording_filename'], $recording_decoded);
					//build array
						$array['recordings'][0]['recording_uuid'] = $row['recording_uuid'];
						$array['recordings'][0]['domain_uuid'] = $domain_uuid;
						$array['recordings'][0]['recording_base64'] = null;
					//set temporary permissions
						$p = permissions::new();
						$p->add('recording_edit', 'temp');
					//execute update
						$database->save($array);
						unset($array);
					//remove temporary permissions
						$p->delete('recording_edit', 'temp');
				}
			}
		}
	}
	unset($sql, $parameters, $result, $row);

//add recordings to the database
	if (is_dir($switch_recordings.'/'.$domain_name.'/')) {
		if ($dh = opendir($switch_recordings.'/'.$domain_name.'/')) {
			while (($recording_filename = readdir($dh)) !== false) {
				if (filetype($switch_recordings."/".$domain_name."/".$recording_filename) == "file") {

					if (!is_array($array_recordings) || !in_array($recording_filename, $array_recordings)) {
						//file not found in db, add it
							$recording_uuid = uuid();
							$recording_name = ucwords(str_replace('_', ' ', pathinfo($recording_filename, PATHINFO_FILENAME)));
							$recording_description = $_GET['rd'];
						//build array
							$array['recordings'][0]['domain_uuid'] = $domain_uuid;
							$array['recordings'][0]['recording_uuid'] = $recording_uuid;
							$array['recordings'][0]['recording_filename'] = $recording_filename;
							$array['recordings'][0]['recording_name'] = $recording_name;
							$array['recordings'][0]['recording_description'] = $recording_description;
							if ($recording_storage_type == 'base64') {
								$recording_base64 = base64_encode(file_get_contents($switch_recordings.'/'.$domain_name.'/'.$recording_filename));
								$array['recordings'][0]['recording_base64'] = $recording_base64;
							}
						//set temporary permissions
							$p = permissions::new();
							$p->add('recording_add', 'temp');
						//execute insert
							$database->save($array);
							unset($array);
						//remove temporary permissions
							$p->delete('recording_add', 'temp');
					}
					else {
						//file found in db, check if base64 present
							if ($recording_storage_type == 'base64') {
								$found_recording_uuid = array_search($recording_filename, $array_recordings);
								if (!$array_base64_exists[$found_recording_uuid]) {
									$recording_base64 = base64_encode(file_get_contents($switch_recordings.'/'.$domain_name.'/'.$recording_filename));
									//build array
										$array['recordings'][0]['domain_uuid'] = $domain_uuid;
										$array['recordings'][0]['recording_uuid'] = $found_recording_uuid;
										$array['recordings'][0]['recording_base64'] = $recording_base64;
									//set temporary permissions
										$p = permissions::new();
										$p->add('recording_edit', 'temp');
									//execute update
										$database->save($array);
										unset($array);
									//remove temporary permissions
										$p->delete('recording_edit', 'temp');
								}
							}
					}

				}
			}
			closedir($dh);
		}

		//redirect
			if ($_GET['rd'] ?? '') {
				header("Location: recordings.php");
				exit;
			}
	}

//get posted data
	if (!empty($_POST['recordings'])) {
		$action = $_POST['action'];
		$recordings = $_POST['recordings'];
	}

//process the http post data by action
	if (!empty($action) && is_array($recordings)) {
		switch ($action) {
			case 'delete':
				if (permission_exists('recording_delete')) {
					$obj = new switch_recordings;
					$obj->delete($recordings);
				}
				break;
		}

		header('Location: recordings.php'.($search != '' ? '?search='.urlencode($search) : ''));
		exit;
	}

//get order and order by
	$order_by = $_GET["order_by"] ?? '';
	$order = $_GET["order"] ?? '';

//add the search term
	$search = $_REQUEST["search"] ?? '';
//get total recordings from the database
	$sql = "select count(*) from v_recordings ";
	$sql .= "where true ";
	if ($show != "all" || !permission_exists('conference_center_all')) {
		$sql .= "and (domain_uuid = :domain_uuid or domain_uuid is null) ";
		$parameters['domain_uuid'] = $domain_uuid;
	}
	if (!empty($search)) {
		$sql .= "and (";
		$sql .= "	lower(recording_name) like :search ";
		$sql .= "	or lower(recording_filename) like :search ";
		$sql .= "	or lower(recording_description) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.strtolower($search).'%';
	}
	$num_rows = $database->select($sql, $parameters ?? null, 'column');

//prepare to page the results
	$rows_per_page = ($domain_paging != '') ? $domain_paging : 50;
	$param = "&search=".urlencode($search);
	if ($show == "all" && permission_exists('recording_all')) {
		$param .= "&show=all";
	}
	$param .= "&order_by=".$order_by."&order=".$order;
	$page = isset($_GET['page']) ? $_GET['page'] : 0;
	[$paging_controls, $rows_per_page] = paging($num_rows, $param, $rows_per_page);
	[$paging_controls_mini, $rows_per_page] = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the file size
	if ($recording_storage_type == 'base64') {
		switch ($database->type) {
			case 'pgsql': $sql_file_size = "length(decode(recording_base64,'base64')) as recording_size, "; break;
			case 'mysql': $sql_file_size = "length(from_base64(recording_base64)) as recording_size, "; break;
		}
	}

//set the time format options: 12h, 24h
	if ($settings->get('domain', 'time_format') == '24h') {
		$time_format = 'HH24:MI:SS';
	}
	else {
		$time_format = 'HH12:MI:SS am';
	}

//get the recordings from the database
	$sql = "select recording_uuid, domain_uuid, ";
	if (!empty($sql_file_size)) { $sql .= $sql_file_size; }
	$sql .= "to_char(timezone(:time_zone, COALESCE(update_date, insert_date)), 'DD Mon YYYY') as date_formatted, \n";
	$sql .= "to_char(timezone(:time_zone, COALESCE(update_date, insert_date)), '".$time_format."') as time_formatted, \n";
	$sql .= "recording_name, recording_filename, recording_description ";
	$sql .= "from v_recordings ";
	$sql .= "where true ";
	if ($show != "all" || !permission_exists('conference_center_all')) {
		$sql .= "and (domain_uuid = :domain_uuid or domain_uuid is null) ";
		$parameters['domain_uuid'] = $domain_uuid;
	}
	if (!empty($search)) {
		$sql .= "and (";
		$sql .= "	lower(recording_name) like :search ";
		$sql .= "	or lower(recording_filename) like :search ";
		$sql .= "	or lower(recording_description) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.strtolower($search).'%';
	}
	$sql .= order_by($order_by, $order, 'recording_name', 'asc');
	$sql .= limit_offset($rows_per_page, $offset);
	$parameters['time_zone'] = $time_zone;
	$recordings = $database->select($sql, $parameters ?? null, 'all');
	unset($sql, $parameters);

//get current recordings password
	if (permission_exists('recording_password')) {
		if (!empty($recording_password)) {
			$recording_password = $recording_password;
		}
		else {
			$sql = "
				select
					split_part(dd.dialplan_detail_data,'=',2)
				from
					v_dialplans as d,
					v_dialplan_details as dd
				where
					d.dialplan_uuid = dd.dialplan_uuid and
					d.domain_uuid = :domain_uuid and
					d.app_uuid = '430737df-5385-42d1-b933-22600d3fb79e' and
					d.dialplan_name = 'recordings' and
					d.dialplan_enabled = true and
					dd.dialplan_detail_tag = 'action' and
					dd.dialplan_detail_type = 'set' and
					dd.dialplan_detail_data like 'pin_number=%' and
					dd.dialplan_detail_enabled = true ";
			$parameters['domain_uuid'] = $domain_uuid;
			$recording_password = $database->select($sql, $parameters, 'column');
			unset($sql, $parameters);
		}
	}

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//pre-compute permissions for template
	$has_recording_add      = permission_exists('recording_add');
	$has_recording_all      = permission_exists('recording_all');
	$has_recording_delete   = permission_exists('recording_delete');
	$has_recording_download = permission_exists('recording_download');
	$has_recording_edit     = permission_exists('recording_edit');
	$has_recording_password = permission_exists('recording_password');
	$has_recording_play     = permission_exists('recording_play');
	$has_recording_upload   = permission_exists('recording_upload');
	$has_domain_select      = permission_exists('domain_select');

//build the action bar buttons
	$btn_add = '';
	if ($has_recording_add && $speech_enabled == 'true') {
		$btn_add = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$theme_button_icon_add,'id'=>'btn_add','link'=>'recording_edit.php']);
	}
	$btn_upload = '';
	if ($has_recording_upload) {
		$btn_upload .= "<form id='form_upload' class='inline' method='post' enctype='multipart/form-data'>\n";
		$btn_upload .= "<input name='action' type='hidden' value='upload'>\n";
		$btn_upload .= "<input name='type' type='hidden' value='rec'>\n";
		$btn_upload .= "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";
		$btn_upload .= button::create(['type'=>'button','label'=>$text['button-upload'],'icon'=>$theme_button_icon_add,'id'=>'btn_upload','onclick'=>"$(this).fadeOut(250, function(){ \$('span#form_upload').fadeIn(250); document.getElementById('ulfile').click(); });"]);
		$btn_upload .= "<span id='form_upload' style='display: none;'>";
		$btn_upload .= button::create(['label'=>$text['button-cancel'],'icon'=>$theme_button_icon_cancel,'type'=>'button','id'=>'btn_upload_cancel','onclick'=>"\$('span#form_upload').fadeOut(250, function(){ document.getElementById('form_upload').reset(); \$('#btn_upload').fadeIn(250) });"]);
		$btn_upload .= "<input type='text' class='txt' style='width: 100px; cursor: pointer;' id='filename' placeholder='Select...' onclick=\"document.getElementById('ulfile').click(); this.blur();\" onfocus='this.blur();'>";
		$btn_upload .= "<input type='file' id='ulfile' name='file' style='display: none;' accept='.wav,.mp3,.ogg' onchange=\"document.getElementById('filename').value = this.files.item(0).name; check_file_type(this);\">";
		$btn_upload .= button::create(['type'=>'submit','label'=>$text['button-upload'],'icon'=>$theme_button_icon_upload]);
		$btn_upload .= "</span>\n";
		$btn_upload .= "</form>";
	}
	$btn_delete = '';
	if ($has_recording_delete && $recordings) {
		$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$theme_button_icon_delete,'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	$btn_show_all = '';
	if ($has_recording_all && $show != 'all') {
		$btn_show_all = button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$theme_button_icon_all,'link'=>'?type=&show=all'.($search != '' ? "&search=".urlencode($search) : null)]);
	}
	$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$theme_button_icon_search,'type'=>'submit','id'=>'btn_search']);

//build the modals
	$modal_delete = '';
	if ($has_recording_delete && $recordings) {
		$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

//build the table header columns
	$th_domain_name = '';
	if ($show == "all" && $has_recording_all) {
		$th_domain_name = th_order_by('domain_name', $text['label-domain'], $order_by, $order, $param, "class='shrink'");
	}
	$th_recording_name        = th_order_by('recording_name', $text['label-recording_name'], $order_by, $order);
	$th_recording_filename    = '';
	if ($recording_storage_type != 'base64') {
		$th_recording_filename = th_order_by('recording_filename', $text['label-file_name'], $order_by, $order, null, "class='hide-md-dn'");
	}
	$th_recording_description = th_order_by('recording_description', $text['label-description'], $order_by, $order, null, "class='hide-sm-dn pct-25'");

//compute column count for progress bar colspan
	$col_count = 0;
	if ($has_recording_delete)                              { $col_count++; }
	$col_count++;	// recording_name
	if ($recording_storage_type != 'base64')                { $col_count++; }	// recording_filename
	if ($has_recording_play || $has_recording_download)     { $col_count++; }	// tools
	$col_count++;	// file_size
	$col_count++;	// date
	$col_count++;	// description

//build the description html
	$description_html = '';
	if ($has_recording_password && is_numeric($recording_password)) {
		$description_html = str_replace('||RECORDING_PASSWORD||', "<nobr style='font-weight: 600;'>".$recording_password."</nobr>", $text['description-with_password']);
	}
	else {
		$description_html = $text['description'] ?? '';
	}

//build the row data
	$x = 0;
	foreach ($recordings as &$row) {
		$list_row_url = '';
		if ($has_recording_edit) {
			$list_row_url = "recording_edit.php?id=".urlencode($row['recording_uuid']);
			if ($row['domain_uuid'] != $_SESSION['domain_uuid'] && $has_domain_select) {
				$list_row_url .= '&domain_uuid='.urlencode($row['domain_uuid']).'&domain_change=true';
			}
		}
		$row['_list_row_url'] = $list_row_url;
		if (!empty($domains[$row['domain_uuid']]['domain_name'])) {
			$row['_domain_label'] = $domains[$row['domain_uuid']]['domain_name'];
		}
		else {
			$row['_domain_label'] = $text['label-global'] ?? '';
		}
		if ($recording_storage_type == 'base64') {
			$row['_file_size'] = byte_convert($row['recording_size']);
		}
		else {
			$_fname = $switch_recordings.'/'.($domains[$row['domain_uuid']]['domain_name'] ?? '').'/'.$row['recording_filename'];
			$row['_file_size'] = file_exists($_fname) ? byte_convert(filesize($_fname)) : '';
		}
		$_tools_html = '';
		if ($has_recording_play || $has_recording_download) {
			if ($has_recording_play) {
				$_rec_ext  = strtolower(pathinfo($row['recording_filename'], PATHINFO_EXTENSION));
				$_rec_type = ($_rec_ext == 'mp3') ? 'audio/mpeg' : (($_rec_ext == 'ogg') ? 'audio/ogg' : 'audio/wav');
				$_tools_html .= "<audio id='recording_audio_".escape($row['recording_uuid'])."' style='display: none;' preload='none' ontimeupdate=\"update_progress('".escape($row['recording_uuid'])."')\" onended=\"recording_reset('".escape($row['recording_uuid'])."');\" src=\"".PROJECT_PATH."/app/recordings/recordings.php?action=download&type=rec&id=".urlencode($row['recording_uuid'])."\" type='".$_rec_type."'></audio>";
				$_tools_html .= button::create(['type'=>'button','title'=>$text['label-play'].' / '.$text['label-pause'],'icon'=>$theme_button_icon_play,'id'=>'recording_button_'.escape($row['recording_uuid']),'onclick'=>"recording_play('".escape($row['recording_uuid'])."')"]);
			}
			if ($has_recording_download) {
				$_tools_html .= button::create(['type'=>'button','title'=>$text['label-download'],'icon'=>$theme_button_icon_download,'link'=>"recordings.php?action=download&type=rec&t=bin&id=".urlencode($row['recording_uuid'])]);
			}
		}
		$row['_tools_html'] = $_tools_html;
		$_progress_html = '';
		if ($has_recording_play) {
			$_progress_html .= "<tr class='list-row' id='recording_progress_bar_".escape($row['recording_uuid'])."' onclick=\"recording_seek(event,'".escape($row['recording_uuid'])."')\" style='display: none;'><td id='playback_progress_bar_background_".escape($row['recording_uuid'])."' class='playback_progress_bar_background' style='padding: 0; border: none;' colspan='".$col_count."'><span class='playback_progress_bar' id='recording_progress_".escape($row['recording_uuid'])."'></span></td></tr>\n";
			$_progress_html .= "<tr class='list-row' style='display: none;'><td></td></tr>\n";
		}
		$row['_progress_bar_html'] = $_progress_html;
		$row['_filename_html']     = str_replace('_', '_&#8203;', escape($row['recording_filename']));
		$row['_edit_button']       = '';
		if ($has_recording_edit && $theme_list_row_edit_button == true) {
			$row['_edit_button'] = button::create(['type'=>'button','title'=>$text['button-edit'],'icon'=>$theme_button_icon_edit,'link'=>$list_row_url]);
		}
		$x++;
	}
	unset($row);

//build the template
	$template = new template();
	$template->engine       = 'smarty';
	$template->template_dir = __DIR__.'/resources/views';
	$template->cache_dir    = sys_get_temp_dir();
	$template->init();

//assign the template variables
	$template->assign('text',                      $text);
	$template->assign('num_rows',                  $num_rows);
	$template->assign('recordings',                $recordings ?? []);
	$template->assign('search',                    $search);
	$template->assign('show',                      $show);
	$template->assign('paging_controls',           $paging_controls);
	$template->assign('paging_controls_mini',      $paging_controls_mini);
	$template->assign('token',                     $token);
	$template->assign('recording_storage_type',    $recording_storage_type);
	$template->assign('has_recording_add',         $has_recording_add);
	$template->assign('has_recording_all',         $has_recording_all);
	$template->assign('has_recording_delete',      $has_recording_delete);
	$template->assign('has_recording_download',    $has_recording_download);
	$template->assign('has_recording_edit',        $has_recording_edit);
	$template->assign('has_recording_play',        $has_recording_play);
	$template->assign('list_row_edit_button',      $theme_list_row_edit_button);
	$template->assign('description_html',          $description_html);
	$template->assign('btn_add',                   $btn_add);
	$template->assign('btn_upload',                $btn_upload);
	$template->assign('btn_delete',                $btn_delete);
	$template->assign('btn_show_all',              $btn_show_all);
	$template->assign('btn_search',                $btn_search);
	$template->assign('modal_delete',              $modal_delete);
	$template->assign('th_domain_name',            $th_domain_name);
	$template->assign('th_recording_name',         $th_recording_name);
	$template->assign('th_recording_filename',     $th_recording_filename);
	$template->assign('th_recording_description',  $th_recording_description);

//include the header
	$document['title'] = $text['title-recordings'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('recordings_list.tpl');
	echo $html;

//include the footer
	require_once "resources/footer.php";

//define the download function (helps safari play audio sources)
	/**
	 * Downloads a file in range mode, allowing the client to request specific byte ranges.
	 *
	 * @param string $file Path to the file being downloaded
	 *
	 * @return void
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
			if ($range == '-') {
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


