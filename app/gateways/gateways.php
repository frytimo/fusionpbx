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

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (!permission_exists('gateway_view')) {
		echo "access denied";
		exit;
	}
	$has_domain_select  = permission_exists('domain_select');
	$has_gateway_add    = permission_exists('gateway_add');
	$has_gateway_all    = permission_exists('gateway_all');
	$has_gateway_delete = permission_exists('gateway_delete');
	$has_gateway_domain = permission_exists('gateway_domain');
	$has_gateway_edit   = permission_exists('gateway_edit');

//add multi-lingual support
	$text = new text()->get();
	//create the url object
	$url = new url();

//get posted data
	if (!empty($_POST['gateways'])) {
		$action = $_POST['action'] ?? '';
		$search = $_POST['search'] ?? '';
		$gateways = $_POST['gateways'] ?? '';
	}

//get total gateway count from the database, check limit, if defined
	if (!empty($action) && $action == 'copy' && !empty($settings->get('limit', 'gateways'))) {
		$sql = "select count(gateway_uuid) from v_gateways ";
		$sql .= "where (domain_uuid = :domain_uuid ".($has_gateway_domain ? " or domain_uuid is null " : null).") ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$total_gateways = $database->select($sql, $parameters, 'column');
		unset($sql, $parameters);
		if ($total_gateways >= $settings->get('limit', 'gateways')) {
			message::add($text['message-maximum_gateways'].' '.$settings->get('limit', 'gateways'), 'negative');
			header('Location: gateways.php');
			exit;
		}
	}

//process the http post data by action
	if (!empty($action) && !empty($gateways)) {
		//dispatch pre-action hook
		app::dispatch_list_pre_action('gateway_list_page_hook', $url, $action, $gateways);

		switch ($action) {
			case 'copy':
				if ($has_gateway_add) {
					$obj = new gateways;
					$obj->copy($gateways);
				}
				break;
			case 'toggle':
				if ($has_gateway_edit) {
					$obj = new gateways;
					$obj->toggle($gateways);
				}
				break;
			case 'delete':
				if ($has_gateway_delete) {
					$obj = new gateways;
					$obj->delete($gateways);
				}
			case 'start':
				$esl = event_socket::create();
				if ($esl && $has_gateway_edit) {
					$obj = new gateways;
					$obj->start($gateways);
				}
				break;
			case 'stop':
				$esl = event_socket::create();
				if ($esl && $has_gateway_edit) {
					$obj = new gateways;
					$obj->stop($gateways);
				}
				break;
		}

			//dispatch post-action hook
			app::dispatch_list_post_action('gateway_list_page_hook', $url, $action, $gateways);

		header('Location: gateways.php'.($search != '' ? '?search='.urlencode($search) : ''));
		exit;
	}

//dispatch pre-query hook
	$query_parameters = [];
	app::dispatch_list_pre_query('gateway_list_page_hook', $url, $query_parameters);

//connect to event socket
	$esl = event_socket::create();

//gateway status function
	if (!function_exists('switch_gateway_status')) {
		/**
		 * Switches the status of a gateway.
		 *
		 * This function sends an API request to retrieve the status of a gateway.
		 * If the first request fails, it attempts to send the same request with the
		 * gateway UUID in uppercase.
		 *
		 * @param string $gateway_uuid The unique identifier of the gateway.
		 * @param string $result_type  The type of response expected (default: 'xml').
		 *
		 * @return string The status of the gateway, or an error message if the request fails.
		 */
		function switch_gateway_status($gateway_uuid, $result_type = 'xml') {
			global $esl;
			if ($esl->is_connected()) {
				$esl = event_socket::create();
				$cmd = 'sofia xmlstatus gateway '.$gateway_uuid;
				$response = trim(event_socket::api($cmd));
				if ($response == "Invalid Gateway!") {
					$cmd = 'sofia xmlstatus gateway '.strtoupper($gateway_uuid);
					$response = trim(event_socket::api($cmd));
				}
				return $response;
			}
		}
	}

//get order and order by
	$order_by = $_GET["order_by"] ?? '';
	$order = $_GET["order"] ?? '';

//set additional variables
	$search = !empty($_GET["search"]) ? $_GET["search"] : '';
	$show = !empty($_GET["show"]) ? $_GET["show"] : '';

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);

