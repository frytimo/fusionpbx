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

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('music_on_hold_view')) {
		echo "access denied";
		exit;
	}
	$has_music_on_hold_add    = permission_exists('music_on_hold_add');
	$has_music_on_hold_all    = permission_exists('music_on_hold_all');
	$has_music_on_hold_delete = permission_exists('music_on_hold_delete');
	$has_music_on_hold_domain = permission_exists('music_on_hold_domain');
	$has_music_on_hold_edit   = permission_exists('music_on_hold_edit');
	$has_music_on_hold_global = permission_exists('music_on_hold_global');
	$has_music_on_hold_path   = permission_exists('music_on_hold_path');

//add multi-lingual support
	$text = new text()->get();

//add additional variables
	$search = $_GET["search"] ?? '';
	$show = $_GET['show'] ?? '';

//get the music_on_hold array
	$sql = "select * from v_music_on_hold ";
	$sql .= "where true ";
	if ($show != "all" || !$has_music_on_hold_all) {
		$sql .= "and (domain_uuid = :domain_uuid or domain_uuid is null) ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	if ($has_music_on_hold_domain) {
		$sql .= "or domain_uuid is null ";
	}
	$sql .= "order by domain_uuid desc, music_on_hold_name asc, music_on_hold_rate asc";
	$streams = $database->select($sql, $parameters ?? null, 'all');
	unset($sql, $parameters);

//get the http post data
	if (!empty($_POST['moh'])) {
		$action = $_POST['action'];
		$moh = $_POST['moh'];
	}

//process the http post data by action
	if (!empty($action) && !empty($moh)) {
		switch ($action) {
			case 'delete':
				if ($has_music_on_hold_delete) {
					$obj = new switch_music_on_hold;
					$obj->delete($moh);
				}
				break;
		}

		header('Location: music_on_hold.php');
		exit;
	}

//get order and order by and sanitize the values
	$order_by = $_GET["order_by"] ?? '';
	$order = $_GET["order"] ?? '';

//download music on hold file
	if (!empty($_GET['action'])
		&& $_GET['action'] == "download"
		&& is_uuid($_GET['id'])
		&& !empty($streams)) {

		//get the uuid
			$stream_uuid = $_GET['id'];

		//get the record
			foreach($streams as $row) {
				if ($stream_uuid == $row['music_on_hold_uuid']) {
					$stream_domain_uuid = $row['domain_uuid'];
					$stream_name = $row['music_on_hold_name'];
					$stream_path = $row['music_on_hold_path'];
					break;
				}
			}

		//replace the sounds_dir variable in the path
			$stream_path = str_replace('$${sounds_dir}', $settings->get('switch', 'sounds'), $stream_path);
			$stream_path = str_replace('..', '', $stream_path);

		//get the file and sanitize it
			$stream_file = basename($_GET['file']);
			$search = array('..', '/', ':');
			$stream_file = str_replace($search, '', $stream_file);

		//join the path and file name
			$stream_full_path = path_join($stream_path, $stream_file);

		//download the file
			if (file_exists($stream_full_path)) {

				$fd = fopen($stream_full_path, "rb");
				if (!empty($_GET['t']) && $_GET['t'] == "bin") {
					header("Content-Type: application/force-download");
					header("Content-Type: application/octet-stream");
					header("Content-Type: application/download");
					header("Content-Description: File Transfer");
				}
				else {
					$stream_file_ext = pathinfo($stream_file, PATHINFO_EXTENSION);
					switch ($stream_file_ext) {
						case "wav" : header("Content-Type: audio/x-wav"); break;
						case "mp3" : header("Content-Type: audio/mpeg"); break;
						case "ogg" : header("Content-Type: audio/ogg"); break;
					}
				}
				header('Content-Disposition: attachment; filename="'.$stream_file.'"');
				header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
				header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
				if (!empty($_GET['t']) && $_GET['t'] == "bin") {
					header("Content-Length: ".filesize($stream_full_path));
				}
				ob_clean();

				//content-range
				if (isset($_SERVER['HTTP_RANGE']) && (empty($_GET['t']) || $_GET['t'] != "bin"))  {
					range_download($stream_full_path);
				}

				fpassthru($fd);
			}
			exit;
	}

