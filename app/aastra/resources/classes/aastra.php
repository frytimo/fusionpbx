<?php

class aastra implements app_config_default_settings {

	const NAME = 'Aastra';
	const UUID = 'c471163a-12fa-11e8-b642-0ed5f89f718b';
	const CATEGORY = 'Provision';

    public static function app_config_default_settings(): array {
        $default_settings = app_default_settings::new(
			'c471163a-12fa-11e8-b642-0ed5f89f718b',
			'provision',
			'aastra_gmt_offset',
			'numeric',
			'0',
			'true',
			'Aastra timezone offset in minutes (e.g. 300 = GMT-5 = Eastern Standard Time)'
		)->add(
			default_setting::new(
				'c47117a2-12fa-11e8-b642-0ed5f89f718b',
				'provision',
				'aastra_time_format',
				'numeric',
				'0',
				'true',
				'Aastra clock format'
			)
		)->add(
			default_setting::new(
				'c4711b1e-12fa-11e8-b642-0ed5f89f718b',
				'provision',
				'aastra_sip_silence_suppression',
				'numeric',
				'0',
				'true',
				'Aastra SIP silence suppression - silence suppression: deactivated (0), activated (1); enabled on G.711 with CN(RFC3389) or G.729AB'
			)
		)->add(
			default_setting::new(
				'c47119aa-12fa-11e8-b642-0ed5f89f718b',
				'provision',
				'aastra_date_format',
				'numeric',
				'0',
				'true',
				'Aastra date format'
			)
		)->add(
			default_setting::new(
				'b31b1423-a04c-4b4a-9c55-a0b3791642c3',
				'provision',
				'aastra_ptime',
				'numeric',
				'20',
				'true',
				'Set Aastra ptime'
			)
		)->add(
			default_setting::new(
				'1455b1b0-68ec-400d-be84-1d1132aea72f',
				'provision',
				'aastra_zone_minutes',
				'numeric',
				'0',
				'true',
				'Offset in minutes from GMT, 300 = GMT-5 = Eastern Standard Time, -120 = GMT+2 = Eastern European Time'
			)
		)->add(
			default_setting::new(
				'c90e804a-d2bb-431a-ace8-5c69e140c539',
				'provision',
				'aastra_sip_silence_suppression',
				'numeric',
				'0',
				'true',
				'Aastra SIP silence suppression - silence suppression: deactivated (0), activated (1); enabled on G.711 with CN(RFC3389) or G.729AB'
			)
		);
		return $default_settings->to_array();
    }
}
