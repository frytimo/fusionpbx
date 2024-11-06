<?php

/*
 * FusionPBX
 * Version: MPL 1.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is FusionPBX
 *
 * The Initial Developer of the Original Code is
 * Mark J Crane <markjcrane@fusionpbx.com>
 * Portions created by the Initial Developer are Copyright (C) 2008-2024
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * Mark J Crane <markjcrane@fusionpbx.com>
 * Tim Fry <tim@fusionpbx.com>
 */

/**
 * Description of fax_queue_service
 *
 * @author Tim Fry <tim@fusionpbx.com>
 */
class fax_queue_service extends service {

	/**
	 * Database object
	 * @var database database object
	 */
	private $database;
	private $settings;

	static $enabled = "";
	static $interval = "";
	static $limit = "";
	static $retry_interval = "";
	static $debug_sql = "";
	static $hostname = "";

	//put your code here
	protected function reload_settings(): void {
		//re-read config file
		self::$config->read();
		//connect to database
		$this->database = new database(['config' => self::$config]);
		//load the settings
		$this->settings = new settings(['database' => $this->database, 'category' => 'fax_queue']);
		//retrieve default settings
		self::$enabled = $this->settings->get('fax_queue', 'enabled', false);
		self::$interval = $this->settings->get('fax_queue', 'interval', 30);
		self::$limit = $this->settings->get('fax_queue', 'limit', 30);
		self::$retry_interval = $this->settings->get('fax_queue', 'retry_interval', 180);
		self::$debug_sql = $this->settings->get('fax_queue', 'debug_sql', false);
		//not on the command line as the service has it's own option
		//self::$log_level = $this->settings->get('fax_queue', 'debug');
	}

	protected static function display_version(): void {
		echo "Verison 1.01";
	}

	protected static function set_command_options() {
		self::append_command_option(command_option::new()
				->short_option('I')
				->long_option('interval')
				->description('Set the interval in seconds between database polling for new faxes and default setting changes')
				->function_append('set_interval')
		);
		self::append_command_option(command_option::new()
				->short_option('R')
				->long_option('retry_interval')
				->description('Set the interval in seconds between fax retries if it fails')
				->function_append('set_retry_interval')
		);
		self::append_command_option(command_option::new()
				->short_option('L')
				->long_option('limit')
				->description('Set the number of faxes to get from the database to send at one time')
				->function_append('set_limit')
		);
		self::append_command_option(command_option::new()
				->short_option('Q')
				->long_option('debug_queue')
				->description('Output the SQL commands used when querying the database for waiting faxes')
				->function_append('set_debug_queue')
		);
		self::append_command_option(command_option::new()
				->short_option('H')
				->long_option('hostname')
				->description('Set the hostname to use when querying the database for waiting faxes')
				->function_append('set_hostname')
		);
	}

	public static function set_limit($value) {
		self::$limit = $value;
	}

	public static function set_interval($value) {
		self::$interval = $value;
	}

	public static function set_hostname($value) {
		self::$hostname = $value;
	}

	public static function set_retry_interval($value) {
		self::$retry_interval = $value;
	}

	public static function set_debug_queue($value) {
		self::$debug_sql = $value;
	}

	public function run(): int {
		chdir($_SERVER['DOCUMENT_ROOT']);
		//set the SQL statement outside the loop because it never changes
		$sql = "select * from v_fax_queue ";
		$sql .= "where hostname = :hostname ";
		$sql .= "and ( ";
		$sql .= "	( ";
		$sql .= "		(fax_status = 'waiting' or fax_status = 'trying' or fax_status = 'busy') ";
		$sql .= "		and (fax_retry_date is null or floor(extract(epoch from now()) - extract(epoch from fax_retry_date)) > :retry_interval) ";
		$sql .= "	)  ";
		$sql .= "	or ( ";
		$sql .= "		fax_status = 'sent' ";
		$sql .= "		and fax_email_address is not null ";
		$sql .= "		and fax_notify_date is null ";
		$sql .= "	) ";
		$sql .= ") ";
		$sql .= "order by domain_uuid asc ";
		$sql .= "limit :limit ";
		$parameters = [];
		//host name is set optionally with startup options
		if (!isset(self::$hostname)) {
			self::$hostname = gethostname();
		}
		$parameters['hostname'] = self::$hostname;
		//use the running boolean from the parent service class
		while ($this->running) {
			// enters a sleep state if the service is set to false in default settings
			do {
				//load config, database, and settings
				$this->reload_settings();
				//pause to prevent excessive database queries
				sleep($this->interval);
			} while ($this->settings->get('fax_queue', 'enabled', false));
			$parameters['limit'] = self::$limit;
			$parameters['retry_interval'] = self::$retry_interval;
			if (isset(self::$debug_sql)) {
				service::log("sql parameters '" . implode(',', $parameters) . "'");
			}
			//get the faxes that have a 'waiting', 'trying', or 'busy' status
			$fax_queue = $this->database->select($sql, $parameters, 'all');
			//process the messages
			$this->send_faxes($fax_queue);
		}
	}

	private function send_faxes($fax_queue) {
		if (!empty($fax_queue)) {
			foreach ($fax_queue as $row) {
				$command = exec('which php') . " " . $_SERVER['DOCUMENT_ROOT'] . "/app/fax_queue/resources/job/fax_send.php ";
				$command .= "'action=send&fax_queue_uuid=" . $row["fax_queue_uuid"] . "&hostname=" . self::$hostname . "'";
				if (isset($this->debug)) {
					//run process inline
					service::log('Command: ' . $command);
					$result = shell_exec($command);
					service::log('Result: ' . $result);
				} else {
					//starts process rapidly doesn't wait for previous process to finish (used for production)
					$handle = popen($command . " > /dev/null &", 'r');
					service::log("'$handle'; " . gettype($handle));
					//read a 4k block
					$read = fread($handle, 4096);
					//log it
					service::log('READ: ' . $read);
					//close it
					pclose($handle);
				}
			}
		}
	}
}

//see how to use this feature in fax_queue/resources/service/fax_queue.php
