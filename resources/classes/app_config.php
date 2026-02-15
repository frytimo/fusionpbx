<?php

class app_config {
	public static function db(): app_db {
		return new app_db();
	}

	public static function permissions(): app_permissions {
		return new app_permissions();
	}
}