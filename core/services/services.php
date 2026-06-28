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
	Portions created by the Initial Developer are Copyright (C) 2026
	the Initial Developer. All Rights Reserved.
*/

// includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

// check permissions
	if (!permission_exists('service_view')) {
		echo "access denied";
		exit;
	}

// add multi-lingual support
	$language = new text;
	$text = $language->get();

// add the settings object
	$settings = new settings(["domain_uuid" => $_SESSION['domain_uuid'], "user_uuid" => $_SESSION['user_uuid']]);

// set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', 'false');

// get the http post data
	if (!empty($_POST['services']) && is_array($_POST['services'])) {
		$action = $_POST['action'];
		$search = $_POST['search'];
		$services = $_POST['services'];
	}

// process async service action (no page reload)
	if (($_POST['ajax'] ?? null) === 'service_action') {
		header('Content-Type: application/json');

		if (!permission_exists('service_edit')) {
			echo json_encode(['status' => 'error', 'message' => 'access denied']);
			exit;
		}

		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			echo json_encode(['status' => 'error', 'message' => 'invalid token']);
			exit;
		}

		$service_uuid = $_POST['service_uuid'] ?? null;
		$row_action = $_POST['row_action'] ?? null;
		$row_index = (int)($_POST['row_index'] ?? -1);
		$allowed_actions = ['start', 'stop', 'restart'];

		if (!is_uuid($service_uuid) || !in_array($row_action, $allowed_actions, true)) {
			echo json_encode(['status' => 'error', 'message' => 'invalid request']);
			exit;
		}

		$array = [];
		$array['services'][0]['service_uuid'] = $service_uuid;
		$array['services'][0]['service_job_action'] = $row_action;
		$database->save($array);

		$next_status = $row_action === 'stop' ? 'false' : 'true';
		echo json_encode([
			'status' => 'ok',
			'row_index' => $row_index,
			'next_status' => $next_status,
			'action' => $row_action
		]);
		exit;
	}

// process async row state poll
	if (($_POST['ajax'] ?? null) === 'service_row_status') {
		header('Content-Type: application/json');

		if (!permission_exists('service_view')) {
			echo json_encode(['status' => 'error', 'message' => 'access denied']);
			exit;
		}

		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			echo json_encode(['status' => 'error', 'message' => 'invalid token']);
			exit;
		}

		$service_uuid = $_POST['service_uuid'] ?? null;
		if (!is_uuid($service_uuid)) {
			echo json_encode(['status' => 'error', 'message' => 'invalid request']);
			exit;
		}

		$sql = "select service_uuid, service_name, service_job_action from v_services where service_uuid = :service_uuid ";
		$parameters = [];
		$parameters['service_uuid'] = $service_uuid;
		$service_row = $database->select($sql, $parameters, 'row');
		unset($sql, $parameters);

		if (!$service_row || empty($service_row['service_name'])) {
			echo json_encode(['status' => 'error', 'message' => 'service not found']);
			exit;
		}

		$service_status = 'false';
		$service_runtime = '-';
		$service_runtime_title = '';
		$service_object_poll = new services;
		$service_array_poll = $service_object_poll->get_services('true');
		foreach ($service_array_poll as $service_state) {
			if (($service_state['name'] ?? '') === $service_row['service_name']) {
				$service_status = !empty($service_state['status']) ? 'true' : 'false';
				if (!empty($service_state['etime'])) {
					$service_runtime = $service_object_poll->format_etime($service_state['etime']);
				}
				if (!empty($service_state['pid'])) {
					$service_runtime_title = 'PID: '.$service_state['pid'];
				}
				break;
			}
		}

		echo json_encode([
			'status' => 'ok',
			'queued' => !empty($service_row['service_job_action']),
			'service_status' => $service_status,
			'service_runtime' => $service_runtime,
			'service_runtime_title' => $service_runtime_title,
			'service_job_action' => $service_row['service_job_action']
		]);
		exit;
	}

// process the http post data by action
	if (!empty($action) && !empty($services) && is_array($services) && @sizeof($services) != 0) {

		// send the array to the database class
		switch ($action) {
			case 'reload':
				if (permission_exists('service_edit')) {
					$obj = new services;
					$obj->reload($services);
				}
				break;
			case 'toggle':
				if (permission_exists('service_edit')) {
					$obj = new services;
					$obj->toggle($services);
				}
				break;
			case 'delete':
				if (permission_exists('service_delete')) {
					$obj = new services;
					$obj->delete($services);
				}
				break;
			case 'start':
				if (permission_exists('service_edit')) {
					$obj = new services;
					$obj->start_jobs($services);
				}
				break;
		}

		// redirect the user
		header('Location: services.php'.($search != '' ? '?search='.urlencode($search) : null));
		exit;
	}

