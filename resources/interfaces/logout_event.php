<?php

/**
 * Interface for handling logout events in FusionPBX.
 * Implementing classes can define actions before and after session destruction.
 */
interface logout_event {

	/**
	 * Executed before the session is destroyed.
	 * Use this to perform cleanup or logging prior to logout.
	 */
	public function on_logout_pre_session_destroy(settings $setttings);

	/**
	 * Executed after the session is destroyed.
	 * Use this to perform post-logout actions, such as redirects or notifications.
	 */
	public function on_logout_post_session_destroy(settings $settings);

}
