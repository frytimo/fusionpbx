<?php

interface app_database {
	public function get_database_schema(): database_schema;
}
