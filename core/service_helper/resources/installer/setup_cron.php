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

// Check if running as root
if (posix_getuid() !== 0) {
    die("Error: This script must be run as root (use sudo).\n");
}

// Define the cron job
$cron_job = "* * * * * /usr/bin/php /var/www/fusionpbx/core/service_helper/service_helper.php\n";

// Get current crontab
exec("crontab -l", $current_crontab, $return_var);
$current_crontab = implode("\n", $current_crontab) . "\n";

// Check if cron job already exists
if (strpos($current_crontab, trim($cron_job)) !== false) {
    echo "Cron job already exists.\n";
    exit(0);
}

// Append new job and set crontab
$new_crontab = $current_crontab . $cron_job;
$temp_file = tempnam(sys_get_temp_dir(), 'crontab');
file_put_contents($temp_file, $new_crontab);
exec("crontab $temp_file", $output, $return_var);
unlink($temp_file);

if ($return_var === 0) {
    echo "Cron job added successfully.\n";
} else {
    echo "Error adding cron job: " . implode("\n", $output) . "\n";
}
