<?php
/**
 * VEYRA — PDO Database Singleton
 */

declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;

    public static function connect(array $config): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            (int) $config['port'],
            $config['name'],
            $config['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']}",
        ];

        self::$instance = new PDO($dsn, $config['user'], $config['pass'], $options);
        return self::$instance;
    }

    public static function get(): PDO
    {
        if (!self::$instance) {
            throw new RuntimeException('Database not connected. Call Database::connect() first.');
        }
        return self::$instance;
    }
}
