<?php

 /**
 * event_guard_iptables class
 *
 */
class event_guard_iptables implements event_guard_interface {

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
	 * grep_path string
	 * @var string
	 */
	private $grep_path;

	/**
	 * chains array
	 * @var array
	 */
	private $chains;

	/**
	 * called when the object is created
	 */
	public function __construct(settings $settings) {
		// Save the settings object
		$this->settings = $settings;

		// Set the database object from the settings object
		$this->database = $settings->database();

		// Set firewall path
		$this->firewall_path = trim((string) shell_exec('command -v iptables'));

		// Set grep path
		$this->grep_path = trim((string) shell_exec('command -v grep'));

		// Create a chain array
		$this->chains[] = 'sip-auth-ip';
		$this->chains[] = 'sip-auth-fail';

		// Add the chains to active iptables
		if ($this->firewall_path != '') {
			foreach ($this->chains as $chain) {
				// Create the chain if it does not already exist
				$command = $this->firewall_path . ' -S ' . escapeshellarg($chain) . ' 2>/dev/null';
				exec($command, $output, $exit_code);
				if ($exit_code !== 0) {
					$command = $this->firewall_path . ' -N ' . escapeshellarg($chain) . ' 2>/dev/null';
					exec($command, $output, $exit_code);
				}

				// Add the jump from INPUT to the chain if it does not already exist
				$command = $this->firewall_path . ' -S INPUT 2>/dev/null | ' . $this->grep_path . ' -F -- ' . escapeshellarg('-j ' . $chain);
				exec($command, $output, $exit_code);
				if ($exit_code !== 0) {
					$command = $this->firewall_path . ' -I INPUT -j ' . escapeshellarg($chain) . ' 2>/dev/null';
					exec($command, $output, $exit_code);
				}
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

		// Check to see if the rule already exists so duplicates are not added
		$command = $this->firewall_path . ' -C ' . escapeshellarg($filter) . ' -s ' . $ip_address . ' -j DROP 2>/dev/null';
		exec($command, $output, $exit_code);
		if ($exit_code === 0) {
			// Return success, the address is already blocked
			return true;
		}

		// Run the block command for iptables
		// Example: iptables -I sip-auth-fail -s 127.0.0.1 -j DROP
		$command = $this->firewall_path . ' -I ' . escapeshellarg($filter) . ' -s ' . $ip_address . ' -j DROP 2>/dev/null';
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
		// Invalid IP address
		if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
			return false;
		}

		// Remove from all chains or a specific one
		if ($filter == 'all') {
			$chains = $this->chains;
		}
		else {
			$chains = [$filter];
		}

		// Remove the IP address from each chain
		foreach($chains as $chain) {
			// Loop until there are no matching rules remaining
			for ($i = 0; $i < 1000; $i++) {
				// Check to see if the rule still exists (the exec() output array must be cleared first)
				unset($output);
				$command = $this->firewall_path . ' -C ' . escapeshellarg($chain) . ' -s ' . $ip_address . ' -j DROP 2>/dev/null';
				exec($command, $output, $exit_code);
				if ($exit_code !== 0) {
					// No matching rule remains
					break;
				}

				// Remove one matching rule
				unset($output);
				$command = $this->firewall_path . ' -D ' . escapeshellarg($chain) . ' -s ' . $ip_address . ' -j DROP 2>/dev/null';
				exec($command, $output, $exit_code);
			}
		}

		// Return success
		return true;
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

		// Check to see if the address is blocked in the chain
		$command = $this->firewall_path . ' -C ' . escapeshellarg($filter) . ' -s ' . $ip_address . ' -j DROP 2>/dev/null';
		exec($command, $output, $exit_code);

		// Return the result
		return $exit_code === 0;
	}

	/**
	 * List the IP addresses blocked in the configured chains.
	 *
	 * @param string $filter The chain name, or 'all' for every chain used by this class.
	 *
	 * @return array An associative array keyed by chain name where each value is a list
	 *               of IP addresses blocked by that chain.
	 */
	public function block_list(string $filter) : array {
		// Select the chains to list
		if ($filter == 'all') {
			$chains = $this->chains;
		}
		else {
			$chains = [$filter];
		}

		// Get the addresses of each chain
		$blocked = [];
		foreach ($chains as $chain) {
			// Example: iptables -S sip-auth-fail
			unset($lines);
			$command = $this->firewall_path . ' -S ' . escapeshellarg($chain) . ' 2>/dev/null';
			exec($command, $lines, $exit_code);
			if ($exit_code !== 0) {
				// The chain does not exist
				continue;
			}

			// Match the rules for a single address, example: -A sip-auth-fail -s 192.0.2.1/32 -j DROP
			foreach ($lines as $line) {
				if (!preg_match('/^-A\s+\S+\s+-s\s+(\S+)\s.*-j\s+DROP$/', trim($line), $matches)) {
					continue;
				}

				// Separate the address from the prefix length, only single addresses are tracked
				$ip_address = $matches[1];
				$prefix_length = '';
				if (str_contains($ip_address, '/')) {
					[$ip_address, $prefix_length] = explode('/', $ip_address, 2);
				}
				if ($prefix_length !== '' && $prefix_length != '32' && $prefix_length != '128') {
					continue;
				}
				if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
					continue;
				}

				// Add the address to the list for the chain
				$blocked[$chain][] = $ip_address;
			}
		}

		// Return the list of blocked addresses
		return $blocked;
	}
}
