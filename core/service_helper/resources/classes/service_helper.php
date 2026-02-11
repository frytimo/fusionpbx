<?php

/**
 * Service Helper Class
 *
 * This class provides utility methods and functionality to assist with
 * service-related operations within the FusionPBX system.
 */
class service_helper implements app_config, app_menu, app_default_settings, app_database {

	/**
	 * Retrieves the list of services that need to be restarted
	 *
	 * @return array Returns an array of services that require restart
	 */
	public static function get_queue_list(database $database) {
		// Query for pending restarts
		$sql = "SELECT
					service_restart_uuid
					,service_name
				FROM
					v_service_restarts
				WHERE
					restart_completed IS NULL
					AND processed_hostname IS NULL
		";
		return $database->select($sql, null, 'all');
	}

	/**
	 * Add a service restart request to the queue
	 *
	 * This method registers a service that needs to be restarted, associating it
	 * with a specific user. The restart request is typically queued
	 * for later execution by the service helper system.
	 *
	 * @param string $service_name The name of the service to restart
	 * @param string|null $user_uuid The UUID of the user requesting the service restart
	 *
	 * @return array|mixed|false Returns the result of the restart queue operation
	 */
	public static function queue_restart(database $database, string $service_name, ?string $user_uuid = null) {
		if (!empty($user_uuid) && !is_uuid($user_uuid)) {
			return false;
		}
		$sql = "INSERT INTO
					v_service_restarts (service_restart_uuid, service_name, restart_requested, insert_date, insert_user)
				VALUES
					(:uuid, :service_name, NOW(), NOW(), :user_uuid)
		";
		$parameters['uuid'] = uuid();
		$parameters['service_name'] = $service_name;
		$parameters['user_uuid'] = $user_uuid;
		$result = $database->execute($sql, $parameters);
		return $result;
	}

	public static function queue_restart_all(auto_loader $autoloader, database $database, ?string $user_uuid = null): bool {
		if (!empty($user_uuid) && !is_uuid($user_uuid)) {
			return false;
		}
		$services = $autoloader->get_class_list('service');
		foreach ($services as $service) {
			$service_name = $service::get_service_name();
			if (!empty($service_name)) {
				self::queue_restart($database, $service_name, $user_uuid);
			}
		}
		return true;
	}

	public static function queue_stop(database $database, string $service_name, ?string $user_uuid = null): bool {
		if (!empty($user_uuid) && !is_uuid($user_uuid)) {
			return false;
		}
		$sql = "INSERT INTO
					v_service_restarts (service_restart_uuid, service_name, restart_requested, insert_date, insert_user)
				VALUES
					(:uuid, :service_name, NOW(), NOW(), :user_uuid)
		";
		$parameters['uuid'] = uuid();
		$parameters['service_name'] = $service_name;
		$parameters['user_uuid'] = $user_uuid;
		$result = $database->execute($sql, $parameters);
		return $result;
	}

	public static function queue_stop_all(auto_loader $autoloader, database $database, ?string $user_uuid = null): bool {
		if (!empty($user_uuid) && !is_uuid($user_uuid)) {
			return false;
		}
		$services = $autoloader->get_class_list('service');
		foreach ($services as $service) {
			$service_name = $service::get_service_name();
			if (!empty($service_name)) {
				self::queue_stop($database, $service_name, $user_uuid);
			}
		}
		return true;
	}

	public static function queue_start(database $database, string $service_name, ?string $user_uuid = null): bool {
		if (!empty($user_uuid) && !is_uuid($user_uuid)) {
			return false;
		}
		$sql = "INSERT INTO
					v_service_restarts (service_restart_uuid, service_name, restart_requested, insert_date, insert_user)
				VALUES
					(:uuid, :service_name, NOW(), NOW(), :user_uuid)
		";
		$parameters['uuid'] = uuid();
		$parameters['service_name'] = $service_name;
		$parameters['user_uuid'] = $user_uuid;
		$result = $database->execute($sql, $parameters);
		return $result;
	}

	public static function queue_start_all(auto_loader $autoloader, database $database, ?string $user_uuid = null): bool {
		if (!empty($user_uuid) && !is_uuid($user_uuid)) {
			return false;
		}
		$services = $autoloader->get_class_list('service');
		foreach ($services as $service) {
			$service_name = $service::get_service_name();
			if (!empty($service_name)) {
				self::queue_start($database, $service_name, $user_uuid);
			}
		}
		return true;
	}
}
