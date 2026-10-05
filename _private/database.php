<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
$dsn = getenv('STUDEVID_DB_DSN');
$user = getenv('STUDEVID_DB_USER');
$password = getenv('STUDEVID_DB_PASSWORD');
if (!$dsn || !$user || $password === false) app_error(503, 'Database configuration is missing. Set STUDEVID_DB_DSN, STUDEVID_DB_USER and STUDEVID_DB_PASSWORD.');
$pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
$GLOBALS['students_database_config'] = 'environment';
