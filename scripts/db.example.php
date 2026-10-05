<?php
// This example is for reference only. The live application uses
// environment variables in the web root. Keep credentials outside the repository.
// Set these values in the server environment; never commit passwords.
$dsn = getenv('STUDEVID_DB_DSN');
$user = getenv('STUDEVID_DB_USER');
$password = getenv('STUDEVID_DB_PASSWORD');
if (!$dsn || !$user || $password === false) {
    throw new RuntimeException('Database environment is incomplete.');
}
$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
