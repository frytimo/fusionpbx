<?php
/*
 * FusionPBX
 * Version: MPL 1.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is FusionPBX
 *
 * Marks all dialplans as using the unified editor (dialplan_editor_version = 'unified').
 * After running this tool, legacy dialplans will open in the unified editor without
 * showing the migration notice.
 *
 * Usage (CLI):
 *   php /var/www/fusionpbx/app/dialplans/resources/migrate_to_unified.php [--dry-run]
 *
 * Options:
 *   --dry-run   Show what would be updated without making any changes.
 */

// must be run from CLI
if (php_sapi_name() !== 'cli') {
	header('HTTP/1.1 403 Forbidden');
	echo "This script must be run from the command line.\n";
	exit(1);
}

$dry_run = in_array('--dry-run', $argv ?? []);

// bootstrap FusionPBX
require dirname(__DIR__, 3) . '/resources/require.php';

// count dialplans that are not yet marked as unified
$sql_count = "SELECT count(*) FROM v_dialplans WHERE dialplan_editor_version IS DISTINCT FROM 'unified'";
$total = (int) $database->select($sql_count, null, 'column');

if ($total === 0) {
	echo "All dialplans are already marked as unified. Nothing to do.\n";
	exit(0);
}

echo ($dry_run ? "[DRY RUN] " : "") . "Found {$total} dialplan(s) to migrate.\n";

if ($dry_run) {
	// show the dialplan names that would be updated
	$sql_list = "SELECT dialplan_uuid, dialplan_name, dialplan_context FROM v_dialplans WHERE dialplan_editor_version IS DISTINCT FROM 'unified' ORDER BY dialplan_context, dialplan_name";
	$rows = $database->select($sql_list, null, 'all');
	if (is_array($rows)) {
		foreach ($rows as $row) {
			echo "  [{$row['dialplan_context']}] {$row['dialplan_name']} ({$row['dialplan_uuid']})\n";
		}
	}
	echo "[DRY RUN] No changes made.\n";
	exit(0);
}

// perform the update
$sql_update = "UPDATE v_dialplans SET dialplan_editor_version = 'unified' WHERE dialplan_editor_version IS DISTINCT FROM 'unified'";
$database->execute($sql_update);

echo "Done. {$total} dialplan(s) marked as unified.\n";
exit(0);
