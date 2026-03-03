<?php

interface login_event {

	/**
	 * Executed before the session is created.
	 * Use this to perform actions such as logging or validation before login.
	 */
	public static function on_login_pre_session_create(settings $settings);

	/**
	 * Executed after the session is created.
	 * Use this to perform actions such as redirects or notifications after login.
	 */
	public static function on_login_post_session_create(settings $settings);

}