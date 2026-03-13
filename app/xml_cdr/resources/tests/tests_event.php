<?php

/*
 * FusionPBX — xml_cdr pipeline test: xml_cdr_event
 *
 * Tests the event DTO: static factory, property values,
 * and the invariant that source_filename is always a basename.
 */

test_runner::add_test('event::create sets event_type and reason', function () {
	$record = record_from_fixture('b_inbound_answered.cdr.xml');

	$event = xml_cdr_event::create($record, 'skipped', 'duplicate uuid', null);

	assert_equals('skipped', $event->event_type);
	assert_equals('duplicate uuid', $event->reason);
});

test_runner::add_test('event::create source_filename is basename only', function () {
	$record = record_from_fixture('b_inbound_answered.cdr.xml');

	$event = xml_cdr_event::create($record, 'error', 'db failure', null);

	// Must be a bare filename, never a path segment
	assert_false(
		strpos($event->source_filename, '/') !== false,
		'source_filename must not contain directory separators'
	);
	assert_equals('b_inbound_answered.cdr.xml', $event->source_filename);
});

test_runner::add_test('event::create timestamp is a recent float', function () {
	$before = microtime(true);
	$record = record_from_fixture('b_inbound_answered.cdr.xml');
	$event  = xml_cdr_event::create($record, 'error', 'oops', null);
	$after  = microtime(true);

	assert_true(is_float($event->timestamp), 'Expected timestamp to be a float');
	assert_true($event->timestamp >= $before && $event->timestamp <= $after,
		'Expected timestamp to be within test execution window'
	);
});

test_runner::add_test('event::create attaches exception when provided', function () {
	$record = record_from_fixture('b_inbound_answered.cdr.xml');
	$exc    = new RuntimeException('test exception');

	$event = xml_cdr_event::create($record, 'error', 'bad', $exc);

	assert_not_null($event->exception);
	assert_equals('test exception', $event->exception->getMessage());
});

test_runner::add_test('event::create records record reference', function () {
	$record = record_from_fixture('b_inbound_answered.cdr.xml');
	$record->domain_uuid = 'abc-123';

	$event = xml_cdr_event::create($record, 'skipped', '', null);

	// event->record holds the original record; domain_uuid is accessible via it
	assert_equals('abc-123', $event->record->domain_uuid);
});
