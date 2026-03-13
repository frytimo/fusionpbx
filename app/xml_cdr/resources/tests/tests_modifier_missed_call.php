<?php

/*
 * FusionPBX — xml_cdr pipeline test: modifier_missed_call
 *
 * Tests all rule-chain branches used to determine whether a call is missed.
 */

// Helper: run caller_id modifier first so destination_number is set
function run_caller_id_and_missed_call(xml_cdr_record $record, settings $s): void {
	(new modifier_caller_id())($s, $record);
	(new modifier_missed_call())($s, $record);
}

test_runner::add_test('missed_call: ORIGINATOR_CANCEL → missed', function () {
	$record   = record_from_fixture('b_inbound_missed.cdr.xml');
	$settings = new settings();

	run_caller_id_and_missed_call($record, $settings);

	assert_equals('true', $record->missed_call,
		'ORIGINATOR_CANCEL inbound with no bridge_uuid should be missed');
});

test_runner::add_test('missed_call: answered call with bridge_uuid → not missed', function () {
	$record   = record_from_fixture('b_inbound_answered.cdr.xml');
	$settings = new settings();

	run_caller_id_and_missed_call($record, $settings);

	assert_equals('false', $record->missed_call,
		'Answered call with bridge_uuid should not be missed');
});

test_runner::add_test('missed_call: cc_side=member, cc_cause=cancel → missed', function () {
	$record   = record_from_fixture('b_call_center_member_cancel.cdr.xml');
	$settings = new settings();

	run_caller_id_and_missed_call($record, $settings);

	assert_equals('true', $record->missed_call,
		'cc_side=member + cc_cause=cancel should be treated as missed');
});

test_runner::add_test('missed_call: voicemail (*99) destination → missed', function () {
	$record   = record_from_fixture('b_voicemail.cdr.xml');
	$settings = new settings();

	run_caller_id_and_missed_call($record, $settings);

	// *99 destinations are considered missed (went to voicemail)
	assert_equals('true', $record->missed_call,
		'Voicemail destination (*99) should be treated as missed');
});

test_runner::add_test('missed_call: outbound answered → not missed', function () {
	$record   = record_from_fixture('b_outbound_answered.cdr.xml');
	$settings = new settings();

	run_caller_id_and_missed_call($record, $settings);

	assert_equals('false', $record->missed_call,
		'Outbound answered call should not be missed');
});
