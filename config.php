<?php
// ============================================
// Configurazione Database
// ============================================

define('DB_HOST', 'db');
define('DB_NAME', 'matrimonio');
define('DB_USER', 'matrimonio_user');
define('DB_PASS', 'password');

define('ADMIN_PASSWORD', 'asd1982A!FRA');

define('TITOLO', 'Francesco &amp; Serena — 27 Settembre 2026');
define('SITOWEB', 'http://localhost:8080/');
define('PREFISSO_INTERNAZIONALE', '39');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
