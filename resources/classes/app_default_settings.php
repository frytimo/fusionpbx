<?php


class app_default_settings {

	protected $default_settings;

	public function __construct(?default_setting $default_setting = null) {
		$this->default_settings = new SplObjectStorage();
		if ($default_setting !== null) {
			$this->default_settings->attach($default_setting);
		}
	}

	public function create(string $uuid, string $category, string $subcategory, string $name, string $value, string $enabled, string $description): self {
		$default_setting = default_setting::new($uuid, $category, $subcategory, $name, $value, $enabled, $description);
		$this->default_settings->attach($default_setting);
		return $this;
	}

	public function add(default_setting $default_setting): self {
		$this->default_settings->attach($default_setting);
		return $this;
	}

	public function to_array(): array {
		$array = [];
		foreach ($this->default_settings as $default_setting) {
			$array[] = $default_setting;
		}
		return $array;
	}

	public function __toString(): string {
		return json_encode($this->to_array(), JSON_PRETTY_PRINT);
	}

	public static function new(string $uuid, string $category, string $subcategory, string $name, string $value, string $enabled, string $description): self {
		$default_setting = default_setting::new($uuid, $category, $subcategory, $name, $value, $enabled, $description);
		return new self($default_setting);
	}

}

// Example usage:
// $default_setting = app_default_settings::new(
// 		'c47117a2-12fa-11e8-b642-0ed5f89f718b',
// 		'provision',
// 		'aastra_time_format',
// 		'numeric',
// 		'0',
// 		'true',
// 		'Aastra clock format'
// 	)->add(
// 		default_setting::new()
// 			->uuid('c47119aa-12fa-11e8-b642-0ed5f89f718b')
// 			->category('provision')
// 			->subcategory('aastra_date_format')
// 			->name('numeric')
// 			->value('0')
// 			->enabled('true')
// 			->description('Aastra date format')
// 	)->add(
// 		default_setting::new(
// 			'8b676397-2cf7-45de-a7ec-f3ceb7d529e3',
// 			'provision',
// 			'aastra_silence_suppression',
// 			'numeric',
// 			'0',
// 			'true',
// 			'Enable Aastra codec silence suppression (on / off)'
// 		)
// 	)
// ;

// echo $default_setting;
