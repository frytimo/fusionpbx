<?php

/**
 * event_guard_interface class
 *
*/
interface event_guard_interface {
	public function block_add(string $ip_address, string $filter) : bool;
	public function block_delete(string $ip_address, string $filter) : bool;
	public function block_exists(string $ip_address, string $filter) : bool;

	/**
	 * List the IP addresses that are blocked by the firewall.
	 *
	 * @param string $filter The filter name, or 'all' for every filter the firewall manages.
	 *
	 * @return array An associative array keyed by filter name where each value is a list
	 *               of IP addresses blocked by that filter.
	 */
	public function block_list(string $filter) : array;
}
