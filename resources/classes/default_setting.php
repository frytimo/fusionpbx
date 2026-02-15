<?php


class default_setting {
	public string $uuid;
	public string $category;
	public string $subcategory;
	public string $name;
	public string $value;
	public string $enabled;
	public string $description;

	public function __construct(string $uuid = '', string $category = '', string $subcategory = '', string $name = '', string $value = '', string $enabled = '', string $description = '') {
		$this->uuid = $uuid;
		$this->category = $category;
		$this->subcategory = $subcategory;
		$this->name = $name;
		$this->value = $value;
		$this->enabled = $enabled;
		$this->description = $description;
	}

	public function uuid(string $uuid): self {
		$this->uuid = $uuid;
		return $this;
	}

	public function category(string $category): self {
		$this->category = $category;
		return $this;
	}

	public function subcategory(string $subcategory): self {
		$this->subcategory = $subcategory;
		return $this;
	}

	public function name(string $name): self {
		$this->name = $name;
		return $this;
	}

	public function value(string $value): self {
		$this->value = $value;
		return $this;
	}
	public function enabled(string $enabled): self {
		$this->enabled = $enabled;
		return $this;
	}

	public function description(string $description): self {
		$this->description = $description;
		return $this;
	}

	public function __toString(): string {
		return json_encode($this->to_array(), JSON_PRETTY_PRINT);
	}

	public function to_array(): array {
		return [
			'default_setting_uuid' => $this->uuid,
			'default_setting_category' => $this->category,
			'default_setting_subcategory' => $this->subcategory,
			'default_setting_name' => $this->name,
			'default_setting_value' => $this->value,
			'default_setting_enabled' => $this->enabled,
			'default_setting_description' => $this->description
		];
	}

	public static function new(string $uuid = '', string $category = '', string $subcategory = '', string $name = '', string $value = '', string $enabled = '', string $description = ''): self {
		return new self($uuid, $category, $subcategory, $name, $value, $enabled, $description);
	}
}
