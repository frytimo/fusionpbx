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

require_once dirname(__DIR__, 2) . "/resources/require.php";
require_once dirname(__DIR__, 2) . "/resources/check_auth.php";

if (permission_exists('service_helper_add')) {
    // add multi-lingual support
    require_once "app_languages.php";
    foreach ($text as $key => $value) {
        $text[$key] = $value[$_SESSION['domain']['language']['code']];
    }
}

// add the header
require_once "resources/header.php";
$document['title'] = $text['title-service_helper_edit'];

// show the content
echo "<form method='post' name='frm' action=''>\n";
echo "<table width='100%'  border='0' cellpadding='0' cellspacing='0'>\n";
echo "<tr>\n";
echo "<td align='left' width='30%' nowrap='nowrap'><b>" . $text['header-service_helper_edit'] . "</b></td>\n";
echo "<td width='70%' align='right'>\n";
echo "	<input type='button' class='btn' name='' alt='" . $text['button-back'] . "' onclick=\"window.location='service_helper.php'\" value='" . $text['button-back'] . "'>\n";
echo "	<input type='submit' class='btn' value='" . $text['button-save'] . "'>\n";
echo "</td>\n";
echo "</tr>\n";
echo "</table>\n";
echo "<br /><br />\n";

echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
echo "<tr>\n";
echo "<th class='th' colspan='2' align='left'>" . $text['header-service_restart'] . "</th>\n";
echo "</tr>\n";
echo "<tr>\n";
echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
echo "	" . $text['label-service_name'] . "\n";
echo "</td>\n";
echo "<td class='vtable' align='left'>\n";
echo "	<input class='formfld' type='text' name='service_name' maxlength='255' value=''>\n";
echo "	<br />" . $text['description-service_name'] . "\n";
echo "</td>\n";
echo "</tr>\n";
echo "</table>\n";
echo "<br /><br />\n";
echo "</form>\n";

// include the footer
require_once "resources/footer.php";