//upload music on hold file
	if (!empty($_POST['action']) && $_POST['action'] == 'upload'
		&& !empty($_FILES)
		&& is_uploaded_file($_FILES['file']['tmp_name'])
		) {

		//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'],'negative');
				header('Location: music_on_hold.php');
				exit;
			}

		//determine name
			if (!empty($_POST['name_new'])) {
				//set the action
					$action = 'add';
				//get the stream_name
					$stream_name = $_POST['name_new'];
				//get the rate
					$stream_rate = is_numeric($_POST['rate']) ? $_POST['rate'] : '';
			}
			else {
				//get the stream uuid
					$stream_uuid = $_POST['name'];
				//find the matching stream
					if (!empty($streams) && @sizeof($streams) != 0) {
						foreach ($streams as $row) {
							if ($stream_uuid == $row['music_on_hold_uuid']) {
								//set the action
									$action = 'update';
								//set the variables
									$stream_domain_uuid = $row['domain_uuid'];
									$stream_name = $row['music_on_hold_name'];
									$stream_path = $row['music_on_hold_path'];
									$stream_rate = $row['music_on_hold_rate'];
									$stream_shuffle = $row['music_on_hold_shuffle'];
									$stream_channels = $row['music_on_hold_channels'];
									$stream_internal = $row['music_on_hold_interval'];
									$stream_timer_name = $row['music_on_hold_timer_name'];
									$stream_chime_list = $row['music_on_hold_chime_list'];
									$stream_chime_freq = $row['music_on_hold_chime_freq'];
									$stream_chime_max = $row['music_on_hold_chime_max'];
									$stream_rate = $row['music_on_hold_rate'];
								//end the loop
									break;
							}
						}
					}
			}

		//get remaining values
			$stream_file_name_temp = $_FILES['file']['tmp_name'];
			$stream_file_name = $_FILES['file']['name'];
			$stream_file_ext = strtolower(pathinfo($stream_file_name, PATHINFO_EXTENSION));

		//check file type
			$valid_file_type = ($stream_file_ext == 'wav' || $stream_file_ext == 'mp3' || $stream_file_ext == 'ogg') ? true : false;

		//proceed for valid file type
			if ($stream_file_ext == 'wav' || $stream_file_ext == 'mp3' || $stream_file_ext == 'ogg') {

				//strip slashes, replace spaces
					$slashes = ["/","\\"];
					$stream_file_name = str_replace($slashes, '', $stream_file_name);
					$stream_file_name = str_replace(' ', '-', $stream_file_name);
					if ($action == "add") {
						$stream_name = str_replace($slashes, '', $stream_name);
						$stream_name = str_replace(' ', '_', $stream_name);
					}

				//detect auto rate
					if ($stream_rate == '') {
						$path_rate = '48000';
						$stream_rate_auto = true;
					}
					else {
						$path_rate = $stream_rate;
						$stream_rate_auto = false;
					}

				//define default path
					if ($action == "add") {
						$stream_path = path_join($settings->get('switch', 'sounds'), 'music', $_SESSION['domain_name'], $stream_name, $path_rate);
						$stream_path = str_replace('.loc', '._loc', $stream_path); // 14.03.22 freeswitch bug
					}

				//find whether the path already exists
					$stream_new_name = true;
					if (!empty($streams) && @sizeof($streams) != 0) {
						foreach ($streams as $row) {
							$alternate_path = str_replace('$${sounds_dir}', $settings->get('switch', 'sounds'), $row['music_on_hold_path']);
							if ($stream_path == $row['music_on_hold_path'] || $stream_path == $alternate_path) {
								$stream_new_name = false;
								break;
							}
						}
					}

				//set the variables
					$stream_path = str_replace('$${sounds_dir}', $settings->get('switch', 'sounds'), $stream_path);

				//add new path
					if ($stream_new_name) {
						$stream_uuid = uuid();
						$array['music_on_hold'][0]['music_on_hold_uuid'] = $stream_uuid;
						$array['music_on_hold'][0]['domain_uuid'] = $domain_uuid;
						$array['music_on_hold'][0]['music_on_hold_name'] = $stream_name;
						$array['music_on_hold'][0]['music_on_hold_path'] = $stream_path;
						$array['music_on_hold'][0]['music_on_hold_rate'] = strlen($stream_rate) != 0 ? $stream_rate : null;
						$array['music_on_hold'][0]['music_on_hold_shuffle'] = 'false';
						$array['music_on_hold'][0]['music_on_hold_channels'] = 1;
						$array['music_on_hold'][0]['music_on_hold_interval'] = 20;
						$array['music_on_hold'][0]['music_on_hold_timer_name'] = 'soft';
						$array['music_on_hold'][0]['music_on_hold_chime_list'] = null;
						$array['music_on_hold'][0]['music_on_hold_chime_freq'] = null;
						$array['music_on_hold'][0]['music_on_hold_chime_max'] = null;

						$p = permissions::new();
						$p->add('music_on_hold_add', 'temp');

						$database->save($array);
						unset($array);

						$p->delete('music_on_hold_add', 'temp');
					}

				//check target folder, move uploaded file
					if (!is_dir($stream_path)) {
						mkdir($stream_path, 0770, true);

						// 14.03.22 freeswitch bug - shouldn't be needed with freeswitch 1.10.8
			                       if (preg_match('|^(/usr/share/freeswitch/sounds/music/(.*?\._loc.*?))/|', $stream_path, $m)) {
			                           $fs_bug_target = $m[2];
			                           $fs_bug_link = str_replace('._loc', '.loc', $m[1]);
			                           symlink($fs_bug_target, $fs_bug_link);
			                       }
					}
					if (is_dir($stream_path)) {
						if (copy($stream_file_name_temp, $stream_path.'/'.$stream_file_name)) {
							@unlink($stream_file_name_temp);
						}
					}

				//set message
					message::add($text['message-upload_completed']);

				//clear the cache
					$cache = new cache;
					$cache->delete("configuration:local_stream.conf");

					$music = new switch_music_on_hold;
					$music->reload();

			}
		//set message for unsupported file type
			else {
				message::add($text['message-unsupported_file_type']);
			}

		//redirect
			header("Location: music_on_hold.php");
			exit;
	}

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//set the time zone
	date_default_timezone_set($settings->get('domain', 'time_zone', date_default_timezone_get()));

