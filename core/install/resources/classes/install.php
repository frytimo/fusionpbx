<?php

/**
 * @param string $database_host
 * @param string $database_port
 * @param string $database_name
 * @param string $database_username
 * @param string $database_password
 */
class install {

	/**
	 * Readonly constants for the class name and app id
	 */
	const APP_NAME = 'install';
	const APP_UUID = '75507e6e-891e-11e5-af63-feff819cdc9f';

	/**
	 * Config object
	 * @var config $config
	 */
	private $config;

	/**
	 * Database object
	 * @var database
	 */
	private $database;

	/**
	 * Domain UUID can be newly created or an existing
	 * @var string
	 */
	private $domain_uuid;
	private $user_uuid;
	
	/**
	 * 
	 * @var bool $domain_exists
	 */
	private $domain_exists;

	public $admin_username;
	public $admin_password;
	public $domain_name;

	/**
	 * called when the object is created
	 */
	public function __construct(config $config) {
		$this->config = $config;
		$this->database = new database(['config' => $config]);
		$this->admin_username = '';
		$this->admin_password = '';
		$this->domain_name = '';
		$this->database_host = '';
		$this->database_port = '';
		$this->database_name = '';
		$this->database_username = '';
		$this->database_password = '';
	}

	public function __set(string $name, $value): void {
		switch ($name) {
			case 'database_host':
			case 'database_port':
			case 'database_name':
			case 'database_username':
			case 'database_password':
				$this->config->{$name} = $value;
				break;
			case 'admin_username':
			case 'admin_password':
			case 'domain_name':
				$this->{$name} = $value;
				break;
		}
	}

	public function __get(string $name) {
		switch($name) {
			case 'database_host':
			case 'database_port':
			case 'database_name':
			case 'database_username':
			case 'database_password':
				return $this->config->{$name};
			case 'admin_username':
			case 'admin_password':
			case 'domain_name':
				return $this->{$name};
		}
	}
	
	private function set_domain_uuid($uuid) {
		$this->domain_uuid = $uuid;
	}

	//uses the config object domain_name to retrieve the domain_uuid
	private function get_domain_uuid(): string {
		if ($this->database->table_exists('v_domains')) {
			$sql = "select domain_uuid from v_domains where domain_name = :domain_name limit 1";
			$parameters['domain_name'] = $this->config->domain_name;
			$domain_uuid = $this->database->select($sql, $parameters, 'column');
		}
		//domain name or table does not exist
		if (empty($domain_uuid)) {
			return '';
		}
		return $domain_uuid;
	}

	//restore the default permissions
	public function run_update_permissions() {
		global $included;
		//default the permissions
		$included = true;
		require_once dirname(__DIR__, 2) . "/core/groups/permissions_default.php";

		//send message to the console
		$text = (new text)->get(null, 'core/upgrade');
		echo $text['message-upgrade_permissions'] . "\n";
	}

	//restore the default menu
	public function run_update_menu() {
		global $included, $sel_menu, $menu_uuid, $menu_language;
		//get the menu uuid and language
		$sql = "select menu_uuid, menu_language from v_menus ";
		$sql .= "where menu_name = :menu_name ";
		$parameters['menu_name'] = 'default';
		$database = framework::database();
		$row = $database->select($sql, $parameters, 'row');
		if (is_array($row) && sizeof($row) != 0) {
			$menu_uuid = $row["menu_uuid"];
			$menu_language = $row["menu_language"];
		}
		unset($sql, $parameters, $row);

		//show the menu
		if (isset($argv[2]) && $argv[2] == 'view') {
			print_r($_SESSION["menu"]);
		}

		//set the menu back to default
		if (!isset($argv[2]) || $argv[2] == 'default') {
			//restore the menu
			$included = true;
			require_once dirname(__DIR__, 2) . "/core/menu/menu_restore_default.php";
			unset($sel_menu);
			$text = (new text)->get(null, 'core/upgrade');
			//send message to the console
			echo $text['message-upgrade_menu'] . "\n";
		}
	}

