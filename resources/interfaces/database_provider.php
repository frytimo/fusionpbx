<?php

interface database_provider {
	public function tables(): array;
	public function table_exists(string $table_name): bool;
	public function table_info(string $table_name): array;
	public function column_exists(string $table_name, string $column_name): bool;
	public function is_connected(): bool;
	public function execute(string $sql, ?array $parameters = []);
	public function fetch_row(string $sql, ?array $parameters = []): array;
	public function fetch_all(string $sql, ?array $parameters = []): array;
	public function fetch_column(string $sql, ?array $parameters = []): string|false;
	public function get_database_indexes(): array;
	public function connect(): bool;
	public function begin_transaction(): bool;
	public function commit(): bool;
	public function add_transaction($sql, ?array $parameters = []): bool;
	public function in_transaction(): bool;
	public function rollback(): bool;
	public function prepare(string $sql);
	public function bind_param($statement, $parameter, $value, $data_type = null): bool;
	public function set_attribute($attribute, $value): bool;
}
