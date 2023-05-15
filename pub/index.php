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
    Tim Fry <tim.fry@hotmail.com>
*/

//start capturing the output buffer
	ob_start();

//start the session
	ini_set("session.cookie_httponly", True);
	if (!isset($_SESSION)) { session_start(); }

//sanitize the $_REQUEST['q'] param
	$request = $_REQUEST['q'];
//	if($request === '/core/dashboard/') {
//		$request = '/core/dashboard/index.php';
//	}


	$base_dir = dirname(__DIR__);
	$core_dir = $base_dir . '/core';
	$app_dir = $base_dir . '/app';
	$theme_dir = $base_dir . '/themes';
	$resource_dir = $base_dir . '/resources';
	$pub_dir = $base_dir . '/pub';

//get the path parts
	$request_path = explode(separator:'/', string:$request);
//set the default route to be login
	$route = "core";
	$app = "dashboard";
	$resource = "index.php";
//assume it is a php
	$include = true;
	if(count($request_path) > 0) {
		//remove first one as all paths start with /
		array_shift($request_path);
		$route = basename(array_shift($request_path), '.php');
		if(count($request_path)>0) {
			$app = array_shift($request_path);
		}
		if(count($request_path)>0) {
			if(!empty($request_path[0])) {
				$resource = array_shift($request_path);
			}
		}
	}
	if(!str_ends_with($resource, '.php')) {
		$include = false;
	}
	$uri = $base_dir .'/'. $route . '/'. $app . '/' . $resource;
	switch($route) {
		case 'app':
		case 'core':
		case 'resources':
		case 'themes':
			//adds multiple includes
			require $resource_dir . '/require.php';
			if($include) {
				require $uri;
			} else {
				if(str_ends_with($request, '.png')) {
				$im = imagecreatefrompng($base_dir . '/'.$request);
				header('Content-Type: image/png');
				imagepng($im);
				imagedestroy($im);
				} else {
					echo $uri;
				}
			}
			break;
		case 'login':
		case 'index':
			require $resource_dir . '/require.php';
			require $core_dir . '/dashboard/index.php';
			break;
		case 'logout':
			session_unset();
			session_destroy();
			header('Location: /core/dashboard/index.php');
			exit();
	}

	if(str_starts_with($request, '/themes') ) {
		if(file_exists($request)) {
			if (str_ends_with(basename($request), '.php')) {
				include $request;
			} else {
				echo $request;
			}
		}
	}

//if logged in, redirect to login destination
	if (isset($_SESSION["username"])) {
		if (isset($_SESSION['login']['destination']['text'])) {
			require PROJECT_ROOT . '.' . $_SESSION['login']['destination']['text'];
		}
		elseif (file_exists($_SERVER["PROJECT_ROOT"]."/core/dashboard/app_config.php")) {
			//header("Location: ".PROJECT_PATH."/core/dashboard/");
			require PROJECT_ROOT . '.' . $request;
		}
		else {
			require_once "resources/header.php";
			require_once "resources/footer.php";
		}
	}
	else {
		//use custom index, if present, otherwise use custom login, if present, otherwise use default login
		if (file_exists($_SERVER["PROJECT_ROOT"]."/themes/".$_SESSION['domain']['template']['name']."/index.php")) {
			require_once "themes/".$_SESSION['domain']['template']['name']."/index.php";
		}
		else {
			require PROJECT_ROOT . '/login.php';	//this shouldn't be here but not sure how to login
		}
	}

//ensure all output buffering is flushed
	ob_end_flush();