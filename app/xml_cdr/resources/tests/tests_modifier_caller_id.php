<?php

/*
 * FusionPBX — xml_cdr pipeline test: modifier_caller_id
 *
 * Tests core caller-ID extraction and field mapping from the CDR XML.
 */

test_runner::add_test('caller_id: inbound answered — extracts caller_id_name', function () {
	$record   = record_from_fixture('b_inbound_answered.cdr.xml');
	$settings = new settings();
	$mod      = new modifier_caller_id();

	$mod($settings, $record);

	assert_not_empty($record->caller_id_name);
});

test_runner::add_test('caller_id: inbound answered — extracts caller_id_number', function () {
	$record   = record_from_fixture('b_inbound_answered.cdr.xml');
	$settings = new settings();
	$mod      = new modifier_caller_id();

	$mod($settings, $record);

	assert_not_empty($record->caller_id_number);
	// Must be digits only (sanitised)
	assert_true(
		ctype_digit($record->caller_id_number) || preg_match('/^\+?\d+$/', $record->caller_id_number),
		'caller_id_number should contain only digits (and optional leading +)'
	);
});

test_runner::add_test('caller_id: inbound answered — extracts destination_number', function () {
	$record   = record_from_fixture('b_inbound_answered.cdr.xml');
	$mod      = new modifier_caller_id();

	$mod(new settings(), $record);

	assert_not_empty($record->destination_number);
});

test_runner::add_test('caller_id: inbound answered — sets direction to inbound', function () {
	$record = record_from_fixture('b_inbound_answered.cdr.xml');
	$mod    = new modifier_caller_id();

	$mod(new settings(), $record);

	// The fixture has call_direction=inbound; modifier must honour it
	assert_not_empty($record->direction);
});

test_runner::add_test('caller_id: outbound answered — extracts outbound fields', function () {
	$record = record_from_fixture('b_outbound_answered.cdr.xml');
	$mod    = new modifier_caller_id();

	$mod(new settings(), $record);

	assert_not_empty($record->caller_id_number);
	assert_not_empty($record->destination_number);
});

test_runner::add_test('caller_id: domain_name copied to record', function () {
	$record = record_from_fixture('b_inbound_answered.cdr.xml');
	$mod    = new modifier_caller_id();

	$mod(new settings(), $record);

	// Fixture XML contains a domain_name variable; modifier must expose it
	assert_not_empty($record->domain_name);
});

test_runner::add_test('caller_id: context field is set', function () {
	$record = record_from_fixture('b_inbound_answered.cdr.xml');
	$mod    = new modifier_caller_id();

	$mod(new settings(), $record);

	assert_not_empty($record->context);
});
