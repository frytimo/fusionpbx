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
	Portions created by the Initial Developer are Copyright (C) 2008-2023
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J. Crane <markjcrane@fusionpbx.com>
*/

//start the session
	ini_set("session.cookie_httponly", True);
	if (!isset($_SESSION)) { session_start(); }

if(!function_exists('get_resource')) {
	function get_resource() {
		//set the actual address requested
		$aa = $_REQUEST['q'] ?? '';

		//remove trailing slash
		if (substr($aa, -1, 1) === '/') {
			$uri = substr($aa, 0, strlen($aa) - 1);
		} else {
			$uri = $aa;
		}

		//get routes
		$routes = array_filter(explode('/', $uri),
				function ($value) { return !is_null($value) && $value !== ''; }
			);

		//get the application
		if (count($routes) > 0) {
			$resource = __DIR__;
			while(count($routes) > 0) {
				$app = array_shift($routes);
				$resource .= '/' . $app;
			}
			//ensure we don't infinitely loop for /index.php
			if ($resource === __FILE__) {
				return __DIR__ . '/core/dashboard/index.php';
			}
			//most landing pages will return here
			//TODO: need to implement security
			if (file_exists($resource) && is_file($resource)) {
				return $resource;
			}
			//allow for /core/dashboard and others to be routed like that
			$redirect = $resource . '/index.php';
			if (file_exists($redirect) && is_file($redirect)) {
				return $redirect;
			}
			//allow for /app/bridges/bridges.php and others to be routed like that
			$redirect = $resource . '/' . $app . '.php';
			if (file_exists($redirect) && is_file($redirect)) {
				return $redirect;
			}
		} else {
			$resource = __DIR__ . '/' . $uri;
		}

		if (substr($resource, -4, 4) === '.php' && __FILE__ !== $resource) {
			return $resource;
		}

		return $resource . 'core/dashboard/index.php';
	}
}

//includes files
	require_once __DIR__ . "/resources/require.php";

//fix the project root
	$_SERVER["PROJECT_ROOT"] = __DIR__;

//if logged in, redirect to login destination
	if (isset($_SESSION["username"])) {
		if (isset($_SESSION['login']['destination']['text'])) {
			include $_SESSION['login']['destination']['text'];
		}
		elseif (file_exists($_SERVER["PROJECT_ROOT"]."/core/dashboard/app_config.php")) {
			$resource = get_resource();
			if (file_exists($resource) && is_file($resource)) {
				include $resource;
			} else {
				http_response_code(404);
				echo "<!DOCTYPE html>404 - Not Found";
				exit();
			}
		}
		else {
			require_once __DIR__ . "/resources/header.php";
			require_once __DIR__ . "/resources/footer.php";
		}
	}
	else {
		//use custom index, if present, otherwise use custom login, if present, otherwise use default login
		if (file_exists($_SERVER["PROJECT_ROOT"]."/themes/".($_SESSION['domain']['template']['name'] ?? '')."/index.php")) {
			require_once "themes/".$_SESSION['domain']['template']['name']."/index.php";
		}
		else {
			//login prompt
			include __DIR__ . '/login.php';
		}
	}

?>