//get total gateway count from the database
	$sql = "select count(*) from v_gateways where true ";
	if (!($show == "all" && $has_gateway_all)) {
		$sql .= "and (domain_uuid = :domain_uuid ".($has_gateway_domain ? " or domain_uuid is null " : null).") ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	if (!empty($search)) {
		$search = strtolower($_GET["search"]);
		$sql .= "and (";
		$sql .= "lower(gateway) like :search ";
		$sql .= "or lower(username) like :search ";
		$sql .= "or lower(auth_username) like :search ";
		$sql .= "or lower(from_user) like :search ";
		$sql .= "or lower(from_domain) like :search ";
		$sql .= "or lower(proxy) like :search ";
		$sql .= "or lower(register_proxy) like :search ";
		$sql .= "or lower(outbound_proxy) like :search ";
		$sql .= "or lower(description) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$total_gateways = $database->select($sql, $parameters ?? [], 'column');
	$num_rows = $total_gateways;

//prepare to page the results
	$rows_per_page = $settings->get('domain', 'paging', 50);
	$param = "&search=".$search;
	$param .= $order_by ? "&order_by=".$order_by."&order=".$order : null;
	$page = !empty($_GET['page']) ? $_GET['page'] : 0;
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the list
	$sql = "select ";
	$sql .= "gateway_uuid, domain_uuid, gateway, username, password, ";
	$sql .= "cast(distinct_to as text), auth_username, realm, from_user, from_domain, ";
	$sql .= "proxy, register_proxy,outbound_proxy,expire_seconds, ";
	$sql .= "cast(register as text), register_transport, contact_params, retry_seconds, ";
	$sql .= "extension, ping, ping_min, ping_max, ";
	$sql .= "cast(contact_in_ping as text) , ";
	$sql .= "cast(caller_id_in_from as text), ";
	$sql .= "cast(supress_cng as text), ";
	$sql .= "sip_cid_type, codec_prefs, channels, ";
	$sql .= "cast(extension_in_contact as text), ";
	$sql .= "context, profile, hostname, ";
	$sql .= "cast(enabled as text), ";
	$sql .= "description ";
	$sql .= "from v_gateways ";
	$sql .= "where true ";
	if (!($show == "all" && $has_gateway_all)) {
		$sql .= "and (domain_uuid = :domain_uuid ".($has_gateway_domain ? " or domain_uuid is null " : null).") ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	if (!empty($search)) {
		$search = strtolower($_GET["search"]);
		$sql .= "and (";
		$sql .= "lower(gateway) like :search ";
		$sql .= "or lower(username) like :search ";
		$sql .= "or lower(auth_username) like :search ";
		$sql .= "or lower(from_user) like :search ";
		$sql .= "or lower(from_domain) like :search ";
		$sql .= "or lower(proxy) like :search ";
		$sql .= "or lower(register_proxy) like :search ";
		$sql .= "or lower(outbound_proxy) like :search ";
		$sql .= "or lower(description) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$sql .= order_by($order_by, $order, 'gateway', 'asc');
	$sql .= limit_offset($rows_per_page, $offset);
	$gateways = $database->select($sql, $parameters ?? [], 'all');
	//dispatch post-query hook
	app::dispatch_list_post_query('gateway_list_page_hook', $url, $gateways);
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//build the action bar buttons
$btn_stop = '';
$btn_start = '';
if ($has_gateway_edit && $gateways) {
$btn_stop = button::create(['type'=>'button','label'=>$text['button-stop'],'icon'=>$settings->get('theme', 'button_icon_stop'),'onclick'=>"modal_open('modal-stop','btn_stop');"]);
$btn_start = button::create(['type'=>'button','label'=>$text['button-start'],'icon'=>$settings->get('theme', 'button_icon_start'),'onclick'=>"modal_open('modal-start','btn_start');"]);
}
$btn_refresh = button::create(['type'=>'button','label'=>$text['button-refresh'],'icon'=>$settings->get('theme', 'button_icon_refresh'),'style'=>'margin-right: 15px;','link'=>'gateways.php']);
$btn_add = '';
if ($has_gateway_add) {
$btn_add = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','link'=>'gateway_edit.php']);
}
$btn_copy = '';
if ($has_gateway_add && $gateways) {
$btn_copy = button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$settings->get('theme', 'button_icon_copy'),'id'=>'btn_copy','name'=>'btn_copy','style'=>'display: none;','onclick'=>"modal_open('modal-copy','btn_copy');"]);
}
$btn_toggle = '';
if ($has_gateway_edit && $gateways) {
$btn_toggle = button::create(['type'=>'button','label'=>$text['button-toggle'],'icon'=>$settings->get('theme', 'button_icon_toggle'),'id'=>'btn_toggle','name'=>'btn_toggle','style'=>'display: none;','onclick'=>"modal_open('modal-toggle','btn_toggle');"]);
}
$btn_delete = '';
if ($has_gateway_delete && $gateways) {
$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
}
$btn_show_all = '';
if ($has_gateway_all && $show !== 'all') {
$btn_show_all = button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$settings->get('theme', 'button_icon_all'),'link'=>'?show=all']);
}
$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);