//set the time format options: 12h, 24h
	if ($settings->get('domain', 'time_format') == '24h') {
		$time_format = 'H:i:s';
	}
	else {
		$time_format = 'h:i:s a';
	}

//build the inline javascript
	$scripts  = "<script language='JavaScript' type='text/javascript'>\n";
	$scripts .= "\tfunction check_file_type(file_input) {\n";
	$scripts .= "\t\tfile_ext = file_input.value.substr((~-file_input.value.lastIndexOf('.') >>> 0) + 2);\n";
	$scripts .= "\t\tif (file_ext != 'mp3' && file_ext != 'wav' && file_ext != 'ogg' && file_ext != '') {\n";
	$scripts .= "\t\t\tdisplay_message(\"".addslashes($text['message-unsupported_file_type'])."\", 'negative', '2750');\n";
	$scripts .= "\t\t}\n";
	$scripts .= "\t}\n";
	$scripts .= "\tfunction name_mode(mode) {\n";
	$scripts .= "\t\tif (mode == 'new') {\n";
	$scripts .= "\t\t\tdocument.getElementById('name_select').style.display='none';\n";
	$scripts .= "\t\t\tdocument.getElementById('btn_new').style.display='none';\n";
	$scripts .= "\t\t\tdocument.getElementById('name_new').style.display='';\n";
	$scripts .= "\t\t\tdocument.getElementById('btn_select').style.display='';\n";
	$scripts .= "\t\t\tdocument.getElementById('rate').style.display='';\n";
	$scripts .= "\t\t\tdocument.getElementById('name_new').focus();\n";
	$scripts .= "\t\t}\n";
	$scripts .= "\t\telse if (mode == 'select') {\n";
	$scripts .= "\t\t\tdocument.getElementById('name_new').style.display='none';\n";
	$scripts .= "\t\t\tdocument.getElementById('name_new').value = '';\n";
	$scripts .= "\t\t\tdocument.getElementById('rate').style.display='none';\n";
	$scripts .= "\t\t\tdocument.getElementById('btn_select').style.display='none';\n";
	$scripts .= "\t\t\tdocument.getElementById('name_select').selectedIndex = 0;\n";
	$scripts .= "\t\t\tdocument.getElementById('name_select').style.display='';\n";
	$scripts .= "\t\t\tdocument.getElementById('btn_new').style.display='';\n";
	$scripts .= "\t\t}\n";
	$scripts .= "\t}\n";
	$scripts .= "</script>";

