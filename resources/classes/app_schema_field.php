<?php

class app_schema_field {
	public $name;
	public $type;
	public $key = [];
	public $search_by = false;
	public $description_key = [];

	public function __construct(string $name) {
		$this->name = $name;
		$this->type = 'text';
		$this->key = [];
		$this->search_by = false;
		$this->description_key = [];
	}

	public function type(string $type): self {
		$this->type = $type;
		return $this;
	}

	public function primary(): self {
		$this->type = 'uuid';
		$this->key['type'] = 'primary';
		return $this;
	}

	public function enabled(?string $description_key = null): self {
		$this->type = 'boolean';
		if ($description_key !== null) {
			$this->description_key = $description_key;
		}
		return $this;
	}

	public function foreign(string $foreign_table, string $foreign_field): self {
		$this->key['type'] = 'foreign';
		$this->key['foreign_table'] = $foreign_table;
		$this->key['foreign_field'] = $foreign_field;
		$this->indexed();
		return $this;
	}

	public function indexed(): self {
		$this->search_by = true;
		return $this;
	}

	public function description(string $description_key): self {
		$this->description_key = $description_key;
		return $this;
	}

	public static function create(string $name): self {
		return new self($name);
	}

	public static function from_array(array $data): self {
		$field = new self($data['name']);
		if (isset($data['type'])) {
			$field->type($data['type']);
		}
		if (isset($data['key'])) {
			if ($data['key']['type'] === 'primary') {
				$field->primary();
			} elseif ($data['key']['type'] === 'foreign') {
				$field->foreign($data['key']['foreign_table'], $data['key']['foreign_field']);
			}
		}
		if (isset($data['search_by']) && $data['search_by']) {
			$field->indexed();
		}
		if (isset($data['description_key'])) {
			$field->description($data['description_key']);
		}
		return $field;
	}
}