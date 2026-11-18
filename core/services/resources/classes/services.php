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

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

/**
 * services class
 */
class services {

	/**
	 * The name of the application, used for permissions and other references.
	 * @var string
	 */
	const app_name = 'services';

	/**
	 * The UUID of the application, used for permissions and other references.
	 * @var string
	 */
	const app_uuid = '540c3ec2-4f0c-467f-a09d-d644439c96f2';

	/**
	 * Set in the constructor. Must be a database object and cannot be null.
	 *
	 * @var database Database Object
	 */
	private $database;

	/**
	 * Name of the service
	 * @var string
	 */
	private $name;

	/**
	 * Name of the table in the database
	 * @var string
	 */
	private $table;

	/**
	 * Name of the field used to toggle the state of the record
	 * @var string
	 */
	private $toggle_field;

	/**
	 * Values used to toggle the state of the record
	 * @var array
	 */
	private $toggle_values;

	/**
	 * Name of the field used for the description of the record
	 * @var string
	 */
	private $description_field;

	/**
	 * Path of the services page for redirection after actions
	 * @var string
	 */
	private $location;

	/**
	 * Constructor for the class.
	 *
	 * This method initializes the object with setting_array and session data.
	 *
	 * @param array $setting_array An optional array of settings to override default values. Defaults to [].
	 */
	public function __construct(array $setting_array = []) {
		// Set objects
		$this->database = $setting_array['database'] ?? database::new();

		// Assign the variables
		$this->name = 'service';
		$this->table = 'services';
		$this->toggle_field = 'service_enabled';
		$this->toggle_values = ['true','false'];
		$this->description_field = 'service_description';
		$this->location = 'services.php';
	}

	/**
	 * Deletes one or multiple records.
	 *
	 * @param array $records An array of record IDs to delete, where each ID is an associative array
	 *                       containing 'uuid' and 'checked' keys. The 'checked' value indicates
	 *                       whether the corresponding checkbox was checked for deletion.
	 *
	 * @return void No return value; this method modifies the database state and sets a message.
	 */
	public function delete($records) {
		// Permission not found
		if (!permission_exists($this->name.'_delete')) {
			return;
		}

		// Add multi-lingual support
		$language = new text;
		$text = $language->get();

		// Validate the token
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-invalid_token'],'negative');
			header('Location: '.$this->location);
			exit;
		}

