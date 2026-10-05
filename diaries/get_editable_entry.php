<?php
require_once dirname(__DIR__) . '/_private/bootstrap.php';
$student_id = require_role('student');
require_once __DIR__ . '/db.php';
$stmt = $pdo->prepare('SELECT * FROM diary_entries WHERE student_id=? AND visible_to_teacher=0 ORDER BY week_start DESC,id DESC LIMIT 1');
$stmt->execute([$student_id]); json_response($stmt->fetch() ?: new stdClass());
