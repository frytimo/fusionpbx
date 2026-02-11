<?php

interface app_config {
	public static function get_config_schema(string $app_name): config_schema;
}