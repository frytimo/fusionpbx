<?php

class app_schema {
	public $database_tables = [];

	public static function table(string $name): app_schema_table {
		$table = new app_schema_table($name);
		return $table;
	}

	public function to_array(): array {
		$array = [];
		foreach ($this->database_tables as $table) {
			$array[] = [
				'name' => $table->name,
				'parent' => $table->parent,
				'fields' => array_map(function($field) {
					return [
						'name' => $field->name,
						'type' => $field->type,
						'key' => $field->key,
						'search_by' => $field->search_by,
						'description_key' => $field->description_key,
					];
				}, $table->fields),
			];
		}

		return $array;
	}

	/**
	 * standard_table composed of the following fields:
	 * PRIMARY KEY, DOMAIN_UUID FOREIGN KEY, NAME, DESCRIPTION, ENABLED, TIMESTAMPS
	 *
	 * @param string $name the name of the table
	 *
	 * @return app_schema_table the table object
	 */
	public static function standard_table(string $name): app_schema_table {
		$table = app_schema::table($name)
			->primary_key()
			->foreign_key('domains')
			->name()
			->description()
			->enabled()
			->timestamps()
		;
		return $table;
	}

}
