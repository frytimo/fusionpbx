<?php

/*
 * FusionPBX — xml_cdr pipeline test: xml_cdr_record
 *
 * Tests the data object: construction, factory methods, lazy parsing,
 * extra_fields, to_array(), and source metadata.
 */

test_runner::add_test('record::from_xml sets source_file and source_filename', function () {
	$raw  = fixture_xml('b_inbound_answered.cdr.xml');
	$path = fixture_path('b_inbound_answered.cdr.xml');
	$r = xml_cdr_record::from_xml($raw, $path);

	assert_equals($path, $r->source_file);
	assert_equals('b_inbound_answered.cdr.xml', $r->source_filename);
	assert_equals('xml', $r->format);
});

test_runner::add_test('record::from_xml leg detection: "a_" prefix → a', function () {
	// Create a temporary record pointing at a file named a_something.cdr.xml
	$raw = fixture_xml('b_inbound_answered.cdr.xml');
	$r   = xml_cdr_record::from_xml($raw, '/tmp/a_test.cdr.xml');

	assert_equals('a', $r->leg);
});

test_runner::add_test('record::from_xml leg detection: non-a_ prefix → b', function () {
	$raw = fixture_xml('b_inbound_answered.cdr.xml');
	$r   = xml_cdr_record::from_xml($raw, '/tmp/b_test.cdr.xml');

	assert_equals('b', $r->leg);
});

test_runner::add_test('record::parsed() returns SimpleXMLElement', function () {
	$r = record_from_fixture('b_inbound_answered.cdr.xml');

	$xml = $r->parsed();

	assert_not_null($xml);
	assert_true($xml instanceof SimpleXMLElement);
});

test_runner::add_test('record::parsed() is lazy and cached', function () {
	$r = record_from_fixture('b_inbound_answered.cdr.xml');

	$xml1 = $r->parsed();
	$xml2 = $r->parsed();

	// Should be the same object reference
	assert_true($xml1 === $xml2, 'Expected parsed() to return cached instance');
});

test_runner::add_test('record::set_raw_content invalidates parse cache', function () {
	$r   = record_from_fixture('b_inbound_answered.cdr.xml');
	$xml = $r->parsed();

	// Replace content with a different well-formed XML snippet
	$r->set_raw_content('<cdr><variables><uuid>new</uuid></variables></cdr>');
	$xml2 = $r->parsed();

	assert_false($xml === $xml2, 'Expected parse cache to be invalidated by set_raw_content()');
});

test_runner::add_test('record::extra_fields roundtrip via set_field/get_field', function () {
	$r = record_from_fixture('b_inbound_answered.cdr.xml');

	$r->set_field('my_custom', 'hello');

	assert_equals('hello', $r->get_field('my_custom'));
	assert_equals(['my_custom' => 'hello'], $r->extra_fields());
});

test_runner::add_test('record::get_field returns null for unknown field', function () {
	$r = record_from_fixture('b_inbound_answered.cdr.xml');

	assert_null($r->get_field('does_not_exist'));
});

test_runner::add_test('record::to_array omits null properties', function () {
	$r = record_from_fixture('b_inbound_answered.cdr.xml');
	$r->xml_cdr_uuid    = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
	$r->domain_uuid     = 'd0d0d0d0-0000-0000-0000-000000000001';
	$r->caller_id_name  = null;  // explicitly null — should be omitted

	$arr = $r->to_array();

	assert_true(array_key_exists('xml_cdr_uuid', $arr), 'Expected xml_cdr_uuid to be in to_array()');
	assert_false(array_key_exists('caller_id_name', $arr), 'Null field should be omitted from to_array()');
});

test_runner::add_test('record::to_array includes extra_fields', function () {
	$r = record_from_fixture('b_inbound_answered.cdr.xml');
	$r->set_field('custom_col', 'value123');

	$arr = $r->to_array();

	assert_true(array_key_exists('custom_col', $arr), 'Extra field should appear in to_array()');
	assert_equals('value123', $arr['custom_col']);
});