	public function run_update_admin() {
		//ensure groups are not empty
		$this->ensure_groups_exist();

		$admin_uuid = $this->get_user_uuid($this->admin_username);

		//if the user did not exist then get a new uuid
		if (empty($admin_uuid)) {
			$admin_uuid = $this->create_admin_user($admin_uuid);
		}

		$group_uuid = $this->get_group_uuid('superadmin');
		if (empty($group_uuid)) {
			$group_uuid = $this->create_group('superadmin', 80);
		}

		//add the admin user to the superadmin group
		$user_group_uuid = $this->get_user_group_uuid($admin_uuid, $group_uuid);
		if (empty($user_group_uuid)) {
			$this->create_user_group_admin($admin_uuid, $group_uuid, 'superadmin');
		}
	}

	private function get_password(): string {
		if (empty($this->admin_password)) {
			throw new \InvalidArgumentException("Admin password cannot be empty");
		}
		return password_hash($this->admin_password, PASSWORD_DEFAULT);
	}

	public function domain_uuid(): string {
		if (empty($this->domain_uuid)) {
			$this->ensure_domain_exist();
		}
		return $this->domain_uuid;
	}

	public function domain_name(): string {
		return $this->config->domain_name;
	}

	private function get_user_uuid($user_name): string {
		//get the user_uuid if the user exists
		$sql = "select user_uuid from v_users "
			. "where domain_uuid = :domain_uuid "
			. "and username = :username "
		;
		$parameters['domain_uuid'] = $this->domain_uuid;
		$parameters['username'] = $user_name;

		$database = $this->database;
		$user_uuid = $database->select($sql, $parameters, 'column');
		if ($user_uuid === false) {
			return '';
		}
		return $user_uuid;
	}

	private function get_group_uuid($group_name): string {
		$sql = "SELECT g.group_uuid"
			. " FROM public.v_groups g"
			. " WHERE g.group_name = :group_name"
			. " AND g.domain_uuid is null"
			. " LIMIT 1"
		;
		$parameters = [];
		$parameters['group_name'] = $group_name;
		$group_uuid = $this->database->select($sql, $parameters, 'column');
		if ($group_uuid === false) {
			$group_uuid = '';
		}
		return $group_uuid;
	}

	private function get_user_group_uuid($user_uuid, $group_uuid): string {
		$sql = "select user_group_uuid from v_user_groups"
			. " where domain_uuid='$this->domain_uuid' and group_uuid=:group_uuid and user_uuid=:user_uuid";

		$parameters = [];
		$parameters['user_uuid'] = $user_uuid;
		$parameters['group_uuid'] = $group_uuid;
		$user_group_uuid = $this->database->select($sql, $parameters, 'column');
		if ($user_group_uuid === false) {
			$user_group_uuid = '';
		}
		return $user_group_uuid;
	}

	private function ensure_tables_exist() {
		//ensure the tables exist to write data into

		if (!$this->database->table_exists('v_software')) {
			$this->write_schema($this->get_schema_from_app_config('core/software'));
		}
		if (!$this->database->table_exists('v_default_settings')) {
			$this->write_schema($this->get_schema_from_app_config('core/default_settings'));
		}
	}

	private function ensure_groups_exist() {
				//ensure the groups exists
		if (empty($this->get_group_uuid('superadmin'))) {
			$this->create_group('superadmin', 80);
		}
		if (empty($this->get_group_uuid('admin'))) {
			$this->create_group('admin', 50);
		}
		if (empty($this->get_group_uuid('supervisor'))) {
			$this->create_group('supervisor', 40);
		}
		if (empty($this->get_group_uuid('user'))) {
			$this->create_group('user', 30);
		}
		if (empty($this->get_group_uuid('agent'))) {
			$this->create_group('agent', 20);
		}
		if (empty($this->get_group_uuid('public'))) {
			$this->create_group('public', 10);
		}
	}

