<?php
require_once dirname(__DIR__) . '/_private/bootstrap.php';
require_role('teacher'); require_post();
$id=input_id($_POST,'id'); require_once dirname(__DIR__) . '/_private/database.php';
$stmt=$pdo->prepare('UPDATE presentation_registrations SET status="cancelled",updated_at=NOW() WHERE id=? AND status="active"');
$stmt->execute([$id]);
header('Location: /studevid/students.php#secPresentations'); exit;
