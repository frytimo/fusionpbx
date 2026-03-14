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
	Portions created by the Initial Developer are Copyright (C) 2018-2025
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (!permission_exists('call_recording_view')) {
		echo "access denied";
		exit;
	}
	$has_call_recording_all        = permission_exists('call_recording_all');
	$has_call_recording_download   = permission_exists('call_recording_download');
	$has_call_recording_play       = permission_exists('call_recording_play');
	$has_call_recording_delete     = permission_exists('call_recording_delete');
	$has_call_recording_transcribe = permission_exists('call_recording_transcribe');
	$has_xml_cdr_details           = permission_exists('xml_cdr_details');

//add multi-lingual support
	$text = new text()->get();

//add the settings object
	$settings = new settings(["domain_uuid" => $_SESSION['domain_uuid'], "user_uuid" => $_SESSION['user_uuid']]);
	$transcribe_enabled = $settings->get('transcribe', 'enabled', false);
	$transcribe_engine = $settings->get('transcribe', 'engine', '');

//set additional variables
	// $url->get() returns sanitized values via FILTER_SANITIZE_FULL_SPECIAL_CHARS
	$search = $url->get('search', '');
	$show = $url->get('show', '');
	$result_count = 0;

//get the http post data
	if ($url->has_post('call_recordings') && is_array($url->post('call_recordings'))) {
		$action = $url->post('action', '');
		$search = $url->post('search', '');
		$call_recordings = $url->post('call_recordings', []);
	}

//process the http post data by action
	if (!empty($action) && is_array($call_recordings) && @sizeof($call_recordings) != 0) {
		switch ($action) {
			case 'download':
				if ($has_call_recording_download) {
					$obj = new call_recordings;
					$obj->download($call_recordings);
				}
				break;
			case 'transcribe':
				if ($has_call_recording_transcribe) {
					$obj = new call_recordings;
					$obj->transcribe($call_recordings);
				}
				break;
			case 'delete':
				if ($has_call_recording_delete) {
					$obj = new call_recordings;
					$obj->delete($call_recordings);
				}
				break;
		}

		//redirect the user
		header('Location: call_recordings.php'.($search != '' ? '?search='.urlencode($search) : ''));
		exit;
	}

//get order and order by
	// Additional preg_replace on order_by as an extra defence-in-depth layer for SQL identifier safety
	$order_by = preg_replace('#[^a-zA-Z0-9_\-]#', '', $url->get('order_by', ''));
	$order = $url->get('order', '');

//normalise the search string to lowercase
	if (!empty($search)) {
		$search = strtolower($search);
	}

//prepare some of the paging values
	// $url validates page >= 0 and rows_per_page from settings
	$rows_per_page = $url->get_rows_per_page();
	$page = $url->get_page();
	$offset = $url->offset();

//set the time zone
	$time_zone = $settings->get('domain', 'time_zone', date_default_timezone_get());

//set the time format options: 12h, 24h
	if ($settings->get('domain', 'time_format') == '24h') {
		$time_format = 'HH24:MI:SS';
	}
	else {
		$time_format = 'HH12:MI:SS am';
	}