//build the upload form
	$upload_form = '';
	if ($has_music_on_hold_add) {
		$modify_add_action = (empty($streams) || @sizeof($streams) == 0) ? "name_mode('new'); \$('#btn_select').hide();" : null;
		$btn_add      = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','onclick'=>"$(this).fadeOut(250, function(){ ".$modify_add_action." \$('span#form_upload').fadeIn(250); });"]);
		$btn_cancel   = button::create(['label'=>$text['button-cancel'],'icon'=>$settings->get('theme', 'button_icon_cancel'),'type'=>'button','id'=>'btn_upload_cancel','onclick'=>"\$('span#form_upload').fadeOut(250, function(){ name_mode('select'); document.getElementById('form_upload').reset(); \$('#btn_add').fadeIn(250) });"]);
		$btn_new_cat  = button::create(['type'=>'button','title'=>!empty($text['label-new']),'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_new','onclick'=>"name_mode('new');"]);
		$btn_sel_cat  = button::create(['type'=>'button','title'=>$text['label-select'],'icon'=>'list','id'=>'btn_select','style'=>'display: none;','onclick'=>"name_mode('select');"]);
		$margin_right = $has_music_on_hold_delete ? 'margin-right: 15px;' : null;
		$btn_upload   = button::create(['type'=>'submit','label'=>$text['button-upload'],'style'=>$margin_right,'icon'=>$settings->get('theme', 'button_icon_upload')]);
		$options_global     = '';
		$options_local      = '';
		$local_found_upload = false;
		if (!empty($streams)) {
			foreach ($streams as $upload_row) {
				if (empty($upload_row['domain_uuid'])) {
					$option_name_up  = empty($upload_row['music_on_hold_rate']) ? $upload_row['music_on_hold_name'] : $upload_row['music_on_hold_name'].'/'.$upload_row['music_on_hold_rate'];
					$options_global .= "\t<option value='".escape($upload_row['music_on_hold_uuid'])."'>".escape($option_name_up)."</option>\n";
				}
				if (is_uuid($upload_row['domain_uuid'])) {
					$local_found_upload = true;
				}
			}
			foreach ($streams as $upload_row) {
				if (!empty($upload_row['domain_uuid'])) {
					$option_name_up = empty($upload_row['music_on_hold_rate']) ? $upload_row['music_on_hold_name'] : $upload_row['music_on_hold_name'].'/'.$upload_row['music_on_hold_rate'];
					$options_local .= "\t<option value='".escape($upload_row['music_on_hold_uuid'])."'>".escape($option_name_up)."</option>\n";
				}
			}
		}
		$name_select  = "<select name='name' id='name_select' class='formfld' style='width: auto; margin: 0;'>\n";
		$name_select .= "\t<option value='' selected='selected' disabled='disabled'>".$text['label-category']."</option>\n";
		if ($has_music_on_hold_domain && !empty($options_global)) {
			$name_select .= "\t<optgroup label='".$text['option-global']."'>\n";
			$name_select .= $options_global;
			$name_select .= "\t</optgroup>\n";
		}
		if ($local_found_upload) {
			if ($has_music_on_hold_domain) {
				$name_select .= "\t<optgroup label='".$text['option-local']."'>\n";
			}
			$name_select .= $options_local;
			if ($has_music_on_hold_domain) {
				$name_select .= "\t</optgroup>\n";
			}
		}
		$name_select .= "</select>";
		$rate_select  = "<select id='rate' name='rate' class='formfld' style='display: none; width: auto; margin: 0;'>\n";
		$rate_select .= "\t<option value=''>".$text['option-default']."</option>\n";
		$rate_select .= "\t<option value='8000'>8 kHz</option>\n";
		$rate_select .= "\t<option value='16000'>16 kHz</option>\n";
		$rate_select .= "\t<option value='32000'>32 kHz</option>\n";
		$rate_select .= "\t<option value='48000'>48 kHz</option>\n";
		$rate_select .= "</select>";
		$upload_form  = "<form id='form_upload' class='inline' method='post' enctype='multipart/form-data'>\n";
		$upload_form .= "<input name='action' type='hidden' value='upload'>\n";
		$upload_form .= "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";
		$upload_form .= $btn_add;
		$upload_form .= "<span id='form_upload' style='display: none;'>";
		$upload_form .= $btn_cancel;
		$upload_form .= $name_select;
		$upload_form .= "<input class='formfld' style='width: 100px; margin: 0; display: none;' type='text' name='name_new' id='name_new' maxlength='255' placeholder=\"".$text['label-category']."\" value=''>";
		$upload_form .= $rate_select;
		$upload_form .= $btn_new_cat;
		$upload_form .= $btn_sel_cat;
		$upload_form .= "<input type='text' class='txt' style='width: 100px; cursor: pointer; margin: 0;' id='filename' placeholder='Select...' onclick=\"document.getElementById('file').click(); this.blur();\" onfocus='this.blur();'>";
		$upload_form .= "<input type='file' id='file' name='file' style='display: none;' accept='.wav,.mp3,.ogg' onchange=\"document.getElementById('filename').value = this.files.item(0).name; check_file_type(this);\">";
		$upload_form .= $btn_upload;
		$upload_form .= "</span>\n";
		$upload_form .= "</form>";
	}

