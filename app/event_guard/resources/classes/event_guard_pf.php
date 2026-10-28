<?php

 /**
 * event_guard_pf class
 *
 */
class event_guard_pf implements event_guard_interface {

	/**
	 * database object
	 * @var database
	 */
	private $database;

	/**
	 * settings object
	 * @var settings
	 */
	private $settings;

	/**
	 * firewall_path string
	 * @var string
	 */
	private $firewall_path;

	/**
	 * filters array
	 * @var array
	 */
	private $filters;

	/**
	 * called when the object is created
	 */
	public function __construct(settings $settings) {
		// Save the settings object
		$this->settings = $settings;

		// Set the database object from the settings object
		$this->database = $settings->database();

		// Set firewall path
		$this->firewall_path = trim((string) shell_exec('command -v pfctl'));

		// Create a filter array
		$this->filters[] = 'sip-auth-ip';
		$this->filters[] = 'sip-auth-fail';

		// Create the tables if they do not already exist
		if ($this->firewall_path != '') {
			foreach ($this->filters as $table) {
				$command = $this->firewall_path . ' -t ' . escapeshellarg($table) . ' -T create 2>/dev/null';
				exec($command, $output, $exit_code);
			}
		}
	}

	/**
	 * Execute a block command for iptables
	 *
	 * @param string $ip_address The IP address to block
	 * @param string $filter     The filter name for nftables, iptables or pf
	 * @param array  $event      The event data containing 'to-user' and 'to-host'
	 *
	 * @return boolean True if the block command was executed successfully, false otherwise
	 */
	public function block_add(string $ip_address, string $filter) : bool {

		// Invalid ip address
		if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
			return false;
		}

		// Check to see if the address is already in the table so duplicates are not added
		$command = $this->firewall_path . ' -t ' . escapeshellarg($filter) . ' -Ts 2>/dev/null';
		exec($command, $lines, $exit_code);
		if ($exit_code === 0 && in_array($ip_address, $lines)) {
			// Return success, the address is already blocked
			return true;
		}

		// Run the block command for pf
		// Example: pfctl -t sip-auth-ip -T add 127.0.0.5
		$command = $this->firewall_path . ' -t ' . escapeshellarg($filter) . ' -T add ' . escapeshellarg($ip_address) . ' 2>/dev/null';
		exec($command, $output, $exit_code);

		// Return the result
		return $exit_code === 0;
	}

	/**
	 * Unblock a specified IP address from a firewall.
	 *
	 * @param string $ip_address The IP address to unblock.
	 * @param string $filter     The filter name used in the firewall configuration.
	 *
	 * @return bool True if the IP address was successfully unblocked, false otherwise.
	 */
	public function block_delete(string $ip_address, string $filter) : bool {
		// Invalid ip address
		if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
			return false;
		}

		// Remove from all tables or a specific one
		if ($filter == 'all') {
			$tables = $this->filters;
		}
		else {
			$tables = [$filter];
		}

		// Unblock the address
		$result = true;
		foreach($tables as $table) {
			// Check to see if the address is in the table (the exec() output array must be cleared first)
			unset($lines);
			$command = $this->firewall_path . ' -t ' . escapeshellarg($table) . ' -Ts 2>/dev/null';
			exec($command, $lines, $exit_code);
			if ($exit_code !== 0 || !in_array($ip_address, $lines)) {
				// The address is not in this table
				continue;
			}

			// Example: pfctl -t sip-auth-ip -T delete 127.0.0.5
			unset($output);
			$command = $this->firewall_path . ' -t ' . escapeshellarg($table) . ' -T delete ' . escapeshellarg($ip_address) . ' 2>/dev/null';
			exec($command, $output, $exit_code);
			if ($exit_code !== 0) {
				$result = false;
			}
		}

		// Return the result
		return $result;
	}

	/**
	 * Check if an IP address is blocked in the configured firewall.
	 *
	 * @param string $ip_address The IP address to check
	 *
	 * @return bool True if the address is blocked, False otherwise
	 */
	public function block_exists(string $ip_address, string $filter) : bool {
		// Invalid IP address
		if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
			return false;
		}

		// Check to see if the address is in the table
		$command = $this->firewall_path . ' -t ' . escapeshellarg($filter) . ' -Ts 2>/dev/null';
		exec($command, $lines, $exit_code);
		if ($exit_code === 0 && in_array($ip_address, $lines)) {
			return true;
		}

		// Return the result
		return false;
	}

	/**
	 * List the IP addresses in the configured tables.
	 *
	 * @param string $filter The table name, or 'all' for every table used by this class.
	 *
	 * @return array An associative array keyed by table name where each value is a list
	 *               of IP addresses in that table.
	 */
	public function block_list(string $filter) : array {
		// Select the tables to list
		if ($filter == 'all') {
			$tables = $this->filters;
		}
		else {
			$tables = [$filter];
		}

		// Get the addresses of each table
		$blocked = [];
		foreach ($tables as $table) {
			// Example: pfctl -t sip-auth-fail -Ts
			unset($lines);
			$command = $this->firewall_path . ' -t ' . escapeshellarg($table) . ' -Ts 2>/dev/null';
			exec($command, $lines, $exit_code);
			if ($exit_code !== 0) {
				// The table does not exist
				continue;
			}

			// Each line contains a single address
			foreach ($lines as $line) {
				$ip_address = trim($line);
				if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
					continue;
				}

				// Add the address to the list for the table
				$blocked[$table][] = $ip_address;
			}
		}

		// Return the list of blocked addresses
		return $blocked;
	}
}
