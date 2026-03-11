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
	Portions created by the Initial Developer are Copyright (C) 2008-2026
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (!permission_exists('destination_view')) {
		echo "access denied";
		exit;
	}
	$has_destination_add             = permission_exists('destination_add');
	$has_destination_all             = permission_exists('destination_all');
	$has_destination_area_code       = permission_exists('destination_area_code');
	$has_destination_cid_name_prefix = permission_exists('destination_cid_name_prefix');
	$has_destination_context         = permission_exists('destination_context');
	$has_destination_delete          = permission_exists('destination_delete');
	$has_destination_edit            = permission_exists('destination_edit');
	$has_destination_export          = permission_exists('destination_export');
	$has_destination_import          = permission_exists('destination_import');
	$has_destination_local           = permission_exists('destination_local');
	$has_destination_trunk_prefix    = permission_exists('destination_trunk_prefix');
	$has_domain_select               = permission_exists('domain_select');
	$has_outbound_caller_id_select   = permission_exists('outbound_caller_id_select');

//add multi-lingual support
	$text = new text()->get();

//pre-defined variables
	$action = '';
	$search = '';
	$show = '';
	$destinations = '';

//get http variables
	if (isset($_REQUEST["action"]) && !empty($_REQUEST["action"])) {
		$action =  $_REQUEST["action"];
	}
	if (isset($_REQUEST["search"]) && !empty($_REQUEST["search"])) {
		$search =  strtolower($_REQUEST["search"]);
	}
	if (isset($_REQUEST["show"]) && !empty($_REQUEST["show"])) {
		$show =  strtolower($_REQUEST["show"]);
	}
	if (isset($_REQUEST["destinations"]) && !empty($_REQUEST["destinations"])) {
		$destinations =  $_REQUEST["destinations"];
	}

//process the http post data by action
	if (!empty($action) && !empty($destinations)) {
		switch ($action) {
			case 'delete':
				if ($has_destination_delete) {
					$obj = new destinations;
					$obj->delete($destinations);
					message::add($text['message-delete']);
				}
				break;
		}

		header('Location: destinations.php'.($search != '' ? '?search='.urlencode($search) : ''));
		exit;
	}

//get the destination select list
	$destination = new destinations;
	$destination_array = $destination->all('dialplan');

