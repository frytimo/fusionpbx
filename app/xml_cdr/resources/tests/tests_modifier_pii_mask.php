<?php

/*
 * FusionPBX — xml_cdr pipeline test: modifier_pii_mask
 *
 * Tests that PII masking modifies only the supplied record (not an implicit
 * original) and that each sensitive field becomes the expected sentinel value.
 *
 * The production pattern is:
 *   $clone = clone $record;
 *   (new modifier_pii_mask())($settings_with_pii_on, $clone);
 *   // original $record is unchanged
 */

// Helper: build settings with pii_mask_debug_output enabled
function pii_settings(): settings {
	return new settings(['cdr.pii_mask_debug_output' => true]);
}

test_runner::add_test('pii_mask: masking a clone does not change original', function () {
	$record = record_from_fixture('b_inbound_answered.cdr.xml');
	(new modifier_caller_id())(new settings(), $record);

	$original_name   = $record->caller_id_name;
	$original_number = $record->caller_id_number;

	$clone = clone $record;
	(new modifier_pii_mask())(pii_settings(), $clone);

	assert_equals($original_name,   $record->caller_id_name,
		'Original caller_id_name must not be changed after masking clone');
	assert_equals($original_number, $record->caller_id_number,
		'Original caller_id_number must not be changed after masking clone');
});

test_runner::add_test('pii_mask: caller_id_name becomes ***', function () {
	$record = record_from_fixture('b_inbound_answered.cdr.xml');
	(new modifier_caller_id())(new settings(), $record);
	(new modifier_pii_mask())(pii_settings(), $record);

	assert_equals('***', $record->caller_id_name);
});

test_runner::add_test('pii_mask: caller_id_number becomes ***', function () {
	$record = record_from_fixture('b_inbound_answered.cdr.xml');
	(new modifier_caller_id())(new settings(), $record);
	(new modifier_pii_mask())(pii_settings(), $record);

	assert_equals('***', $record->caller_id_number);
});

test_runner::add_test('pii_mask: network_addr becomes 0.0.0.0', function () {
	$record = record_from_fixture('b_inbound_answered.cdr.xml');
	(new modifier_caller_id())(new settings(), $record);
	(new modifier_pii_mask())(pii_settings(), $record);

	assert_equals('0.0.0.0', $record->network_addr);
});

test_runner::add_test('pii_mask: pii_mask_debug_output=false → fields not masked', function () {
	$record   = record_from_fixture('b_inbound_answered.cdr.xml');
	$settings = new settings(['cdr.pii_mask_debug_output' => false]);
	(new modifier_caller_id())(new settings(), $record);

	$original_name = $record->caller_id_name;
	(new modifier_pii_mask())($settings, $record);

	// When the feature is off the modifier should be a no-op
	assert_equals($original_name, $record->caller_id_name,
		'Modifier should be a no-op when pii_mask_debug_output is false');
});
