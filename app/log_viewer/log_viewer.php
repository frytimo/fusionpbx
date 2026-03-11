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
	James Rose <james.o.rose@gmail.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('log_view')) {
		echo "access denied";
		exit;
	}
	$has_log_download = permission_exists('log_download');
	$has_log_view     = permission_exists('log_view');

//add multi-lingual support
	$text = new text()->get();

//set a default line number value (off)
	if (!isset($_POST['line_number']) || $_POST['line_number'] == '') {
		$_POST['line_number'] = 0;
	}

//set a default ordinal (descending)
	if (!isset($_POST['sort']) || $_POST['sort'] == '') {
		$_POST['sort'] = "asc";
	}

//set a default file size
	if (!isset($_POST['size']) || empty($_POST['size'])) {
		$_POST['size'] = "512";
	}

//set a default filter
	if (!isset($_POST['filter'])) {
		$_POST['filter'] = '';
	}

//set default default log file
	if (isset($_POST['log_file'])) {
		$approved_files = glob($settings->get('switch', 'log').'/freeswitch.log*');
		if (is_array($approved_files)) {
			foreach($approved_files as $approved_file) {
				if ($approved_file == $settings->get('switch', 'log').'/'.$_POST['log_file']) {
					$log_file = $approved_file;
				}
			}
		}
	}
	else {
		$log_file = $settings->get('switch', 'log').'/freeswitch.log';
	}

//download the log
	if ($has_log_download) {
		if (isset($_GET['n'])) {
			if (isset($filename)) { unset($filename); }
			$approved_files = glob($settings->get('switch', 'log').'/freeswitch.log*');
			if (is_array($approved_files)) {
				foreach($approved_files as $approved_file) {
					if ($approved_file == $settings->get('switch', 'log').'/'.$_GET['n']) {
						$filename = $approved_file;
					}
				}
			}
			if (isset($filename) && file_exists($filename)) {
				@session_cache_limiter('public');
				$fd = fopen($filename, "rb");
				header("Content-Type: binary/octet-stream");
				header("Content-Length: " . filesize($filename));
				header('Content-Disposition: attachment; filename="'.basename($filename).'"');
				fpassthru($fd);
				exit;
			}
		}
	}

//get the file size
	if (file_exists($log_file)) {
		$file_size = filesize($log_file);
	}

//open the log file
	if (file_exists($log_file)) {
		$file = fopen($log_file, "r") or exit($text['error-open_file']);
	}

