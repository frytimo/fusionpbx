<?php

class default_settings_schema {
	

	/**
	 * Array of schema fields
	 *
	 * @var array<default_settings_schema_field>
	 */
	private $fields;

	public function __construct() {
		$this->fields = [];
	}

	public function add_setting(default_settings_schema_field $field): void {
		$this->fields[] = $field;
	}

	public function to_array(): array {
		// this is a schema, so the method is empty
	}

	public function index_of(string $key): int {
		// this is a schema, so the method is empty
	}


}