<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

function userDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env('FILERECONDB', env('USERDB_HOST', 'localhost'));
    $username = env('DB_USERNAME', 'root');
    $password = env('DB_PASSWORD', '');

    $preferredDbName = env('FILERECONDB_NAME', 'filerecondb');
    $candidateDbNames = array_values(array_unique(array_filter([
        $preferredDbName,
        'filerecondb',
        'filerecon',
        env('USERDB_NAME', 'userdb'),
    ], static function ($value) {
        return is_string($value) && trim($value) !== '';
    })));

    $lastException = null;
    foreach ($candidateDbNames as $dbName) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $dbName);

        try {
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            return $pdo;
        } catch (PDOException $e) {
            $lastException = $e;
        }
    }

    if ($lastException instanceof PDOException) {
        throw $lastException;
    }

    return $pdo;
}
/**
 * Connection to the masterdata database used for partner/master files.
 * Uses environment variables: MASTERDB_HOST, MASTERDB_NAME, DB_USERNAME, DB_PASSWORD
 */
function masterDataConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env('MASTERDB_HOST', 'localhost');
    $dbName = env('MASTERDB_NAME', 'masterdata');
    $username = env('DB_USERNAME', 'root');
    $password = env('DB_PASSWORD', '');

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $dbName);

    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

/**
 * Connection to the file recon database used by insertion/duplicate checks.
 * Uses environment variables: USERDB_HOST, FILERECONDB_NAME, DB_USERNAME, DB_PASSWORD
 */
function fileRecDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env('USERDB_HOST', 'localhost');
    $dbName = env('FILERECONDB_NAME', 'filerecondb');
    $username = env('DB_USERNAME', 'root');
    $password = env('DB_PASSWORD', '');

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $dbName);

    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
