<?php

class pgsql_db implements database_provider {
	/** @var string */
	private $host;
	/** @var string */
	private $port;
	/** @var string */
	private $username;
	/** @var string */
	private $password;
	/** @var string */
	private $db_name;
	/** @var PDO */
	private $db;

	public function __construct(config $config) {
		$host = $config->get('database.0.host');
		$port = $config->get('database.0.port', '5432');
		$username = $config->get('database.0.username');
		$password = $config->get('database.0.password');
		$db_name = $config->get('database.0.name');
		$this->db = new PDO('pgsql:host=' . $config->get('database.0.host') . ';dbname=' . $config->get('database.0.name'), $config->get('database.0.username'), $config->get('database.0.password'));
		$this->host = $host;
		$this->port = $port;
		$this->username = $username;
		$this->password = $password;
		$this->db_name = $db_name;

		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->db->setAttribute(PDO::PGSQL_ATTR_DISABLE_PREPARES, true);
	}

	public function execute(string $sql, ?array $parameters = []) {
		$prep_statement = $this->db->prepare($sql);

		return $prep_statement->execute($parameters);
	}

	public function fetch_row(string $sql, ?array $parameters = []): array {
		$prep_statement = $this->db->prepare($sql);
		$prep_statement->execute($parameters);

		$result = $prep_statement->fetch(PDO::FETCH_ASSOC);
		if ($result === false) {
			return [];
		}
		return $result;
	}

	public function fetch_all(string $sql, ?array $parameters = []): array {
		$prep_statement = $this->db->prepare($sql);
		$prep_statement->execute($parameters);

		return $prep_statement->fetchAll(PDO::FETCH_ASSOC);
	}

	public function fetch_column(string $sql, ?array $parameters = []): string {
		$prep_statement = $this->db->prepare($sql);
		$prep_statement->execute($parameters);

		return $prep_statement->fetchColumn();
	}

	public function type(): string {
		return 'pgsql';
	}

	public function tables(): array {
		$result = [];
		$sql = 'select table_name as name ';
		$sql .= 'from information_schema.tables ';
		$sql .= "where table_schema='public' ";
		$sql .= "and table_type='BASE TABLE' ";
		$sql .= 'order by table_name ';

		$prep_statement = $this->db->prepare($sql);
		$prep_statement->execute();
		$tmp = $prep_statement->fetchAll(PDO::FETCH_NAMED);
		if (is_array($tmp)) {
			// return a map of $result[] = 'name';
			$result = array_column($tmp, 'name');
		}

		return $result;
	}

	public function begin_transaction(): bool {
		return $this->db->beginTransaction();
	}

	public function add_transaction($sql, ?array $parameters = []): bool {
		$prep_statement = $this->db->prepare($sql);

		return $prep_statement->execute($parameters);
	}

	public function bind_param($statement, $parameter, $value, $data_type = null): bool {
		if ($data_type === null) {
			return $statement->bindValue($parameter, $value);
		} else {
			return $statement->bindValue($parameter, $value, $data_type);
		}
	}

	/**
	 * Prepares a SQL statement for execution and returns a statement object.
	 *
	 * @param string $sql
	 *
	 * @return PDOStatement|false
	 */
	public function prepare(string $sql) {
		return $this->db->prepare($sql);
	}

	public function commit(): bool {
		return $this->db->commit();
	}

	public function set_attribute($attribute, $value): bool {
		return $this->db->setAttribute($attribute, $value);
	}

	public function in_transaction(): bool {
		return $this->db->inTransaction();
	}

	public function rollback(): bool {
		return $this->db->rollBack();
	}

	public function table_info(string $table_name): array {
		$result = [];
		$sql = "SELECT column_name, data_type FROM information_schema.columns WHERE table_name = :table_name";
		$prep_statement = $this->db->prepare($sql);
		$prep_statement->execute(['table_name' => $table_name]);
		$tmp = $prep_statement->fetchAll(PDO::FETCH_NAMED);
		if (is_array($tmp)) {
			// return a map of $result['name'] = 'type';
			$result = array_column($tmp, 'data_type', 'column_name');
		}

		return $result;
	}

	public function connect(): bool {
		$this->db = new PDO("pgsql:host=$this->host port=$this->port dbname=$this->db_name user=$this->username password=$this->password");

		return true;
	}

	public function column_exists(string $table_name, string $column_name): bool {
		$sql = "SELECT attname FROM pg_attribute WHERE attrelid = (SELECT oid FROM pg_class WHERE relname = '$table_name' limit 1) AND attname = '$column_name'; ";

		return $this->db->query($sql)->fetch(PDO::FETCH_ASSOC) !== false;
	}

	public function get_database_indexes(): array {
		$result = [];
		$sql = "SELECT indexname, indexdef FROM pg_indexes WHERE schemaname = 'public'";
		$prep_statement = $this->db->prepare($sql);
		$prep_statement->execute();
		$tmp = $prep_statement->fetchAll(PDO::FETCH_NAMED);
		if (is_array($tmp)) {
			// return a map of $result['indexname'] = 'indexdef';
			$result = array_column($tmp, 'indexdef', 'indexname');
		}

		return $result;
	}

	public function table_exists(string $table_name): bool {
		$sql = 'select table_name as name ';
		$sql .= 'from information_schema.tables ';
		$sql .= "where table_schema='public' ";
		$sql .= "and table_type='BASE TABLE' ";
		$sql .= "and table_name = :table_name ";
		$sql .= 'order by table_name ';

		$prep_statement = $this->db->prepare($sql);
		$prep_statement->execute(['table_name' => $table_name]);

		return $prep_statement->fetch(PDO::FETCH_ASSOC) !== false;
	}

	public function is_connected(): bool {
		try {
			$stmt = false;
			if ($this->db !== null)
				$stmt = $this->db->query('SELECT 1');

			return $stmt !== false;
		} catch (PDOException $ex) {
			// database is not connected
			return false;
		}

		return true;
	}
}