//function to return the action names in the order defined
	/**
	 * Returns a list of actions based on the provided destination array and actions.
	 *
	 * @param array $destination_array   The array containing the data to process.
	 * @param array $destination_actions The array of actions to apply to the destination array.
	 *
	 * @return array A list of actions resulting from the processing of the destination array and actions.
	 */
	function action_name($destination_array, $destination_actions) {
		global $settings;
		$actions = [];
		if (!empty($destination_array) && is_array($destination_array)) {
			if (!empty($destination_actions) && is_array($destination_actions)) {
				foreach ($destination_actions as $destination_action) {
					if (!empty($destination_action)) {
						foreach ($destination_array as $group => $row) {
							if (!empty($row) && is_array($row)) {
								foreach ($row as $key => $value) {
									if ($destination_action == $value) {
										if ($group == 'other') {
											if (!isset($language2) && !isset($text2)) {
												if (file_exists(dirname(__DIR__, 2)."/app/dialplans/app_languages.php")) {
													$language2 = new text;
													$text2 = $language2->get($settings->get('domain', 'language', 'en-us'), 'app/dialplans');
												}
											}
											$actions[] = trim($text2['title-other'].' &#x203A; '.$text2['option-'.str_replace('&lowbar;','_',$key)]);
										}
										else {
											if (file_exists(dirname(__DIR__, 2)."/app/".$group."/app_languages.php")) {
												$language3 = new text;
												$text3 = $language3->get($settings->get('domain', 'language', 'en-us'), 'app/'.$group);
												$actions[] = trim($text3['title-'.$group].' &#x203A; '.$key);
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}
		return $actions;
	}

//set the type
	$destination_type = 'inbound';
	if (!empty($_REQUEST['type'])) {
		switch ($_REQUEST['type']) {
			case 'inbound': $destination_type = 'inbound'; break;
			case 'outbound': $destination_type = 'outbound'; break;
			case 'local': $destination_type = 'local'; break;
			default: $destination_type = 'inbound';
		}
	}

//get variables used to control the order
	$order_by = $_GET["order_by"] ?? '';
	$order = $_GET["order"] ?? '';

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);

//prepare to page the results
	$sql = "select count(*) from v_destinations ";
	if ($show == "all" && $has_destination_all) {
		$sql .= "where destination_type = :destination_type ";
	}
	else {
		$sql .= "where destination_type = :destination_type ";
		$sql .= "and (domain_uuid = :domain_uuid or domain_uuid is null) ";
		$parameters['domain_uuid'] = $domain_uuid;
	}
	if (!empty($search)) {
		$sql .= "and (";
		$sql .= "lower(destination_type) like :search ";
		$sql .= "or lower(destination_number) like :search ";
		$sql .= "or lower(destination_cid_name_prefix) like :search ";
		$sql .= "or lower(destination_context) like :search ";
		$sql .= "or lower(destination_accountcode) like :search ";
		if ($has_outbound_caller_id_select) {
			$sql .= "or lower(destination_caller_id_name) like :search ";
			$sql .= "or destination_caller_id_number like :search ";
		}
		$sql .= "or lower(destination_description) like :search ";
		$sql .= "or lower(destination_data) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$parameters['destination_type'] = $destination_type;
	$num_rows = $database->select($sql, $parameters, 'column');

//prepare to page the results
	$rows_per_page = $settings->get('domain', 'paging', 50);
	$param = "&search=".urlencode($search);
	$param .= "&type=".$destination_type;
	if ($show == "all" && $has_destination_all) {
		$param .= "&show=all";
	}
	if (!empty($_GET['page'])) {
		$page = $_GET['page'];
	}
	if (!isset($page)) { $page = 0; $_GET['page'] = 0; }
	[$paging_controls, $rows_per_page] = paging($num_rows, $param, $rows_per_page);
	[$paging_controls_mini, $rows_per_page] = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the list
	$sql = "select ";
	$sql .= " d.destination_uuid, ";
	$sql .= " d.domain_uuid, ";
	if ($show == "all" && $has_destination_all) {
		$sql .= " domain_name, ";
	}
	$sql .= " d.destination_type, ";
	$sql .= " d.destination_prefix, ";
	$sql .= " d.destination_trunk_prefix, ";
	$sql .= " d.destination_area_code, ";
	$sql .= " d.destination_number, ";
	$sql .= " d.destination_actions, ";
	$sql .= " d.destination_cid_name_prefix, ";
	$sql .= " d.destination_context, ";
	$sql .= " d.destination_caller_id_name, ";
	$sql .= " d.destination_caller_id_number, ";
	$sql .= " cast(d.destination_enabled as text), ";
	$sql .= " d.destination_description ";
	$sql .= "from v_destinations as d ";
	if ($show == "all" && $has_destination_all) {
		$sql .= "LEFT JOIN v_domains as dom ";
		$sql .= "ON d.domain_uuid = dom.domain_uuid ";
		$sql .= "where destination_type = :destination_type ";
	}
	else {
		$sql .= "where destination_type = :destination_type ";
		$sql .= "and (domain_uuid = :domain_uuid or domain_uuid is null) ";
		$parameters['domain_uuid'] = $domain_uuid;
	}
	if (!empty($search)) {
		$sql .= "and (";
		$sql .= " lower(destination_type) like :search ";
		$sql .= " or lower(destination_number) like :search ";
		$sql .= " or lower(destination_cid_name_prefix) like :search ";
		$sql .= " or lower(destination_context) like :search ";
		$sql .= " or lower(destination_accountcode) like :search ";
		if ($has_outbound_caller_id_select) {
			$sql .= " or lower(destination_caller_id_name) like :search ";
			$sql .= " or destination_caller_id_number like :search ";
		}
		$sql .= " or lower(destination_description) like :search ";
		$sql .= " or lower(destination_data) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$sql .= order_by($order_by, $order, 'destination_number, destination_order ', 'asc');
	$sql .= limit_offset($rows_per_page, $offset);
	$destinations = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//update the array to add the actions
	if ($show != "all") {
		$x = 0;
		foreach ($destinations as $row) {
			$destinations[$x]['actions'] = '';
			if (!empty($row['destination_actions'])) {
				//prepare the destination actions
				if (!empty(json_decode($row['destination_actions'], true))) {
					//add the actions to the array
					$destination_app_data = [];
					foreach (json_decode($row['destination_actions'], true) as $action) {
						$destination_app_data[] = $action['destination_app'].':'.$action['destination_data'];
					}
					$actions = action_name($destination_array, $destination_app_data);
					$destinations[$x]['actions'] = (!empty($actions)) ? implode(', ', $actions) : '';
				}
			}
			$x++;
		}
	}

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//build the action bar buttons
	$btn_inbound = button::create(['type'=>'button','label'=>$text['button-inbound'],'icon'=>'location-arrow fa-rotate-90','link'=>'?type=inbound'.($show == 'all' ? '&show=all' : null).($search != '' ? "&search=".urlencode($search) : null)]);
	$btn_outbound = button::create(['type'=>'button','label'=>$text['button-outbound'],'icon'=>'location-arrow','link'=>'?type=outbound'.($show == 'all' ? '&show=all' : null).($search != '' ? "&search=".urlencode($search) : null)]);
	$btn_local = '';
	if ($has_destination_local) {
		$btn_local = button::create(['type'=>'button','label'=>$text['button-local'],'icon'=>'vector-square','link'=>'?type=local'.($show == 'all' ? '&show=all' : null).($search != '' ? "&search=".urlencode($search) : null)]);
	}
	$btn_import = '';
	if ($has_destination_import) {
		$btn_import = button::create(['type'=>'button','label'=>$text['button-import'],'icon'=>$settings->get('theme', 'button_icon_import'),'link'=>'destination_imports.php']);
	}
	$btn_export = '';
	if ($has_destination_export) {
		$btn_export = button::create(['type'=>'button','label'=>$text['button-export'],'icon'=>$settings->get('theme', 'button_icon_export'),'link'=>'destination_download.php']);
	}
	$btn_add = '';
	if ($has_destination_add) {
		$btn_add = button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','style'=>'margin-left: 15px;','link'=>'destination_edit.php?type='.urlencode($destination_type)]);
	}
	$btn_delete = '';
	if ($has_destination_delete && $destinations) {
		$btn_delete = button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	$btn_show_all = '';
	if ($has_destination_all && $show != 'all') {
		$btn_show_all = button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$settings->get('theme', 'button_icon_all'),'link'=>'?type='.urlencode($destination_type).'&show=all'.($search != '' ? "&search=".urlencode($search) : null)]);
	}
	$btn_search = button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);

//build the modals
	$modal_delete = '';
	if ($has_destination_delete && $destinations) {
		$modal_delete = modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

//build the table header columns
	$th_domain_name = '';
	if ($show == 'all' && $has_destination_all) {
		$th_domain_name = th_order_by('domain_name', $text['label-domain'], $order_by, $order, $param, "class='shrink'");
	}
	$th_destination_type   = th_order_by('destination_type', $text['label-destination_type'], $order_by, $order, $param, "class='shrink'");
	$th_destination_prefix = th_order_by('destination_prefix', $text['label-destination_prefix'], $order_by, $order, $param, "class='shrink center'");
	$th_destination_trunk_prefix = '';
	if ($has_destination_trunk_prefix) {
		$th_destination_trunk_prefix = th_order_by('destination_trunk_prefix', $text['label-destination_trunk_prefix'], $order_by, $order, $param, "class='shrink'");
	}
	$th_destination_area_code = '';
	if ($has_destination_area_code) {
		$th_destination_area_code = th_order_by('destination_area_code', $text['label-destination_area_code'], $order_by, $order, $param, "class='shrink'");
	}
	$th_destination_number  = th_order_by('destination_number', $text['label-destination_number'], $order_by, $order, $param, "class='shrink'");
	$th_destination_actions = '';
	if ($show != 'all') {
		$th_destination_actions = "<th>".$text['label-destination_actions']."</th>";
	}
	$th_destination_cid_name_prefix = '';
	if ($has_destination_cid_name_prefix) {
		$th_destination_cid_name_prefix = th_order_by('destination_cid_name_prefix', $text['label-destination_cid_name_prefix'], $order_by, $order, $param);
	}
	$th_destination_context = '';
	if ($has_destination_context) {
		$th_destination_context = th_order_by('destination_context', $text['label-destination_context'], $order_by, $order, $param);
	}
	$th_destination_caller_id_name   = '';
	$th_destination_caller_id_number = '';
	if ($has_outbound_caller_id_select) {
		$th_destination_caller_id_name   = th_order_by('destination_caller_id_name', $text['label-destination_caller_id_name'], $order_by, $order, $param);
		$th_destination_caller_id_number = th_order_by('destination_caller_id_number', $text['label-destination_caller_id_number'], $order_by, $order, $param);
	}
	$th_destination_enabled     = th_order_by('destination_enabled', $text['label-destination_enabled'], $order_by, $order, $param);
	$th_destination_description = th_order_by('destination_description', $text['label-destination_description'], $order_by, $order, $param, "class='hide-sm-dn'");

//build the row data
	$x = 0;
	foreach ($destinations as &$row) {
		$list_row_url = '';
		if ($has_destination_edit) {
			$list_row_url = "destination_edit.php?id=".urlencode($row['destination_uuid']);
			if ($row['domain_uuid'] != $_SESSION['domain_uuid'] && $has_domain_select) {
				$list_row_url .= '&domain_uuid='.urlencode($row['domain_uuid']).'&domain_change=true';
			}
		}
		$row['_list_row_url'] = $list_row_url;
		$domain_label = '';
		if ($show == 'all' && $has_destination_all) {
			$domain_label = !empty($row['domain_name']) ? $row['domain_name'] : $text['label-global'];
		}
		$row['_domain_label']      = $domain_label;
		$row['_formatted_number']  = format_phone($row['destination_number']);
		$row['_type_label']        = $text['option-'.$row['destination_type']];
		$row['_enabled_label']     = $text['label-'.$row['destination_enabled']];
		$row['_edit_button'] = '';
		if ($has_destination_edit && $list_row_edit_button) {
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
	$template->assign('text',                            $text);
	$template->assign('num_rows',                        $num_rows);
	$template->assign('destinations',                    $destinations ?? []);
	$template->assign('search',                          $search);
	$template->assign('show',                            $show);
	$template->assign('destination_type',                $destination_type);
	$template->assign('paging_controls',                 $paging_controls);
	$template->assign('paging_controls_mini',            $paging_controls_mini);
	$template->assign('token',                           $token);
	$template->assign('has_destination_add',             $has_destination_add);
	$template->assign('has_destination_all',             $has_destination_all);
	$template->assign('has_destination_area_code',       $has_destination_area_code);
	$template->assign('has_destination_cid_name_prefix', $has_destination_cid_name_prefix);
	$template->assign('has_destination_context',         $has_destination_context);
	$template->assign('has_destination_delete',          $has_destination_delete);
	$template->assign('has_destination_edit',            $has_destination_edit);
	$template->assign('has_destination_local',           $has_destination_local);
	$template->assign('has_destination_trunk_prefix',    $has_destination_trunk_prefix);
	$template->assign('has_outbound_caller_id_select',   $has_outbound_caller_id_select);
	$template->assign('list_row_edit_button',            $list_row_edit_button);
	$template->assign('btn_inbound',                     $btn_inbound);
	$template->assign('btn_outbound',                    $btn_outbound);
	$template->assign('btn_local',                       $btn_local);
	$template->assign('btn_import',                      $btn_import);
	$template->assign('btn_export',                      $btn_export);
	$template->assign('btn_add',                         $btn_add);
	$template->assign('btn_delete',                      $btn_delete);
	$template->assign('btn_show_all',                    $btn_show_all);
	$template->assign('btn_search',                      $btn_search);
	$template->assign('modal_delete',                    $modal_delete);
	$template->assign('th_domain_name',                  $th_domain_name);
	$template->assign('th_destination_type',             $th_destination_type);
	$template->assign('th_destination_prefix',           $th_destination_prefix);
	$template->assign('th_destination_trunk_prefix',     $th_destination_trunk_prefix);
	$template->assign('th_destination_area_code',        $th_destination_area_code);
	$template->assign('th_destination_number',           $th_destination_number);
	$template->assign('th_destination_actions',          $th_destination_actions);
	$template->assign('th_destination_cid_name_prefix',  $th_destination_cid_name_prefix);
	$template->assign('th_destination_context',          $th_destination_context);
	$template->assign('th_destination_caller_id_name',   $th_destination_caller_id_name);
	$template->assign('th_destination_caller_id_number', $th_destination_caller_id_number);
	$template->assign('th_destination_enabled',          $th_destination_enabled);
	$template->assign('th_destination_description',      $th_destination_description);

//invoke pre-render hook
	app::dispatch_list_pre_render('destination_list_page_hook', $url_paging, $template);

//include the header
	$document['title'] = $text['title-destinations'];
	require_once "resources/header.php";

//render the template
	$html = $template->render('destinations_list.tpl');

//invoke post-render hook
	app::dispatch_list_post_render('destination_list_page_hook', $url_paging, $html);
	echo $html;

//include the footer
	require_once "resources/footer.php";
