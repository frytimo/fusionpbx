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
 Mark J Crane <markjcrane@fusionpbx.com>
*/

//define the vars class
if (!class_exists('vars')) {
	class vars {

		/**
		 * declare private variables
		 */
		private $app_name;
		private $app_uuid;
		private $permission_prefix;
		private $list_page;
		private $table;
		private $uuid_prefix;
		private $toggle_field;
		private $toggle_values;

		/**
		 * called when the object is created
		 */
		public function __construct() {

			//assign private variables
				$this->app_name = 'vars';
				$this->app_uuid = '54e08402-c1b8-0a9d-a30a-f569fc174dd8';
				$this->permission_prefix = 'var_';
				$this->list_page = 'vars.php';
				$this->table = 'vars';
				$this->uuid_prefix = 'var_';
				$this->toggle_field = 'var_enabled';
				$this->toggle_values = ['true','false'];

		}

		/**
		 * delete records
		 */
		public function delete($records) {
			if (permission_exists($this->permission_prefix.'delete')) {

				//add multi-lingual support
					$language = new text;
					$text = $language->get();

				//validate the token
					$token = new token;
					if (!$token->validate($_SERVER['PHP_SELF'])) {
						message::add($text['message-invalid_token'],'negative');
						header('Location: '.$this->list_page);
						exit;
					}

				//delete multiple records
					if (!empty($records) && @sizeof($records) != 0) {

						//build the delete array
							foreach ($records as $x => $record) {
								if (!empty($record['checked']) && $record['checked'] == 'true' && is_uuid($record['uuid'])) {
									$array[$this->table][$x][$this->uuid_prefix.'uuid'] = $record['uuid'];
								}
							}

						//delete the checked rows
							if (!empty($array) && @sizeof($array) != 0) {

								//execute delete
									$database = framework::database();
									$database->app_name = $this->app_name;
									$database->app_uuid = $this->app_uuid;
									$database->delete($array);
									unset($array);

								//unset the user defined variables
									unset($_SESSION["user_defined_variables"]);

								//rewrite the xml
									save_var_xml();

								//set message
									message::add($text['message-delete']);
							}
							unset($records);
					}
			}
		}

		/**
		 * toggle records
		 */
		public function toggle($records) {
			if (permission_exists($this->permission_prefix.'edit')) {

				//add multi-lingual support
					$language = new text;
					$text = $language->get();

				//validate the token
					$token = new token;
					if (!$token->validate($_SERVER['PHP_SELF'])) {
						message::add($text['message-invalid_token'],'negative');
						header('Location: '.$this->list_page);
						exit;
					}

				//toggle the checked records
					if (!empty($records) && @sizeof($records) != 0) {

						//get current toggle state
							foreach ($records as $x => $record) {
								if (!empty($record['checked']) && $record['checked'] == 'true' && is_uuid($record['uuid'])) {
									$uuids[] = "'".$record['uuid']."'";
								}
							}
							if (!empty($uuids) && @sizeof($uuids) != 0) {
								$sql = "select ".$this->uuid_prefix."uuid as uuid, ".$this->toggle_field." as toggle from v_".$this->table." ";
								$sql .= "where ".$this->uuid_prefix."uuid in (".implode(', ', $uuids).") ";
								$database = framework::database();
								$rows = $database->select($sql, null, 'all');
								if (!empty($rows) && @sizeof($rows) != 0) {
									foreach ($rows as $row) {
										$states[$row['uuid']] = $row['toggle'];
									}
								}
								unset($sql, $parameters, $rows, $row);
							}

						//build update array
							$x = 0;
							foreach ($states as $uuid => $state) {
								$array[$this->table][$x][$this->uuid_prefix.'uuid'] = $uuid;
								$array[$this->table][$x][$this->toggle_field] = $state == $this->toggle_values[0] ? $this->toggle_values[1] : $this->toggle_values[0];
								$x++;
							}

						//save the changes
							if (!empty($array) && @sizeof($array) != 0) {

								//save the array
									$database = framework::database();
									$database->app_name = $this->app_name;
									$database->app_uuid = $this->app_uuid;
									$database->save($array);
									unset($array);

								//unset the user defined variables
									unset($_SESSION["user_defined_variables"]);

								//rewrite the xml
									save_var_xml();

								//set message
									message::add($text['message-toggle']);
							}
							unset($records, $states);
					}

			}
		}

		/**
		 * copy records
		 */
		public function copy($records) {
			if (permission_exists($this->permission_prefix.'add')) {

				//add multi-lingual support
					$language = new text;
					$text = $language->get();

				//validate the token
					$token = new token;
					if (!$token->validate($_SERVER['PHP_SELF'])) {
						message::add($text['message-invalid_token'],'negative');
						header('Location: '.$this->list_page);
						exit;
					}

				//copy the checked records
					if (!empty($records) && @sizeof($records) != 0) {

						//get checked records
							foreach ($records as $x => $record) {
								if (!empty($record['checked']) && $record['checked'] == 'true' && is_uuid($record['uuid'])) {
									$uuids[] = "'".$record['uuid']."'";
								}
							}

						//create insert array from existing data
							if (!empty($uuids) && @sizeof($uuids) != 0) {
								$sql = "select * from v_".$this->table." ";
								$sql .= "where ".$this->uuid_prefix."uuid in (".implode(', ', $uuids).") ";
								$database = framework::database();
								$rows = $database->select($sql, null, 'all');
								if (!empty($rows) && @sizeof($rows) != 0) {
									foreach ($rows as $x => $row) {

										//copy data
											$array[$this->table][$x] = $row;

										//overwrite
											$array[$this->table][$x][$this->uuid_prefix.'uuid'] = uuid();
											$array[$this->table][$x]['var_description'] = trim($row['var_description'] ?? '').trim(' ('.$text['label-copy'].')');

									}
								}
								unset($sql, $parameters, $rows, $row);
							}

						//save the changes and set the message
							if (!empty($array) && @sizeof($array) != 0) {

								//save the array
									$database = framework::database();
									$database->app_name = $this->app_name;
									$database->app_uuid = $this->app_uuid;
									$database->save($array);
									unset($array);

								//unset the user defined variables
									unset($_SESSION["user_defined_variables"]);

								//rewrite the xml
									save_var_xml();

								//set message
									message::add($text['message-copy']);

							}
							unset($records);
					}

			}
		}

		public static function save_var_xml() {
			if (!empty($_SESSION['switch']['conf']) && is_array($_SESSION['switch']['conf'])) {

				//skip this function if the conf directory is empty
				if (empty($_SESSION['switch']['conf']['dir'])) {
					return false;
				}

				//open the vars.xml file
				$fout = fopen($_SESSION['switch']['conf']['dir'] . "/vars.xml", "w");

				//get the hostname
				$hostname = trim(event_socket_request_cmd('api switchname'));
				if (empty($hostname)) {
					$hostname = trim(gethostname());
				}
				if (empty($hostname)) {
					return;
				}

				//build the xml
				$sql = "select * from v_vars ";
				$sql .= "where var_enabled = 'true' ";
				$sql .= "order by var_category, var_order asc ";
				$database = framework::database();
				$variables = $database->select($sql, null, 'all');
				$prev_var_category = '';
				$xml = '';
				if (!empty($variables)) {
					foreach ($variables as &$row) {
						if ($row['var_category'] != 'Provision') {
							if ($prev_var_category != $row['var_category']) {
								$xml .= "\n<!-- " . $row['var_category'] . " -->\n";
							}
							if (empty($row['var_command'])) {
								$row['var_command'] = 'set';
							}
							if ($row['var_category'] == 'Exec-Set') {
								$row['var_command'] = 'exec-set';
							}
							if (empty($row['var_hostname'])) {
								$xml .= "<X-PRE-PROCESS cmd=\"" . $row['var_command'] . "\" data=\"" . $row['var_name'] . "=" . $row['var_value'] . "\" />\n";
							} elseif ($row['var_hostname'] == $hostname) {
								$xml .= "<X-PRE-PROCESS cmd=\"" . $row['var_command'] . "\" data=\"" . $row['var_name'] . "=" . $row['var_value'] . "\" />\n";
							}
						}
						$prev_var_category = $row['var_category'];
					}
				}
				$xml .= "\n";
				fwrite($fout, $xml);
				unset($sql, $variables, $xml);
				fclose($fout);

				//apply settings
				$_SESSION["reload_xml"] = true;

				//$cmd = "api reloadxml";
				//event_socket_request_cmd($cmd);
				//unset($cmd);
			}
		}


	}
}

?>