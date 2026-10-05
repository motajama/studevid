<?php
require_once dirname(__DIR__) . '/_private/bootstrap.php';
$student_id = require_role('student');
require_post();
require_once __DIR__ . '/db.php';
$entry_id = !empty($_POST['entry_id']) ? input_id($_POST,'entry_id') : null;
$entry = [];
if ($entry_id) {
    $stmt=$pdo->prepare('SELECT * FROM diary_entries WHERE id=? AND student_id=?');
    $stmt->execute([$entry_id,$student_id]); $entry=$stmt->fetch();
    if (!$entry) app_error(404,'Zápis was not found.');
}
$values=[];
foreach (['what_did_you_do','what_did_it_give_me','doubts_or_questions','task_reflection','publications_reflection','private_notes'] as $field) {
    // Preserve older reflection fields when a form does not contain them.
    $values[]=input_text($_POST,$field,(string)($entry[$field] ?? ''));
}
$notesToTeacher = isset($_POST['notes_to_teacher']) ? 1 : 0;
$values[]=(isset($_POST['visible_to_teacher']) || $notesToTeacher) ? 1 : 0;
$values[]=$notesToTeacher;
if ($entry_id) {
    $stmt=$pdo->prepare('UPDATE diary_entries SET what_did_you_do=?,what_did_it_give_me=?,doubts_or_questions=?,task_reflection=?,publications_reflection=?,private_notes=?,visible_to_teacher=?,notes_to_teacher=?,updated_at=NOW() WHERE id=? AND student_id=?');
    $stmt->execute([...$values,$entry_id,$student_id]);
} else {
    $stmt=$pdo->prepare('INSERT INTO diary_entries (what_did_you_do,what_did_it_give_me,doubts_or_questions,task_reflection,publications_reflection,private_notes,visible_to_teacher,notes_to_teacher,student_id,week_start) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([...$values,$student_id,date('Y-m-d',strtotime('monday this week'))]);
}
header('Location: /studevid/diaries/dashboard.php'); exit;