		// Delete multiple records
		if (is_array($records) && @sizeof($records) != 0) {
			$array = [];
			// Build the delete array
			$x = 0;
			foreach ($records as $record) {
				// Add to the array
				if (is_array($record) && ($record['checked'] ?? '') == 'true' && is_uuid($record['uuid'] ?? '')) {
					$array[$this->table][$x][$this->name.'_uuid'] = $record['uuid'];
				}

				// Increment the id
				$x++;
			}

			// Delete the checked rows
			if (is_array($array) && @sizeof($array) != 0) {
				//execute delete
				$this->database->delete($array);
				unset($array);

				// Set the message
				message::add($text['message-delete'], 'alert');
			}
			unset($records);
		}
	}

	/**
	 * Toggles the state of one or more records.
	 *
	 * @param array $records  An array of record IDs to delete, where each ID is an associative array
	 *                        containing 'uuid' and 'checked' keys. The 'checked' value indicates
	 *                        whether the corresponding checkbox was checked for deletion.
	 *
	 * @return void No return value; this method modifies the database state and sets a message.
	 */
	public function toggle($records) {
		// Permission not found
		if (!permission_exists($this->name.'_edit')) {
			return;
		}

		// Add multi-lingual support
		$language = new text;
		$text = $language->get();

		// Validate the token
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-invalid_token'],'negative');
			header('Location: '.$this->location);
			exit;
		}

		// Toggle the checked records
		if (is_array($records) && @sizeof($records) != 0) {
			$uuids = [];
			$states = [];
			$services = '';
			// Get current toggle state
			foreach($records as $record) {
				if (is_array($record) && ($record['checked'] ?? '') == 'true' && is_uuid($record['uuid'] ?? '')) {
					$uuids[] = "'".$record['uuid']."'";
				}
			}
			if (is_array($uuids) && @sizeof($uuids) != 0) {
				$sql = "select ".$this->name."_uuid as uuid, ".$this->toggle_field." as toggle from v_".$this->table." ";
				$sql .= "where ".$this->name."_uuid in (".implode(', ', $uuids).") ";
				$rows = $this->database->select($sql, null, 'all');
				if (is_array($rows) && @sizeof($rows) != 0) {
					foreach ($rows as $row) {
						$states[$row['uuid']] = $row['toggle'];
					}
				}
				unset($sql, $parameters, $rows, $row);
			}

			// Build the update array
			$array = [];
			$x = 0;
			foreach($states as $uuid => $state) {
				// Create the array
				$array[$this->table][$x][$this->name.'_uuid'] = $uuid;
				$array[$this->table][$x][$this->toggle_field] = $state == $this->toggle_values[0] ? $this->toggle_values[1] : $this->toggle_values[0];

				// Increment the id
				$x++;
			}

			// Save the changes
			if (is_array($array) && @sizeof($array) != 0) {
				// Save the array
				$this->database->save($array);
				unset($array);

				// Set the message
				message::add($text['message-toggle']);
			}
			unset($records, $states);
		}
	}

	/**
	 * Reload the state of a service without restarting it
	 *
	 * @param array $records  An array of services to reload, where each ID is an associative array
	 *                        containing 'uuid' and 'checked' keys. The 'checked' value indicates
	 *                        whether the corresponding checkbox was checked to reload the service.
	 *
	 * @return void No return value;
	 */
	public function reload($records) {
		// Permission not found
		if (!permission_exists($this->name.'_edit')) {
			return;
		}

		// Add multi-lingual support
		$language = new text;
		$text = $language->get();

		// Validate the token
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-invalid_token'],'negative');
			header('Location: '.$this->location);
			exit;
		}

		// reloaad the checked services
		if (is_array($records) && @sizeof($records) != 0) {
			$services = '';
			$uuids = [];
			// Get current reload state
			foreach($records as $record) {
				if (is_array($record) && ($record['checked'] ?? '') == 'true' && is_uuid($record['uuid'] ?? '')) {
					$uuids[] = "'".$record['uuid']."'";
				}
			}
			// Reload the selected services
			if (is_array($uuids) && @sizeof($uuids) != 0) {
				$sql = "select service_name as name from v_".$this->table." ";
				$sql .= "where ".$this->name."_uuid in (".implode(', ', $uuids).") ";
				$rows = $this->database->select($sql, null, 'all');
				if (is_array($rows) && @sizeof($rows) != 0) {
					foreach ($rows as $row) {
						$service_name = $row['name'];
						$service_class_name = $row['name'].'_service';
						if (class_exists($service_class_name)) {
							if (method_exists($service_class_name, 'send_reload')) {
								// Reload the service
								$service_class_name::send_reload();

								// Add to the list of services that were reloaded
								$services .= "<li>".$service_name."</li>\n";
							}
						}
					}
				}

				// Set the message
				$msg = "<strong>".$text['message-services_reloaded'].":</strong><br />\n";
				$msg .= "<div style='display: flex; justify-content: center;'>\n";
				$msg .= "	<ul style='text-align: left; margin: 0;'>\n";
				$msg .= $services;
				$msg .= "	</ul>\n";
				$msg .= "</div>\n";
				message::add($msg);
			}
		}
	}

	/**
	 * Get the list of services
	 *
	 * This function iterates through all service files found in the application's
	 * core and app directories, and builds an array of the services
	 *
	 * @param bool $details Whether to include details about the service status (default: false)
	 * @param string $source The source to retrieve services from, either 'database' or 'files' (default: 'database')
	 *
	 * @return array Return a list of services with name
	 */
	public function get_services($details = false, $source = 'database'): array {
		$services = [];

		if ($source == 'files') {
			// Determine the search file name
			if (stristr(PHP_OS, 'Linux')) {
				$operating_system = 'debian';
			}
			if (stristr(PHP_OS, 'FreeBSD')) {
				$operating_system = 'freebsd';
			}

			// Get the list of services
			$core_files = glob(dirname(__DIR__, 4) . "/core/*/resources/service/".$operating_system."/*.service");
			$app_files = glob(dirname(__DIR__, 4) . "/app/*/resources/service/".$operating_system."/*.service");
			$service_files = array_merge($core_files, $app_files);

			// Additional files for backwards compatibility
			if (stristr(PHP_OS, 'Linux')) {
				$additional_services = glob(dirname(__DIR__, 4) . "/app/*/resources/service/".$operating_system."/debian.service");
				if (!empty($additional_services)) {
					$service_files = array_merge($service_files, $additional_services);
				}
				$additional_services = glob(dirname(__DIR__, 4) . "/app/*/resources/service/*.service");
				if (!empty($additional_services)) {
					$service_files = array_merge($service_files, $additional_services);
				}
			}

			// Build the services array
			$services = [];

			$i = 0;
			$service_status = [];
			foreach($service_files as $file) {
				// Get the service name
				$service_name = $this->find_service_name($file);

				// Service name empty skip iteration
				if (empty($service_name)) {
					continue; 
				}

				// Get the service status
				if ($details) {
					$service_status = $this->is_running($service_name);
				}

				// Build the services array
				$services[$i]['name'] = $service_name;
				$services[$i]['file'] = $file;

				// Add the service status to the array
				if ($details) {
					$services[$i]['pid'] = $service_status['pid'];
					$services[$i]['status'] = $service_status['status'];
					$services[$i]['etime'] = $service_status['etime'];
				}

				// Increment
				$i++;
			}
		}

		if ($source == 'database') {
			// Get the list
			$sql = "select ";
			$sql .= " service_uuid, ";
			$sql .= " service_name, ";
			$sql .= " service_category, ";
			$sql .= " service_file, ";
			$sql .= " cast(service_enabled as text), ";
			$sql .= " service_description ";
			$sql .= "from v_services ";
			$sql .= "order by service_name asc";
			$database_services = $this->database->select($sql, $parameters ?? null, 'all');
			unset($sql, $parameters);

			$services = [];

			$i = 0;
			$service_status = [];
			foreach($database_services as $row) {
				// Service name empty skip iteration
				if (empty($row['service_name'])) {
					continue; 
				}

				// Get the service status
				if ($details) {
					$service_status = $this->is_running($row['service_name']);
				}

				// Build the services array
				$services[$i]['name'] = $row['service_name'];
				$services[$i]['file'] = $row['service_file'];
				$services[$i]['enabled'] = $row['service_enabled'];
				//$services[$i]['file'] = $file;

				// Add the service status to the array
				if ($details) {
					$services[$i]['pid'] = $service_status['pid'];
					$services[$i]['status'] = $service_status['status'];
					$services[$i]['etime'] = $service_status['etime'];
				}

				// Increment
				$i++;
			}

		}

		// Return the service array
		return $services;
	}

	/**
	 * Add missing services into the database
	 *
	 * This function adds missing services
	 * into the services table in the database
	 *
	 * @return void No return value; this method modifies the database.
	 */
	public function add_missing() {
		// Add multi-lingual support
		$language = new text;
		$text = $language->get();

		// Check for existing table - needed for first run when the table may not exist yet during command line upgrade
		if (!$this->database->table_exists(database::TABLE_PREFIX . $this->table)) {
			return;
		}

		// Get the list of services
		$service_array = $this->get_services(false, 'files');

		// Service mapped to the category
		$service_map['api'] = 'system';
		$service_map['call_center_callbacks'] = 'switch';
		$service_map['campaign_logs'] = 'switch';
		$service_map['campaign_queue'] = 'switch';
		$service_map['websockets'] = 'websockets';
		$service_map['active_calls'] = 'websockets';
		$service_map['active_conferences'] = 'websockets';
		$service_map['maintenance_service'] = 'system';
		$service_map['operator_panel'] = 'websockets';
		$service_map['system_status'] = 'websockets';
		$service_map['message_events'] = 'system';
		$service_map['message_queue'] = 'system';
		$service_map['transcribe_queue'] = 'queue';

		// Get the list
		$sql = "select ";
		$sql .= " service_uuid, ";
		$sql .= " service_name, ";
		$sql .= " service_category, ";
		$sql .= " cast(service_enabled as text), ";
		$sql .= " service_description ";
		$sql .= "from v_services ";
		$sql .= "order by service_name asc";
		$database_services = $this->database->select($sql, $parameters ?? null, 'all');
		unset($sql, $parameters);

		// Create an array to store service names from the database
		$service_names = [];
		if (!empty($database_services)) {
			$service_names = array_column($database_services, 'service_name');
		}

		// Add services that are not in the database
		$service_found = false;
		$services_new  = '';
		$i = 0;
		$array = [];
		foreach ($service_array as $service) {
			// Sanitize the service name
			$service_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $service['name']);

			// Built the array to save to the database
			if (!is_array($service_array) || !in_array($service_name, $service_names)) {
				// Get the category
				$service_category = '';
				if (class_exists($service_name)) {
					if (method_exists($service_name, 'get_category')) {
						$service_category = $service_name::get_category();
					}
				}

				// Alternate method to get the category
				if (empty($service_category)) {
					$service_category = $service_map[$service_name];
				}

				// Set service found to true
				$service_found = true;

				// Append the service label
				$services_new .= "<li>".$service_name."</li>\n";

				// Prepare the array
				$array['services'][$i]['service_uuid'] = uuid();
				$array['services'][$i]['service_name'] = $service_name;
				$array['services'][$i]['service_category'] = $service_category;
				$array['services'][$i]['service_file'] = $service['file'];
				$array['services'][$i]['service_enabled'] = 'true';
				$i++;
			}
		}

		// Add missing services into the database
		if (!empty($array)) {
			// Set temporary permissions
			$p = permissions::new();
			$p->add('service_add', 'temp');

			// Save to the database
			$this->database->save($array);
			unset($array);

			// Remove temporary permissions
			$p->delete('service_add', 'temp');
		}
		if ($service_found) {
			$msg = "<strong>".$text['message-added_new_services'].":</strong><br />\n";
			$msg .= "<div style='display: flex; justify-content: center;'>\n";
			$msg .= "	<ul style='text-align: left; margin: 0;'>\n";
			$msg .= $services_new;
			$msg .= "	</ul>\n";
			$msg .= "</div>\n";
			message::add($msg);
		}
	}

	/**
	 * Install new or changed service files.
	 *
	 * Each enabled service file is compared with the installed copy and only new or changed
	 * files are copied. On Linux, systemd is reloaded once and the changed units are enabled
	 * with a single systemctl call.
	 *
	 * @param string $name  Service name to upgrade, or 'all' to upgrade all services. (default: 'all')
	 * @param bool   $start Start the installed services after enabling them. (default: true)
	 *
	 * @return array Names of the services that were installed or updated.
	 */
	public function upgrade($name = 'all', bool $start = true): array {
		// Copy only the service files that are new or have changed
		$changed = [];
		foreach ($this->get_target_services($name, true) as $service) {
			$service_file = $this->resolve_service_file($service);
			if (empty($service_file)) {
				continue;
			}

			$installed_file = $this->installed_service_file($service['name']);
			if (file_exists($installed_file) && hash_file('sha256', $service_file) === hash_file('sha256', $installed_file)) {
				continue;
			}

			if (!@copy($service_file, $installed_file)) {
				$this->output("Failed to install " . $service['name'] . " to " . $installed_file . "\n");
				continue;
			}
			chmod($installed_file, stristr(PHP_OS, 'BSD') ? 0755 : 0644);
			$changed[] = $service['name'];
		}

		if (empty($changed)) {
			$this->output("No new or changed services\n");
			return [];
		}
		$this->output_names($changed);

		// Register the changed services with the init system
		if (stristr(PHP_OS, 'Linux')) {
			$this->run_command('systemctl daemon-reload');
			$this->systemctl('enable', $changed, $start ? ['--now'] : []);
		}
		if (stristr(PHP_OS, 'BSD')) {
			foreach ($changed as $service_name) {
				$this->run_command("sysrc " . escapeshellarg($service_name . "_enable=YES"));
				if ($start) {
					$this->run_command("service " . escapeshellarg($service_name) . " start");
				}
			}
		}

		return $changed;
	}

	/**
	 * Starts enabled services.
	 *
	 * @param string $name Service name to start, or 'all' to start all services. (default: 'all')
	 *
	 * @return void
	 */
	public function start($name = 'all') {
		$this->service_action('start', $this->get_installed_service_names($name, true));
	}

	/**
	 * Restarts enabled services.
	 *
	 * On Linux all services are restarted with a single systemctl call so they restart in parallel.
	 *
	 * @param string $name Service name to restart, or 'all' to restart all services. (default: 'all')
	 *
	 * @return void No return value;
	 */
	public function restart($name = 'all') {
		$this->service_action('restart', $this->get_installed_service_names($name, true));
	}

	/**
	 * Stops services, including services that are disabled in the database.
	 *
	 * @param string $name Service name to stop, or 'all' to stop all services. (default: 'all')
	 *
	 * @return void No return value;
	 */
	public function stop($name = 'all') {
		$this->service_action('stop', $this->get_installed_service_names($name, false));
	}

	/**
	 * Sends SIGHUP to the main process of running enabled services so they reload their settings.
	 *
	 * @param string $name Service name to signal, or 'all' to signal all services. (default: 'all')
	 *
	 * @return array Names of the services that were signaled.
	 */
	public function send_sighup($name = 'all'): array {
		$names = $this->get_installed_service_names($name, true);
		$signaled = [];

		if (stristr(PHP_OS, 'Linux') && !empty($names)) {
			// Only running units can be signaled; is-active prints one state per unit in order
			$states = [];
			exec('systemctl is-active ' . implode(' ', array_map('escapeshellarg', $names)) . ' 2>/dev/null', $states);
			foreach ($names as $i => $service_name) {
				if (($states[$i] ?? '') === 'active') {
					$signaled[] = $service_name;
				}
			}
			$this->systemctl('kill', $signaled, ['--kill-who=main', '--signal=SIGHUP']);
		}
		if (stristr(PHP_OS, 'BSD')) {
			// The rc.d scripts store the PID in /var/run/<name>.pid
			foreach ($names as $service_name) {
				$pid_file = '/var/run/' . $service_name . '.pid';
				$pid = is_readable($pid_file) ? (int)trim(file_get_contents($pid_file)) : 0;
				if ($pid > 0 && posix_kill($pid, 1)) {
					$signaled[] = $service_name;
				}
			}
		}

		$this->output_names($signaled);
		return $signaled;
	}

	/**
	 * Gets the enabled or all services from the database filtered by name with sanitized names.
	 *
	 * @param string $name         Service name, or 'all' for all services.
	 * @param bool   $enabled_only Only return services that are enabled.
	 *
	 * @return array List of services keyed numerically.
	 */
	private function get_target_services(string $name, bool $enabled_only): array {
		$targets = [];
		foreach ($this->get_services() as $service) {
			if ($name !== 'all' && $service['name'] !== $name) {
				continue;
			}
			if ($enabled_only && $service['enabled'] !== 'true') {
				continue;
			}
			$service_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $service['name']);
			if ($service_name === '') {
				continue;
			}
			$service['name'] = $service_name;
			$targets[$service_name] = $service;
		}
		return array_values($targets);
	}

	/**
	 * Gets the names of the target services that are installed in the init system.
	 *
	 * @param string $name         Service name, or 'all' for all services.
	 * @param bool   $enabled_only Only return services that are enabled.
	 *
	 * @return array List of service names.
	 */
	private function get_installed_service_names(string $name, bool $enabled_only): array {
		$names = [];
		foreach ($this->get_target_services($name, $enabled_only) as $service) {
			if (file_exists($this->installed_service_file($service['name']))) {
				$names[] = $service['name'];
			}
		}
		return $names;
	}

	/**
	 * Gets the source service file, falling back to previous locations for backwards compatibility.
	 *
	 * @param array $service Service array with sanitized 'name' and 'file' keys.
	 *
	 * @return string The service file path, or an empty string if none was found.
	 */
	private function resolve_service_file(array $service): string {
		if (!empty($service['file']) && file_exists($service['file'])) {
			return $service['file'];
		}
		$service_dir = dirname(__DIR__, 4) . "/app/" . $service['name'] . "/resources/service/";
		$candidates = [];
		if (stristr(PHP_OS, 'Linux')) {
			$candidates = [$service_dir . "debian.service", $service_dir . $service['name'] . ".service"];
		}
		if (stristr(PHP_OS, 'BSD')) {
			$candidates = [$service_dir . "freebsd.service"];
		}
		foreach ($candidates as $candidate) {
			if (file_exists($candidate)) {
				return $candidate;
			}
		}
		return '';
	}

	/**
	 * Gets the path where the init system expects the service file.
	 *
	 * @param string $service_name Sanitized service name.
	 *
	 * @return string The installed service file path.
	 */
	protected function installed_service_file(string $service_name): string {
		if (stristr(PHP_OS, 'BSD')) {
			return "/usr/local/etc/rc.d/" . $service_name;
		}
		return "/etc/systemd/system/" . $service_name . ".service";
	}

	/**
	 * Runs a start, stop or restart action on the services.
	 *
	 * @param string $action       The action to run.
	 * @param array  $service_names Sanitized service names.
	 *
	 * @return void
	 */
	private function service_action(string $action, array $service_names) {
		if (empty($service_names)) {
			return;
		}
		$this->output_names($service_names);
		if (stristr(PHP_OS, 'Linux')) {
			$this->systemctl($action, $service_names);
		}
		if (stristr(PHP_OS, 'BSD')) {
			foreach ($service_names as $service_name) {
				$this->run_command("service " . escapeshellarg($service_name) . " " . escapeshellarg($action));
			}
		}
	}

	/**
	 * Runs one systemctl command for all the given units.
	 *
	 * @param string $action        The systemctl verb.
	 * @param array  $service_names Sanitized service names.
	 * @param array  $options       Additional systemctl options.
	 *
	 * @return int The exit code, or 0 when there was nothing to do.
	 */
	private function systemctl(string $action, array $service_names, array $options = []): int {
		if (empty($service_names)) {
			return 0;
		}
		$command = 'systemctl';
		foreach ($options as $option) {
			$command .= ' ' . escapeshellarg($option);
		}
		$command .= ' ' . escapeshellarg($action) . ' ' . implode(' ', array_map('escapeshellarg', $service_names));
		return $this->run_command($command);
	}

	/**
	 * Runs a shell command and passes its output through.
	 *
	 * @param string $command The escaped command to run.
	 *
	 * @return int The exit code.
	 */
	protected function run_command(string $command): int {
		$result_code = 0;
		system($command, $result_code);
		return $result_code;
	}

	/**
	 * Writes a message to the console when running from the command line.
	 *
	 * @param string $message The message.
	 *
	 * @return void
	 */
	private function output(string $message) {
		if (PHP_SAPI === 'cli') {
			echo $message;
		}
	}

	/**
	 * Writes each service name to the console when running from the command line.
	 *
	 * @param array $service_names The service names.
	 *
	 * @return void
	 */
	private function output_names(array $service_names) {
		foreach ($service_names as $service_name) {
			$this->output($service_name . "\n");
		}
	}

	/**
	 * Finds the service name in an INI file from a given file.
	 *
	 * @param string $file The fully qualified path and file containing the ExecStart command.
	 *
	 * @return string|null The service name if found, otherwise an empty string.
	 */
	public function find_service_name(string $file) {
		if (stristr(PHP_OS, 'Linux')) {
			$parsed = parse_ini_file($file);
			$exec_cmd = $parsed['ExecStart'];
			$parts = explode(' ', $exec_cmd);
			$php_file = $parts[1] ?? '';
			if (!empty($php_file)) {
				$path_info = pathinfo($php_file);
				return $path_info['filename'];
			}
		}
		if (stristr(PHP_OS, 'FreeBSD')) {
			$service_content = file_get_contents($file);
			if (preg_match('/^\s*name\s*=\s*["\']([^"\']+)["\']\s*$/m', $service_content, $name_matches)) {
				if (!empty($name_matches[1])) {
					return $name_matches[1];
				}
			}
		}
		return '';
	}

	/**
	 * Checks whether the current user is the root user or not.
	 *
	 * @return bool True if the current user has root privileges, false otherwise.
	 */
	public function is_root(): bool {
		return posix_getuid() === 0;
	}

	/**
	 * Retrieves the name of a PHP class from an ExecStart directive in a service file.
	 *
	 * @param string $file Path to the service file.
	 *
	 * @return string The name of the PHP class, or empty string if not found.
	 */
	public function get_class_name(string $file) {
		if (!file_exists($file)) {
			return '';
		}
		$parsed = parse_ini_file($file);
		$exec_cmd = $parsed['ExecStart'] ?? '';
		$parts = explode(' ', $exec_cmd ?? '');
		$php_file = $parts[1] ?? '';
		if (!empty($php_file)) {
			return $php_file;
		}
		return '';
	}

	/**
	 * Checks if a process with the given name is currently running.
	 *
	 * @param string $name The name of the process to check for.
	 *
	 * @return array An array containing information about the process's status,
	 *               including whether it's status, its PID, and how long it's been running.
	 */
	public function is_running(string $name): array {
		$name = escapeshellarg($name);
		$command = "ps -aux | grep $name | grep -v grep | awk '{print \$2}' | head -n 1";
		$pid = trim(shell_exec($command) ?? '');
		if ($pid && is_numeric($pid)) {
			$command = "ps -p $pid -o etime= | tr -d '\n'";
			$etime = trim(shell_exec($command) ?? '');
			return ['status' => true, 'pid' => $pid, 'etime' => $etime];
		}
		return ['status' => false, 'pid' => $pid, 'etime' => null];
	}

	/**
	 * Formats a time duration string into a human-readable format.
	 *
	 * The input string can be in one of the following formats:
	 * - dd-hh:mm:ss
	 * - hh:mm:ss
	 * - mm:ss
	 * - seconds (no units)
	 *
	 * If the input string is empty or invalid, an empty string will be returned.
	 *
	 * @param string $etime Time duration string to format.
	 *
	 * @return string Formatted time duration string in human-readable format.
	 */
	public function format_etime($etime): string {
		// Format: [[dd-]hh:]mm:ss
		if (empty($etime)) return '-';

		$days = 0; $hours = 0; $minutes = 0; $seconds = 0;

		// Handle dd-hh:mm:ss
		if (preg_match('/^(\d+)-(\d+):(\d+):(\d+)$/', $etime, $m)) {
			[$_, $days, $hours, $minutes, $seconds] = $m;
		}
		// Handle hh:mm:ss
		elseif (preg_match('/^(\d+):(\d+):(\d+)$/', $etime, $m)) {
			[$_, $hours, $minutes, $seconds] = $m;
		}
		// Handle mm:ss
		elseif (preg_match('/^(\d+):(\d+)$/', $etime, $m)) {
			[$_, $minutes, $seconds] = $m;
		}

		$out = [];
		if ($days)		$out[] = $days . 'd';
		if ($hours)	 $out[] = $hours . 'h';
		if ($minutes) $out[] = $minutes . 'm';
		if ($seconds || empty($out)) $out[] = $seconds . 's';

		return implode(' ', $out);
	}

}
