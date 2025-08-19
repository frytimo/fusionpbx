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
 * Portions created by the Initial Developer are Copyright (C) 2008-2025
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * Mark J Crane <markjcrane@fusionpbx.com>
 * Tim Fry <tim@fusionpbx.com>
 */

/**
 * A gateway_modifier will replace the UUID of a gateway with the name of a gateway
 *
 * @author Tim Fry <tim@fusionpbx.com>
 */
class gateway_modifier implements modifier {

	/**
	 * Associative array of gateway_uuid => gateway_name
	 * @var array
	 */
	private $gateways;

	/**
	 * Reference to a database object
	 * @var database
	 */
	private $database;

	/**
	 * Creates a gateway modifier that will replace the UUID with the name of a gateway
	 * @param array $gateways Associative array of UUID/Names
	 */
	public function __construct(database $database) {
		$this->gateways = self::fetch_enabled_gateways($database);
		$this->database = $database;
	}

	public function __invoke(string $key, &$value, callable $next): void {
		if ($key === 'application_data') {
			// Get the UUID from sofia/gateway/11112222-3333-4444-5555-666677778888/$1
			$uuid = substr($value, 14, 36);
			// Ensure it is actually a UUID
			if (is_uuid($uuid)) {
				// Check the gateway names cache
				foreach ($this->gateways as $gateway_uuid => $gateway_name) {
					$data = str_replace($gateway_uuid, $gateway_name, $value);
					// If the UUID was actually replaced with the name of the gateway then it was successful
					if ($data != $value) {
						$value = $data;
					} else {
						//
						// Gateway lookup needed because we don't have the name yet. This will
						// happen when a gateway is added after the active_calls service was
						// started.
						//
						// Query the database for the name of the gateway using the UUID
						$gateway_name = self::fetch_gateway_name_by_uuid($this->database, $uuid);
						// Make sure we have the new name
						if (!empty($gateway_name)) {
							// Cache the gateway name and UUID for future lookups
							$this->gateways[$uuid] = $gateway_name;
							// Update the gateway to display with the new name
							$value = str_replace($uuid, $gateway_name, $value);
						}
					}
				}
			}
		}
		// Call the next modifier
		$next($key, $value);
	}
//
// Old Function no ability to detect new names but can replace gateway uuid from any $value
//
//	public function __invoke(string $key, &$value, callable $next) {
//		// Check the gateway names cache
//		foreach ($this->gateways as $gateway_uuid => $gateway_name) {
//			$data = str_replace($gateway_uuid, $gateway_name, $value);
//			// If the UUID was actually replaced with the name of the gateway then it was successful
//			if ($data != $value) {
//				$value = $data;
//			}
//		}
//		// Call the next modifier
//		$next($key, $value);
//	}

	/**
	 * Returns the gateway name based on the gateway UUID provided ignoring whether or not the gateway is enabled
	 * @param database $database
	 * @param string $gateway_uuid
	 * @return string
	 * @internal This helper function should be in the gateways class
	 */
	public static function fetch_gateway_name_by_uuid(database $database, string $gateway_uuid): string {
		$table_prefix = database::TABLE_PREFIX;
		return ($database->execute("select gateway_name from {$table_prefix}gateways where gateway_uuid = :gateway_uuid limit 1", ['gateway_uuid' => $gateway_uuid], 'column') ?: '');
	}

	/**
	 * Returns an array of currently enabled gateways in the database
	 * @return array UUID key based enabled gateways
	 */
	public static function fetch_enabled_gateways(database $database): array {
		$table_prefix = database::TABLE_PREFIX;
		return array_column($database->execute("select gateway, gateway_uuid from {$table_prefix}gateways where enabled='true'") ?: [], 'gateway', 'gateway_uuid');
	}
}
