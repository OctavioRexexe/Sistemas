<?php
/**
 * TRIGGERX - Clase Database (PDO Singleton)
 * Prepared Statements obligatorios - Sin SQL injection posible
 */
defined('TRIGGERX') or die('Acceso denegado');

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;
    private int $queryCount = 0;

    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                throw $e;
            }
            error_log('DB Connection Error: ' . $e->getMessage());
            http_response_code(503);
            die(json_encode(['error' => 'Error de conexión a la base de datos']));
        }
    }

    /** Obtener instancia única */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Obtener PDO raw */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Ejecutar query con prepared statement
     * @return PDOStatement
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $this->queryCount++;
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                throw $e;
            }
            error_log('DB Query Error: ' . $e->getMessage() . ' | SQL: ' . $sql);
            throw new RuntimeException('Error en consulta de base de datos');
        }
    }

    /** Obtener todos los registros */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /** Obtener un registro */
    public function fetch(string $sql, array $params = []): array|false
    {
        return $this->query($sql, $params)->fetch();
    }

    /** Obtener valor único */
    public function fetchColumn(string $sql, array $params = []): mixed
    {
        return $this->query($sql, $params)->fetchColumn();
    }

    /** Insertar y retornar último ID */
    public function insert(string $table, array $data): string|false
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));
        return $this->pdo->lastInsertId();
    }

    /** Actualizar registros */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(', ', array_map(fn($col) => "`{$col}` = ?", array_keys($data)));
        $sql = "UPDATE `{$table}` SET {$set} WHERE {$where}";
        return $this->query($sql, array_merge(array_values($data), $whereParams))->rowCount();
    }

    /** Eliminar registros */
    public function delete(string $table, string $where, array $params = []): int
    {
        return $this->query("DELETE FROM `{$table}` WHERE {$where}", $params)->rowCount();
    }

    /** Iniciar transacción */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /** Confirmar transacción */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /** Revertir transacción */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    /** Número de queries ejecutadas */
    public function getQueryCount(): int
    {
        return $this->queryCount;
    }

    // Prevenir clonación
    private function __clone() {}
    public function __wakeup(): never { throw new \Exception('No serializable'); }
}
