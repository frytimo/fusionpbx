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
 * The Initial Developer of the Original Code is
 * Mark J Crane <markjcrane@fusionpbx.com>
 * Portions created by the Initial Developer are Copyright (C) 2008-2025
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * Mark J Crane <markjcrane@fusionpbx.com>
 * Tim Fry <tim@fusionpbx.com>
 */

/**
 * Writes a human-readable debug summary of each CDR record to stdout.
 *
 * Active only when the 'cdr.debug' setting is true (default: false).
 *
 * When the 'cdr.pii_mask_debug_output' setting is also true, PII fields
 * are masked on a *clone* of the record before logging — the original
 * record is never modified.
 */
class xml_cdr_console_debug_writer implements xml_cdr_listener {

	/** @var modifier_pii_mask|null Lazy-created PII masker. */
	private ?modifier_pii_mask $pii_mask = null;

	/**
	 * Write a human-readable debug summary to stdout when cdr.debug is enabled.
	 *
	 * A clone of the record is used when PII masking is active so the original
	 * record is never modified.
	 *
	 * @param settings       $settings Application settings.
	 * @param xml_cdr_record $record   The fully-processed CDR record.
	 *
	 * @return void
	 */
	public function on_cdr(settings $settings, xml_cdr_record $record): void {
		if (!$settings->get('cdr', 'debug', false)) {
			return;
		}

		// Clone so we never mutate the canonical record
		$display = clone $record;

		// Apply PII masking to the clone if configured
		if ($settings->get('cdr', 'pii_mask_debug_output', false)) {
			if ($this->pii_mask === null) {
				$this->pii_mask = new modifier_pii_mask();
			}
			($this->pii_mask)($settings, $display);
		}

		echo $this->format($display);
	}
	private function format(xml_cdr_record $r): string {
		$lines = [];
		$lines[] = str_repeat('-', 60);
		$lines[] = sprintf("CDR  uuid=%-38s  file=%s", $r->xml_cdr_uuid ?? '?', $r->source_filename ?? '?');
		$lines[] = sprintf(
			"     direction=%-8s  status=%-12s  missed=%s",
			$r->direction ?? '?',
			$r->status ?? '?',
			$r->missed_call ?? '?'
		);
		$lines[] = sprintf(
			"     caller=%s <%s>  → %s",
			$r->caller_id_name ?? '',
			$r->caller_id_number ?? '',
			$r->destination_number ?? ''
		);
		$lines[] = sprintf(
			"     start=%s  billsec=%d  hangup=%s",
			$r->start_stamp ?? '',
			(int)($r->billsec ?? 0),
			$r->hangup_cause ?? ''
		);
		if (!empty($r->record_name)) {
			$lines[] = sprintf("     recording=%s/%s", $r->record_path ?? '', $r->record_name ?? '');
		}
		$lines[] = '';
		return implode("\n", $lines) . "\n";
	}

}
