<?php

trait simple_table {

	public static function simple_table(): app_schema_table {
		$table = app_db::table(static::class)
			->primary_key()
			->name()
			->description()
			->enabled()
			->timestamps()
		;
		return $table;
	}

}