<?php

/*
 * FusionPBX — xml_cdr pipeline test: modifier_provider_prefix
 *
 * Tests: prefix is stripped from the start of destination_number;
 *        no-prefix fixture is unaffected.
 */

test_runner::add_test('provider_prefix: strips prefix from destination_number', function () {
	// b_provider_prefix.cdr.xml has provider_prefix=9, destination_number=91003
	$record   = record_from_fixture('b_provider_prefix.cdr.xml');
	$settings = new settings();

	// First populate destination_number via caller_id modifier
	(new modifier_caller_id())($settings, $record);

	// Then strip the prefix
	(new modifier_provider_prefix())($settings, $record);

	assert_equals('1003', $record->destination_number,
		'Expected provider_prefix=9 to be stripped from destination 91003');
});

test_runner::add_test('provider_prefix: no-prefix fixture destination unchanged', function () {
	$record   = record_from_fixture('b_inbound_answered.cdr.xml');
	$settings = new settings();

	(new modifier_caller_id())($settings, $record);

	$before = $record->destination_number;

	(new modifier_provider_prefix())($settings, $record);

	$after = $record->destination_number;

	assert_equals($before, $after,
		'destination_number should not change when provider_prefix is absent');
});
