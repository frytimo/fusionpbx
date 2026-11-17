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
	Tim Fry <tim@fusionpbx.com>
*/

/**
 * Shared upgrade tasks used by the upgrade.php command line options and the interactive upgrade menu.
 *
 * Methods print their progress to the console when running from the command line.
 */
class upgrade {

	/**
	 * Database object.
	 *
	 * @var database
	 */
	private $database;

	/**
	 * Settings object.
	 *
	 * @var settings
	 */
	private $settings;

	/**
	 * Translated text for the core/upgrade application.
	 *
	 * @var array
	 */
	private $text;

	/**
	 * Absolute path to the project root.
	 *
	 * @var string
	 */
	private $project_root;

	/**
	 * Constructor for the class.
	 *
	 * @param array $setting_array Optional 'database', 'settings' and 'text' overrides.
	 */
	public function __construct(array $setting_array = []) {
		$this->database = $setting_array['database'] ?? database::new();
		$this->settings = $setting_array['settings'] ?? new settings(['database' => $this->database]);
		$this->text = $setting_array['text'] ?? (new text)->get(null, 'core/upgrade');
		$this->project_root = dirname(__DIR__, 4);
	}

	/**
	 * Creates config.conf from a legacy config.php when config.conf does not exist yet.
	 *
	 * This must run before resources/require.php so it cannot depend on the autoloader or database.
	 *
	 * @return bool True when a new config.conf was written.
	 */
	public static function migrate_config_php(): bool {
		$config_path = '';
		if (stristr(PHP_OS, 'BSD')) {
			$config_path = '/usr/local/etc/fusionpbx';
		}
		elseif (stristr(PHP_OS, 'Linux')) {
			$config_path = '/etc/fusionpbx';
		}
		elseif (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			$config_path = getenv('SystemDrive') . DIRECTORY_SEPARATOR . 'ProgramData' . DIRECTORY_SEPARATOR . 'fusionpbx';
		}
		if (file_exists($config_path . DIRECTORY_SEPARATOR . 'config.php') && !file_exists($config_path . DIRECTORY_SEPARATOR . 'config.conf')) {
			// Include the config.php
			include $config_path . DIRECTORY_SEPARATOR . 'config.php';

			// Set the default config file location
			if (stristr(PHP_OS, 'BSD')) {
				$config_path = '/usr/local/etc/fusionpbx';
				$config_file = $config_path.'/config.conf';
				$document_root = '/usr/local/www/fusionpbx';

				$conf_dir = '/usr/local/etc/freeswitch';
				$sounds_dir = '/usr/share/freeswitch/sounds';
				$database_dir = '/var/lib/freeswitch/db';
				$recordings_dir = '/var/lib/freeswitch/recordings';
				$storage_dir = '/var/lib/freeswitch/storage';
				$voicemail_dir = '/var/lib/freeswitch/storage/voicemail';
				$scripts_dir = '/usr/share/freeswitch/scripts';
				$php_dir = PHP_BINDIR;
				$cache_location = '/var/cache/fusionpbx';
			}
			if (stristr(PHP_OS, 'Linux')) {
				$config_path = '/etc/fusionpbx/';
				$config_file = $config_path.'/config.conf';
				$document_root = '/var/www/fusionpbx';

				$conf_dir = '/etc/freeswitch';
				$sounds_dir = '/usr/share/freeswitch/sounds';
				$database_dir = '/var/lib/freeswitch/db';
				$recordings_dir = '/var/lib/freeswitch/recordings';
				$storage_dir = '/var/lib/freeswitch/storage';
				$voicemail_dir = '/var/lib/freeswitch/storage/voicemail';
				$scripts_dir = '/usr/share/freeswitch/scripts';
				$php_dir = PHP_BINDIR;
				$cache_location = '/var/cache/fusionpbx';
			}
			if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
				$system_drive = getenv('SystemDrive');
				$config_path = $system_drive . DIRECTORY_SEPARATOR . 'ProgramData' . DIRECTORY_SEPARATOR . 'fusionpbx' ;
				$config_file = $config_path.DIRECTORY_SEPARATOR.'config.conf';
				$document_root = dirname(__DIR__, 4);

				$conf_dir = $_SERVER['ProgramFiles'].DIRECTORY_SEPARATOR.'freeswitch'.DIRECTORY_SEPARATOR.'conf';
				$sounds_dir = $_SERVER['ProgramFiles'].DIRECTORY_SEPARATOR.'freeswitch'.DIRECTORY_SEPARATOR.'sounds';
				$database_dir = $_SERVER['ProgramFiles'].DIRECTORY_SEPARATOR.'freeswitch'.DIRECTORY_SEPARATOR.'db';
				$recordings_dir = $_SERVER['ProgramFiles'].DIRECTORY_SEPARATOR.'freeswitch'.DIRECTORY_SEPARATOR.'recordings';
				$storage_dir = $_SERVER['ProgramFiles'].DIRECTORY_SEPARATOR.'freeswitch'.DIRECTORY_SEPARATOR.'storage';
				$voicemail_dir = $_SERVER['ProgramFiles'].DIRECTORY_SEPARATOR.'freeswitch'.DIRECTORY_SEPARATOR.'voicemail';
				$scripts_dir = $_SERVER['ProgramFiles'].DIRECTORY_SEPARATOR.'freeswitch'.DIRECTORY_SEPARATOR.'scripts';
				$php_dir = dirname(PHP_BINARY);
				$cache_location = dirname(dirname(__DIR__, 4)).DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'fusionpbx';
			}

			// Make the config directory
			if (isset($config_path)) {
				system('mkdir -p ' . escapeshellarg($config_path));
			}
			else {
				echo "Config directory not found\n";
				exit;
			}

			// Build the config file
			$conf = "\n";
			$conf .= "#database system settings\n";
			$conf .= "database.0.type = ".$db_type."\n";
			$conf .= "database.0.host = ".$db_host."\n";
			$conf .= "database.0.port = ".$db_port."\n";
			$conf .= "database.0.sslmode = prefer\n";
			$conf .= "database.0.name = ".$db_name."\n";
			$conf .= "database.0.username = ".$db_username."\n";
			$conf .= "database.0.password = ".$db_password."\n";
			$conf .= "\n";
			$conf .= "#database switch settings\n";
			$conf .= "database.1.type = sqlite\n";
			$conf .= "database.1.path = ".$database_dir."\n";
			$conf .= "database.1.name = core.db\n";
			$conf .= "\n";
			$conf .= "#general settings\n";
			$conf .= "document.root = ".$document_root."\n";
			$conf .= "project.path =\n";
			$conf .= "temp.dir = /tmp\n";
			$conf .= "php.dir = ".$php_dir."\n";
			$conf .= "php.bin = php\n";
			$conf .= "\n";
			$conf .= "#security settings\n";
			$conf .= "security.header.content_security_policy.frame_ancestors =\n";
			$conf .= "\n";
			$conf .= "#session settings\n";
			$conf .= "session.cookie_httponly = true\n";
			$conf .= "session.cookie_secure = true\n";
			$conf .= "session.cookie_samesite = Lax\n";
			$conf .= "\n";
			$conf .= "#cache settings\n";
			$conf .= "cache.method = file\n";
			$conf .= "cache.location = ".$cache_location."\n";
			$conf .= "cache.settings = true\n";
			$conf .= "\n";
			$conf .= "#switch settings\n";
			$conf .= "switch.conf.dir = ".$conf_dir."\n";
			$conf .= "switch.sounds.dir = ".$sounds_dir."\n";
			$conf .= "switch.database.dir = ".$database_dir."\n";
			$conf .= "switch.recordings.dir = ".$recordings_dir."\n";
			$conf .= "switch.storage.dir = ".$storage_dir."\n";
			$conf .= "switch.voicemail.dir = ".$voicemail_dir."\n";
			$conf .= "switch.scripts.dir = ".$scripts_dir."\n";
			$conf .= "\n";
			$conf .= "#switch xml handler\n";
			$conf .= "xml_handler.fs_path = false\n";
			$conf .= "xml_handler.reg_as_number_alias = false\n";
			$conf .= "xml_handler.number_as_presence_id = true\n";
			$conf .= "\n";
			$conf .= "#error reporting hide show all errors except notices and warnings\n";
			$conf .= "error.reporting = 'E_ALL ^ E_NOTICE ^ E_WARNING'\n";

			// Write the config file
			if (!file_exists($config_file)) { @touch($config_file); }
			@chmod($config_file, 0640);
			$file_handle = fopen($config_file,"w");
			if (!$file_handle){ echo "Failed to write the config file\n"; exit; }
			fwrite($file_handle, $conf);
			fclose($file_handle);

			//restrict the config file permissions (contains database credentials)
			@chmod($config_file, 0640);
			echo "Created " . rtrim($config_file, '/\\') . " from " . rtrim($config_path, '/\\') . DIRECTORY_SEPARATOR . "config.php\n";
			return true;
		}
		return false;
	}

	/**
	 * Pulls the latest changes for the main application.
	 *
	 * @return bool True when the pull succeeded.
	 */
	public function pull_main(): bool {
		$this->output("[ Update ] The main application\n");

		$git_result = git_pull($this->project_root);
		foreach ((array)($git_result['message'] ?? []) as $response_line) {
			$this->output($this->project_root . ": " . $response_line . "\n");
		}
		return !empty($git_result['result']);
	}

	/**
	 * Pulls the latest changes for each optional application repository.
	 *
	 * @return bool True when every repository was pulled successfully.
	 */
	public function pull_optional(): bool {
		$this->output("[ Update ] The optional applications\n");

		$success = true;
		$messages = [];
		foreach (git_find_repos($this->project_root . "/app") as $repo => $row) {
			$this->output($row[0] . "\n");

			$git_result = git_pull($repo);
			if ($git_result['result']) {
				$messages[$repo] = $this->text['message-optional_apps_upgrade_source_cli'] . (!empty($git_result['message']) && is_array($git_result['message']) ? ' - '.implode("\n", $git_result['message']) : '');
			}
			else {
				$success = false;
				if (!empty($git_result['message']) && is_array($git_result['message'])) {
					$message = "ERROR:\n" . implode("\n", $git_result['message']);
				} else {
					$message = $git_result['message'];
				}
				$messages[$repo] = $this->text['message-optional_apps_upgrade_source_failed_cli'] . " - " . $message;
			}
		}
		foreach ($messages as $repo => $message) {
			$this->output($repo . ": " . $message . "\n");
		}
		return $success;
	}

	/**
	 * Updates the database tables, columns and data types.
	 *
	 * @param string $format Schema output format. 'text' includes the executed SQL statements.
	 *
	 * @return string The schema upgrade response.
	 */
	public function upgrade_schema(string $format = ''): string {
		$this->output("[ Update ] Table and field structure.\n");

		$schema = new schema(['database' => $this->database]);
		$response = (string)$schema->upgrade($format);
		foreach (explode("\n", $response) as $row) {
			if (trim($row) !== '') {
				$this->output(trim($row) . "\n");
			}
		}
		return $response;
	}

	/**
	 * Restores the application defaults.
	 *
	 * Adds the nginx websocket location, updates the php-fpm service and runs all app_defaults.php files.
	 *
	 * @return void
	 */
	public function upgrade_defaults() {
		$this->output("[ Update ] Restore application defaults.\n");

		$this->update_nginx_websockets();
		$this->update_php_fpm();

		$domain = new domains;
		$domain->upgrade();
	}

	/**
	 * Adds the websocket proxy location to the nginx configuration when it is missing.
	 *
	 * Only runs from the command line.
	 *
	 * @return bool True when the nginx configuration was updated.
	 */
	public function update_nginx_websockets(): bool {
		if (!is_cli()) {
			return false;
		}

		$nginx_path = '/etc/nginx/sites-enabled/fusionpbx';
		if (!file_exists($nginx_path)) {
			return false;
		}

		// Get the nginx configuration
		$nginx_config = file_get_contents($nginx_path);
		if (strpos($nginx_config, '/websockets/') !== false) {
			return false;
		}

		// Define the location block to add
		$websocket_settings = "        #redirect websockets to port 8080\n";
		$websocket_settings .= "                location /websockets/ {\n";
		$websocket_settings .= "                proxy_pass http://127.0.0.1:8080;\n";
		$websocket_settings .= "                proxy_http_version 1.1;\n";
		$websocket_settings .= "                proxy_set_header Upgrade \$http_upgrade;\n";
		$websocket_settings .= "                proxy_set_header Connection \"upgrade\";\n";
		$websocket_settings .= "                proxy_set_header Host \$host;\n";
		$websocket_settings .= "        }\n";
		$websocket_settings .= "\n";

		// Insert before the letsencrypt location inside the ssl server block
		$updated = false;
		$ssl_found = false;
		$character_count = 0;
		foreach (explode("\n", $nginx_config) as $line) {
			// Count each line and add an additional character for the line feed
			$character_count += strlen($line) + 1;

			if (trim($line) == 'listen 443 ssl;') {
				$ssl_found = true;
			}

			if ($ssl_found && trim($line) == '#redirect letsencrypt to dehydrated') {
				$new_config = substr_replace($nginx_config, $websocket_settings, $character_count - strlen($line."\n"), 0);
				if (file_put_contents($nginx_path, $new_config) !== false) {
					$this->output("Websockets configuration updated.\n");
					$updated = true;
				}
			}
		}
		return $updated;
	}

	/**
	 * Adds the ReadWritePaths needed by FusionPBX to the php-fpm systemd service.
	 *
	 * @return void
	 */
	public function update_php_fpm() {
		if (PHP_OS !== 'Linux') {
			return;
		}

		// Get the PHP version
		$php_version =  PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

		// Set the path for PHP FPM service file
		$service_file = "/usr/lib/systemd/system/php".$php_version."-fpm.service";

		// Check if the service file exists
		if (!file_exists($service_file)) {
			$this->output("The service file does not exist\n");
			return;
		}

		// Check if the service file is writable
		if (!is_writable($service_file)) {
			$this->output("The service file is not writable\n");
			return;
		}

		// Make sure the /usr/share/fusionpbx directory exists
		if (!file_exists('/usr/share/fusionpbx')) {
			if (!mkdir('/usr/share/fusionpbx', 0755, true)) {
				$this->output("Failed to create directory: /usr/share/fusionpbx\n");
			}
		}

		// Build the read write paths array
		$read_write_paths = [];
		$read_write_paths[] = '/tmp';
		$read_write_paths[] = '/etc/freeswitch';
		$read_write_paths[] = '/usr/share/freeswitch';
		$read_write_paths[] = '/var/lib/freeswitch';
		$read_write_paths[] = $this->settings->get('cache', 'location', '/var/cache/fusionpbx');
		$read_write_paths[] = '/usr/share/fusionpbx';
		$read_write_paths[] = '/etc/fusionpbx'; // the install wizard writes config.conf here (password recovery)

		// Build the line to add to the service file
		$line_to_insert = "ReadWritePaths=";
		foreach ($read_write_paths as $read_write_path) {
			if (file_exists($read_write_path)) {
				$line_to_insert .= $read_write_path.' ';
			}
		}

		// Get the file contents
		$file_contents = file_get_contents($service_file);
		$new_contents = '';

		// Replace an existing ReadWritePaths line when it differs
		if (strpos($file_contents, 'ReadWritePaths') !== false) {
			$new_contents = preg_replace('/^ReadWritePaths.*$/m', trim($line_to_insert), $file_contents);
			if ($file_contents === $new_contents) {
				$new_contents = '';
			}
		}
		// Otherwise insert it on the empty line before [Install]
		else {
			$lines = explode("\n", $file_contents);
			$insert_index = -1;
			for ($i = 0; $i < count($lines); $i++) {
				if (trim($lines[$i]) === '[Install]') {
					for ($j = $i - 1; $j >= 0; $j--) {
						if (trim($lines[$j]) === '') {
							$insert_index = $j;
							break;
						}
					}
					break;
				}
			}
			array_splice($lines, $insert_index, 0, trim($line_to_insert));
			$new_contents = implode("\n", $lines) . "\n";
		}

		// Update the service file, daemon-reload, and restart service
		if (!empty($new_contents)) {
			file_put_contents($service_file, $new_contents);
			system('systemctl daemon-reload');
			system('systemctl restart ' . escapeshellarg('php' . $php_version . '-fpm'));
		}
	}

	/**
	 * Gets the menus.
	 *
	 * @return array Rows with menu_uuid, menu_name and menu_language.
	 */
	public function get_menus(): array {
		$sql = "select menu_uuid, menu_name, menu_language ";
		$sql .= "from v_menus ";
		return $this->database->select($sql, null, 'all') ?: [];
	}

	/**
	 * Gets the names of the menus.
	 *
	 * @return array List of menu names.
	 */
	public function list_menus(): array {
		$names = [];
		foreach ($this->get_menus() as $row) {
			if (!empty($row)) {
				$names[] = $row['menu_name'];
			}
		}
		return $names;
	}

	/**
	 * Restores the menu named 'default' to its default items.
	 *
	 * @return bool False when there is no menu named 'default'.
	 */
	public function restore_default_menu(): bool {
		$this->output("[ Update ] Restore the default menu\n");

		$menu_uuid = null;
		$menu_language = null;
		foreach ($this->get_menus() as $row) {
			if ($row['menu_name'] == 'default') {
				$menu_uuid = $row['menu_uuid'];
				$menu_language = $row['menu_language'];
				break;
			}
		}
		if (empty($menu_uuid)) {
			$this->output("The default menu was not found\n");
			return false;
		}

		// Variables used by the included file
		$included = true;
		$database = $this->database;
		$settings = $this->settings;
		require $this->project_root . "/core/menu/menu_restore_default.php";

		$this->output("\n");
		return true;
	}

	/**
	 * Restores the default groups and group permissions.
	 *
	 * @return void
	 */
	public function restore_group_permissions() {
		$this->output("[ Update ] Restore the group permissions\n");

		// Default the groups in case they are missing
		$groups = new groups;
		$groups->defaults();

		// Variables used by the included file
		$included = true;
		$database = $this->database;
		$settings = $this->settings;
		require $this->project_root . "/core/groups/permissions_default.php";

		$this->output("\n");
	}

	/**
	 * Restores the file ownership to the web server user.
	 *
	 * Only runs as root.
	 *
	 * @return bool False when not running as root.
	 */
	public function restore_file_permissions(): bool {
		$this->output("[ Update ] Restore the file permissions\n");

		if (posix_getuid() !== 0) {
			$this->output(($this->text['label-not_running_as_root'] ?? "Not root user - operation skipped") . "\n");
			return false;
		}

		$directories = [];

		// Adjust the project root
		$directories[] = $this->project_root;

		// Adjust the /etc/freeswitch
		$directories[] = $this->settings->get('switch', 'conf', null);
		$directories[] = $this->settings->get('switch', 'call_center', null); //normally in conf but can be different
		$directories[] = $this->settings->get('switch', 'dialplan', null); //normally in conf but can be different
		$directories[] = $this->settings->get('switch', 'directory', null); //normally in conf but can be different
		$directories[] = $this->settings->get('switch', 'languages', null); //normally in conf but can be different
		$directories[] = $this->settings->get('switch', 'sip_profiles', null); //normally in conf but can be different

		// Adjust the /usr/share/freeswitch/{scripts,sounds}
		$directories[] = $this->settings->get('switch', 'scripts', null);
		$directories[] = $this->settings->get('switch', 'sounds', null);

		// Adjust the /var/lib/freeswitch/{db,recordings,storage,voicemail}
		$directories[] = $this->settings->get('switch', 'db', null);
		$directories[] = $this->settings->get('switch', 'recordings', null);
		$directories[] = $this->settings->get('switch', 'storage', null);
		$directories[] = $this->settings->get('switch', 'voicemail', null); //normally included in storage but can be different

		// Adjust the service runtime directory
		$directories[] = '/var/run/fusionpbx';

		// Only set the xml_cdr directory permissions
		$log_directory = $this->settings->get('switch', 'log', null);
		if ($log_directory !== null) {
			$directories[] = $log_directory . '/xml_cdr';
		}

		// Adjust the autoloader cache file
		$directories[] = sys_get_temp_dir() . '/' . auto_loader::CLASSES_FILE;

		// The web server user is www-data on Linux but www on BSD systems
		$web_user = stristr(PHP_OS, 'BSD') ? 'www' : 'www-data';

		foreach (array_unique(array_filter($directories)) as $dir) {
			// Skip /dev/shm and paths that do not exist on this system
			if (strpos($dir, '/dev/shm') !== false || !file_exists($dir)) {
				continue;
			}

			exec("chown -R " . escapeshellarg($web_user . ':' . $web_user) . " " . escapeshellarg($dir), $chown_output, $chown_return);
			if ($chown_return !== 0) {
				$this->output("WARNING: failed to change ownership of $dir (chown exit code $chown_return)\n");
			}
		}

		$this->output("\n");
		return true;
	}

	/**
	 * Restores the file ownership and the default group permissions.
	 *
	 * @return void
	 */
	public function restore_permissions() {
		$this->restore_file_permissions();
		$this->restore_group_permissions();
	}

	/**
	 * Installs, stops, starts, restarts or reloads the services.
	 *
	 * An empty action installs new or changed services and then restarts all enabled services.
	 *
	 * @param string $action One of '', 'update', 'stop', 'start', 'restart' or 'reload'.
	 *
	 * @return bool False when the action is unknown.
	 */
	public function services(string $action = ''): bool {
		if (!in_array($action, self::service_actions(), true)) {
			$this->output("Unknown service action: " . preg_replace('/[^a-zA-Z0-9_-]/', '', $action) . "\n");
			$this->output("Valid actions: " . implode(', ', array_filter(self::service_actions())) . "\n");
			return false;
		}

		// Add missing services
		$service = new services(['database' => $this->database]);
		$service->add_missing();

		if ($action === '' || $action === 'update') {
			$this->output("[ Update ] Install new or changed services\n");
			// The restart below starts the services when no action is given
			$service->upgrade('all', $action === 'update');
		}

		if ($action === 'stop') {
			$this->output("[ Update ] Stop services\n");
			$service->stop('all');
		}

		if ($action === 'start') {
			$this->output("[ Update ] Start services\n");
			$service->start('all');
		}

		if ($action === 'reload') {
			$this->output("[ Update ] Reload services (SIGHUP)\n");
			$service->send_sighup('all');
		}

		if ($action === '' || $action === 'restart') {
			$this->output("[ Update ] Restart services\n");
			$service->restart('all');
		}

		return true;
	}

	/**
	 * Gets the valid service actions.
	 *
	 * @return array The service actions. An empty string is the default action.
	 */
	public static function service_actions(): array {
		return ['', 'update', 'stop', 'start', 'restart', 'reload'];
	}

	/**
	 * Runs every upgrade step except pulling the source code.
	 *
	 * @return void
	 */
	public function run_all() {
		$this->upgrade_schema('text');
		$this->upgrade_defaults();
		$this->restore_default_menu();
		$this->restore_permissions();
		$this->services('');
	}

	/**
	 * Replaces the current process with a new upgrade.php process so newly pulled code is used.
	 *
	 * Only returns when the process could not be replaced.
	 *
	 * @param array $arguments Command line arguments for upgrade.php.
	 *
	 * @return void
	 */
	public static function exec_upgrade(array $arguments) {
		$script = dirname(__DIR__, 2) . '/upgrade.php';
		if (function_exists('pcntl_exec')) {
			// Flush buffered output before the process image is replaced
			while (ob_get_level() > 0) {
				ob_end_flush();
			}
			flush();
			@pcntl_exec(PHP_BINARY, array_merge([$script], $arguments));
		}
		echo "Unable to start a new upgrade process. Run: " . PHP_BINARY . " " . $script . " " . implode(' ', $arguments) . "\n";
	}

	/**
	 * Writes a message to the console when running from the command line.
	 *
	 * @param string $message The message.
	 *
	 * @return void
	 */
	private function output(string $message) {
		if (is_cli()) {
			echo $message;
		}
	}

}
