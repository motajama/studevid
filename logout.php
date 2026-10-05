<?php
require_once __DIR__ . '/_private/bootstrap.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post(); $_SESSION = [];
    setcookie(session_name(), '', ['expires'=>time()-3600,'path'=>'/','httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','samesite'=>'Lax']);
    session_destroy(); header('Location: /studevid/login.php'); exit;
}
?>
<!doctype html><html lang="en"><head><title>Sign out</title><?php require __DIR__ . '/_private/views/header.php'; ?></head>
<body><main><h1>Sign out se</h1><form method="post"><?= csrf_field() ?><button type="submit">Confirm sign out</button></form></main></body></html>
