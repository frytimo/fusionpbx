<?php

/**
 * access control nodes class
 */
class access_control_nodes extends access_controls {

	/**
	 * declare constant variables
	 */
	const PERMISSION_PREFIX = 'access_control_node_';
	const LIST_PAGE         = 'access_controls.php';
	const TABLE             = 'access_control_nodes';
	const UUID_PREFIX       = 'access_control_node_';

	/**
	 * Clears the ACL session cache, the persistent cache, and triggers an ACL
	 * reload after node records are deleted.
	 *
	 * @return void
	 */
	public function after_delete(): void {
		parent::after_delete();

		$cache = new cache;
		$cache->delete("configuration:acl.conf");

		event_socket::async("reloadacl");
	}
}
