<?php

/*
 * FusionPBX — xml_cdr pipeline test: modifier_call_status
 *
 * Tests status derivation logic and epoch-based field extraction.
 * Depends on modifier_caller_id and modifier_missed_call running first
 * (same order as the production pipeline priority chain).
 */

// Helper: run caller_id → missed_call → call_status in sequence
function run_status_chain(xml_cdr_record $record, settings $s, ?database $db = null): void {
	(new modifier_caller_id())($s, $record);
	(new modifier_missed_call())($s, $record);
	(new modifier_call_status())($s, $record);
}

test_runner::add_test('call_status: inbound answered → status=answered', function () {
	$record = record_from_fixture('b_inbound_answered.cdr.xml');
	run_status_chain($record, new settings());

	assert_equals('answered', $record->status);
});

test_runner::add_test('call_status: inbound answered → billsec > 0', function () {
	$record = record_from_fixture('b_inbound_answered.cdr.xml');
	run_status_chain($record, new settings());

	assert_true((int) $record->billsec > 0, 'Expected billsec > 0 for answered call');
});

test_runner::add_test('call_status: inbound answered → start_epoch is set', function () {
	$record = record_from_fixture('b_inbound_answered.cdr.xml');
	run_status_chain($record, new settings());

	assert_not_empty($record->start_epoch);
	assert_true((int) $record->start_epoch > 0, 'Expected start_epoch to be a positive integer');
});

test_runner::add_test('call_status: inbound missed (ORIGINATOR_CANCEL) → status=missed', function () {
	$record = record_from_fixture('b_inbound_missed.cdr.xml');
	run_status_chain($record, new settings());

	assert_equals('missed', $record->status);
});

test_runner::add_test('call_status: inbound missed → billsec = 0', function () {
	$record = record_from_fixture('b_inbound_missed.cdr.xml');
	run_status_chain($record, new settings());

	assert_equals('0', (string) $record->billsec);
});

test_runner::add_test('call_status: voicemail destination → status=voicemail', function () {
	$record = record_from_fixture('b_voicemail.cdr.xml');
	run_status_chain($record, new settings());

	assert_equals('voicemail', $record->status);
});

test_runner::add_test('call_status: missing start_epoch → discard exception', function () {
	$record = record_from_fixture('b_no_start_epoch.cdr.xml');

	assert_exception(
		function () use ($record) {
			run_status_chain($record, new settings());
		},
		xml_cdr_discard_exception::class
	);
});

test_runner::add_test('call_status: outbound answered → status=answered', function () {
	$record = record_from_fixture('b_outbound_answered.cdr.xml');
	run_status_chain($record, new settings());

	assert_equals('answered', $record->status);
});

test_runner::add_test('call_status: call_center member_cancel → status=missed', function () {
	$record = record_from_fixture('b_call_center_member_cancel.cdr.xml');
	run_status_chain($record, new settings());

	assert_equals('missed', $record->status);
});
