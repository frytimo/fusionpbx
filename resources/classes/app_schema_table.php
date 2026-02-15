<?php

class app_schema_table {
	public $name;
	public $fields;
	public $parent;
	public $singular_name;

	public function __construct(string $name, ?string $singular_name = null) {
		if (!str_starts_with($name, database::TABLE_PREFIX)) {
			$table_name = database::TABLE_PREFIX . $name;
		} else {
			syslog(LOG_WARNING, "Table name '$name' should not start with '" . database::TABLE_PREFIX . "'. The '" . database::TABLE_PREFIX . "' prefix is reserved for views. Consider renaming the table to avoid potential confusion. (" . __FILE__ . ': ' . __LINE__ . ')');
		}
		$this->name = $table_name;
		$this->fields = [];
		$this->parent = null;
		if ($singular_name === null) {
			$singular_name = database::singular($name);
		}
		$this->singular_name = $singular_name ?? $name;
	}

	public function primary_key(?string $name = null, ?string $description_key = null): self {
		if ($name === null) {
			$name = $this->singular_name . '_uuid';
		}
		$field = new app_schema_field($name);
		$field->primary();
		if ($description_key === null) {
			$field->description_key = 'description-primary_key';
		}
		$this->fields[$name] = $field;
		return $this;
	}

	/**
	 * Sets the column name
	 *
	 * @param string $field_name
	 * @param boolean $indexed
	 * @param string $description_key
	 *
	 * @return self
	 */
	public function column(string|array $field_names, bool $indexed = false, string $description_key = '', string $type = ''): self {
		if (is_array($field_names)) {
			foreach ($field_names as $ndx => $field_name) {
				$this->column($field_name, $indexed, $description_key, "$ndx");
			}
			return $this;
		} else {
			$field_name = $field_names;
		}
		$field = new app_schema_field($field_name);
		if ($indexed) {
			$field->indexed();
		}
		// create the field object
		$field = new app_schema_field($field_name);

		// set the field type if provided
		if (!empty($type) && !is_numeric($type)) {
			$field->type($type);
		}

		// index the field if requested
		if ($indexed) {
			$field->indexed();
		}

		// set the description key for the field
		$field->description_key = $description_key;

		// add the field to the table
		return $this->append_field($field);
	}

	/**
	 * Allows an array of column names
	 *
	 * @param string|array $field_names
	 * @param boolean $indexed
	 * @param string $description_key
	 * @return self
	 */
	public function columns(string|array $field_names, bool $indexed = false, string $description_key = '', string $type = ''): self {
		if (is_array($field_names)) {
			foreach ($field_names as $ndx => $field_name) {
				$this->column($field_name, $indexed, $description_key, "$ndx");
			}
			return $this;
		}
		return $this->column($field_names, $indexed, $description_key, $type);
	}

	/**
	 * Sets the column name by automatically prefixing the singular table name to $name
	 *
	 * For example, if the table name is 'v_providers' and the field name is 'enabled', the
	 * resulting column name will be 'provider_enabled'. If the field name already starts
	 * with the singular table name, it will not be prefixed again. For example, if the field
	 * name is 'provider_enabled', it will remain 'provider_enabled'. The description key
	 * will default to 'description-' followed by the non-prefixed field name if not provided.
	 *
	 * @param string $name
	 * @param boolean $indexed
	 * @param string $description_key
	 * @return self
	 */
	public function field(string|array $name, bool $indexed = false, string $description_key = '', string $type = ''): self {
		if (is_array($name)) {
			// list of fields to add
			foreach ($name as $ndx => $field_name) {
				$this->field($field_name, $indexed, $description_key, "$ndx");
			}
			return $this;
		}
		// set the column name for the field
		if (!str_starts_with($name, $this->singular_name . '_')) {
			$proper_name = $this->singular_name . '_' . $name;
		} else {
			$proper_name = $name;
		}

		// set the description key for the field
		if ($description_key === '') {
			$description_key = 'description-' . $name;
		}

		// create the field object
		$field = new app_schema_field($proper_name);

		// set the field type if provided
		if (!empty($type) && !is_numeric($type)) {
			$field->type($type);
		}

		// index the field if requested
		if ($indexed) {
			$field->indexed();
		}

		// set the description key for the field
		$field->description_key = $description_key;

		// add the field to the table
		return $this->append_field($field);
	}

