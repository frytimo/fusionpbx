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
 * Lightweight stubs that replace FusionPBX framework dependencies
 * so the pipeline classes can be exercised without a running database
 * or web server.
 */
// uuid() helper (used in database writes — stub returns a predictable value)
if (!function_exists('uuid')) {
	function uuid(): string {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff) | 0x4000,
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
	}
}

if (!function_exists('is_uuid')) {
	function is_uuid(string $s): bool {
		return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $s);
	}
}
// settings stub
if (!class_exists('settings')) {
	class settings {
		private array $data;

		public function __construct(array $data = []) {
			$this->data = $data;
		}

		public function get(string $category, string $name, mixed $default = null): mixed {
			$key = $category . '.' . $name;
			if (array_key_exists($key, $this->data)) {
				return $this->data[$key];
			}
			return $default;
		}
	}
}
// database stub — records every save() call for inspection by tests
if (!class_exists('database')) {
	class database {
		/** @var array[] All data arrays passed to save(). */
		public array $saves = [];
		/** @var array<string,mixed> Canned query results. */
		public array $query_results = [];

		public function save(array $data, bool $verbose = false): void {
			$this->saves[] = $data;
		}

		public function select(string $sql, array $params, string $mode = 'all'): mixed {
			// Return canned result if registered, otherwise null/0
			foreach ($this->query_results as $match => $result) {
				if (str_contains($sql, $match)) {
					return $result;
				}
			}
			return ($mode === 'column') ? null : [];
		}

		/** Register a canned result for queries containing $sql_fragment. */
		public function will_return(string $sql_fragment, mixed $result): void {
			$this->query_results[$sql_fragment] = $result;
		}
	}
}
// auto_loader stub — returns empty array (no discovered classes) by default
// Tests can replace with a concrete list if needed.
if (!class_exists('auto_loader')) {
	class auto_loader {
		private static array $overrides = [];

		public static function override(string $interface, array $classes): void {
			self::$overrides[$interface] = $classes;
		}

		public function get_interface_list(string $interface): array {
			return self::$overrides[$interface] ?? [];
		}
	}
}
// Fixture loader helper
function fixture_path(string $filename): string {
	return __DIR__ . '/fixtures/' . $filename;
}

function fixture_xml(string $filename): string {
	$content = file_get_contents(fixture_path($filename));
	if ($content === false) {
		throw new \RuntimeException("Fixture not found: $filename");
	}
	return $content;
}

/**
 * Create an xml_cdr_record from a fixture file (reads from fixtures/ dir).
 */
function record_from_fixture(string $filename): xml_cdr_record {
	$raw  = fixture_xml($filename);
	$path = fixture_path($filename);
	if (str_ends_with($filename, '.json')) {
		return xml_cdr_record::from_json($raw, $path);
	}
	return xml_cdr_record::from_xml($raw, $path);
}
