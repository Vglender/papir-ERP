<?php

class Database
{
    /** @var array<string, array<string, string>> */
    protected static $configs = [];

    /** @var array<string, mysqli> */
    protected static $connections = [];

    /**
     * Инициализация конфигов.
     *
     * @param array<string, array<string, string>> $configs
     * @return void
     */
    public static function init(array $configs)
    {
        self::$configs = $configs;
    }

    /**
     * Получить mysqli-подключение по имени конфигурации.
     *
     * @param string $name
     * @return mysqli
     * @throws Exception
     */
    public static function connection($name)
    {
        if (isset(self::$connections[$name]) && self::$connections[$name] instanceof mysqli) {
            if (@self::$connections[$name]->ping()) {
                return self::$connections[$name];
            }
        }

        if (!isset(self::$configs[$name])) {
            throw new Exception('Database config not found: ' . $name);
        }

        $config = self::$configs[$name];

        $mysqli = @new mysqli(
            $config['host'],
            $config['username'],
            $config['password'],
            $config['database']
        );

        if ($mysqli->connect_error) {
            throw new Exception('Connection failed [' . $name . ']: ' . $mysqli->connect_error);
        }

        $charset = isset($config['charset']) ? $config['charset'] : 'utf8mb4';
        if (!$mysqli->set_charset($charset)) {
            throw new Exception('Charset setup failed [' . $name . ']: ' . $mysqli->error);
        }

        self::$connections[$name] = $mysqli;

        return $mysqli;
    }

