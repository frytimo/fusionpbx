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
  Portions created by the Initial Developer are Copyright (C) 2022
  the Initial Developer. All Rights Reserved.

  Contributor(s):
  Mark J Crane <markjcrane@fusionpbx.com>
 */

//set the include path
$document_root = dirname(__DIR__, 2);

//composer class auto loader
if (file_exists($document_root . '/resources/libs/autoload.php')) {
	require_once $document_root . '/resources/libs/autoload.php';
}

//fusionpbx class auto loader
require_once $document_root . '/resources/classes/auto_loader.php';

//framework
require_once $document_root . '/resources/classes/framework.php';
framework::initialize();

$config = framework::config();

if ($config->is_empty()) {
	//set the max execution time to 1 hour
	ini_set('max_execution_time', 3600);

	//set debug to true or false
	$debug = false;

	//set the default domain_uuid
	$domain_uuid = uuid();

	//add the menu uuid
	$menu_uuid = 'b4750c3f-2a86-b00d-b7d0-345c14eca286';

	//set the default time zone
	date_default_timezone_set('UTC');

	//create the installer to get the default config based on OS
	$config = install::get_default_config();

	//set the domain name in the config
	$domain_array = explode(":", $_SERVER["HTTP_HOST"]);
	$config->domain_name = $domain_array[0];
	unset($domain_array);

	//check for debugging
	if (gethostname() === 'php') {
		$config->db_host = 'db';
		$config->set("switch.event_socket.host", 'fs');
		$config->set('init.domain.name', 'localhost');
		$config->set('init.admin.name', 'admin');
		$config->set('init.admin.password', 'password');
		$config->set('error.reporting', 'all');
	}

	//re-initialize the framework with the default config settings
	framework::initialize($config);

	//start the session before text object stores values in session
	session_start();

	//temp directory
	$_SESSION['server']['temp']['dir'] = $config->temp_dir;

	//set a default template
	$_SESSION['domain']['template']['name'] = 'default';
	$_SESSION['theme']['menu_brand_image']['text'] = PROJECT_PATH . '/themes/default/images/logo.png';
	$_SESSION['theme']['menu_brand_type']['text'] = 'image';

	//set a default step if not already set
	if (empty($_REQUEST['step'])) {
		$_REQUEST['step'] = '1';
	}

	//add multi-lingual support
	$language = new text;
	$text = $language->get();

	//initialize a template object
	$view = new template();
	$view->engine = 'smarty';
	$view->template_dir = __DIR__ . '/resources/views/';
	$view->cache_dir = $config->temp_dir;
	$view->init();

	//assign default values to the template
	$view->assign("admin_username", $config->admin_username);
	$view->assign("admin_password", $config->admin_password);
	$view->assign("domain_name", $config->domain_name);
	$view->assign("database_host", $config->db_host);
	$view->assign("database_port", $config->db_port);
	$view->assign("database_name", $config->db_name);
	$view->assign("database_username", $config->db_username);
	$view->assign("database_password", $config->db_password);

	//add translations
	foreach ($text as $key => $value) {
		$view->assign(str_replace("-", "_", $key), $text[$key]);
		//$view->assign("label_username", $text['label-username']);
		//$view->assign("label_password", $text['label-password']);
		//$view->assign("button_back", $text['button-back']);
	}

	foreach ($_POST as $key => $value) {
		switch ($key) {
			case 'admin_username':
			case 'admin_password':
			case 'domain_name':
			case 'database_host':
			case 'database_port':
			case 'database_name':
			case 'database_username':
			case 'database_password':
				$_SESSION['install'][$key] = $value;
		}
	}

	if ($_REQUEST["step"] == "1") {
		$content = $view->render('configuration.tpl');
	}
	if ($_REQUEST["step"] == "2") {
		$content = $view->render('database.tpl');
	}

	if (!empty($content)) {
		$view->assign("content", $content);
		echo $view->render('template.tpl');
	}

	if ($_REQUEST['step'] == "install") {
		$install = new install($config);

		foreach ($_SESSION['install'] as $key => $value) {
			switch ($key) {
				case 'admin_username':
				case 'admin_password':
				case 'domain_name':
				case 'database_host':
				case 'database_port':
				case 'database_name':
				case 'database_username':
				case 'database_password':
					$install->{$key} = $value;
			}
		}

		//update the framework to use the settings from the install pages
		framework::initialize($config);

		$domain_uuid = $install->domain_uuid();
		$domain_name = $install->domain_name();

		//set the session domain id and name
		$_SESSION['domain_uuid'] = $domain_uuid;
		$_SESSION['domain_name'] = $domain_name;

		//schema
		$install->run_update_schema();
		//create admin
		$install->run_update_admin();
		//app defaults
		$install->run_update_domains();
		//menus
		$install->run_update_menu();
		//permissions
		$install->run_update_permissions();

		install::write_config($config);
		header("Location: /logout.php");
	}
} else {
	$msg = "Already Installed";
	//report to user
	message::add($msg);
	//redirect with message
	header("Location: " . PROJECT_PATH . "/index.php?msg=" . urlencode($msg));
	exit;
}
exit();

