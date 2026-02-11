<?php

interface logout_event {
	public static function pre_session_destroy_logout_event(settings $settings);
	public static function post_session_destroy_logout_event(settings $settings);
}