	public function run_update_files() {
		if (!$this->domain_exists) {
			if (file_exists(dirname(__DIR__, 4) . "/app/xml_cdr")) {
				xml_cdr_conf_xml();
			}
			if (file_exists($this->config->get('switch.conf.dir'))) {
				switch_conf_xml();
			}
		}
	}

	/**
	 * Read a schema array from an existing FusionPBX app_config style file
	 * @param type $path full directory path
	 * @param type $file if not provided default is app_config.php
	 * @return type
	 */
	function get_schema_from_app_config($path, $file = 'app_config.php') {
		if (str_starts_with($path, '/')) {
			$app_config = dirname(__DIR__, 4) . "$path/$file";
		} else {
			$app_config = dirname(__DIR__, 4) . "/$path/$file";
		}
		$x = 0;
		if (file_exists($app_config)) {
			require $app_config;
		}

		if(!empty($apps) && is_array($apps) && count($apps) > 0) {
			if(!empty($apps[0]['db'])) {
				$array = $apps[0]['db'];
				unset ($apps, $x);
				return $array;
			}
		}
		unset ($apps, $x);
		return null;
	}

	/**
	 * Writes a FusionPBX app_config.php style schema array to the database
	 * @param type $con PDO connection
	 * @param type $schema FusionPBX app_config.php style schema array
	 */
	function write_schema($schema) {
		if (empty($schema)) {
			return;
		}
		if (!is_array($schema)) {
			return;
		}
		foreach($schema as $table) {
			$table_name = $table['table']['name'];
			if(is_array($table_name)) {
				$table_name = $table['table']['name']['text'];
			}
			$sql = "create table if not exists $table_name (";
			if(!empty($table['fields'])) {
				foreach($table['fields'] as $field) {
					if (isset($field['deprecated']) && $field['deprecated'] == true) {
						continue;
					}
					$field_name = $field['name'];
					if(is_array($field_name)) {
						$field_name = $field['name']['text'];
					}
					$field_type = $field['type'];
					if(is_array($field_type)) {
						$field_type = $field_type['pgsql'];
					}
					$sql .= "$field_name $field_type";
					if(!empty($field['key']['type'])) {
						$field_key_type = $field['key']['type'];
						if($field_key_type === 'primary') {
							$sql .= " primary key";
						}
						if($field_key_type === 'foreign') {
							$foreign_key_table = $field['key']['reference']['table'];
							$foreign_key_field = $field['key']['reference']['field'];
						}
					}
					$sql .= ",";
				}
				if(substr($sql, -1) === ",") {
					$sql = substr($sql, 0, strlen($sql)-1);
				}
			}
			$sql .= ")";
			$this->database->execute($sql);
		}
	}

	private function ensure_domain_exist() {
		//ensure the table is there first
		if (!$this->database->table_exists('v_domains')) {
			$this->write_schema($this->get_schema_from_app_config('core/domains'));
		}
		//check for the need to create
		$uuid = $this->get_domain_uuid();
		if (empty($uuid)) {
			$this->domain_exists = false;
			$uuid = uuid();

			//prepare the array
			$sql = "insert into v_domains(domain_uuid,domain_name,domain_enabled)"
			. " values('$uuid','".$this->config->domain_name."','true')";
			$this->database->execute($sql);
		} else {
			$this->domain_exists = true;
		}

		$this->set_domain_uuid($uuid);
	}

	//run all app_defaults.php files
	function run_update_domains() {
		require_once dirname(__DIR__, 4) . "/resources/classes/config.php";
		require_once dirname(__DIR__, 4) . "/resources/classes/domains.php";
		$domain = new domains(['config' => $this->config, 'database' => $this->database]);
		$domain->display_type = 'html';
		$domain->upgrade();
	}

