<?php
/* $Id$ */
/*
	click_to_call.php
	Copyright (C) 2008, 2021 Mark J Crane
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
	James Rose <james.o.rose@gmail.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('click_to_call_view')) {
		echo "access denied";
		exit;
	}
	$has_click_to_call_call = permission_exists('click_to_call_call');

//add multi-lingual support
	$text = new text()->get();


//predefine the variables
	$src = '';
	$src_cid_name = '';
	$src_cid_number = '';

	$dest = '';
	$dest_cid_name = '';
	$dest_cid_number = '';

	$auto_answer = ''; //true,false
	$rec = ''; //true,false
	$ringback = '';
	$context = $_SESSION['domain_name'];

//send the call
	$call_result_html = '';
	if (is_array($_GET) && isset($_GET['src']) && isset($_GET['dest'])) {

	//retrieve submitted variables
		$src = $_GET['src'] ?? '';
		$src_cid_name = $_GET['src_cid_name'] ?? '';
		$src_cid_number = $_GET['src_cid_number'] ?? '';

		$dest = $_GET['dest'] ?? '';
		$dest_cid_name = $_GET['dest_cid_name'] ?? '';
		$dest_cid_number = $_GET['dest_cid_number'] ?? '';

		$auto_answer = $_GET['auto_answer'] ?? ''; //true,false
		$rec = $_GET['rec'] ?? ''; //true,false
		$ringback = $_GET['ringback'] ?? '';
		$context = $_SESSION['domain_name'];

	//clean up variable values
		$src = str_replace(array('(',')',' '), '', $src);
		$dest = (strpbrk($dest, '@') != FALSE) ? str_replace(array('(',')',' '), '', $dest) : str_replace(array('.','(',')','-',' '), '', $dest);

	//adjust variable values
		$sip_auto_answer = ($auto_answer == "true") ? ",sip_auto_answer=true" : null;

	//mozilla thunderbird TBDialout workaround (seems it can only handle the first %NUM%)
		$dest = ($dest == "%NUM%") ? $src_cid_number : $dest;

	//translate ringback
		switch ($ringback) {
			case "music": $ringback_value = "\'local_stream://moh\'"; break;
			case "uk-ring": $ringback_value = "\'%(400,200,400,450);%(400,2200,400,450)\'"; break;
			case "fr-ring": $ringback_value = "\'%(1500,3500,440.0,0.0)\'"; break;
			case "pt-ring": $ringback_value = "\'%(1000,5000,400.0,0.0)\'"; break;
			case "rs-ring": $ringback_value = "\'%(1000,4000,425.0,0.0)\'"; break;
			case "it-ring": $ringback_value = "\'%(1000,4000,425.0,0.0)\'"; break;
			case "de-ring": $ringback_value = "\'%(1000,4000,425.0,0.0)\'"; break;
			case "us-ring":
			default:
				$ringback = 'us-ring';
				$ringback_value = "\'%(2000,4000,440.0,480.0)\'";
		}

	//create the event socket connection and send the event socket command
		$esl = event_socket::create();
		if ($esl->is_connected()) {

		//set call uuid
			$origination_uuid = trim(event_socket::api("create_uuid"));

		//add record path and name
			if ($rec == "true") {
				$record_path = $settings->get('switch', 'recordings')."/".$_SESSION['domain_name']."/archive/".date("Y")."/".date("M")."/".date("d");
				if (!empty($settings->get('recordings', 'extension'))) {
					$record_extension = $settings->get('recordings', 'extension');
				}
				else {
					$record_extension = 'wav';
				}
				if (!empty($settings->get('recordings', 'template'))) {
					$record_name = $settings->get('recordings', 'template');
					$record_name = str_replace('${year}', date("Y"), $record_name);
					$record_name = str_replace('${month}', date("M"), $record_name);
					$record_name = str_replace('${day}', date("d"), $record_name);
					$record_name = str_replace('${source}', $src, $record_name);
					$record_name = str_replace('${caller_id_name}', $src_cid_name, $record_name);
					$record_name = str_replace('${caller_id_number}', $src_cid_number, $record_name);
					$record_name = str_replace('${caller_destination}', $dest, $record_name);
					$record_name = str_replace('${destination}', $dest, $record_name);
					$record_name = str_replace('${uuid}', $origination_uuid, $record_name);
					$record_name = str_replace('${record_extension}', $record_extension, $record_name);
				}
				else {
					$record_name = $origination_uuid.'.'.$record_extension;
				}
			}

		//determine call direction
			$dir = (user_exists($dest)) ? 'local' : 'outbound';

		//define a leg - set source to display the defined caller id name and number
			$source_common = "{";
			$source_common .= "click_to_call=true";
			$source_common .= ",origination_caller_id_name='".$src_cid_name."'";
			$source_common .= ",origination_caller_id_number=".$src_cid_number;
			$source_common .= ",instant_ringback=true";
			$source_common .= ",ringback=".$ringback_value;
			$source_common .= ",presence_id=".$src."@".$_SESSION['domains'][$domain_uuid]['domain_name'];
			$source_common .= ",call_direction=".$dir;
			if ($rec == "true") {
				$source_common .= ",record_path='".$record_path."'";
				$source_common .= ",record_name='".$record_name."'";
			}

			if (user_exists($src)) {
				$source = $source_common.$sip_auto_answer.
					",domain_uuid=".$domain_uuid.
					",domain_name=".$_SESSION['domains'][$domain_uuid]['domain_name']."}user/".$src."@".$_SESSION['domains'][$domain_uuid]['domain_name'];
			}
			else {
				$bridge_array = outbound_route_to_bridge($_SESSION['domain_uuid'], $src);
				$source = $source_common."}".$bridge_array[0];
			}
			unset($source_common);

		//define b leg - set destination to display the defined caller id name and number
			$destination_common = " &bridge({origination_caller_id_name='".$dest_cid_name."',origination_caller_id_number=".$dest_cid_number;
			if (user_exists($dest)) {
				if (strpbrk($dest, '@') != FALSE) {
					$switch_cmd = $destination_common.",call_direction=outbound}sofia/external/".$dest.")";
				}
				else {
					$switch_cmd = " &transfer('".$dest." XML ".$context."')";
				}
			}
			else {
				if (user_exists($src) && empty($dest_cid_number)) {
					$sql = "select outbound_caller_id_name, outbound_caller_id_number from v_extensions where domain_uuid = :domain_uuid and extension = :src ";
					$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
					$parameters['src'] = $src;
					$result = $database->select($sql, $parameters, 'all');
					foreach ($result as $row) {
						$dest_cid_name = $row["outbound_caller_id_name"];
						$dest_cid_number = $row["outbound_caller_id_number"];
						break;
					}
				}
				if ($has_click_to_call_call) {
					if (strpbrk($dest, '@') != FALSE) {
						$switch_cmd = $destination_common.",call_direction=outbound}sofia/external/".$dest.")";
					}
					else {
						$bridge_array = outbound_route_to_bridge($_SESSION['domain_uuid'], $dest);
						$switch_cmd = " &transfer('".$dest." XML ".$context."')";
					}
				}
			}
			unset($destination_common);
		}
		else {
			$call_result_html .= "<div align='center'><strong>Connection to Event Socket failed.</strong></div>";
		}

	//ensure we are still connected and send the event socket command
		if ($esl->is_connected()) {
			$switch_cmd = "originate ".$source.$switch_cmd;
			$call_result_html .= "<div align='center'><strong>".escape($src)." has called ".escape($dest)."</strong></div>\n";
			$result = trim(event_socket::api($switch_cmd));
			if (substr($result, 0,3) == "+OK") {
				if ($rec == "true") {
					date_default_timezone_set(date_default_timezone_get());
					if (is_uuid($origination_uuid) && file_exists($record_path)) {
						$switch_cmd = "uuid_record $origination_uuid start $record_path/$record_name";
					}
					$result2 = trim(event_socket::api($switch_cmd));
				}
			}
			$call_result_html .= "<div align='center'><br />".escape($result)."<br /><br /></div>\n";
		}
		else {
			$call_result_html .= "<div align='center'><strong>Connection to Event Socket failed.</strong></div>";
		}
	}

//build the form html
	$form_html = '';
	$form_html .= "	<table width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\n";
	$form_html .= "	<tr>\n";
	$form_html .= "	<td align='left'>\n";
	$form_html .= "		<span class=\"title\">\n";
	$form_html .= "			<strong>".$text['label-click2call']."</strong>\n";
	$form_html .= "		</span>\n";
	$form_html .= "	</td>\n";
	$form_html .= "	<td align='right'>\n";
	$form_html .= "		&nbsp;\n";
	$form_html .= "	</td>\n";
	$form_html .= "	</tr>\n";
	$form_html .= "	<tr>\n";
	$form_html .= "	<td align='left' colspan='2'>\n";
	$form_html .= "		<span class=\"vexpl\">\n";
	$form_html .= "			".$text['desc-click2call']."\n";
	$form_html .= "		</span>\n";
	$form_html .= "	</td>\n";
	$form_html .= "\n";
	$form_html .= "	</tr>\n";
	$form_html .= "	</table>";
	$form_html .= "	<br />";
	$form_html .= "<form method=\"get\">\n";
	$form_html .= "<table border='0' width='100%' cellpadding='0' cellspacing='0'>\n";
	$form_html .= "<tr>\n";
	$form_html .= "	<td class='vncellreq' width='40%'>".$text['label-src-caller-id-nam']."</td>\n";
	$form_html .= "	<td class='vtable' align='left'>\n";
	$form_html .= "		<input name=\"src_cid_name\" value='".escape($src_cid_name)."' class='formfld'>\n";
	$form_html .= "		<br />\n";
	$form_html .= "		".$text['desc-src-caller-id-nam']."\n";
	$form_html .= "	</td>\n";
	$form_html .= "</tr>\n";
	$form_html .= "<tr>\n";
	$form_html .= "	<td class='vncellreq'>".$text['label-src-caller-id-num']."</td>\n";
	$form_html .= "	<td class='vtable' align='left'>\n";
	$form_html .= "		<input name=\"src_cid_number\" value='".escape($src_cid_number)."' class='formfld'>\n";
	$form_html .= "		<br />\n";
	$form_html .= "		".$text['desc-src-caller-id-num']."\n";
	$form_html .= "	</td>\n";
	$form_html .= "</tr>\n";
	$form_html .= "<tr>\n";
	$form_html .= "	<td class='vncell' width='40%'>".$text['label-dest-caller-id-nam']."</td>\n";
	$form_html .= "	<td class='vtable' align='left'>\n";
	$form_html .= "		<input name=\"dest_cid_name\" value='".escape($dest_cid_name)."' class='formfld'>\n";
	$form_html .= "		<br />\n";
	$form_html .= "		".$text['desc-dest-caller-id-nam']."\n";
	$form_html .= "	</td>\n";
	$form_html .= "</tr>\n";
	$form_html .= "<tr>\n";
	$form_html .= "	<td class='vncell'>".$text['label-dest-caller-id-num']."</td>\n";
	$form_html .= "	<td class='vtable' align='left'>\n";
	$form_html .= "		<input name=\"dest_cid_number\" value='".escape($dest_cid_number)."' class='formfld'>\n";
	$form_html .= "		<br />\n";
	$form_html .= "		".$text['desc-dest-caller-id-num']."\n";
	$form_html .= "	</td>\n";
	$form_html .= "</tr>\n";
	$form_html .= "<tr>\n";
	$form_html .= "	<td class='vncellreq'>".$text['label-src-num']."</td>\n";
	$form_html .= "	<td class='vtable' align='left'>\n";
	$form_html .= "		<input name=\"src\" value='".escape($src)."' class='formfld'>\n";
	$form_html .= "		<br />\n";
	$form_html .= "		".$text['desc-src-num']."\n";
	$form_html .= "	</td>\n";
	$form_html .= "</tr>\n";
	$form_html .= "<tr>\n";
	$form_html .= "	<td class='vncellreq'>".$text['label-dest-num']."</td>\n";
	$form_html .= "	<td class='vtable' align='left'>\n";
	$form_html .= "		<input name=\"dest\" value='".escape($dest)."' class='formfld'>\n";
	$form_html .= "		<br />\n";
	$form_html .= "		".$text['desc-dest-num']."\n";
	$form_html .= "	</td>\n";
	$form_html .= "</tr>\n";
	$form_html .= " <tr>\n";
	$form_html .= "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	$form_html .= "	".$text['label-auto-answer']."\n";
	$form_html .= "</td>\n";
	$form_html .= "<td class='vtable' align='left'>\n";
	$form_html .= "    <select class='formfld' name='auto_answer'>\n";
	$form_html .= "    <option value=''></option>\n";
	$form_html .= "    <option value='true' ".($auto_answer == "true" ? "selected='selected'" : null).">".$text['label-true']."</option>\n";
	$form_html .= "    <option value='false' ".($auto_answer == "false" ? "selected='selected'" : null).">".$text['label-false']."</option>\n";
	$form_html .= "    </select>\n";
	$form_html .= "<br />\n";
	$form_html .= $text['desc-auto-answer']."\n";
	$form_html .= "</td>\n";
	$form_html .= "</tr>\n";
	$form_html .= "<tr>\n";
	$form_html .= "<td class='vncell' valign='top' align='left' nowrap>\n";
	$form_html .= "    ".$text['label-record']."\n";
	$form_html .= "</td>\n";
	$form_html .= "<td class='vtable' align='left'>\n";
	$form_html .= "    <select class='formfld' name='rec'>\n";
	$form_html .= "    <option value=''></option>\n";
	$form_html .= "    <option value='true' ".($rec == "true" ? "selected='selected'" : null).">".$text['label-true']."</option>\n";
	$form_html .= "    <option value='false' ".($rec == "false" ? "selected='selected'" : null).">".$text['label-false']."</option>\n";
	$form_html .= "    </select>\n";
	$form_html .= "<br />\n";
	$form_html .= $text['desc-record']."\n";
	$form_html .= "</td>\n";
	$form_html .= "</tr>\n";
	$form_html .= "<tr>\n";
	$form_html .= "<td class='vncellreq' valign='top' align='left' nowrap>\n";
	$form_html .= "    ".$text['label-ringback']."\n";
	$form_html .= "</td>\n";
	$form_html .= "<td class='vtable' align='left'>\n";
	$form_html .= "    <select class='formfld' name='ringback'>\n";
	$form_html .= "    <option value=''></option>\n";
	$form_html .= "    <option value='us-ring' ".($ringback == "us-ring" ? "selected='selected'" : null).">".$text['opt-usring']."</option>\n";
	$form_html .= "    <option value='fr-ring' ".($ringback == "fr-ring" ? "selected='selected'" : null).">".$text['opt-frring']."</option>\n";
	$form_html .= "    <option value='pt-ring' ".($ringback == "pt-ring" ? "selected='selected'" : null).">".$text['opt-ptring']."</option>\n";
	$form_html .= "    <option value='uk-ring' ".($ringback == "uk-ring" ? "selected='selected'" : null).">".$text['opt-ukring']."</option>\n";
	$form_html .= "    <option value='rs-ring' ".($ringback == "rs-ring" ? "selected='selected'" : null).">".$text['opt-rsring']."</option>\n";
	$form_html .= "    <option value='ru-ring' ".($ringback == "ru-ring" ? "selected='selected'" : null).">".$text['opt-ruring']."</option>\n";
	$form_html .= "    <option value='it-ring' ".($ringback == "it-ring" ? "selected='selected'" : null).">".$text['opt-itring']."</option>\n";
	$form_html .= "    <option value='de-ring' ".($ringback == "de-ring" ? "selected='selected'" : null).">".$text['opt-dering']."</option>\n";
	$form_html .= "    <option value='music' ".($ringback == "music" ? "selected='selected'" : null).">".$text['opt-moh']."</option>\n";
	$form_html .= "    </select>\n";
	$form_html .= "<br />\n";
	$form_html .= $text['desc-ringback']."\n";
	$form_html .= "</td>\n";
	$form_html .= "</tr>\n";
	$form_html .= "<tr>\n";
	$form_html .= "	<td colspan='2' align='right'>\n";
	$form_html .= "		<br>";
	$form_html .= "		<button type='submit' class='btn btn-default'><i class='fas fa-phone fa-lg'></i>&nbsp;&nbsp;&nbsp;".$text['button-call']."</button>\n";
	$form_html .= "	</td>\n";
	$form_html .= "</tr>\n";
	$form_html .= "</table>\n";
	$form_html .= "<br><br>";
	$form_html .= "</form>";

//build the template
	$template = new template();
	$template->engine = 'smarty';
	$template->template_dir = __DIR__.'/resources/views';
	$template->cache_dir = sys_get_temp_dir();
	$template->init();

//assign the template variables
	$template->assign('text',              $text);
	$template->assign('call_result_html',  $call_result_html);
	$template->assign('form_html',         $form_html);

//invoke pre-render hook
	app::dispatch_list_pre_render('click_to_call_list_page_hook', 'click_to_call.php', $template);

//include the header
	$document['title'] = $text['title-click_to_call'] ?? $text['label-click2call'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('click_to_call_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('click_to_call_list_page_hook', 'click_to_call.php', $html);
	echo $html;

//show the footer
	require_once "resources/footer.php";
