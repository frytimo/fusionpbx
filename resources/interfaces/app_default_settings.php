<?php

interface app_default_settings {
	public function get_default_settings_schema(): default_settings_schema;
}