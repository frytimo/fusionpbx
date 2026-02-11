<?php

/**
 * FusionPBX
 * Version: MIT
 *
 * Copyright (c) 2008-2024 Mark J Crane <markjcrane@fusionpbx.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * Contributor(s):
 * Mark J Crane <markjcrane@fusionpbx.com>
 * Tim Fry <tim@fusionpbx.com>
 */

// Ensure CLI only
if (php_sapi_name() !== 'cli') {
    die("Error: This script must be run from the command line.\n");
}

// Load the auto loader
require_once dirname(__DIR__, 4) . '/resources/classes/auto_loader.php';
$autoload = new auto_loader();

// Load the functions file
require_once dirname(__DIR__, 4) . '/resources/functions.php';

// Load the config
$config = config::load();

// Connect to the database
$database = database::new(['config' => $config]);

$restarts = service_helper::get_queue_list($database);

$hostname = gethostname();

foreach ($restarts as $row) {
    $service_name = escapeshellarg($row['service_name']);
    $parameters = [];
    // Execute restart
    exec("sudo systemctl restart $service_name", $output, $return_var);
    if ($return_var === 0) {
        // Update DB on success
        $update_sql = "UPDATE
							v_service_restarts
						SET
							restart_completed = NOW()
							,processed_hostname = :hostname
						WHERE
							service_restart_uuid = :uuid
		";
        $parameters['uuid'] = $row['service_restart_uuid'];
        $parameters['hostname'] = $hostname;
        $database->execute($update_sql, $parameters);
    } else {
        // Log failure and mark as processed to avoid retries
        error_log("Failed to restart $service_name: " . implode("\n", $output));
        $update_sql = "UPDATE
							v_service_restarts
						SET
							processed_hostname = :hostname
						WHERE
							service_restart_uuid = :uuid
		";
        $parameters['uuid'] = $row['service_restart_uuid'];
        $parameters['hostname'] = $hostname;
        $database->execute($update_sql, $parameters);
    }
}

return 0;
