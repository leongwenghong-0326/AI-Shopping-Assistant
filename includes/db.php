<?php
/**
 * PDO database connection helper.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * @return PDO
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        // Database names with spaces (common on some cPanel setups) cannot reliably
        // go in the DSN dbname= field — login first, then USE `name`.
        if (preg_match('/\s/', DB_NAME)) {
            $pdo = new PDO(
                sprintf('mysql:host=%s;charset=%s', DB_HOST, DB_CHARSET),
                DB_USER,
                DB_PASS,
                $options
            );
            $safeName = str_replace('`', '``', DB_NAME);
            $pdo->exec("USE `{$safeName}`");
        } else {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        }
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        throw new RuntimeException('Unable to connect to the database.');
    }

    return $pdo;
}

/**
 * Connect without selecting a database (used by installer).
 */
function db_server(): PDO
{
    $dsn = sprintf('mysql:host=%s;charset=%s', DB_HOST, DB_CHARSET);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    return new PDO($dsn, DB_USER, DB_PASS, $options);
}
