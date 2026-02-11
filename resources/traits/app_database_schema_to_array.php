<?php

trait app_database_schema_to_array {
	public function to_array(): array {
		$array = [];
		foreach ($this->database_tables as $table) {
			$array[] = $table->to_array();
		}
		return $array;
	}
}