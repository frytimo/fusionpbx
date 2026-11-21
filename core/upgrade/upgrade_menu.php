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
  Tim Fry <tim.fry@hotmail.com>
 */

//check the permission
defined('STDIN') or die('Unauthorized');

//include files
require_once dirname(__DIR__, 2) . "/resources/require.php";

//get the language code from global defaults
$language_code = $settings->get('domain', 'language');

//get the software name
$software_name = $settings->get('theme', 'title');

//set the scope for text to be used in any function
global $text, $display_type;

//add multi-lingual support
$language = new text;
$text = $language->get($language_code, 'core/upgrade');

//output to text type instead of html
$display_type = 'text';

//run the upgrade menu
show_upgrade_menu(new upgrade(['database' => $database, 'settings' => $settings, 'text' => $text]));

/**
 * Display the upgrade menu to the user.
 *
 * This function displays a menu with various upgrade options and prompts the
 * user for input. Based on the user's selection, it runs the corresponding
 * shared upgrade task.
 *
 * @param upgrade $upgrade The shared upgrade tasks.
 *
 * @return void
 *
 * @global text $text
 * @global string $software_name
 */
function show_upgrade_menu(upgrade $upgrade) {
	//set the global variables
	global $text, $software_name;

	//build the options array
	$options = [
		['1', $text['label-upgrade_source'], $text['description-update_all_source_files']],
		['1a', $text['label-main_software'], $text['description-main_software']],
		['1b', $text['label-optional_applications'], $text['description-optional_applications']],
		['2', $text['label-upgrade_schema'], $text['description-upgrade_schema']],
		['3', $text['label-upgrade_apps'], $text['description-upgrade_apps']],
		['4', $text['label-upgrade_menu'], $text['description-upgrade_menu']],
		['5', $text['label-upgrade_permissions'], $text['description-upgrade_permissions']],
		['6', $text['label-update_file_permissions'], $text['description-update_file_permissions']],
		['7', $text['label-upgrade_services'], $text['description-upgrade_services']],
		['8', $text['label-restart_services'], $text['description-restart_services']]
	];

	//show the menu
	while (true) {
		//show the upgrade title
		echo $text['title-cli_upgrade'] . "\n";
		echo "  ".$software_name . " " . show_software_version();
		echo "\n";

		//show the command menu
		echo "Options:\n";
		foreach ($options as [$key, $label, $description]) {
			if (!is_numeric($key)) { echo "    "; }
			echo "  $key) $label" . ((!empty($option) && $option == 'h') ? " - " . $description : "") . "\n";
		}
		echo "\n";
		echo "  a) All\n";
		echo "  h) Help\n";
		echo "  q) Quit\n";

		//prompt for user input
		echo "\n";
		echo "Enter an Option: ";

		//read the input and then call the correction actions
		$option = readline();
		switch ($option) {
			case 1:
				//the code on disk changed so exit instead of running the old code
				$upgrade->pull_main();
				$upgrade->pull_optional();
				exit();
			case '1a':
				$upgrade->pull_main();
				exit();
			case '1b':
				$upgrade->pull_optional();
				break;
			case 2:
				$upgrade->upgrade_schema('text');
				break;
			case 3:
				$upgrade->upgrade_defaults();
				break;
			case 4:
				$upgrade->restore_default_menu();
				break;
			case 5:
				$upgrade->restore_group_permissions();
				break;
			case 6:
				echo ($text['label-header1'] ?? "Root account or sudo account must be used for this option") . "\n";
				echo ($text['label-header2'] ?? "This option is used for resetting the permissions on the filesystem after executing commands using the root user account") . "\n";
				$upgrade->restore_file_permissions();
				break;
			case 7:
				$upgrade->services('update');
				break;
			case 8:
				$upgrade->services('restart');
				break;
			case 'a':
				//pull the code then replace this process so the remaining steps run the new code
				$upgrade->pull_main();
				$upgrade->pull_optional();
				upgrade::exec_upgrade(['--all']);
				exit(1);
			case 'q':
				exit();
		}

		//add a few line feeds
		echo "\n\n";
	}
}

/**
 * Returns a string containing the current version of the software.
 *
 * @return string|null The software version as a string, or null if not available.
 */
function show_software_version(): ?string {
	return software::version() . "\n";
}
