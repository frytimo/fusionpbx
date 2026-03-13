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
 * Minimal test runner for the xml_cdr pipeline.
 *
 * Usage (from the workspace root):
 *   php app/xml_cdr/resources/tests/test_runner.php
 *
 * Each test file must call test_runner::add_test($name, $callable).
 * The callable receives no arguments and must return nothing; it should
 * throw an AssertionError or any exception to signal failure.
 *
 * Exit code: 0 on all-pass, 1 on any failure.
 */
class test_runner {

	private static array $tests  = [];
	private static int   $passed = 0;
	private static int   $failed = 0;

	/** Register a test case. */
	public static function add_test(string $name, callable $fn): void {
		self::$tests[$name] = $fn;
	}

	/** Run all registered tests and print results. */
	public static function run(): int {
		echo "\n=== xml_cdr pipeline tests ===\n\n";

		foreach (self::$tests as $name => $fn) {
			try {
				$fn();
				echo "  [PASS] $name\n";
				self::$passed++;
			} catch (\Throwable $e) {
				echo "  [FAIL] $name\n";
				echo "         " . get_class($e) . ': ' . $e->getMessage() . "\n";
				self::$failed++;
			}
		}

		echo "\n";
		echo '-------------------------------------------' . "\n";
		echo sprintf("Results: %d passed, %d failed\n", self::$passed, self::$failed);
		echo "\n";

		return self::$failed > 0 ? 1 : 0;
	}

}

// Assertion helper used by test files
function assert_equals(mixed $expected, mixed $actual, string $message = ''): void {
	if ($expected !== $actual) {
		$msg = $message ?: sprintf("Expected %s, got %s", var_export($expected, true), var_export($actual, true));
		throw new \AssertionError($msg);
	}
}

function assert_true(bool $condition, string $message = 'Expected true'): void {
	if (!$condition) {
		throw new \AssertionError($message);
	}
}

function assert_false(bool $condition, string $message = 'Expected false'): void {
	if ($condition) {
		throw new \AssertionError($message);
	}
}

function assert_not_empty(mixed $value, string $message = 'Expected non-empty value'): void {
	if (empty($value)) {
		throw new \AssertionError($message);
	}
}

function assert_null(mixed $value, string $message = 'Expected null'): void {
	if ($value !== null) {
		throw new \AssertionError($message . ': got ' . var_export($value, true));
	}
}

function assert_not_null(mixed $value, string $message = 'Expected non-null'): void {
	if ($value === null) {
		throw new \AssertionError($message);
	}
}

function assert_exception(callable $fn, string $expected_class, string $message = ''): void {
	try {
		$fn();
		throw new \AssertionError($message ?: "Expected $expected_class to be thrown, but nothing was thrown");
	} catch (\Throwable $e) {
		if (!($e instanceof $expected_class)) {
			throw new \AssertionError(
				$message ?: sprintf("Expected %s, got %s: %s", $expected_class, get_class($e), $e->getMessage())
			);
		}
	}
}
// Bootstrap: load the classes that are tested without the full FusionPBX stack
define('BASEDIR', dirname(__DIR__, 4));   // /var/www/fusionpbx

// Minimal stubs for interfaces and helpers that tests need without a full DB
require_once __DIR__ . '/test_helpers.php';

// Load interfaces (interfaces/ folder)
require_once dirname(__DIR__) . '/interfaces/xml_cdr_consumer.php';
require_once dirname(__DIR__) . '/interfaces/xml_cdr_enricher.php';
require_once dirname(__DIR__) . '/interfaces/xml_cdr_modifier.php';
require_once dirname(__DIR__) . '/interfaces/xml_cdr_listener.php';
require_once dirname(__DIR__) . '/interfaces/xml_cdr_notifier.php';

// Load all pipeline classes from classes/ (exceptions, core classes, consumers,
// enrichers, modifiers, listeners, notifiers all live here so auto_loader finds them)
$_classes_dir = dirname(__DIR__) . '/classes';
require_once $_classes_dir . '/xml_cdr_pipeline_exception.php';
require_once $_classes_dir . '/xml_cdr_skip_exception.php';
require_once $_classes_dir . '/xml_cdr_discard_exception.php';
require_once $_classes_dir . '/xml_cdr_record.php';
require_once $_classes_dir . '/xml_cdr_event.php';
require_once $_classes_dir . '/xml_cdr_pipeline.php';
require_once $_classes_dir . '/xml_cdr_retry_queue.php';
require_once $_classes_dir . '/modifier_xml_sanitize.php';
require_once $_classes_dir . '/modifier_call_block.php';
require_once $_classes_dir . '/modifier_caller_id.php';
require_once $_classes_dir . '/modifier_missed_call.php';
require_once $_classes_dir . '/modifier_call_status.php';
require_once $_classes_dir . '/modifier_provider_prefix.php';
require_once $_classes_dir . '/modifier_pii_mask.php';
require_once $_classes_dir . '/xml_cdr_syslog_notifier.php';
unset($_classes_dir);
// Discover and load all test files
foreach (glob(__DIR__ . '/tests_*.php') as $test_file) {
	require_once $test_file;
}
// Run
exit(test_runner::run());
