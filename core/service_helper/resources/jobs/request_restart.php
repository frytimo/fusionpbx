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

// Check arguments
if ($argc < 2) {
    die("Usage: php request_restart.php <service_name>\n");
}

$service_name = $argv[1];

// Load FusionPBX config
$config_file = '/etc/fusionpbx/config.conf';
if (!file_exists($config_file)) {
    die("Config file not found.\n");
}
$config = parse_ini_file($config_file);
if (!$config) {
    die("Failed to parse config.\n");
}

// DB connection
try {
    $dsn = "pgsql:host={$config['database.0.host']};port={$config['database.0.port']};dbname={$config['database.0.name']}";
    $pdo = new PDO($dsn, $config['database.0.username'], $config['database.0.password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB connection failed.\n");
}

// Insert restart request
$uuid = uuid();
$sql = "INSERT INTO v_service_restarts (service_restart_uuid, service_name, restart_requested) VALUES (?, ?, NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute([$uuid, $service_name]);

echo "Restart requested for $service_name.\n";
