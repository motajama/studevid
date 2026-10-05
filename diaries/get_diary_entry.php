<?php
require_once dirname(__DIR__) . '/_private/bootstrap.php';
require_once __DIR__ . '/db.php';
$entry_id = input_id($_GET, 'id');
if (!empty($_SESSION['student_id'])) {
    $stmt = $pdo->prepare('SELECT * FROM diary_entries WHERE id=? AND student_id=?');
    $stmt->execute([$entry_id, require_role('student')]);
} else {
    $teacher_id = require_role('teacher');
    $stmt = $pdo->prepare('SELECT de.* FROM diary_entries de WHERE de.id=? AND de.visible_to_teacher=1 AND EXISTS (SELECT 1 FROM student_teacher st WHERE st.student_id=de.student_id AND st.teacher_id=?)');
    $stmt->execute([$entry_id,$teacher_id]);
}
$entry = $stmt->fetch();
if (!$entry) app_error(404, 'Zápis was not found.');
json_response($entry);