//process and save the data
if (count($_POST) > 0) {

	if ($_REQUEST["step"] == "install") {

//		//get the superadmin group_uuid
//		$sql = "select group_uuid from v_groups ";
//		$sql .= "where group_name = :group_name ";
//		$parameters['group_name'] = 'superadmin';
//		$database = framework::database();
//		$group_uuid = $database->select($sql, $parameters, 'column');
//		unset($parameters);
//
//		//add the user permission
//		$p = new permissions;
//		$p->add("user_add", "temp");
//		$p->add("user_edit", "temp");
//		$p->add("user_group_add", "temp");
//
//		//save to the user data
//		$array['users'][0]['domain_uuid'] = $domain_uuid;
//		$array['users'][0]['user_uuid'] = $user_uuid;
//		$array['users'][0]['username'] = $admin_username;
//		$array['users'][0]['password'] = $password_hash;
//		$array['users'][0]['salt'] = $user_salt;
//		$array['users'][0]['user_enabled'] = 'true';
//		$array['user_groups'][0]['user_group_uuid'] = uuid();
//		$array['user_groups'][0]['domain_uuid'] = $domain_uuid;
//		$array['user_groups'][0]['group_name'] = 'superadmin';
//		$array['user_groups'][0]['group_uuid'] = $group_uuid;
//		$array['user_groups'][0]['user_uuid'] = $user_uuid;
//		$database = framework::database();
//		$database->app_name = 'users';
//		$database->app_uuid = '112124b3-95c2-5352-7e9d-d14c0b88f207';
//		$database->uuid($user_uuid);
//		$database->save($array);
//		$message = $database->message;
//		unset($array);
//
//		//remove the temporary permission
//		$p->delete("user_add", "temp");
//		$p->delete("user_edit", "temp");
//		$p->delete("user_group_add", "temp");
//
//		//copy the files and directories from resources/install
//		/*
//		  if (!$domain_exists) {
//		  require_once "resources/classes/install.php";
//		  $install = new install;
//		  $install->domain_uuid = $domain_uuid;
//		  $install->domain = $domain_name;
//		  $install->switch_conf_dir = $switch_conf_dir;
//		  $install->copy_conf();
//		  $install->copy();
//		  }
//		 */
//
//		//update xml_cdr url, user and password in xml_cdr.conf.xml
//		if (!$domain_exists) {
//			if (file_exists($_SERVER["DOCUMENT_ROOT"] . PROJECT_PATH . "/app/xml_cdr")) {
//				xml_cdr_conf_xml();
//			}
//		}
//
//		//write the switch.conf.xml file
//		if (!$domain_exists) {
//			if (file_exists($switch_conf_dir)) {
//				switch_conf_xml();
//			}
//		}
//
//		#app defaults
//		$output = shell_exec('cd ' . $_SERVER["DOCUMENT_ROOT"] . ' && php /var/www/fusionpbx/core/upgrade/upgrade_domains.php');

		//install completed - prompt the user to login
		header("Location: /logout.php");
	}
}

?>