//build the where clause (shared between count and list queries)
	$sql_where = "where true ";
	if ($show != "all" || !$has_call_recording_all) {
		$sql_where .= "and r.domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	$sql_where .= "and r.domain_uuid = d.domain_uuid ";
	if (!empty($search)) {
		$sql_where .= "and (";
		$sql_where .= "	lower(r.call_direction) like :search ";
		$sql_where .= "	or lower(r.caller_id_name) like :search ";
		$sql_where .= "	or lower(r.caller_id_number) like :search ";
		$sql_where .= "	or lower(r.caller_destination) like :search ";
		$sql_where .= "	or lower(r.destination_number) like :search ";
		$sql_where .= "	or lower(r.call_recording_name) like :search ";
		$sql_where .= "	or lower(r.call_recording_path) like :search ";
		$sql_where .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}

//count the total number of records
	$sql = "select count(*) from view_call_recordings as r, v_domains as d ";
	$sql .= $sql_where;
	$num_rows = $database->select($sql, $parameters ?? null, 'column');
	unset($sql);

//limit the number of results
	if (!empty($settings->get('cdr', 'limit')) && $settings->get('cdr', 'limit') > 0) {
		$num_rows = min($num_rows, $settings->get('cdr', 'limit'));
	}

//set total rows on the paging object and clamp page to last valid page
	$url->set_total_rows($num_rows);
	if ($num_rows > 0 && $page > ($url->pages() - 1)) {
		$url->set_page($url->pages() - 1);
		$page = $url->get_page();
		$offset = $url->offset();
	}

//get the list
	$sql = "select r.domain_uuid, d.domain_name, r.call_recording_uuid, r.call_direction, ";
	$sql .= "r.call_recording_name, r.call_recording_path, r.call_recording_transcription, r.call_recording_length, ";
	$sql .= "r.caller_id_name, r.caller_id_number, r.caller_destination, r.destination_number, ";
	$sql .= "to_char(timezone(:time_zone, r.call_recording_date), 'DD Mon YYYY') as call_recording_date_formatted, \n";
	$sql .= "to_char(timezone(:time_zone, r.call_recording_date), '".$time_format."') as call_recording_time_formatted \n";
	$sql .= "from view_call_recordings as r, v_domains as d ";
	//$sql .= "from v_call_recordings as r, v_domains as d ";
	$sql .= $sql_where;
	$sql .= order_by($order_by, $order, 'r.call_recording_date', 'desc');
	$sql .= limit_offset($rows_per_page, $offset);
	$parameters['time_zone'] = $time_zone;
	$call_recordings = $database->select($sql, $parameters ?? null, 'all');
	unset($sql, $parameters, $sql_where);

//set the result count for paging
	$result_count = is_array($call_recordings) ? sizeof($call_recordings) : 0;

//detect if any transcriptions available
	if ($transcribe_enabled && !empty($transcribe_engine) && !empty($call_recordings) && is_array($call_recordings)) {
		$transcriptions_exists = false;
		foreach ($call_recordings as $row) {
//			if (!empty($row['call_recording_transcription'])) { $transcriptions_exists = true; }
		}
	}

//build extra url params used by column sort header links
	$param = !empty($search) ? "&search=".urlencode($search) : '';
	if ($show == "all" && $has_call_recording_all) {
		$param .= "&show=all";
	}

//prepare paging controls using the url object
	$paging_controls_mini = url::html_paging_mini_controls($url);
	$paging_controls = url::html_paging_controls($url);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//build the action bar buttons
	$btn_download = '';
	if ($has_call_recording_download && !empty($call_recordings)) {
		$btn_download = button::create(['type'=>'button','label'=>$text['button-download'],'icon'=>$settings->get('theme', 'button_icon_download'),'id'=>'btn_download','name'=>'btn_download','style'=>'display: none;','collapse'=>'hide-xs','onclick'=>"list_action_set('download'); list_form_submit('form_list');"]);
	}
	$btn_transcribe = '';
	if ($has_call_recording_transcribe && $transcribe_enabled && !empty($transcribe_engine) && !empty($call_recordings)) {
		$btn_transcribe = button::create(['type'=>'button','label'=>$text['button-transcribe'],'icon'=>$settings->get('theme', 'button_icon_transcribe'),'id'=>'btn_transcribe','name'=>'btn_transcribe','style'=>'display: none;','collapse'=>'hide-xs','onclick'=>"list_action_set('transcribe'); list_form_submit('form_list');"]);
	}
	$btn_delete = '';
	if ($has_call_recording_delete && !empty($call_recordings)) {
		$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none; margin-right: 15px;','collapse'=>'hide-xs','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	$btn_show_all = '';
	if ($has_call_recording_all && $show != 'all') {
		$btn_show_all = button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$settings->get('theme', 'button_icon_all'),'link'=>$url->build_relative()]);
	}
	$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search','style'=>(!empty($search) ? 'display: none;' : null),'collapse'=>'hide-xs']);
	$btn_reset  = button::create(['label'=>$text['button-reset'],'icon'=>$settings->get('theme', 'button_icon_reset'),'type'=>'button','id'=>'btn_reset','link'=>'call_recordings.php','style'=>(empty($search) ? 'display: none;' : null),'collapse'=>'hide-xs']);

