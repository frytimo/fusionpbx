<?php

/*
 * Service Manager
 * ~~~~~~~~~~~~~~~
 *
 * Description:
 * This script manages starting, stopping, and restarting services based on
 * service_job_action values in the services table.
 * It is intended to be run from a cron job and will not execute if accessed via a web browser.
 *
 * Requirements:
 * - PHP CLI
 * - Access to the database to retrieve job configurations
 * - Systemctl command available for managing services
 *
 * Security:
 * - Only allows specific commands (start, stop, restart) to be executed for security reasons.
 * - Logs disallowed commands without revealing sensitive information.
 *
 */

// Include the required files
require_once dirname(__DIR__, 4) . "/resources/require.php";

// Ensure this script is only run from the command line
if (!is_cli()) {
	header("HTTP/1.1 403 Forbidden");
	exit;
}

// Get services with pending actions
$sql = "SELECT service_uuid, service_name, service_job_action ";
$sql .= "FROM v_services ";
$sql .= "WHERE service_job_action IS NOT NULL AND service_job_action <> ''";
$services = $database->select($sql, [], 'all');

// Process services with pending actions
foreach ($services as $service_row) {
	if (empty($service_row['service_job_action'])) {
		continue;
	}

	// Field values
	$action = $service_row['service_job_action'];
	$service = $service_row['service_name'];

	// Filter for security: only allow certain characters in the command
	switch($action) {
		// Allow these commands
		case 'start':
		case 'stop':
		case 'restart':
			// Execute command and capture status/output
			$command = "systemctl {$action} {$service}";
			$output = [];
			$exit_code = 0;

			// Wait for the command to finish and capture output and exit code
			exec(escapeshellcmd($command), $output, $exit_code);

			if ($exit_code === 0) {
				// Clear action after successful execution
				$array = [];
				$array['services'][0]['service_uuid'] = $service_row['service_uuid'];
				$array['services'][0]['service_job_action'] = null;
				$database->save($array);

				error_log("service_manager.php: Executed {$command} for service {$service}");
			}
			else {
				error_log("service_manager.php: Failed {$command} for service {$service}; exit={$exit_code}; output=".implode(' | ', $output));
			}
			break;
		// Disallow any other commands
		default:
			// Only log the UUID of the job for security reasons, not the command itself
			error_log("service_manager.php: Disallowed action '{$action}' for service_uuid " . $service_row['service_uuid']);
			continue 2; // Skip to the next job
	}
}

// Exit the script
exit;
