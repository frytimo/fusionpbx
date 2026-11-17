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

// Create config.conf from a legacy config.php before the configuration is loaded
	require_once __DIR__ . '/resources/classes/upgrade.php';
	upgrade::migrate_config_php();

// Include file
	require dirname(__DIR__, 2) . "/resources/require.php";

// Check the permission
	if (defined('STDIN')) {
		$display_type = 'text'; //html, text
	}
	else {
		require_once "resources/check_auth.php";
		if (!(permission_exists('upgrade_schema') || permission_exists('upgrade_source'))) {
			echo "access denied";
			exit;
		}
		// Reject requests initiated from another site, this page updates the database and defaults
		if (!token::same_origin()) {
			echo "access denied";
			exit;
		}
		$display_type = 'html'; //html, text
	}

// Get the command line arguments
	$upgrade_type = '';
	$upgrade_action = '';
	if (defined('STDIN')) {
		$upgrade_type = $argv[1] ?? '';
		$upgrade_action = $argv[2] ?? '';
	}

// Use upgrade language file
	$language = new text;
	$text = $language->get(null, 'core/upgrade');

// Always update the now global autoload cache just-in-case the source files have updated
	$autoload->update();

// Trigger clear cache for any classes that require it
	foreach ($autoload->get_interface_list('clear_cache') as $class) {
		$class::clear_cache();
	}

// Show the title
	if ($display_type == 'text') {
		echo "\n";
	}

// Map each option alias to the task it runs
	$options = [
		'' => 'default',
		'help' => 'help', '-h' => 'help', '--help' => 'help',
		'version' => 'version', '-v' => 'version', '--version' => 'version',
		'main' => 'main', '-n' => 'main', '--main' => 'main',
		'optional' => 'optional', '-o' => 'optional', '--optional' => 'optional',
		'schema' => 'schema', '-s' => 'schema', '--schema' => 'schema',
		'defaults' => 'defaults', '-d' => 'defaults', '--defaults' => 'defaults',
		'menu' => 'menu', '-m' => 'menu', '--menu' => 'menu',
		'permissions' => 'permissions', '-p' => 'permissions', '--permissions' => 'permissions',
		'group' => 'group', '-g' => 'group', '--group' => 'group',
		'file' => 'file', '-f' => 'file', '--file' => 'file',
		'services' => 'services', '-u' => 'services', '--service' => 'services', '--services' => 'services',
		'all' => 'all', '-a' => 'all', '--all' => 'all',
		'interactive' => 'interactive', '-i' => 'interactive', '--interactive' => 'interactive',
	];

	$upgrade = new upgrade(['database' => $database, 'settings' => $settings, 'text' => $text]);

	switch ($options[$upgrade_type] ?? 'unknown') {
		case 'help':
			echo "Usage:\n";
			echo "  upgrade.php [-hvnostdmpgfuai] [--menu [default|list]] [--services [update|stop|start|restart|reload]]\n";
			echo "  A terminal-based upgrade tool used to simplify or automate the upgrade.\n";
			echo "  With no option the database schema and application defaults are updated.\n";
			echo "\n";
			echo "Options:\n";
			echo "  -h --help                              Show this help message.\n";
			echo "  -v --version                           Show the version.\n";
			echo "  -n --main                              Update the main application.\n";
			echo "  -o --optional                          Update the optional applications.\n";
			echo "  -s --schema                            Update the database tables, columns and data types.\n";
			echo "  -d --defaults                          Restore application defaults.\n";
			echo "  -m --menu [default|list]               Restore menu default or show the menu list\n";
			echo "  -p --permissions                       Restore default file and group permissions.\n";
			echo "  -g --group                             Restore default group permissions.\n";
			echo "  -f --file                              Update the file permissions.\n";
			echo "  -u --services [action]                 Install new or changed services and restart all services.\n";
			echo "                                         update   Only install new or changed services.\n";
			echo "                                         stop     Stop all services.\n";
			echo "                                         start    Start all enabled services.\n";
			echo "                                         restart  Restart all enabled services.\n";
			echo "                                         reload   Send SIGHUP to running services to reload settings.\n";
			echo "  -a --all                               Run schema, defaults, menu, permissions and services.\n";
			echo "  -i --interactive                       Show the interactive menu.\n";
			echo "\n";
			break;

		case 'version':
			echo software::version()."\n";
			break;

		case 'main':
			exit($upgrade->pull_main() ? 0 : 1);

		case 'optional':
			exit($upgrade->pull_optional() ? 0 : 1);

		case 'schema':
			$upgrade->upgrade_schema();
			break;

		case 'defaults':
			$upgrade->upgrade_defaults();
			break;

		case 'menu':
			if ($upgrade_action === 'list') {
				echo "Menu List\n";
				foreach ($upgrade->list_menus() as $menu_name) {
					echo $menu_name."\n";
				}
				echo "\n";
			}
			elseif ($upgrade_action === '' || $upgrade_action === 'default') {
				exit($upgrade->restore_default_menu() ? 0 : 1);
			}
			else {
				echo "Valid menu actions: default, list\n";
				exit(1);
			}
			break;

		case 'permissions':
			$upgrade->restore_permissions();
			break;

		case 'group':
			$upgrade->restore_group_permissions();
			break;

		case 'file':
			exit($upgrade->restore_file_permissions() ? 0 : 1);

		case 'services':
			exit($upgrade->services($upgrade_action) ? 0 : 1);

		case 'all':
			$upgrade->run_all();
			break;

		case 'interactive':
			require __DIR__ . '/upgrade_menu.php';
			exit();

		case 'default':
			// Update the database schema and run the application defaults
			$upgrade->upgrade_schema('text');
			$upgrade->upgrade_defaults();

			// Show the content
			if ($display_type == 'html') {
				echo "<div align='center'>\n";
				echo "<table width='40%'>\n";
				echo "<tr>\n";
				echo "<th align='left'>".$text['header-message']."</th>\n";
				echo "</tr>\n";
				echo "<tr>\n";
				echo "<td class='row_style1'><strong>".$text['message-upgrade']."</strong></td>\n";
				echo "</tr>\n";
				echo "</table>\n";
				echo "</div>\n";

				echo "<br />\n";
				echo "<br />\n";
				echo "<br />\n";
				echo "<br />\n";
				echo "<br />\n";
				echo "<br />\n";
				echo "<br />\n";

				require_once "resources/footer.php";
			}
			else {
				echo "\n";
			}
			break;

		default:
			echo "Unknown option: " . preg_replace('/[^a-zA-Z0-9_-]/', '', $upgrade_type) . "\n";
			echo "Use --help to show the available options.\n";
			exit(1);
	}
