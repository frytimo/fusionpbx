<?php

/*
 * FusionPBX — xml_cdr pipeline test: modifier_call_block
 *
 * Tests: call_block=true + save_cdr=false → discard;
 *        call_block=true + save_cdr=true  → continues;
 *        call_block absent               → continues.
 */

test_runner::add_test('call_block: call_block=true and save_cdr=false → discard exception', function () {
	$record = record_from_fixture('b_call_block.cdr.xml');
	// Setting: do NOT save CDR when blocked
	$settings = new settings(['call_block.save_call_detail_record' => false]);
	$mod      = new modifier_call_block();

	assert_exception(
		function () use ($record, $mod, $settings) {
			$mod($settings, $record);
		},
		xml_cdr_discard_exception::class
	);
});

test_runner::add_test('call_block: call_block=true but save_cdr=true → no exception', function () {
	$record   = record_from_fixture('b_call_block.cdr.xml');
	$settings = new settings(['call_block.save_call_detail_record' => true]);
	$mod      = new modifier_call_block();

	// Must not throw
	$mod($settings, $record);
	assert_true(true); // reached here without exception
});

test_runner::add_test('call_block: call_block variable absent → no exception', function () {
	$record   = record_from_fixture('b_inbound_answered.cdr.xml');
	$settings = new settings(['call_block.save_call_detail_record' => false]);
	$mod      = new modifier_call_block();

	$mod($settings, $record);
	assert_true(true);
});

test_runner::add_test('call_block: call_block=false (explicit) → no exception', function () {
	// Build an XML where call_block is explicitly false
	$xml    = fixture_xml('b_inbound_answered.cdr.xml');
	$xml    = str_replace('</variables>', '<call_block>false</call_block></variables>', $xml);
	$record = xml_cdr_record::from_xml($xml, '/tmp/b_not_blocked.cdr.xml');
	$settings = new settings(['call_block.save_call_detail_record' => false]);
	$mod      = new modifier_call_block();

	$mod($settings, $record);
	assert_true(true);
});