//build the modals
$modal_stop = '';
$modal_start = '';
if ($has_gateway_edit && $gateways) {
$modal_stop = modal::create(['id'=>'modal-stop','type'=>'general','message'=>$text['confirm-stop_gateways'],'actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_stop','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('stop'); list_form_submit('form_list');"])]);
$modal_start = modal::create(['id'=>'modal-start','type'=>'general','message'=>$text['confirm-start_gateways'],'actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_start','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('start'); list_form_submit('form_list');"])]);
}
$modal_copy = '';
if ($has_gateway_add && $gateways) {
$modal_copy = modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
}
$modal_toggle = '';
if ($has_gateway_edit && $gateways) {
$modal_toggle = modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
}
$modal_delete = '';
if ($has_gateway_delete && $gateways) {
$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
}

//build the table header columns
$th_domain_name = '';
if ($show == 'all' && $has_gateway_all) {
$th_domain_name = th_order_by('domain_name', $text['label-domain'], $order_by, $order, $param);
}
$th_gateway     = th_order_by('gateway', $text['label-gateway'], $order_by, $order);
$th_context     = th_order_by('context', $text['label-context'], $order_by, $order);
$th_register    = th_order_by('register', $text['label-register'], $order_by, $order);
$th_hostname    = th_order_by('hostname', $text['label-hostname'], $order_by, $order, null, "class='hide-sm-dn'");
$th_enabled     = th_order_by('enabled', $text['label-enabled'], $order_by, $order, null, "class='center'");
$th_description = th_order_by('description', $text['label-description'], $order_by, $order, null, "class='hide-sm-dn'");
$th_esl_status  = '';
$th_esl_action  = '';
$th_esl_state   = '';
if ($esl->is_connected()) {
$th_esl_status = "<th class='hide-sm-dn'>".$text['label-status']."</th>\n";
if ($has_gateway_edit) {
$th_esl_action = "<th class='center'>".$text['label-action']."</th>\n";
}
$th_esl_state = "<th>".$text['label-state']."</th>\n";
}

//build the row data
$x = 0;
foreach ($gateways as &$row) {
app::dispatch_list_render_row('gateway_list_page_hook', $url, $row, $x);
$list_row_url = '';
if ($has_gateway_edit) {
$list_row_url = "gateway_edit.php?id=".urlencode($row['gateway_uuid']);
if (!empty($row['domain_uuid']) && $row['domain_uuid'] != $_SESSION['domain_uuid'] && $has_domain_select) {
$list_row_url .= '&domain_uuid='.urlencode($row['domain_uuid']).'&domain_change=true';
}
}
$row['_list_row_url']     = $list_row_url;
$row['_domain_name']      = is_uuid($row['domain_uuid']) ? ($_SESSION['domains'][$row['domain_uuid']]['domain_name'] ?? '') : $text['label-global'];
$row['_register_display'] = ucwords(escape($row['register']));
$row['_enabled_label']    = $text['label-'.$row['enabled']];
$esl_cells = '';
if ($esl->is_connected()) {
if ($row['enabled'] == 'true') {
$response = switch_gateway_status($row['gateway_uuid']);
if ($response == 'Invalid Gateway!') {
$esl_cells .= "<td class='hide-sm-dn'>".$text['label-status-stopped']."</td>\n";
if ($has_gateway_edit) {
$esl_cells .= "<td class='no-link center'>";
$esl_cells .= button::create(['type'=>'button','class'=>'link','label'=>$text['label-action-start'],'title'=>$text['button-start'],'id'=>'btn_toggle_start','name'=>'btn_toggle_start','onclick'=>"list_self_check('checkbox_{$x}'); modal_open('modal-toggle_start','btn_toggle_start');"]);
$esl_cells .= modal::create(['id'=>'modal-toggle_start','type'=>'start','message'=>$text['confirm-start_gateway'],'actions'=>button::create(['type'=>'button','label'=>$text['button-start'],'icon'=>'check','id'=>'btn_toggle_start','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('start'); list_form_submit('form_list');"])]);
$esl_cells .= "</td>\n";
}
$esl_cells .= "<td>&nbsp;</td>\n";
} else {
try {
$xml = new SimpleXMLElement($response);
$state = $xml->state;
$esl_cells .= "<td class='hide-sm-dn'>".$text['label-status-running']."</td>\n";
if ($has_gateway_edit) {
$esl_cells .= "<td class='no-link center'>";
$esl_cells .= button::create(['type'=>'button','class'=>'link','label'=>$text['label-action-stop'],'title'=>$text['button-stop'],'id'=>'btn_toggle_stop','name'=>'btn_toggle_stop','onclick'=>"list_self_check('checkbox_{$x}'); modal_open('modal-toggle_stop','btn_toggle_stop');"]);
$esl_cells .= modal::create(['id'=>'modal-toggle_stop','type'=>'general','message'=>$text['confirm-stop_gateway'],'actions'=>button::create(['type'=>'button','label'=>$text['button-stop'],'icon'=>'check','id'=>'btn_toggle_stop','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('stop'); list_form_submit('form_list');"])]);
$esl_cells .= "</td>\n";
}
$esl_cells .= "<td>".escape($state)."</td>\n";
} catch (Exception $e) {
//ignore
}
}
} else {
$esl_cells .= "<td class='hide-sm-dn'>&nbsp;</td>\n";
if ($has_gateway_edit) {
$esl_cells .= "<td>&nbsp;</td>\n";
}
$esl_cells .= "<td>&nbsp;</td>\n";
}
}
$row['_esl_cells']    = $esl_cells;
$row['_toggle_button'] = '';
if ($has_gateway_edit) {
$row['_toggle_button'] = button::create(['type'=>'button','class'=>'link','label'=>$text['label-'.$row['enabled']],'title'=>$text['button-toggle'],'id'=>'btn_toggle_enabled','name'=>'btn_toggle_enabled','onclick'=>"list_self_check('checkbox_{$x}'); modal_open('modal-toggle_enabled','btn_toggle_enabled');"]);
$row['_toggle_button'] .= modal::create(['id'=>'modal-toggle_enabled','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle_enabled','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
}
$row['_edit_button'] = '';
if ($has_gateway_edit && $list_row_edit_button) {
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
$template->assign('text',                 $text);
$template->assign('num_rows',             $num_rows);
$template->assign('gateways',             $gateways ?? []);
$template->assign('search',               $search);
$template->assign('show',                 $show);
$template->assign('paging_controls',      $paging_controls);
$template->assign('paging_controls_mini', $paging_controls_mini);
$template->assign('token',                $token);
$template->assign('has_gateway_add',      $has_gateway_add);
$template->assign('has_gateway_all',      $has_gateway_all);
$template->assign('has_gateway_delete',   $has_gateway_delete);
$template->assign('has_gateway_edit',     $has_gateway_edit);
$template->assign('list_row_edit_button', $list_row_edit_button);
$template->assign('btn_stop',             $btn_stop);
$template->assign('btn_start',            $btn_start);
$template->assign('btn_refresh',          $btn_refresh);
$template->assign('btn_add',              $btn_add);
$template->assign('btn_copy',             $btn_copy);
$template->assign('btn_toggle',           $btn_toggle);
$template->assign('btn_delete',           $btn_delete);
$template->assign('btn_show_all',         $btn_show_all);
$template->assign('btn_search',           $btn_search);
$template->assign('modal_stop',           $modal_stop);
$template->assign('modal_start',          $modal_start);
$template->assign('modal_copy',           $modal_copy);
$template->assign('modal_toggle',         $modal_toggle);
$template->assign('modal_delete',         $modal_delete);
$template->assign('th_domain_name',       $th_domain_name);
$template->assign('th_gateway',           $th_gateway);
$template->assign('th_context',           $th_context);
$template->assign('th_register',          $th_register);
$template->assign('th_hostname',          $th_hostname);
$template->assign('th_enabled',           $th_enabled);
$template->assign('th_description',       $th_description);
$template->assign('th_esl_status',        $th_esl_status);
$template->assign('th_esl_action',        $th_esl_action);
$template->assign('th_esl_state',         $th_esl_state);

//invoke pre-render hook
app::dispatch_list_pre_render('gateway_list_page_hook', $url, $template);

//include the header
$document['title'] = $text['title-gateways'];
require_once "resources/header.php";

//render the template
$html = $template->render('gateways_list.tpl');

//invoke post-render hook
app::dispatch_list_post_render('gateway_list_page_hook', $url, $html);
echo $html;

//include the footer
require_once "resources/footer.php";
