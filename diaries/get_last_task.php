<?php
require_once dirname(__DIR__) . '/_private/bootstrap.php';
$student_id = require_role('student');
require_once __DIR__ . '/db.php';
$stmt = $pdo->prepare('SELECT tasks FROM consultations WHERE student_id=? ORDER BY date DESC LIMIT 1');
$stmt->execute([$student_id]); $tasks = $stmt->fetchColumn();
header('Content-Type: text/html; charset=utf-8');
echo $tasks !== false ? nl2br(h($tasks)) : 'No úkol not found.';
