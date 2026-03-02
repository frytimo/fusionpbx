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
	Portions created by the Initial Developer are Copyright (C) 2018-2024
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (!permission_exists('bridge_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//create a new instance of the url with paging class
	$url_paging = new url_paging($settings);

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);

//get request data from url object
	$show = $url_paging->get('show', '');
	$order = $url_paging->get('order', '');
	$action = $url_paging->get('action', '');
	$search = $url_paging->get('search', '');
	$order_by = $url_paging->get('order_by', '');

//get bridges from the url for post processing
	$bridges = $url_paging->get('bridges', []);

//invoke pre-action hook
	if (!empty($action) && !empty($bridges)) {
		app::dispatch_list_pre_action('bridge_list_page_hook', $url_paging, $action, $bridges);
	}

//process the http post data by action
	if (!empty($action) && !empty($bridges)) {
		switch ($action) {
			case 'copy':
				if (permission_exists('bridge_add')) {
					$obj = new bridges;
					$obj->copy($bridges);
				}
				break;
			case 'toggle':
				if (permission_exists('bridge_edit')) {
					$obj = new bridges;
					$obj->toggle($bridges);
				}
				break;
			case 'delete':
				if (permission_exists('bridge_delete')) {
					$obj = new bridges;
					$obj->delete($bridges);
				}
				break;
		}

		//invoke post-action hook
		app::dispatch_list_post_action('bridge_list_page_hook', $url_paging, $action, $bridges);

		header('Location: bridges.php'.(!empty($search) ? '?search='.urlencode($search) : ''));
		exit;
	}

//invoke pre-query hook
	$query_parameters = [];
	app::dispatch_list_pre_query('bridge_list_page_hook', $url_paging, $query_parameters);

//get the count
	$num_rows = bridges::count($url_paging);
	$url_paging->set_total_rows($num_rows);

//prepare to page the results
	$paging_controls = url_paging::html_paging_controls($url_paging);
	$paging_controls_mini = url_paging::html_paging_mini_controls($url_paging);

//get the list
	$bridges = bridges::fetch($url_paging);

//invoke post-fetch hook
	app::dispatch_list_post_query('bridge_list_page_hook', $url_paging, $bridges);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-bridges'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['title-bridges']."</b><div class='count'>".number_format($num_rows)."</div></div>\n";
	echo "	<div class='actions'>\n";
	if (permission_exists('bridge_import')) {
		echo button::create(['type'=>'button','label'=>$text['button-import'],'icon'=>$settings->get('theme', 'button_icon_import'),'style'=>'margin-right: 15px;','link'=>'bridge_imports.php']);
	}
	if (permission_exists('bridge_add')) {
		echo button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','link'=>'bridge_edit.php']);
	}
	if (permission_exists('bridge_add') && $bridges) {
		echo button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$settings->get('theme', 'button_icon_copy'),'id'=>'btn_copy','name'=>'btn_copy','style'=>'display: none;','onclick'=>"modal_open('modal-copy','btn_copy');"]);
	}
	if (permission_exists('bridge_edit') && $bridges) {
		echo button::create(['type'=>'button','label'=>$text['button-toggle'],'icon'=>$settings->get('theme', 'button_icon_toggle'),'id'=>'btn_toggle','name'=>'btn_toggle','style'=>'display: none;','onclick'=>"modal_open('modal-toggle','btn_toggle');"]);
	}
	if (permission_exists('bridge_delete') && $bridges) {
		echo button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	echo 		"<form id='form_search' class='inline' method='get'>\n";
	if (permission_exists('bridge_all')) {
		if (isset($show) && $show == 'all') {
			echo "		<input type='hidden' name='show' value='all'>\n";
		}
		else {
			echo button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$settings->get('theme', 'button_icon_all'),'link'=>'?show=all']);
		}
	}
	echo 		"<input type='text' class='txt list-search' name='search' id='search' value=\"".escape($search)."\" placeholder=\"".$text['label-search']."\" onkeydown=''>";
	echo button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);
	//echo button::create(['label'=>$text['button-reset'],'icon'=>$settings->get('theme', 'button_icon_reset'),'type'=>'button','id'=>'btn_reset','link'=>'bridges.php','style'=>($search == '' ? 'display: none;' : null)]);
	if ($paging_controls_mini != '') {
		echo 	"<span style='margin-left: 15px;'>".$paging_controls_mini."</span>\n";
	}
	echo "		</form>\n";
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	if (permission_exists('bridge_add') && $bridges) {
		echo modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
	}
	if (permission_exists('bridge_edit') && $bridges) {
		echo modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
	}
	if (permission_exists('bridge_delete') && $bridges) {
		echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

	echo $text['title_description-bridge']."\n";
	echo "<br /><br />\n";

	echo "<div class='card'>\n";
	echo "<form id='form_list' method='post'>\n";
	echo "<input type='hidden' id='action' name='action' value=''>\n";
	echo "<input type='hidden' name='search' value=\"".escape($search)."\">\n";

	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	if (permission_exists('bridge_add') || permission_exists('bridge_edit') || permission_exists('bridge_delete')) {
		echo "	<th class='checkbox'>\n";
		echo "		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);' ".(empty($bridges) ? "style='visibility: hidden;'" : null).">\n";
		echo "	</th>\n";
	}
	if (!empty($show) && $show == 'all' && permission_exists('bridge_all')) {
		echo th_order_by('domain_name', $text['label-domain'], $order_by, $order);
	}
	echo th_order_by('bridge_name', $text['label-bridge_name'], $order_by, $order);
	echo th_order_by('bridge_destination', $text['label-bridge_destination'], $order_by, $order);
	echo th_order_by('bridge_enabled', $text['label-bridge_enabled'], $order_by, $order, null, "class='center'");
	echo "	<th class='hide-sm-dn'>".$text['label-bridge_description']."</th>\n";
	if (permission_exists('bridge_edit') && $list_row_edit_button) {
		echo "	<td class='action-button'>&nbsp;</td>\n";
	}
	echo "</tr>\n";

	if (!empty($bridges)) {
		$x = 0;
		foreach ($bridges as $row) {
			//invoke row-render hook
			app::dispatch_list_render_row('bridge_list_page_hook', $url_paging, $row, $x);

			$list_row_url = '';
			if (permission_exists('bridge_edit')) {
				$list_row_url = "bridge_edit.php?id=".urlencode($row['bridge_uuid']);
				if ($row['domain_uuid'] != $_SESSION['domain_uuid'] && permission_exists('domain_select')) {
					$list_row_url .= '&domain_uuid='.urlencode($row['domain_uuid']).'&domain_change=true';
				}
			}
			echo "<tr class='list-row' href='".$list_row_url."'>\n";
			if (permission_exists('bridge_add') || permission_exists('bridge_edit') || permission_exists('bridge_delete')) {
				echo "	<td class='checkbox'>\n";
				echo "		<input type='checkbox' name='bridges[$x][checked]' id='checkbox_".$x."' value='true' onclick=\"checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }\">\n";
				echo "		<input type='hidden' name='bridges[$x][uuid]' value='".escape($row['bridge_uuid'])."' />\n";
				echo "	</td>\n";
			}
			if (!empty($_GET['show']) && $_GET['show'] == 'all' && permission_exists('bridge_all')) {
				echo "	<td>".escape($row['domain_name'])."</td>\n";
			}
			echo "	<td>\n";
			if (permission_exists('bridge_edit')) {
				echo "	<a href='".$list_row_url."' title=\"".$text['button-edit']."\">".escape($row['bridge_name'])."</a>\n";
			}
			else {
				echo "	".escape($row['bridge_name']);
			}
			echo "	</td>\n";
			echo "	<td>".escape($row['bridge_destination'])."</td>\n";
			if (permission_exists('bridge_edit')) {
				echo "	<td class='no-link center'>\n";
				echo button::create(['type'=>'submit','class'=>'link','label'=>$text['label-'.$row['bridge_enabled']],'title'=>$text['button-toggle'],'onclick'=>"list_self_check('checkbox_".$x."'); list_action_set('toggle'); list_form_submit('form_list')"]);
			}
			else {
				echo "	<td class='center'>\n";
				echo $text['label-'.$row['bridge_enabled']];
			}
			echo "	</td>\n";
			echo "	<td class='description overflow hide-sm-dn'>".escape($row['bridge_description'])."</td>\n";
			if (permission_exists('bridge_edit') && $list_row_edit_button) {
				echo "	<td class='action-button'>\n";
				echo button::create(['type'=>'button','title'=>$text['button-edit'],'icon'=>$settings->get('theme', 'button_icon_edit'),'link'=>$list_row_url]);
				echo "	</td>\n";
			}
			echo "</tr>\n";
			$x++;
		}
		unset($bridges);
	}

	echo "</table>\n";
	echo "<br />\n";
	echo "<div align='center'>".$paging_controls."</div>\n";
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";
	echo "</form>\n";
	echo "</div>\n";

//include the footer
	require_once "resources/footer.php";
