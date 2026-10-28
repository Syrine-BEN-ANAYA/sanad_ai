<?php

declare(strict_types=1);

namespace SanadAi\Config;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = require dirname(__DIR__, 2)
            . '/config/database.php';

        if (
            ($config['database'] ?? '') === ''
            || ($config['username'] ?? '') === ''
        ) {
            throw new RuntimeException(
                'Database configuration is incomplete.'
            );
        }

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $config['driver'],
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            self::$connection = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE =>
                        PDO::ERRMODE_EXCEPTION,

                    PDO::ATTR_DEFAULT_FETCH_MODE =>
                        PDO::FETCH_ASSOC,

                    PDO::ATTR_EMULATE_PREPARES =>
                        false,

                    // rowCount() must report matched rows, so that an
                    // UPDATE writing identical values is not a "miss".
                    PDO::MYSQL_ATTR_FOUND_ROWS =>
                        true,

                    // Keep MySQL's session time zone identical to PHP's
                    // so TIMESTAMP columns written by PHP (date()) and by
                    // MySQL (CURRENT_TIMESTAMP) agree with each other.
                    PDO::MYSQL_ATTR_INIT_COMMAND =>
                        "SET time_zone = '" . date('P') . "'",
                ]
            );
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Database connection failed.',
                0,
                $e
            );
        }

        return self::$connection;
    }
}