	public function indexed(): self {
		$last_field = end($this->fields);
		$last_field->indexed();
		return $this;
	}

	public function type_text(): self {
		$last_field = end($this->fields);
		$last_field->type('text');
		return $this;
	}

	public function type_uuid(): self {
		$last_field = end($this->fields);
		$last_field->type('uuid');
		return $this;
	}

	public function type_boolean(): self {
		$last_field = end($this->fields);
		$last_field->type('boolean');
		return $this;
	}

	public function boolean(): self {
		return $this->type_boolean();
	}

	public function type_timestamptz(): self {
		$last_field = end($this->fields);
		$last_field->type('timestamptz');
		return $this;
	}

	public function type_date(): self {
		$last_field = end($this->fields);
		$last_field->type('date');
		return $this;
	}

	public function type_char(int $length): self {
		$last_field = end($this->fields);
		$last_field->type("char($length)");
		return $this;
	}

	public function fields(string|array $names, bool $indexed = false, string $description_key = '', string $type = ''): self {
		if (is_array($names)) {
			// recursive call for each field name in the array
			foreach ($names as $ndx => $name) {
				$this->fields($name, $indexed, $description_key, "$ndx");
			}
			return $this;
		}
		return $this->field($names, $indexed, $description_key, $type);
	}

	public function foreign_key(string $foreign_table, string $foreign_field = '', string $description_key = ''): self {
		if (!str_starts_with($foreign_table, database::TABLE_PREFIX)) {
			$proper_name = database::TABLE_PREFIX . $foreign_table;
		} else {
			syslog(LOG_WARNING, "Table name '$foreign_table' should not start with '" . database::TABLE_PREFIX . "'. The '" . database::TABLE_PREFIX . "' prefix is reserved for views. Consider renaming the table to avoid potential confusion. (" . __FILE__ . ': ' . __LINE__ . ')');
		}
		if ($foreign_field === '') {
			$foreign_field = database::singular($foreign_table) . '_uuid';
		}
		$field = new app_schema_field($foreign_field);
		$field->foreign($proper_name, $foreign_field);
		if ($description_key !== null) {
			$field->description_key = $description_key;
		}
		$this->fields[$foreign_field] = $field;
		return $this;
	}

	public function enabled(string $description_key = ''): self {
		return $this->field('enabled', false, $description_key);
	}

	public function name(string $description_key = ''): self {
		return $this->field('name', true, $description_key);
	}

	public function description(string $description_key = ''): self {
		return $this->field('description', true, $description_key);
	}

	public function append_field(app_schema_field $field): self {
		$this->fields[$field->name] = $field;
		return $this;
	}

	public function timestamps(): self {
		$this->append_field(app_schema_field::create('insert_user')->type('uuid')->description(''));
		$this->append_field(app_schema_field::create('update_user')->type('uuid')->description(''));
		$this->append_field(app_schema_field::create('insert_date')->type('timestamptz')->description(''));
		$this->append_field(app_schema_field::create('update_date')->type('timestamptz')->description(''));
		return $this;
	}

	public function parent(string $parent, string $foreign_table = '', string $foreign_field = '', string $description_key = ''): self {
		$this->parent = $parent;
		if ($foreign_table === '') {
			$foreign_table = $parent;
		}
		$this->foreign_key($foreign_table, $foreign_field, $description_key);
		return $this;
	}

	public function to_array(): array {
		return [ 'table' => [
			'name' => $this->name,
			'parent' => $this->parent,
		],
			'fields' => array_map(function($field) {
				return [
					'name' => $field->name,
					'type' => $field->type,
					'key' => $field->key,
					'search_by' => $field->search_by,
					'description_key' => $field->description_key,
				];
			}, $this->fields),

		];
	}
}
