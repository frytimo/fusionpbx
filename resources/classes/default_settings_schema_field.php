<?php

class default_settings_schema_field {
	private $uuid;
	private $category;
	private $subcategory;
	private $type;
	private $value;
	private $enabled;
	private $description;

	public function __construct(string $uuid = '') {
		if (empty($uuid)) {
			$this->uuid = '';
		} else {
			$this->uuid = $uuid;
		}
		$this->category = '';
		$this->subcategory = '';
		$this->type = '';
		$this->value = null;
		$this->enabled = false;
		$this->description = '';
	}

	public function get_uuid(): string {
		return $this->uuid;
	}

	public function set_uuid(string $uuid): self {
		$this->uuid = $uuid;
		return $this;
	}

	public function get_type(): string {
		return $this->type;
	}

	public function set_type(string $type): self {
		$this->type = $type;
		return $this;
	}

	public function get_default_value(): mixed {
		return $this->value;
	}

	public function enabled(): self {
		$this->enabled = true;
		return $this;
	}

	public function disabled(): self {
		$this->enabled = false;
		return $this;
	}

	public function is_enabled(): bool {
		return $this->enabled === true;
	}
	public function is_disabled(): bool {
		return $this->enabled === false;
	}

	public function get_category(): string {
		return $this->category;
	}

	public function set_category(string $category): self {
		$this->category = $category;
		return $this;
	}

	public function get_subcategory(): string {
		return $this->subcategory;
	}

	public function set_subcategory(string $subcategory): self {
		$this->subcategory = $subcategory;
		return $this;
	}

	public function set_description(string $description): self {
		$this->description = $description;
		return $this;
	}

	public function get_description(): string {
		return $this->description;
	}

	public function set_default_value(mixed $default_value): self {
		$this->value = $default_value;
		return $this;
	}
}