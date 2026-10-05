<?php
require_once dirname(__DIR__) . '/_private/bootstrap.php';
$student_id = require_role('student');
require_once __DIR__ . '/db.php';
$stmt = $pdo->prepare('SELECT * FROM publications WHERE student_id=? ORDER BY created_at,id');
$stmt->execute([$student_id]); json_response($stmt->fetchAll());