    /**
     * Выполнить сырой SQL.
     *
     * @param string $dbName
     * @param string $sql
     * @return array<string, mixed>
     */
    public static function query($dbName, $sql)
    {
        try {
            $mysqli = self::connection($dbName);
            $result = $mysqli->query($sql);

            if ($result === false) {
                return [
                    'ok' => false,
                    'error' => $mysqli->error,
                    'sql' => $sql,
                ];
            }

            return [
                'ok' => true,
                'result' => $result,
                'affected_rows' => $mysqli->affected_rows,
                'insert_id' => $mysqli->insert_id,
            ];
        } catch (Exception $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'sql' => $sql,
            ];
        }
    }

    /**
     * Получить все строки запроса в виде массива.
     *
     * @param string $dbName
     * @param string $sql
     * @return array<string, mixed>
     */
    public static function fetchAll($dbName, $sql)
    {
        $query = self::query($dbName, $sql);

        if (!$query['ok']) {
            return $query;
        }

        $rows = [];
        if ($query['result'] instanceof mysqli_result) {
            while ($row = $query['result']->fetch_assoc()) {
                $rows[] = $row;
            }
            $query['result']->free();
        }

        return [
            'ok' => true,
            'rows' => $rows,
            'count' => count($rows),
        ];
    }

    /**
     * Получить одну строку.
     *
     * @param string $dbName
     * @param string $sql
     * @return array<string, mixed>
     */
    public static function fetchRow($dbName, $sql)
    {
        $result = self::fetchAll($dbName, $sql);

        if (!$result['ok']) {
            return $result;
        }

        return [
            'ok' => true,
            'row' => isset($result['rows'][0]) ? $result['rows'][0] : null,
        ];
    }

    /**
     * INSERT.
     *
     * @param string $dbName
     * @param string $table
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function insert($dbName, $table, array $data)
    {
        try {
            $mysqli = self::connection($dbName);

            $columns = [];
            $values = [];

            foreach ($data as $column => $value) {
                $columns[] = '`' . $column . '`';
                $values[] = self::escapeValue($mysqli, $value);
            }

            $sql = "INSERT INTO `{$table}` (" . implode(', ', $columns) . ")
                    VALUES (" . implode(', ', $values) . ")";

            $result = $mysqli->query($sql);

            if ($result === false) {
                return [
                    'ok' => false,
                    'error' => $mysqli->error,
                    'sql' => $sql,
                ];
            }

            return [
                'ok' => true,
                'insert_id' => $mysqli->insert_id,
                'affected_rows' => $mysqli->affected_rows,
            ];
        } catch (Exception $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * UPDATE по условиям.
     *
     * @param string $dbName
     * @param string $table
     * @param array<string, mixed> $data
     * @param array<int|string, mixed> $where
     * @return array<string, mixed>
     */
    public static function update($dbName, $table, array $data, $where)
    {
        try {
            $mysqli = self::connection($dbName);

            $setParts = [];
            foreach ($data as $column => $value) {
                $setParts[] = '`' . $column . '` = ' . self::escapeValue($mysqli, $value);
            }

            $whereSql = self::buildWhere($mysqli, $where);

            $sql = "UPDATE `{$table}` SET " . implode(', ', $setParts) . " WHERE {$whereSql}";
            $result = $mysqli->query($sql);

            if ($result === false) {
                return [
                    'ok' => false,
                    'error' => $mysqli->error,
                    'sql' => $sql,
                ];
            }

            return [
                'ok' => true,
                'affected_rows' => $mysqli->affected_rows,
            ];
        } catch (Exception $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * DELETE по условиям.
     *
     * @param string $dbName
     * @param string $table
     * @param array<int|string, mixed> $where
     * @return array<string, mixed>
     */
    public static function delete($dbName, $table, $where)
    {
        try {
            $mysqli = self::connection($dbName);
            $whereSql = self::buildWhere($mysqli, $where);

            $sql = "DELETE FROM `{$table}` WHERE {$whereSql}";
            $result = $mysqli->query($sql);

            if ($result === false) {
                return [
                    'ok' => false,
                    'error' => $mysqli->error,
                    'sql' => $sql,
                ];
            }

            return [
                'ok' => true,
                'affected_rows' => $mysqli->affected_rows,
            ];
        } catch (Exception $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * SELECT MAX(col) + 1
     *
     * @param string $dbName
     * @param string $table
     * @param string $column
     * @return array<string, mixed>
     */
    public static function nextId($dbName, $table, $column)
    {
        $sql = "SELECT MAX(`{$column}`) AS max_id FROM `{$table}`";
        $row = self::fetchRow($dbName, $sql);

        if (!$row['ok']) {
            return $row;
        }

        $nextId = 1;
        if (!empty($row['row']) && $row['row']['max_id'] !== null) {
            $nextId = (int)$row['row']['max_id'] + 1;
        }

        return [
            'ok' => true,
            'next_id' => $nextId,
        ];
    }

    /**
     * UPDATE / INSERT по ключевым полям.
     *
     * @param string $dbName
     * @param string $table
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string>|string $keyColumns
     * @return array<string, mixed>
     */
    public static function upsertRows($dbName, $table, array $rows, $keyColumns)
    {
        $result = [
            'ok' => true,
            'updated' => 0,
            'inserted' => 0,
            'errors' => [],
        ];

        if (!is_array($keyColumns)) {
            $keyColumns = [$keyColumns];
        }

        try {
            $mysqli = self::connection($dbName);

            foreach ($rows as $row) {
                try {
                    $where = [];

                    foreach ($keyColumns as $keyColumn) {
                        if (!array_key_exists($keyColumn, $row)) {
                            throw new Exception('Missing key column: ' . $keyColumn);
                        }
                        $where[$keyColumn] = $row[$keyColumn];
                    }

                    $existsSql = "SELECT 1 FROM `{$table}` WHERE " . self::buildWhere($mysqli, $where) . " LIMIT 1";
                    $exists = self::fetchRow($dbName, $existsSql);

                    if (!$exists['ok']) {
                        throw new Exception($exists['error']);
                    }

                    if ($exists['row']) {
                        $upd = self::update($dbName, $table, $row, $where);
                        if (!$upd['ok']) {
                            throw new Exception($upd['error']);
                        }
                        $result['updated']++;
                    } else {
                        $ins = self::insert($dbName, $table, $row);
                        if (!$ins['ok']) {
                            throw new Exception($ins['error']);
                        }
                        $result['inserted']++;
                    }
                } catch (Exception $e) {
                    $result['errors'][] = $e->getMessage();
                }
            }
        } catch (Exception $e) {
            return [
                'ok' => false,
                'updated' => 0,
                'inserted' => 0,
                'errors' => [$e->getMessage()],
            ];
        }

        return $result;
    }

    /**
     * Закрыть конкретное соединение.
     *
     * @param string $dbName
     * @return void
     */
    public static function close($dbName)
    {
        if (isset(self::$connections[$dbName]) && self::$connections[$dbName] instanceof mysqli) {
            self::$connections[$dbName]->close();
            unset(self::$connections[$dbName]);
        }
    }

    /**
     * Закрыть все соединения.
     *
     * @return void
     */
    public static function closeAll()
    {
        foreach (self::$connections as $dbName => $connection) {
            if ($connection instanceof mysqli) {
                $connection->close();
            }
        }

        self::$connections = [];
    }

    /**
     * @param mysqli $mysqli
     * @param mixed $value
     * @return string
     */
    protected static function escapeValue(mysqli $mysqli, $value)
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        if (is_array($value)) {
            $value = implode(', ', $value);
        }

        return "'" . $mysqli->real_escape_string((string)$value) . "'";
    }

    /**
     * @param mysqli $mysqli
     * @param array<int|string, mixed> $where
     * @return string
     * @throws Exception
     */
    protected static function buildWhere(mysqli $mysqli, $where)
    {
        // Поддержка старого формата:
        // ['colum' => 'id', 'value' => 5]
        if (isset($where['colum']) && array_key_exists('value', $where)) {
            return '`' . $where['colum'] . '` = ' . self::escapeValue($mysqli, $where['value']);
        }

        // Поддержка старого массива:
        // [
        //   ['colum' => 'id', 'value' => 5],
        //   ['colum' => 'sku', 'value' => 'ABC']
        // ]
        if (isset($where[0]) && is_array($where[0]) && isset($where[0]['colum'])) {
            $parts = [];
            foreach ($where as $item) {
                $parts[] = '`' . $item['colum'] . '` = ' . self::escapeValue($mysqli, $item['value']);
            }
            return implode(' AND ', $parts);
        }

        // Новый нормальный формат:
        // ['id' => 5, 'sku' => 'ABC']
        if (is_array($where)) {
            $parts = [];
            foreach ($where as $column => $value) {
                $parts[] = '`' . $column . '` = ' . self::escapeValue($mysqli, $value);
            }

            if (!$parts) {
                throw new Exception('WHERE conditions are empty');
            }

            return implode(' AND ', $parts);
        }

        throw new Exception('Invalid WHERE format');
    }
	
	public static function upsertOne($dbName, $table, array $row, $keyColumns)
		{
			return self::upsertRows($dbName, $table, [$row], $keyColumns);
		}
			
	public static function escape($dbName, $value)
		{
			$mysqli = self::connection($dbName);
			return $mysqli->real_escape_string((string)$value);
		}
	public static function begin($dbName)
		{
			$mysqli = self::connection($dbName);
			$mysqli->begin_transaction();
			return ['ok' => true];
		}

		public static function commit($dbName)
		{
			$mysqli = self::connection($dbName);
			$mysqli->commit();
			return ['ok' => true];
		}

		public static function rollback($dbName)
		{
			$mysqli = self::connection($dbName);
			$mysqli->rollback();
			return ['ok' => true];
		}	
	
	public static function exists($dbName, $table, array $where)
	{
		$row = self::fetchRow(
			$dbName,
			"SELECT 1 FROM `{$table}` WHERE " . self::buildWhere(self::connection($dbName), $where) . " LIMIT 1"
		);

		return [
			'ok' => $row['ok'],
			'exists' => ($row['ok'] && !empty($row['row'])),
		];
	}
	
	public static function fetchValue($dbName, $sql, $field)
	{
		$row = self::fetchRow($dbName, $sql);

		if (!$row['ok']) {
			return $row;
		}

		return [
			'ok' => true,
			'value' => isset($row['row'][$field]) ? $row['row'][$field] : null,
		];
	}
	
	public static function execute($dbName, $sql)
	{
		return self::query($dbName, $sql);
	}
}


// ----------------------------WRAPER-----------------------------------


function connectbd($name)
{
    return Database::connection($name);
}

function query_bd($name, $query)
{
    return Database::query($name, $query);
}

function query_insert($bd, $table, $parametrs)
{
    return Database::insert($bd, $table, $parametrs);
}

function query_upd($bd, $table, $parametrs, $keys)
{
    return Database::update($bd, $table, $parametrs, $keys);
}

function delete_row($bd, $table, $keys)
{
    return Database::delete($bd, $table, $keys);
}

function last_id($bd, $table, $colum)
{
    return Database::nextId($bd, $table, $colum);
}

function UpdBdTbbyId($data, $table, $bd, $key_colum = null, $base_colum = null)
{
    if ($base_colum !== null && $base_colum !== $key_colum) {
        // пока базовая версия работает по ключам upsert,
        // сложную логику base_colum можно вернуть отдельным этапом
    }

    return Database::upsertRows($bd, $table, $data, $key_colum ?: 'id');
}