//build the show all button
	$btn_show_all = '';
	if ($has_music_on_hold_all) {
		if ($show == 'all') {
			$btn_show_all = "<input type='hidden' name='show' value='all'>";
		}
		else {
			$btn_show_all = button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$settings->get('theme', 'button_icon_all'),'link'=>'?type=&show=all'.(!empty($search) ? '&search='.urlencode($search) : null)]);
		}
	}

//build the delete button
	$btn_delete = '';
	if ($has_music_on_hold_delete && $streams) {
		$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}

//build the modal
	$modal_delete = '';
	if ($has_music_on_hold_delete && $streams) {
		$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

//build the table header for domain column
	$th_domain_header = '';
	if ($show == 'all' && $has_music_on_hold_all) {
		$th_domain_header = th_order_by('domain_name', $text['label-domain'], $order_by, $order, null, "class='shrink'");
	}

//build the row data for display
	$display_streams = [];
	$previous_name   = '';
	$x = 0;
	if (!empty($streams)) {
		foreach ($streams as $row) {
			if (empty($row['domain_uuid']) && !$has_music_on_hold_global && !($show == 'all' && $has_music_on_hold_all)) {
				continue;
			}
			$music_on_hold_name  = $row['music_on_hold_name'];
			$music_on_hold_rate  = $row['music_on_hold_rate'];
			$auto_rate           = empty($music_on_hold_rate);
			$stream_rate_label   = $auto_rate ? $text['option-default'] : ($music_on_hold_rate / 1000).' kHz';
			$row['_show_category'] = ($previous_name != $music_on_hold_name);
			$row['_is_global']     = !is_uuid($row['domain_uuid']);
			$stream_icons_arr = [];
			$i = 0;
			if ($has_music_on_hold_path) {
				$stream_icons_arr[$i]['icon']  = 'fa-folder-open';
				$stream_icons_arr[$i]['title'] = $row['music_on_hold_name'];
				$i++;
			}
			if ($row['music_on_hold_shuffle'] == 'true') {
				$stream_icons_arr[$i]['icon']  = 'fa-random';
				$stream_icons_arr[$i]['title'] = $text['label-shuffle'];
				$i++;
			}
			if (!empty($row['music_on_hold_chime_list'])) {
				$stream_icons_arr[$i]['icon']  = 'fa-bell';
				$stream_icons_arr[$i]['title'] = $text['label-chime_list'].': '.$row['music_on_hold_chime_list'];
				$i++;
			}
			if ($row['music_on_hold_channels'] == '2') {
				$stream_icons_arr[$i]['icon']   = 'fa-headphones';
				$stream_icons_arr[$i]['title']  = $text['label-stereo'];
				$stream_icons_arr[$i]['margin'] = 6;
				$i++;
			}
			$icons_html = '';
			foreach ($stream_icons_arr as $stream_icon) {
				$icons_html .= "<span class='fas ".$stream_icon['icon']." icon_body' title='".escape($stream_icon['title'])."' style='width: 12px; height: 12px; margin-left: ".(!empty($stream_icon['margin']) ? $stream_icon['margin'] : 8)."px; vertical-align: text-top; cursor: help;'></span>";
			}
			if ($has_music_on_hold_edit) {
				$row['_stream_details'] = "<a href='music_on_hold_edit.php?id=".urlencode($row['music_on_hold_uuid'])."' class='default-color'>".$stream_rate_label.'</a> '.$icons_html;
			}
			else {
				$row['_stream_details'] = $stream_rate_label.' '.$icons_html;
			}
			$row['_domain_label'] = '';
			if ($show == 'all' && $has_music_on_hold_all) {
				if (!empty($_SESSION['domains'][$row['domain_uuid']]['domain_name'])) {
					$row['_domain_label'] = $_SESSION['domains'][$row['domain_uuid']]['domain_name'];
				}
				else {
					$row['_domain_label'] = $text['label-global'];
				}
			}
			$stream_path_resolved = str_replace("\$\${sounds_dir}", $settings->get('switch', 'sounds') ?? '', $row['music_on_hold_path']);
			$stream_files_raw = [];
			if (file_exists($stream_path_resolved)) {
				$stream_files_raw = array_merge(
					glob($stream_path_resolved.'/*.wav'),
					glob($stream_path_resolved.'/*.mp3'),
					glob($stream_path_resolved.'/*.ogg')
				);
			}
			$row['_files'] = [];
			foreach ($stream_files_raw as $stream_file_path) {
				$row_uuid         = uuid();
				$stream_file      = pathinfo($stream_file_path, PATHINFO_BASENAME);
				$stream_file_size = byte_convert(filesize($stream_file_path));
				$stream_file_date = date("M d, Y ".$time_format, filemtime($stream_file_path));
				$stream_file_ext  = pathinfo($stream_file, PATHINFO_EXTENSION);
				switch ($stream_file_ext) {
					case "wav": $stream_file_type = "audio/wav";  break;
					case "mp3": $stream_file_type = "audio/mpeg"; break;
					case "ogg": $stream_file_type = "audio/ogg";  break;
					default:    $stream_file_type = "audio/wav";  break;
				}
				$btn_play = button::create(['type'=>'button','title'=>$text['label-play'].' / '.$text['label-pause'],'icon'=>$settings->get('theme', 'button_icon_play'),'id'=>'recording_button_'.$row_uuid,'onclick'=>"recording_play('".$row_uuid."','".urlencode($stream_file)."&moh_id=".urlencode($row['music_on_hold_uuid'])."');"]);
				$btn_dl   = button::create(['type'=>'button','title'=>$text['label-download'],'icon'=>$settings->get('theme', 'button_icon_download'),'link'=>"?action=download&id=".urlencode($row['music_on_hold_uuid'])."&file=".urlencode($stream_file)]);
				$row['_files'][] = [
					'row_uuid'  => $row_uuid,
					'file_name' => $stream_file,
					'file_size' => $stream_file_size,
					'file_date' => $stream_file_date,
					'file_type' => $stream_file_type,
					'btn_play'  => $btn_play,
					'btn_dl'    => $btn_dl,
					'x'         => $x,
				];
				$x++;
			}
			$previous_name     = $music_on_hold_name;
			$display_streams[] = $row;
		}
	}
	unset($streams, $row);

//build the template
	$template = new template();
	$template->engine       = 'smarty';
	$template->template_dir = __DIR__.'/resources/views';
	$template->cache_dir    = sys_get_temp_dir();
	$template->init();

//assign the template variables
	$template->assign('text',                     $text);
	$template->assign('streams',                  $display_streams);
	$template->assign('search',                   $search);
	$template->assign('show',                     $show);
	$template->assign('token',                    $token);
	$template->assign('has_music_on_hold_add',    $has_music_on_hold_add);
	$template->assign('has_music_on_hold_all',    $has_music_on_hold_all);
	$template->assign('has_music_on_hold_delete', $has_music_on_hold_delete);
	$template->assign('has_music_on_hold_edit',   $has_music_on_hold_edit);
	$template->assign('scripts',                  $scripts);
	$template->assign('upload_form',              $upload_form);
	$template->assign('btn_show_all',             $btn_show_all);
	$template->assign('btn_delete',               $btn_delete);
	$template->assign('modal_delete',             $modal_delete);
	$template->assign('th_domain_header',         $th_domain_header);

//invoke pre-render hook
	app::dispatch_list_pre_render('music_on_hold_list_page_hook', null, $template);

//include the header
	$document['title'] = $text['title-music_on_hold'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('music_on_hold_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('music_on_hold_list_page_hook', null, $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";

//define the download function (helps safari play audio sources)
	/**
	 * Downloads a file in chunks as requested by the client.
	 *
	 * This function is used to handle byte-range requests, allowing clients
	 * to request specific parts of the file.
	 *
	 * @param string $file The path to the file being downloaded.
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