// get order and order by
	$order_by = $_GET["order_by"] ?? null;
	$order = $_GET["order"] ?? null;

// define the variables
	$search = '';
	$show = '';
	$list_row_url = '';

// add the search variable
	if (!empty($_GET["search"])) {
		$search = strtolower($_GET["search"]);
	}

// add the show variable
	if (!empty($_GET["show"])) {
		$show = $_GET["show"];
	}

// get the status of the services
	$service_object = new services;
	$service_object->add_missing();

// get the status of the services
	$service_array = $service_object->get_services('true');

// get the count
	$sql = "select count(service_uuid) ";
	$sql .= "from v_services ";
	$sql .= "where true ";
	if (!empty($search)) {
		$sql .= "and ( ";
		$sql .= " lower(service_name) like :search ";
		$sql .= " or lower(service_category) like :search ";
		$sql .= " or lower(service_description) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$num_rows = $database->select($sql, $parameters ?? null, 'column');
	unset($sql, $parameters);

// get the list
	$sql = "select ";
	$sql .= "service_uuid, ";
	$sql .= "service_name, ";
	$sql .= "service_category, ";
	$sql .= "cast(service_enabled as text), ";
	$sql .= "service_description, ";
	$sql .= "service_job_action ";
	$sql .= "from v_services ";
	$sql .= "where true ";
	if (!empty($search)) {
		$sql .= "and ( ";
		$sql .= " lower(service_name) like :search ";
		$sql .= " or lower(service_category) like :search ";
		$sql .= " or lower(service_description) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$sql .= order_by($order_by, $order, 'service_name', 'asc');
	$sql .= limit_offset($rows_per_page, $offset);
	$services = $database->select($sql, $parameters ?? null, 'all');
	unset($sql, $parameters);

// add the service details to the services array
	foreach($services as $i => $service) {
		foreach ($service_array as $row) {
			if ($service['service_name'] == $row['name']) {
				$services[$i]['service_status'] = $row['status'] ? 'true' : 'false';
				$services[$i]['service_pid'] = $row['pid'];
				$services[$i]['service_etime'] = $row['etime'];
				break;
			}
		}
	}

// create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

// additional includes
	$document['title'] = $text['title-services'];
	require_once "resources/header.php";

// show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['title-services']."</b><div class='count'>".$num_rows."</div></div>\n";
	echo "	<div class='actions'>\n";
	if (permission_exists('service_edit') && $services) {
		echo button::create(['type'=>'button','label'=>$text['button-reload'],'icon'=>$_SESSION['theme']['button_icon_reload'],'id'=>'btn_reload','name'=>'btn_reload','style'=>'display:none;','onclick'=>"modal_open('modal-reload','btn_reload');"]);
	}
	if (permission_exists('service_edit') && $services) {
		echo button::create(['type'=>'button','label'=>$text['button-toggle'],'icon'=>$_SESSION['theme']['button_icon_toggle'],'id'=>'btn_toggle','name'=>'btn_toggle','style'=>'display:none;','onclick'=>"modal_open('modal-toggle','btn_toggle');"]);
	}
	if (permission_exists('service_delete') && $services) {
		echo button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$_SESSION['theme']['button_icon_delete'],'id'=>'btn_delete','name'=>'btn_delete','style'=>'display:none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	echo 		"<form id='form_search' class='inline' method='get'>\n";
	echo 		"<input type='text' class='txt list-search' name='search' id='search' value=\"".escape($search)."\" placeholder=\"".$text['label-search']."\" onkeydown=''>";
	echo button::create(['label'=>$text['button-search'],'icon'=>$_SESSION['theme']['button_icon_search'],'type'=>'submit','id'=>'btn_search']);
	if ($paging_controls_mini != '') {
		echo 	"<span style='margin-left: 15px;'>".$paging_controls_mini."</span>\n";
	}
	echo "		</form>\n";
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	if (permission_exists('service_add') && $services) {
		echo modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
	}
	if (permission_exists('service_edit') && $services) {
		echo modal::create(['id'=>'modal-reload','type'=>'reload','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_reload','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('reload'); list_form_submit('form_list');"])]);
	}
	if (permission_exists('service_edit') && $services) {
		echo modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
	}
	if (permission_exists('service_delete') && $services) {
		echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

	echo $text['title_description-services']."\n";

	echo "<br /><br />\n";

	echo "<form id='form_list' method='post'>\n";
	echo "<input type='hidden' id='action' name='action' value=''>\n";
	echo "<input type='hidden' name='search' value=\"".escape($search ?? '')."\">\n";

	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	if (permission_exists('service_add') || permission_exists('service_edit') || permission_exists('service_delete')) {
		echo "	<th class='checkbox'>\n";
		echo "		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);' ".empty($services ? "style='visibility: hidden;'" : null).">\n";
		echo "	</th>\n";
	}
	echo th_order_by('service_name', $text['label-service_name'], $order_by, $order);
	echo th_order_by('service_status', $text['label-service_status'], $order_by, $order);
	echo th_order_by('service_category', $text['label-service_category'], $order_by, $order);
	echo "	<th class='hide-sm-dn'>".$text['label-service_runtime']."</th>\n";
	echo th_order_by('service_enabled', $text['label-enabled'], $order_by, $order, null, "class='center'");
	echo "	<th>".$text['label-service_job_action']."</th>\n";
	echo "	<th class='hide-sm-dn'>".$text['label-service_description']."</th>\n";
	if (permission_exists('service_edit') && $list_row_edit_button == 'true') {
		echo "	<td class='action-button'>&nbsp;</td>\n";
	}
	echo "</tr>\n";

	if (!empty($services) && is_array($services) && @sizeof($services) != 0) {
		$x = 0;
		foreach ($services as $row) {
			$service_status = ($row['service_status'] == 'true')
				? "<span style='background-color: #28a745; color: white; padding: 2px 8px; border-radius: 10px;'>".$text['label-yes']."</span>"
				: "<span style='background-color: #dc3545; color: white; padding: 2px 8px; border-radius: 10px;'>".$text['label-no']."</span>";
			$etime = isset($row['service_etime']) ? $service_object->format_etime($row['service_etime']) : '-';
			$pid = $row['service_pid'] ?? '';
			$tooltip_attr = $pid ? "title='PID: $pid'" : '';

			if (permission_exists('service_edit')) {
				$list_row_url = "service_edit.php?id=".urlencode($row['service_uuid']);
			}
			echo "<tr class='list-row' href='".$list_row_url."'>\n";
			if (permission_exists('service_add') || permission_exists('service_edit') || permission_exists('service_delete')) {
				echo "	<td class='checkbox'>\n";
				echo "		<input type='checkbox' name='services[$x][checked]' id='checkbox_".$x."' value='true' onclick=\"checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }\">\n";
				echo "		<input type='hidden' name='services[$x][uuid]' value='".escape($row['service_uuid'])."' />\n";
				echo "		<input type='hidden' name='services[$x][job_action]' id='job_action_".$x."' value='' />\n";
				echo "	</td>\n";
			}
			echo "	<td>\n";
			if (permission_exists('service_edit')) {
				echo "	<a href='".$list_row_url."' title=\"".$text['button-edit']."\">".escape($row['service_name'])."</a>\n";
			}
			else {
				echo "	".escape($row['service_name']);
			}
			echo "	</td>\n";
			echo "	<td id='service_status_".$x."' $tooltip_attr>".$service_status."</td>\n";
			echo "	<td>".escape($row['service_category'])."</td>\n";
			echo "	<td id='service_runtime_".$x."' class='description overflow hide-sm-dn'>".escape($etime)."</td>\n";
			if (permission_exists('service_edit')) {
				echo "	<td class='no-link center'>\n";
				echo "		<input type='hidden' name='number_translations[$x][service_enabled]' value='".escape($row['service_enabled'])."' />\n";
				echo button::create(['type'=>'submit','class'=>'link','label'=>$text['label-'.$row['service_enabled']],'title'=>$text['button-toggle'],'onclick'=>"list_self_check('checkbox_".$x."'); list_action_set('toggle'); list_form_submit('form_list')"]);
			}
			else {
				echo "	<td class='center'>\n";
				echo $text['label-'.$row['service_enabled']];
			}
			echo "	</td>\n";
			if (permission_exists('service_edit')) {
				echo "	<td id='service_actions_".$x."' class='no-link'>\n";
				if ($row['service_job_action'] === 'processing' || $row['service_job_action'] === 'start' || $row['service_job_action'] === 'stop' || $row['service_job_action'] === 'restart') {
					echo "<span style='background-color: #ffc107; color: black; padding: 2px 8px; border-radius: 10px;'>".($text['label-processing'] ?? 'Processing')."</span>";
				} elseif ($row['service_status'] == 'true') {
					echo "<a href='#' class='link service-action-link' data-service-uuid='".escape($row['service_uuid'])."' data-row-index='".$x."' data-row-action='stop' title='".escape($text['option-stop'])."'>".escape($text['option-stop'])."</a>";
					echo "&nbsp;";
					echo "<a href='#' class='link service-action-link' data-service-uuid='".escape($row['service_uuid'])."' data-row-index='".$x."' data-row-action='restart' title='".escape($text['option-restart'])."'>".escape($text['option-restart'])."</a>";
				} else {
					echo "<a href='#' class='link service-action-link' data-service-uuid='".escape($row['service_uuid'])."' data-row-index='".$x."' data-row-action='start' title='".escape($text['option-start'])."'>".escape($text['option-start'])."</a>";
				}
				echo "	</td>\n";
			}
			else {
				echo "	<td>-</td>\n";
			}
			echo "	<td class='description overflow hide-sm-dn'>".escape($row['service_description'])."</td>\n";
			if (permission_exists('service_edit') && $list_row_edit_button == 'true') {
				echo "	<td class='action-button'>\n";
				echo button::create(['type'=>'button','title'=>$text['button-edit'],'icon'=>$_SESSION['theme']['button_icon_edit'],'link'=>$list_row_url]);
				echo "	</td>\n";
			}
			echo "</tr>\n";
			$x++;
		}
		unset($services);
	}

	echo "</table>\n";
	echo "<br />\n";
	echo "<div align='center'>".$paging_controls."</div>\n";
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";
	echo "</form>\n";

	echo "<script>\n";
	echo "const serviceActionTokenName = ".json_encode($token['name']).";\n";
	echo "const serviceActionTokenHash = ".json_encode($token['hash']).";\n";
	echo "const serviceActionLabelStart = ".json_encode($text['option-start']).";\n";
	echo "const serviceActionLabelStop = ".json_encode($text['option-stop']).";\n";
	echo "const serviceActionLabelRestart = ".json_encode($text['option-restart']).";\n";
	echo "const serviceLabelYes = ".json_encode($text['label-yes']).";\n";
	echo "const serviceLabelNo = ".json_encode($text['label-no']).";\n";
	echo "const serviceLabelProcessing = ".json_encode($text['label-processing'] ?? 'Processing').";\n";
	echo "const serviceRuntimeStarting = ".json_encode($text['label-starting'] ?? 'Starting...').";\n";
	echo "const serviceRowPollers = {};\n";
	echo "function service_status_badge_html(nextStatus) {\n";
	echo "	if (nextStatus === 'true') {\n";
	echo "		return '<span style=\\\"background-color: #28a745; color: white; padding: 2px 8px; border-radius: 10px;\\\">' + serviceLabelYes + '</span>';\n";
	echo "	}\n";
	echo "	return '<span style=\\\"background-color: #dc3545; color: white; padding: 2px 8px; border-radius: 10px;\\\">' + serviceLabelNo + '</span>';\n";
	echo "}\n";
	echo "function service_actions_html(serviceUuid, rowIndex, nextStatus) {\n";
	echo "\tconst makeLink = (action, label) => '<a href=\"#\" class=\"link service-action-link\" data-service-uuid=\"' + serviceUuid + '\" data-row-index=\"' + rowIndex + '\" data-row-action=\"' + action + '\" title=\"' + label + '\">' + label + '</a>';\n";
	echo "\tif (nextStatus === 'true') {\n";
	echo "\t\treturn makeLink('stop', serviceActionLabelStop) + '&nbsp;' + makeLink('restart', serviceActionLabelRestart);\n";
	echo "\t}\n";
	echo "\treturn makeLink('start', serviceActionLabelStart);\n";
	echo "}\n";
	echo "function service_processing_badge_html() {\n";
	echo "\treturn '<span style=\\\"background-color: #ffc107; color: black; padding: 2px 8px; border-radius: 10px;\\\">' + serviceLabelProcessing + '</span>';\n";
	echo "}\n";
	echo "function service_poll_until_complete(serviceUuid, rowIndex) {\n";
	echo "\tif (serviceRowPollers[rowIndex]) { clearInterval(serviceRowPollers[rowIndex]); }\n";
	echo "\tserviceRowPollers[rowIndex] = setInterval(function() {\n";
	echo "\t\tconst formData = new FormData();\n";
	echo "\t\tformData.append('ajax', 'service_row_status');\n";
	echo "\t\tformData.append('service_uuid', serviceUuid);\n";
	echo "\t\tformData.append(serviceActionTokenName, serviceActionTokenHash);\n";
	echo "\t\tfetch('services.php', { method: 'POST', body: formData, credentials: 'same-origin' })\n";
	echo "\t\t\t.then((response) => response.json())\n";
	echo "\t\t\t.then((data) => {\n";
	echo "\t\t\t\tif (data.status !== 'ok') { return; }\n";
	echo "\t\t\t\tif (data.queued) { return; }\n";
	echo "\t\t\t\tconst statusCell = document.getElementById('service_status_' + rowIndex);\n";
	echo "\t\t\t\tconst actionCell = document.getElementById('service_actions_' + rowIndex);\n";
	echo "\t\t\t\tconst runtimeCell = document.getElementById('service_runtime_' + rowIndex);\n";
	echo "\t\t\t\tif (statusCell) {\n";
	echo "\t\t\t\t\tstatusCell.innerHTML = service_status_badge_html(data.service_status);\n";
	echo "\t\t\t\t\tif (data.service_runtime_title) { statusCell.setAttribute('title', data.service_runtime_title); }\n";
	echo "\t\t\t\t\telse { statusCell.removeAttribute('title'); }\n";
	echo "\t\t\t\t}\n";
	echo "\t\t\t\tif (actionCell) { actionCell.innerHTML = service_actions_html(serviceUuid, rowIndex, data.service_status); }\n";
	echo "\t\t\t\tif (runtimeCell) { runtimeCell.textContent = data.service_runtime || '-'; }\n";
	echo "\t\t\t\tclearInterval(serviceRowPollers[rowIndex]);\n";
	echo "\t\t\t\tdelete serviceRowPollers[rowIndex];\n";
	echo "\t\t\t})\n";
	echo "\t\t\t.catch(() => {});\n";
	echo "\t}, 2000);\n";
	echo "}\n";
	echo "document.addEventListener('click', function(evt) {\n";
	echo "	const link = evt.target.closest('.service-action-link');\n";
	echo "	if (!link) { return; }\n";
	echo "	service_action_request(link.dataset.serviceUuid, link.dataset.rowAction, parseInt(link.dataset.rowIndex || '0', 10), evt);\n";
	echo "});\n";
	echo "function service_action_request(serviceUuid, rowAction, rowIndex, evt) {\n";
	echo "	if (evt) { evt.preventDefault(); evt.stopPropagation(); }\n";
	echo "	const formData = new FormData();\n";
	echo "	formData.append('ajax', 'service_action');\n";
	echo "	formData.append('service_uuid', serviceUuid);\n";
	echo "	formData.append('row_action', rowAction);\n";
	echo "	formData.append('row_index', rowIndex);\n";
	echo "	formData.append(serviceActionTokenName, serviceActionTokenHash);\n";
	echo "	return fetch('services.php', { method: 'POST', body: formData, credentials: 'same-origin' })\n";
	echo "		.then((response) => response.json())\n";
	echo "		.then((data) => {\n";
	echo "			if (data.status !== 'ok') {\n";
	echo "				throw new Error(data.message || 'Request failed');\n";
	echo "			}\n";
	echo "			const actionCell = document.getElementById('service_actions_' + rowIndex);\n";
	echo "			const runtimeCell = document.getElementById('service_runtime_' + rowIndex);\n";
	echo "			if (actionCell) { actionCell.innerHTML = service_processing_badge_html(); }\n";
	echo "			if (runtimeCell && rowAction === 'start') { runtimeCell.textContent = serviceRuntimeStarting; }\n";
	echo "			service_poll_until_complete(serviceUuid, rowIndex);\n";
	echo "		})\n";
	echo "		.catch((error) => {\n";
	echo "			console.error(error);\n";
	echo "			alert(error.message || 'Service action failed');\n";
	echo "		});\n";
	echo "}\n";
	echo "</script>\n";

// include the footer
	require_once "resources/footer.php";

?>