	//restore the default permissions
	function do_upgrade_permissions() {
		global $included;
		//default the permissions
		$included = true;
		require_once dirname(__DIR__, 4) . "/core/groups/permissions_default.php";

		//send message to the console
		$text = (new text)->get(null, 'core/upgrade');
		echo $text['message-upgrade_permissions'] . "\n";
	}

	//upgrade schema and/or data_types
	public function run_update_schema(bool $data_types = false) {
		//put tables in that the install.sh script inserts
		//$this->ensure_tables_exist();
		//get the database schema put it into an array then compare and update the database as needed.
		$obj = new schema(['database' => $this->database]);
		$obj->data_types = $data_types;
		//run with no output
		//ob_start();
		$obj->schema('html');
		//ob_clean();
	}

	/**
	 * <p>Used to create the config.conf file.</p>
	 * <p>BSD /usr/local/etc/fusionpbx</p>
	 * <p>Linux /etc/fusionpbx</p>
	 * @return config Object containing the configuration required for the detected OS
	 */
	public static function get_default_config(): config {

		//set the default config file location
		$os = strtoupper(substr(PHP_OS, 0, 3));
		switch ($os) {
			case "BSD":
				$config_path = '/usr/local/etc/fusionpbx';
				$config_file = $config_path . '/config.conf';
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
				break;
			case "LIN":
				$config_path = '/etc/fusionpbx/';
				$config_file = $config_path . '/config.conf';
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
				break;
			case "WIN":
				$system_drive = getenv('SystemDrive');
				$config_path = $system_drive . DIRECTORY_SEPARATOR . 'ProgramData' . DIRECTORY_SEPARATOR . 'fusionpbx';
				$config_file = $config_path . DIRECTORY_SEPARATOR . 'config.conf';
				$document_root = $_SERVER["DOCUMENT_ROOT"];

				$conf_dir = $_SERVER['ProgramFiles'] . DIRECTORY_SEPARATOR . 'freeswitch' . DIRECTORY_SEPARATOR . 'conf';
				$sounds_dir = $_SERVER['ProgramFiles'] . DIRECTORY_SEPARATOR . 'freeswitch' . DIRECTORY_SEPARATOR . 'sounds';
				$database_dir = $_SERVER['ProgramFiles'] . DIRECTORY_SEPARATOR . 'freeswitch' . DIRECTORY_SEPARATOR . 'db';
				$recordings_dir = $_SERVER['ProgramFiles'] . DIRECTORY_SEPARATOR . 'freeswitch' . DIRECTORY_SEPARATOR . 'recordings';
				$storage_dir = $_SERVER['ProgramFiles'] . DIRECTORY_SEPARATOR . 'freeswitch' . DIRECTORY_SEPARATOR . 'storage';
				$voicemail_dir = $_SERVER['ProgramFiles'] . DIRECTORY_SEPARATOR . 'freeswitch' . DIRECTORY_SEPARATOR . 'voicemail';
				$scripts_dir = $_SERVER['ProgramFiles'] . DIRECTORY_SEPARATOR . 'freeswitch' . DIRECTORY_SEPARATOR . 'scripts';
				$php_dir = dirname(PHP_BINARY);
				$cache_location = dirname($_SERVER['DOCUMENT_ROOT']) . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'fusionpbx';
				break;
		}

		//end the script if the config path is not set
		if (!isset($config_path)) {
			throw new Exception("Config file path not found\n");
		}

		$config = new config();

		//build the default config file
		$config->config_file = 'config.conf';
		$config->config_path = $config_path;
		$config->set('#database system settings');
		$config->db_type = 'pgsql';
		$config->db_host = '127.0.0.1';
		$config->db_port = '5432';
		$config->db_sslmode = 'prefer';
		$config->db_name = 'fusionpbx';
		$config->db_username = 'fusionpbx';
		$config->db_password = 'fusionpbx';
		$config->set('#database switch settings');
		$config->set("database.1.type", "sqlite");
		$config->set("database.1.path", $database_dir);
		$config->set("database.1.name", "core.db");

		$config->set("#general settings");
		$config->set("document.root", $document_root);
		$config->set("project.path", $document_root);
		$config->set("temp.dir", sys_get_temp_dir());
		$config->set("php.dir", PHP_BINDIR);
		$config->set("php.bin", PHP_BINARY);

		$config->set("#cache settings");
		$config->set("cache.method", "file");
		$config->set("cache.location", $cache_location);
		$config->set("cache.settings", "true");

		$config->set("#switch settings");
		$config->set("switch.conf.dir", $conf_dir);
		$config->set("switch.sounds.dir", $sounds_dir);
		$config->set("switch.database.dir", $database_dir);
		$config->set("switch.recordings.dir", $recordings_dir);
		$config->set("switch.storage.dir", $storage_dir);
		$config->set("switch.voicemail.dir", $voicemail_dir);
		$config->set("switch.scripts.dir", $scripts_dir);
		$config->set("switch.event_socket.host", '127.0.0.1');
		$config->set("switch.event_socket.port", '8021');
		$config->set("switch.event_socket.password", 'ClueCon');

		$config->set("#switch xml handler");
		$config->set("xml_handler.fs_path", "false");
		$config->set("xml_handler.reg_as_number_alias", "false");
		$config->set("xml_handler.number_as_presence_id", "true");

		$config->set("#error reporting options: user,dev,all");
		$config->set("error.reporting", "user");

		return $config;
	}

