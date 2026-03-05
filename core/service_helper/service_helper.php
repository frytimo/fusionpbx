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

require_once dirname(__DIR__, 2) . '/resources/require.php';
require_once dirname(__DIR__, 2) . '/resources/check_auth.php';

if (!permission_exists('service_helper_view')) {
    echo "access denied";
    exit;
}

// Add multi-lingual support
$text = new text()->get();

// Add the header
require_once 'resources/header.php';
$document['title'] = $text['title-service_helper'];

// get the http post data
if (count($_POST) > 0 && strlen($_POST["persistformvar"]) == 0) {
    // request a service restart
    if (permission_exists('service_helper_add')) {
        $service_name = $_POST['service_name'];
        if (!empty($service_name)) {
            service_helper::queue_restart($database, $service_name, $_SESSION['user_uuid']);
            message::add($text['message-restart_requested']);
        }
    }

    header("Location: service_helper.php");
    exit;
}

// show the content
echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
echo "<tr>\n";
echo "<td width='50%' align='left' nowrap='nowrap'><b>" . $text['header-service_helper'] . "</b></td>\n";
echo "<td width='50%' align='right'>\n";
if (permission_exists('service_helper_add')) {
    echo "	<input type='button' class='btn' name='' alt='" . $text['button-add'] . "' onclick=\"window.location='service_helper_edit.php'\" value='" . $text['button-add'] . "'>\n";
}
echo "</td>\n";
echo "</tr>\n";
echo "</table>\n";
echo "<br />\n";

// list the service restarts
$result = service_helper::get_queue_list($database);

echo "<table class='tr_hover' width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
echo "<tr>\n";
echo "<th>" . $text['label-service_name'] . "</th>\n";
echo "<th>" . $text['label-restart_requested'] . "</th>\n";
echo "<th>" . $text['label-restart_completed'] . "</th>\n";
echo "<th>" . $text['label-processed_hostname'] . "</th>\n";
echo "</tr>\n";

if (is_array($result) && @sizeof($result) != 0) {
    foreach ($result as $row) {
        echo "<tr>\n";
        echo "	<td>" . $row['service_name'] . "</td>\n";
        echo "	<td>" . $row['restart_requested'] . "</td>\n";
        echo "	<td>" . ($row['restart_completed'] ?: 'Pending') . "</td>\n";
        echo "	<td>" . $row['processed_hostname'] . "</td>\n";
        echo "</tr>\n";
    }
}
echo "</table>\n";

// include the footer
require_once "resources/footer.php";
