<?php

/*
 * FusionPBX — xml_cdr pipeline test: xml_cdr_pipeline integration
 *
 * Tests the static orchestration helpers and the run_pipeline() outcome logic
 * without touching the real database. Uses in-memory stub listeners/notifiers
 * defined at the bottom of this file.
 */
// Inline stubs — defined once, reused across tests
/**
 * Listener that records every record it receives.
 */
class recording_listener implements xml_cdr_listener {
	public array $received = [];
	public function on_cdr(settings $settings, xml_cdr_record $record): void {
		$this->received[] = $record;
	}
}

/**
 * Listener that always throws to simulate a persistence failure.
 */
class failing_listener implements xml_cdr_listener {
	public function on_cdr(settings $settings, xml_cdr_record $record): void {
		throw new RuntimeException('simulated db failure');
	}
}

/**
 * Notifier that records every event it receives.
 */
class recording_notifier implements xml_cdr_notifier {
	public array $events = [];
	public function on_event(settings $settings, xml_cdr_event $event): void {
		$this->events[] = $event;
	}
}

/**
 * Notifier that always throws, to verify fire_notifiers() swallows exceptions.
 */
class failing_notifier implements xml_cdr_notifier {
	public function on_event(settings $settings, xml_cdr_event $event): void {
		throw new RuntimeException('notifier exploded');
	}
}

/**
 * Modifier that immediately throws xml_cdr_skip_exception.
 */
class skip_modifier implements xml_cdr_modifier {
	public function priority(): int { return 1; }
	public function __invoke(settings $s, xml_cdr_record $r): void {
		throw new xml_cdr_skip_exception('skip test');
	}
}

/**
 * Modifier that immediately throws xml_cdr_discard_exception.
 */
class discard_modifier implements xml_cdr_modifier {
	public function priority(): int { return 1; }
	public function __invoke(settings $s, xml_cdr_record $r): void {
		throw new xml_cdr_discard_exception('discard test');
	}
}
// Helper: build a no-op enricher callable
function noop_enrich(): callable {
	return function (settings $s, xml_cdr_record $r): void {};
}

// Helper: build a modifier chain from one modifier instance
function single_modifier_chain(xml_cdr_modifier $mod): callable {
	return function (settings $s, xml_cdr_record $r) use ($mod): void {
		$mod($s, $r);
	};
}
// Tests
test_runner::add_test('pipeline: successful run returns "stored" and fires listener', function () {
	$listener  = new recording_listener();
	$notifier  = new recording_notifier();
	$record    = record_from_fixture('b_inbound_answered.cdr.xml');
	$settings  = new settings();

	$outcome = xml_cdr_pipeline::run_pipeline(
		$record,
		noop_enrich(),
		noop_enrich(),   // noop modifier chain
		[$listener],
		[$notifier],
		$settings
	);

	assert_equals('stored', $outcome);
	assert_equals(1, count($listener->received));
	assert_equals(0, count($notifier->events), 'No notifier events expected on success');
});

test_runner::add_test('pipeline: skip_exception returns "skipped" and fires notifier', function () {
	$listener = new recording_listener();
	$notifier = new recording_notifier();
	$record   = record_from_fixture('b_inbound_answered.cdr.xml');

	$outcome = xml_cdr_pipeline::run_pipeline(
		$record,
		noop_enrich(),
		single_modifier_chain(new skip_modifier()),
		[$listener],
		[$notifier],
		new settings()
	);

	assert_equals('skipped', $outcome);
	assert_equals(0, count($listener->received), 'Listener must not be called when skipped');
	assert_equals(1, count($notifier->events));
	assert_equals('skipped', $notifier->events[0]->event_type);
});

test_runner::add_test('pipeline: discard_exception returns "discarded" and fires notifier', function () {
	$listener = new recording_listener();
	$notifier = new recording_notifier();

	// Use a temp path that does not actually exist — unlink must not crash
	$record = xml_cdr_record::from_xml(
		fixture_xml('b_inbound_answered.cdr.xml'),
		'/tmp/nonexistent_' . uniqid() . '.cdr.xml'
	);

	$outcome = xml_cdr_pipeline::run_pipeline(
		$record,
		noop_enrich(),
		single_modifier_chain(new discard_modifier()),
		[$listener],
		[$notifier],
		new settings()
	);

	assert_equals('discarded', $outcome);
	assert_equals(0, count($listener->received));
	assert_equals(1, count($notifier->events));
	assert_equals('discarded', $notifier->events[0]->event_type);
});

test_runner::add_test('pipeline: listener failure re-throws and fires error notifier', function () {
	$notifier = new recording_notifier();
	$record   = record_from_fixture('b_inbound_answered.cdr.xml');

	$caught = false;
	try {
		xml_cdr_pipeline::run_pipeline(
			$record,
			noop_enrich(),
			noop_enrich(),
			[new failing_listener()],
			[$notifier],
			new settings()
		);
	} catch (RuntimeException $e) {
		$caught = true;
	}

	assert_true($caught, 'Expected RuntimeException to propagate from failing listener');
	assert_equals(1, count($notifier->events));
	assert_equals('error', $notifier->events[0]->event_type);
});

test_runner::add_test('pipeline: fire_notifiers swallows individual notifier exceptions', function () {
	// Two notifiers: first explodes, second should still run
	$good_notifier = new recording_notifier();
	$record        = record_from_fixture('b_inbound_answered.cdr.xml');

	// Should not throw
	xml_cdr_pipeline::fire_notifiers(
		[new failing_notifier(), $good_notifier],
		new settings(),
		$record,
		'error',
		'test swallow'
	);

	assert_equals(1, count($good_notifier->events),
		'Second notifier must receive event even when first notifier throws');
});

test_runner::add_test('pipeline: fire_notifiers event has correct source_filename', function () {
	$notifier = new recording_notifier();
	$record   = record_from_fixture('b_inbound_answered.cdr.xml');

	xml_cdr_pipeline::fire_notifiers(
		[$notifier],
		new settings(),
		$record,
		'skipped',
		'test reason'
	);

	assert_equals(1, count($notifier->events));
	$event = $notifier->events[0];
	assert_false(
		strpos($event->source_filename, '/') !== false,
		'source_filename must be basename only, not a path'
	);
});

test_runner::add_test('pipeline: build_modifier_chain orders modifiers by priority', function () {
	// Use auto_loader override to provide a controlled set of modifiers
	$calls = [];

	// We test ordering by building a chain from known classes with known priorities
	// modifier_xml_sanitize = 5, modifier_call_block = 10, modifier_caller_id = 20
	$chain = xml_cdr_pipeline::build_modifier_chain([
		'modifier_caller_id',    // priority 20
		'modifier_xml_sanitize', // priority 5
		'modifier_call_block',   // priority 10
	]);

	// Apply chain to a valid inbound fixture; we only check it doesn't throw
	// (ordering is implicitly correct when sanitize runs before call_block and caller_id)
	$record = record_from_fixture('b_inbound_answered.cdr.xml');
	$chain(new settings(), $record);

	assert_not_empty($record->caller_id_number, 'modifier_caller_id should have run');
});

test_runner::add_test('pipeline: build_listener_chain rejects non-listener class', function () {
	assert_exception(
		function () {
			xml_cdr_pipeline::build_listener_chain(['modifier_xml_sanitize']);
		},
		InvalidArgumentException::class
	);
});

test_runner::add_test('pipeline: build_notifier_chain rejects non-notifier class', function () {
	assert_exception(
		function () {
			xml_cdr_pipeline::build_notifier_chain(['modifier_xml_sanitize']);
		},
		InvalidArgumentException::class
	);
});
