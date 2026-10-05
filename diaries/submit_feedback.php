<?php
require_once dirname(__DIR__) . '/_private/bootstrap.php';
$teacher_id = require_role('teacher'); require_post();
$entry_id = input_id($_POST, 'entry_id'); $feedback = input_text($_POST, 'teacher_feedback');
require_once __DIR__ . '/db.php';
$stmt = $pdo->prepare('SELECT 1 FROM diary_entries de WHERE de.id=? AND de.visible_to_teacher=1 AND EXISTS (SELECT 1 FROM student_teacher st WHERE st.student_id=de.student_id AND st.teacher_id=?)');
$stmt->execute([$entry_id,$teacher_id]);
if (!$stmt->fetchColumn()) app_error(404, 'Zápis was not found.');
$stmt = $pdo->prepare('UPDATE diary_entries de SET teacher_feedback=?,updated_at=NOW() WHERE de.id=? AND de.visible_to_teacher=1 AND EXISTS (SELECT 1 FROM student_teacher st WHERE st.student_id=de.student_id AND st.teacher_id=?)');
$stmt->execute([$feedback,$entry_id,$teacher_id]);
json_response(['success'=>true,'message'=>'Backná vazba uložena.']);
