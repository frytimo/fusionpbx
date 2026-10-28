<?php

 /**
 * event_guard_nftables class
 *
 */
class event_guard_nftables implements event_guard_interface {

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
		$this->firewall_path = trim((string) shell_exec('command -v nft'));

		// Create a chain array
		$this->chains[] = 'sip-auth-ip';
		$this->chains[] = 'sip-auth-fail';

		// Add the chains if they do not already exist
		if ($this->firewall_path != '') {
			foreach ($this->chains as $chain) {
				$command = $this->firewall_path . ' list chain inet filter ' . escapeshellarg($chain) . ' 2>/dev/null';
				exec($command, $output, $exit_code);
				if ($exit_code !== 0) {
					$command = $this->firewall_path . ' add chain inet filter ' . escapeshellarg($chain) . ' { type filter hook input priority -50 \; } 2>/dev/null';
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
		// Invalid IP address
		if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
			return false;
		}

		// Check to see if the address is already blocked so duplicates are not added
		$command = $this->firewall_path . ' -a list chain inet filter ' . escapeshellarg($filter) . ' 2>/dev/null';
		exec($command, $lines, $exit_code);
		if ($exit_code === 0 && strpos(implode("\n", $lines), 'ip saddr ' . $ip_address . ' ') !== false) {
			// Return success, the address is already blocked
			return true;
		}

		// Run the block command for nftables
		// Example: nft add rule inet filter sip-auth-ip ip saddr 192.168.1.100 counter drop
		$command = $this->firewall_path . ' add rule inet filter ' . escapeshellarg($filter) . ' ip saddr ' . $ip_address . ' counter drop 2>/dev/null';
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
			for ($i = 0; $i < 999; $i++) {
				// List the chain rules with their handles (the exec() output array must be cleared first)
				unset($lines);
				$command = $this->firewall_path . ' -a list chain inet filter ' . escapeshellarg($chain) . ' 2>/dev/null';
				exec($command, $lines, $exit_code);
				if ($exit_code !== 0) {
					// The chain does not exist
					break;
				}

				// Collect the handles of the matching rules
				$handles = [];
				foreach ($lines as $line) {
					if (strpos($line, 'ip saddr ' . $ip_address . ' ') !== false) {
						if (preg_match('/# handle (\d+)/', $line, $matches)) {
							$handles[] = $matches[1];
						}
					}
				}
				if (count($handles) == 0) {
					// No matching rules remain
					break;
				}

				// Delete the matching rules by handle
				$deleted = false;
				foreach ($handles as $handle) {
					unset($output);
					$command = $this->firewall_path . ' delete rule inet filter ' . escapeshellarg($chain) . ' handle ' . $handle . ' 2>/dev/null';
					exec($command, $output, $exit_code);
					if ($exit_code === 0) {
						$deleted = true;
					}
				}
				if (!$deleted) {
					// The rules could not be deleted
					break;
				}
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
		$command = $this->firewall_path . ' -a list chain inet filter ' . escapeshellarg($filter) . ' 2>/dev/null';
		exec($command, $lines, $exit_code);
		if ($exit_code === 0 && strpos(implode("\n", $lines), 'ip saddr ' . $ip_address . ' ') !== false) {
			return true;
		}

		// Return failed
		return false;
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
			// Example: nft -a list chain inet filter sip-auth-fail
			unset($lines);
			$command = $this->firewall_path . ' -a list chain inet filter ' . escapeshellarg($chain) . ' 2>/dev/null';
			exec($command, $lines, $exit_code);
			if ($exit_code !== 0) {
				// The chain does not exist
				continue;
			}

			// Match the rules for a single address, example: ip saddr 192.0.2.1 counter drop
			foreach ($lines as $line) {
				if (!preg_match('/\bip saddr\s+(\S+)\s.*\bdrop\b/', $line, $matches)) {
					continue;
				}

				// Only single addresses are tracked, addresses with a prefix length are skipped
				$ip_address = $matches[1];
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
