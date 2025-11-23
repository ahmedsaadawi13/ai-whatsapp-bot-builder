<?php
// FILE: /app/core/Database.php

/**
 * Database Connection Handler
 *
 * Manages PDO database connections with connection pooling
 * and prepared statement support for security.
 */
class Database {

    private static $instance = null;
    private $pdo;

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $config = require __DIR__ . '/../../config/database.php';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['database']
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false
        ];

        try {
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], $options);
        } catch (PDOException $e) {
            error_log('Database Connection Error: ' . $e->getMessage());
            throw new Exception('Database connection failed');
        }
    }

    /**
     * Get singleton instance
     *
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get PDO connection
     *
     * @return PDO
     */
    public function getConnection() {
        return $this->pdo;
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception('Cannot unserialize singleton');
    }
}