//build the modals
	$modal_delete = '';
	if ($has_call_recording_delete && !empty($call_recordings)) {
		$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

//build the table header columns
	$th_domain_name          = '';
	if ($show == "all" && $has_call_recording_all) {
		$th_domain_name = th_order_by('domain_name', $text['label-domain'], $order_by, $order, $param, "class='hide-sm-dn shrink'");
	}
	$th_caller_id_name       = th_order_by('caller_id_name', $text['label-caller_id_name'], $order_by, $order, null, "class='hide-sm-dn'");
	$th_caller_id_number     = th_order_by('caller_id_number', $text['label-caller_id_number'], $order_by, $order, null, "class='pct-15'");
	$th_caller_destination   = th_order_by('caller_destination', $text['label-caller_destination'], $order_by, $order, null, "class='pct-10 hide-sm-dn'");
	$th_destination_number   = th_order_by('destination_number', $text['label-destination_number'], $order_by, $order, null, "class='pct-10'");
	$th_call_recording_name  = th_order_by('call_recording_name', $text['label-call_recording_name'], $order_by, $order, null, "class='pct-20 hide-sm-dn'");
	$th_call_recording_length = th_order_by('call_recording_length', $text['label-call_recording_length'], $order_by, $order, null, "class='right hide-sm-dn shrink'");
	$th_call_recording_date  = th_order_by('call_recording_date', $text['label-call_recording_date'], $order_by, $order, null, "class='pct-20 center'");
	$th_call_direction       = th_order_by('call_direction', $text['label-call_direction'], $order_by, $order, null, "class='hide-sm-dn shrink'");

//compute column count for progress bar colspan
	$col_count = 8;	// caller_id_name caller_id_number caller_destination destination_number call_recording_name length date direction
	if ($show == "all" && $has_call_recording_all)                           { $col_count++; }
	if ($has_call_recording_delete)                                          { $col_count++; }
	if ($has_call_recording_play || $has_call_recording_download)            { $col_count++; }

//build the row data
	$x = 0;
	if (is_array($call_recordings) && @sizeof($call_recordings) != 0) {
		foreach ($call_recordings as &$row) {
			$_list_row_url = '';
			if ($has_call_recording_play) {
				$_list_row_url = "javascript:recording_play('".escape($row['call_recording_uuid'])."');";
			}
			$row['_list_row_url'] = $_list_row_url;
			$row['_caller_id_number_fmt']  = escape(format_phone(substr($row['caller_id_number'], 0, 20)));
			$row['_caller_destination_fmt'] = escape(format_phone(substr($row['caller_destination'], 0, 20)));
			$row['_destination_number_fmt'] = escape(format_phone(substr($row['destination_number'], 0, 20)));
			$row['_duration']              = escape(gmdate("G:i:s", $row['call_recording_length']));
			$row['_direction_label']       = ($row['call_direction'] != '' ? escape($text['label-'.$row['call_direction']] ?? $row['call_direction']) : '');
			$_tools_html = '';
			if ($has_call_recording_play || $has_call_recording_download) {
				if (file_exists($row['call_recording_path'].'/'.$row['call_recording_name'])) {
					if ($has_call_recording_play) {
						$_rec_ext  = pathinfo($row['call_recording_name'], PATHINFO_EXTENSION);
						$_rec_type = ($_rec_ext == 'mp3') ? 'audio/mpeg' : (($_rec_ext == 'ogg') ? 'audio/ogg' : 'audio/wav');
						$_tools_html .= "<audio id='recording_audio_".escape($row['call_recording_uuid'])."' style='display: none;' preload='none' ontimeupdate=\"update_progress('".escape($row['call_recording_uuid'])."')\" onended=\"recording_reset('".escape($row['call_recording_uuid'])."');\" src='download.php?id=".urlencode($row['call_recording_uuid'])."' type='".$_rec_type."'></audio>";
						$_tools_html .= button::create(['type'=>'button','title'=>$text['label-play'].' / '.$text['label-pause'],'icon'=>$settings->get('theme', 'button_icon_play'),'id'=>'recording_button_'.escape($row['call_recording_uuid']),'onclick'=>"recording_play('".escape($row['call_recording_uuid'])."')"]);
					}
					if ($has_call_recording_download) {
						$_tools_html .= button::create(['type'=>'button','title'=>$text['label-download'],'icon'=>$settings->get('theme', 'button_icon_download'),'link'=>'download.php?id='.urlencode($row['call_recording_uuid']).'&binary']);
					}
					if ($has_call_recording_transcribe && $transcribe_enabled && !empty($transcribe_engine) && !empty($row['call_recording_transcription'])) {
						$_tools_html .= button::create(['type'=>'button','title'=>$text['label-transcription'],'icon'=>$settings->get('theme', 'button_icon_transcribe'),'style'=>'','link'=>PROJECT_PATH.'/app/xml_cdr/xml_cdr_details.php?id='.urlencode($row['call_recording_uuid'])]);
					}
				}
			}
			$row['_tools_html'] = $_tools_html;
			$_progress_html = '';
			if ($has_call_recording_play) {
				$_progress_html .= "<tr class='list-row' id='recording_progress_bar_".escape($row['call_recording_uuid'])."' style='display: none;' onclick=\"recording_seek(event,'".escape($row['call_recording_uuid'])."')\"><td id='playback_progress_bar_background_".escape($row['call_recording_uuid'])."' class='playback_progress_bar_background' colspan='".$col_count."'><span class='playback_progress_bar' id='recording_progress_".escape($row['call_recording_uuid'])."'></span></td>".($has_xml_cdr_details ? "<td class='action-button' style='border-bottom: none !important;'></td>" : null)."</tr>\n";
				$_progress_html .= "<tr class='list-row' style='display: none;'><td></td></tr>\n";
			}
			$row['_progress_bar_html'] = $_progress_html;
			$row['_cdr_button'] = '';
			if ($has_xml_cdr_details) {
				$row['_cdr_button'] = button::create(['type'=>'button','title'=>$text['button-view'],'icon'=>$settings->get('theme', 'button_icon_view'),'link'=>PROJECT_PATH.'/app/xml_cdr/xml_cdr_details.php?id='.urlencode($row['call_recording_uuid'])]);
			}
			$x++;
		}
		unset($row);
	}

//build the template
	$template = new template();
	$template->engine       = 'smarty';
	$template->template_dir = __DIR__.'/resources/views';
	$template->cache_dir    = sys_get_temp_dir();
	$template->init();

//assign the template variables
	$template->assign('text',                       $text);
	$template->assign('num_rows',                   $num_rows);
	$template->assign('call_recordings',            $call_recordings ?? []);
	$template->assign('search',                     $search);
	$template->assign('show',                       $show);
	$template->assign('paging_controls',            $paging_controls);
	$template->assign('paging_controls_mini',       $paging_controls_mini);
	$template->assign('token',                      $token);
	$template->assign('has_call_recording_all',     $has_call_recording_all);
	$template->assign('has_call_recording_delete',  $has_call_recording_delete);
	$template->assign('has_call_recording_download',$has_call_recording_download);
	$template->assign('has_call_recording_play',    $has_call_recording_play);
	$template->assign('has_xml_cdr_details',        $has_xml_cdr_details);
	$template->assign('btn_download',               $btn_download);
	$template->assign('btn_transcribe',             $btn_transcribe);
	$template->assign('btn_delete',                 $btn_delete);
	$template->assign('btn_show_all',               $btn_show_all);
	$template->assign('btn_search',                 $btn_search);
	$template->assign('btn_reset',                  $btn_reset);
	$template->assign('modal_delete',               $modal_delete);
	$template->assign('th_domain_name',             $th_domain_name);
	$template->assign('th_caller_id_name',          $th_caller_id_name);
	$template->assign('th_caller_id_number',        $th_caller_id_number);
	$template->assign('th_caller_destination',      $th_caller_destination);
	$template->assign('th_destination_number',      $th_destination_number);
	$template->assign('th_call_recording_name',     $th_call_recording_name);
	$template->assign('th_call_recording_length',   $th_call_recording_length);
	$template->assign('th_call_recording_date',     $th_call_recording_date);
	$template->assign('th_call_direction',          $th_call_direction);

//invoke pre-render hook
	app::dispatch_list_pre_render('call_recording_list_page_hook', $url, $template);

//include the header
	$document['title'] = $text['title-call_recordings'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('call_recordings_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('call_recording_list_page_hook', $url, $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";
