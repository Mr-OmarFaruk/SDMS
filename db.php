<?php
declare(strict_types=1);

// Keep one database connection for the whole request.
function db(): PDO
{
    static $pdo;

    if ($pdo === null) {
        $host = config('db_host');
        $port = config('db_port');
        $database = config('db_name');
        $username = config('db_user');
        $password = config('db_pass');

        try {
            // PDO prepares SQL statements and reports connection errors as exceptions.
            $pdo = new PDO(
                "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException) {
            http_response_code(503);
            die('Database connection failed. Check your database credentials in config.local.php or DB_* environment variables.');
        }
    }

    return $pdo;
}