//build the log html
	$log_html = '';
	if ($has_log_view) {
		$MAXEL = 3;
		$default_color = '#fff';
		$default_type = 'normal';
		$default_font = 'monospace';
		$default_file_size = '512000';

		$array_filter[0]['pattern'] = '[NOTICE]';
		$array_filter[0]['color'] = 'cyan';
		$array_filter[0]['type'] = 'normal';
		$array_filter[0]['font'] = 'monospace';
		$array_filter[1]['pattern'] = '[INFO]';
		$array_filter[1]['color'] = 'chartreuse';
		$array_filter[1]['type'] = 'normal';
		$array_filter[1]['font'] = 'monospace';
		$array_filter[2]['pattern'] = 'Dialplan:';
		$array_filter[2]['color'] = 'burlywood';
		$array_filter[2]['type'] = 'normal';
		$array_filter[2]['font'] = 'monospace';
		$array_filter[2]['pattern2'] = 'Regex (PASS)';
		$array_filter[2]['color2'] = 'chartreuse';
		$array_filter[2]['pattern3'] = 'Regex (FAIL)';
		$array_filter[2]['color3'] = 'red';
		$array_filter[3]['pattern'] = '[WARNING]';
		$array_filter[3]['color'] = 'fuchsia';
		$array_filter[3]['type'] = 'normal';
		$array_filter[3]['font'] = 'monospace';
		$array_filter[4]['pattern'] = '[ERR]';
		$array_filter[4]['color'] = 'red';
		$array_filter[4]['type'] = 'bold';
		$array_filter[4]['font'] = 'monospace';
		$array_filter[5]['pattern'] = '[DEBUG]';
		$array_filter[5]['color'] = 'gold';
		$array_filter[5]['type'] = 'bold';
		$array_filter[5]['font'] = 'monospace';
		$array_filter[6]['pattern'] = '[CRIT]';
		$array_filter[6]['color'] = 'red';
		$array_filter[6]['type'] = 'bold';
		$array_filter[6]['font'] = 'monospace';

		$file_size = 0;
		if (file_exists($log_file)) {
			$file_size = filesize($log_file);
		}

		$user_file_size = '32768';
		if ($_POST['size'] === 'max') {
			$_POST['size'] = $file_size;
		}
		if (!is_numeric($_POST['size'])) {
			$user_file_size = 512 * 1024;
		} else {
			$user_file_size = $_POST['size'] * 1024;
		}
		if (!empty($_REQUEST['filter'])) {
			$filter = $_REQUEST['filter'];
		}

		$log_display_info = "<div style='padding-bottom: 10px; text-align: right; color: #fff; margin-bottom: 15px; border-bottom: 1px solid #fff;'>";
		$log_display_info .= "	".$text['label-displaying']." ".number_format($user_file_size,0,'.',',')." of ".number_format($file_size,0,'.',',')." ".$text['label-bytes'].".";
		$log_display_info .= "</div>";

		if (!empty($file)) {
			if ($user_file_size >= '0') {
				if ($user_file_size == '0') {
					$user_file_size = $default_file_size;
				}
				if ($file_size >= $user_file_size) {
					$byte_count = $file_size - $user_file_size;
					fseek($file, $byte_count);
				} else {
					if ($file_size >= $default_file_size) {
						$byte_count = $file_size - $default_file_size;
						fseek($file, $byte_count);
						$log_display_info .= $text['label-open_at']." " . $byte_count . " ".$text['label-bytes']."<br>";
					} else {
						$byte_count = '0';
						fseek($file, 0);
						$log_display_info .= "<br>".$text['label-open_file']."<br>";
					}
				}
			} else {
				if ($file_size >= $default_file_size) {
					$byte_count = $file_size - $default_file_size;
					fseek($file, $byte_count);
					$log_display_info .= $text['label-open_at']." " . $byte_count . " ".$text['label-bytes']."<br>";
				} else {
					$byte_count = '0';
					fseek($file, 0);
					$log_display_info .= "<br>".$text['label-open_file']."<br>";
				}
			}

			$byte_count = 0;
			while (!feof($file)) {
				$log_line = escape(fgets($file));
				$byte_count++;
				$noprint = false;
				$skip_line = false;
				if (!empty($filter)) {
					$skip_line = (strpos($log_line, $filter) === false);
				}
				if ($skip_line === false) {
					foreach ($array_filter as $v1) {
						$pos = strpos($log_line, escape($v1['pattern']));
						if ($pos !== false) {
							for ($i = 2; $i <= $MAXEL; $i++) {
								if (isset($v1["pattern".$i])) {
									$log_line = str_replace(escape($v1["pattern".$i]), "<span style='color: ".$v1["color".$i].";'>".$v1["pattern".$i]."</span>", $log_line);
								}
							}
							$array_output[] = "<span style='color: ".$v1['color']."; font-family: ".$v1['font'].";'>".$log_line."</span><br>";
							$noprint = true;
						}
					}
					if ($noprint !== true) {
						$array_output[] = "<span style='color: ".$default_color."; font-family: ".$default_font.";'>".$log_line."</span><br>";
					}
				}
			}
		}

		if ($_POST['sort'] == 'desc') {
			$array_output = array_reverse($array_output ?? []);
			$adj_index = 0;
		} else {
			$adj_index = 1;
		}

		$log_lines_html = '';
		if (!empty($array_output) && is_array($array_output)) {
			foreach ($array_output as $index => $line) {
				$line_num = '';
				if ($line != "<span style='color: #fff; font-family: monospace;'></span><br>") {
					if ($_POST['line_number']) {
						$line_num = "<span style='font-family: courier; color: #aaa; font-size: 10px;'>".($index + $adj_index)."&nbsp;&nbsp;&nbsp;</span>";
					}
					$log_lines_html .= $line_num." ".$line;
				}
			}
		}
		$log_html = $log_display_info . $log_lines_html;
		unset($array_output, $array_filter);
	}

//close the file
	if (!empty($file)) {
		fclose($file);
	}

//build the file options html
	$file_options_html = '';
	$files = glob($settings->get('switch', 'log').'/freeswitch.log*');
	if (is_array($files)) {
		foreach ($files as $file_name) {
			$selected = ($file_name == $log_file) ? "selected='selected'" : "";
			$file_options_html .= "<option value='".htmlspecialchars(basename($file_name), ENT_QUOTES)."' ".$selected.">".htmlspecialchars(basename($file_name), ENT_QUOTES)."</option>";
		}
	}

//build the action bar buttons
	$btn_update = button::create(['type'=>'submit','label'=>$text['button-update'],'icon'=>$settings->get('theme', 'button_icon_save'),'style'=>'margin-left: 15px;','name'=>'submit']);
	$btn_download = '';
	if ($has_log_download) {
		$btn_download = button::create(['type'=>'button','label'=>$text['button-download'],'icon'=>$settings->get('theme', 'button_icon_download'),'style'=>'margin-left: 15px;','link'=>'log_viewer.php?a=download&n='.basename($log_file)]);
	}

//build the template
	$template = new template();
	$template->engine = 'smarty';
	$template->template_dir = __DIR__.'/resources/views';
	$template->cache_dir = sys_get_temp_dir();
	$template->init();

//assign the template variables
	$template->assign('text',               $text);
	$template->assign('log_html',           $log_html);
	$template->assign('file_options_html',  $file_options_html);
	$template->assign('btn_update',         $btn_update);
	$template->assign('btn_download',       $btn_download);
	$template->assign('filter',             $_POST['filter'] ?? '');
	$template->assign('line_number',        $_POST['line_number'] ?? 0);
	$template->assign('sort',               $_POST['sort'] ?? 'asc');
	$template->assign('size',               $_POST['size'] ?? '512');
	$template->assign('log_file',           basename($log_file));
	$template->assign('has_log_view',       $has_log_view);

//invoke pre-render hook
	app::dispatch_list_pre_render('log_list_page_hook', 'log_viewer.php', $template);

//include the header
	$document['title'] = $text['title-log_viewer'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('log_viewer_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('log_list_page_hook', 'log_viewer.php', $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";

