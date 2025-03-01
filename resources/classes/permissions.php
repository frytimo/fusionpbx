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
	Copyright (C) 2016 - 2024	All Rights Reserved.
*/

/**
 * permissions class
 *
 */

	class permissions {

		private $database;
		private $domain_uuid;
		private $user_uuid;
		private $groups;
		private $permissions;
		private static $permission;
		private $apcu_enabled;

		/**
		 * called when the object is created
		 */
		public function __construct($database = null, $domain_uuid = null, $user_uuid = null) {

			//intitialize as empty arrays
			$this->groups = [];
			$this->permissions = [];

			//handle the database object
			if (isset($database)) {
				$this->database = $database;
			}
			else {
				$this->database = database::new();
			}

			//set the domain_uuid
			if (!empty($domain_uuid) && is_uuid($domain_uuid)) {
				$this->domain_uuid = $domain_uuid;
			}
			elseif (isset($_SESSION['domain_uuid']) && is_uuid($_SESSION['domain_uuid'])) {
				$this->domain_uuid = $_SESSION['domain_uuid'];
			}

			//set the user_uuid
			if (!empty($user_uuid) && is_uuid($user_uuid)) {
				$this->user_uuid = $user_uuid;
			}
			elseif (isset($_SESSION['user_uuid']) && is_uuid($_SESSION['user_uuid'])) {
				$this->user_uuid = $_SESSION['user_uuid'];
			}

			//cache the permissions
			$this->apcu_enabled = function_exists('apcu_enabled') && apcu_enabled();

			if (!$this->apcu_enabled) {
				$this->load_cache();
			} else {
				//get the permissions
				if (isset($_SESSION['permissions'])) {
					$this->permissions = $_SESSION['permissions'];
				}
				else {
					//create the groups object
					$groups = new groups($this->database, $this->domain_uuid, $this->user_uuid);
					$this->groups = $groups->assigned();

					//get the list of groups assigned to the user
					if (!empty($this->groups)) {
						$this->assigned();
					}
				}
			}
		}

		/**
		 * get the array of permissions
		 */
		public function get_permissions() {
			return $this->permissions;
		}

		/**
		 * Add the permission
		 * @var string $permission
		 */
		public function add($permission, $type) {
			//add the permission if it is not in array
			if (!$this->exists($permission)) {
				$this->permissions[$permission] = $type;
			}
		}

		/**
		 * Remove the permission
		 * @var string $permission
		 */
		public function delete($permission, $type) {
			if ($this->exists($permission) && !empty($this->permissions[$permission])) {
				if ($type === "temp") {
					if ($this->permissions[$permission] === "temp") {
						unset($this->permissions[$permission]);
					}
				}
				else {
					if ($this->permissions[$permission] !== "temp") {
						unset($this->permissions[$permission]);
					}
				}
			}
		}

		/**
		 * Check to see if the permission exists
		 * @param string $permission_name
		 * @return bool True if exists. False otherwise
		 */
		public function exists(string $permission_name): bool {

			//if run from command line then return true
			if (defined('STDIN')) {
				return true;
			}

			//search for the permission
			if (!empty($permission_name)) {
				return isset($this->permissions[$permission_name]);
			}

			return false;
		}

		/**
		 * get the assigned permissions
		 * @var array $groups
		 */
		private function assigned() {
			//define the array
			$permissions = [];
			$parameter_names = [];

			//return empty array if there are no groups
			if (empty($this->groups)) {
				return [];
			}

			//prepare the parameters
			$x = 0;
			foreach ($this->groups as $field) {
				if (!empty($field['group_name'])) {
					$parameter_names[] = ":group_name_".$x;
					$parameters['group_name_'.$x] = $field['group_name'];
					$x++;
				}
			}

			//get the permissions assigned to the user through the assigned groups
			$sql = "select distinct(permission_name) from v_group_permissions ";
			$sql .= "where (domain_uuid = :domain_uuid or domain_uuid is null) ";
			$sql .= "and group_name in (".implode(", ", $parameter_names).") \n";
			$sql .= "and permission_assigned = 'true' ";
			$parameters['domain_uuid'] = $this->domain_uuid;
			$group_permissions = $this->database->select($sql, $parameters, 'all');

			//format the permission array
			foreach ($group_permissions as $row) {
				$permissions[$row['permission_name']] = 1;
			}

			//save permissions to this object
			$this->permissions = $permissions;
		}

		/**
		 * save the assigned permissions to a session
		 */
		public function session() {
			if (!empty($this->permissions)) {
				foreach ($this->permissions as $permission_name => $row) {
					$_SESSION['permissions'][$permission_name] = true;
					$_SESSION["user"]["permissions"][$permission_name] = true;
				}
			}
		}

		/**
		 * Returns a new permission object
		 */
		public static function new($database = null, $domain_uuid = null, $user_uuid = null): self {
			if (self::$permission === null) {
				self::$permission = new permissions($database, $domain_uuid, $user_uuid);
			}
			return self::$permission;
		}

		private function load_cache() {
			if ($this->apcu_enabled) {
				$key = 'permissions_' . $this->user_uuid;
				if (apcu_exists($key)) {
					$this->permissions = apcu_fetch($key);
				} else {
					foreach ($this->database->select("SELECT permission_name from v_group_permissions p join v_user_groups g on p.group_uuid = g.group_uuid where g.user_uuid = :id", ['id' => $this->user_uuid]) as $row) {
						$name = $row['permission_name'];
						$this->permissions[$name] = $name;
					}
					apcu_store($key, $this->permissions, 5 * 60/*seconds*/);
				}
			}
		}
	}

//examples
	/*
	//add the permission
		$p = permissions::new();
		$p->add($permission);
	//delete the permission
		$p = permissions::new();
		$p->delete($permission);
	*/
