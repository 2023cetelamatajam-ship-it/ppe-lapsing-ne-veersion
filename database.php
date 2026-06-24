<?php
declare(strict_types=1);

function initialize_runtime_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS asset_transfers (
            transfer_id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id INT NOT NULL,
            from_department_id INT NULL,
            to_department_id INT NULL,
            from_location VARCHAR(150) DEFAULT NULL,
            to_location VARCHAR(150) DEFAULT NULL,
            transfer_date DATE NOT NULL,
            notes TEXT DEFAULT NULL,
            transferred_by_user_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_asset_transfers_asset (asset_id),
            INDEX idx_asset_transfers_date (transfer_date),
            CONSTRAINT fk_asset_transfers_asset
                FOREIGN KEY (asset_id) REFERENCES assets(asset_id) ON DELETE CASCADE,
            CONSTRAINT fk_asset_transfers_from_department
                FOREIGN KEY (from_department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
            CONSTRAINT fk_asset_transfers_to_department
                FOREIGN KEY (to_department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
            CONSTRAINT fk_asset_transfers_user
                FOREIGN KEY (transferred_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
        )'
    );
}

$dbConfig = [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: '3306',
    'name' => getenv('DB_NAME') ?: 'ppe_ai_system',
    'user' => getenv('DB_USER') ?: 'root',
    'pass' => getenv('DB_PASS') ?: '',
    'charset' => 'utf8mb4',
];

$pdo = null;
$dbConnectionError = null;

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $dbConfig['host'],
        $dbConfig['port'],
        $dbConfig['name'],
        $dbConfig['charset']
    );

    $pdo = new PDO(
        $dsn,
        $dbConfig['user'],
        $dbConfig['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    initialize_runtime_schema($pdo);
} catch (Throwable $exception) {
    $dbConnectionError = $exception->getMessage();
}

function db(): PDO
{
    global $pdo, $dbConnectionError;

    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Database connection unavailable: ' . (string) $dbConnectionError);
    }

    return $pdo;
}

function db_error(): ?string
{
    global $dbConnectionError;

    return $dbConnectionError;
}
