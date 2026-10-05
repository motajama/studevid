<?php
require_once dirname(__DIR__) . '/_private/bootstrap.php';
require_once __DIR__ . '/db.php';
if (!empty($_SESSION['student_id'])) {
    $student_id = require_role('student'); $visibility = '';
} else {
    require_role('teacher'); $student_id = input_id($_GET, 'student_id');
    require_student_access($pdo, $student_id); $visibility = ' AND visible_to_teacher=1';
}
$stmt = $pdo->prepare('SELECT id,week_start,visible_to_teacher FROM diary_entries WHERE student_id=?' . $visibility . ' ORDER BY week_start DESC,id DESC');
$stmt->execute([$student_id]); json_response($stmt->fetchAll());