	public static function write_config(config $config) {
		//config directory is not writable
		if (!is_writable($config->config_path)) {
			throw new Exception("Check permissions " . $config->config_path . " must be writable.\n");
		}

		//make the config directory
		if (!file_exists($config->config_path) && !mkdir($config->config_path, 775, true)) {
			throw new \Exception("Unable to create the directory structure $config->config_path");
		}

		//write the config file
		file_put_contents($config->config_path_and_filename, (string) $config);

		//if the config.conf file was saved return true
		if (!file_exists($config->config_path_and_filename)) {
			throw new Exception("Unable to write the configuration to $config->config_path_and_filename");
		}
	}

	public function create_group($group_name, $group_level): string {
		//ensure table exists first
		if (!$this->database->table_exists('v_groups')) {
//			$sql = "create table v_groups(group_uuid uuid primary key, group_name text, group_level numeric)";
//			$this->database->execute($sql);
			$this->write_schema($this->get_schema_from_app_config('core/groups'));
		}
		$uuid = uuid();
		$sql = "insert into v_groups(group_uuid,group_name,group_level) values ('$uuid','$group_name',$group_level)";
		$this->database->execute($sql);
		return $uuid;
	}

	public function create_admin_user(): string {
		$uuid = uuid();
		//ensure the users table exists
		if (!$this->database->table_exists('v_users')) {
//			$this->database->execute('create table v_users(user_uuid uuid primary key,username text, password, text');
			$this->write_schema($this->get_schema_from_app_config('core/users'));
		}
		$sql = "insert into v_users(user_uuid, username, password) values('$uuid','$this->admin_username','".$this->get_password()."')";
		$this->database->execute($sql);
		return $uuid;
	}

	public function create_user_group_admin($user_uuid, $group_uuid, $group_name): string {
		if (!$this->database->table_exists('v_user_groups')) {
			//$this->database->execute('create table v_user_groups(user_group_uuid uuid primary key,domain_uuid uuid,group_name text,group_uuid uuid,user_uuid uuid)');

		}
		$uuid = uuid();
		$sql = "insert into v_user_groups(user_group_uuid,domain_uuid,group_name,group_uuid,user_uuid)"
			. " values('$uuid','$this->domain_uuid','$group_name','$group_uuid','$user_uuid')";
		$this->database->execute($sql);
		return $uuid;
	}
}

?>
