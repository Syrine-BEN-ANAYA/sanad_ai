<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

use SanadAi\Config\Database;

$migrationsPath = __DIR__ . '/../database/migrations';

$pdo = Database::connection();

$pdo->exec("
    CREATE TABLE IF NOT EXISTS migrations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB
      DEFAULT CHARSET=utf8mb4
      COLLATE=utf8mb4_unicode_ci
");

$executed = $pdo
    ->query("SELECT migration FROM migrations ORDER BY id")
    ->fetchAll(PDO::FETCH_COLUMN);

$files = glob($migrationsPath . '/*.php');

sort($files);

foreach ($files as $file) {
    $migrationName = basename($file);

    if (in_array($migrationName, $executed, true)) {
        echo "SKIP  {$migrationName}" . PHP_EOL;
        continue;
    }

    echo "RUN   {$migrationName}" . PHP_EOL;

    $migration = require $file;

    if (!is_callable($migration)) {
        throw new RuntimeException(
            "Migration {$migrationName} must return a callable."
        );
    }

    try {
        $migration($pdo);

        $stmt = $pdo->prepare(
            "INSERT INTO migrations (migration) VALUES (?)"
        );

        $stmt->execute([$migrationName]);

        echo "DONE  {$migrationName}" . PHP_EOL;

    } catch (Throwable $e) {
        echo "FAIL  {$migrationName}" . PHP_EOL;
        throw $e;
    }
}

echo "Migrations completed." . PHP_EOL;