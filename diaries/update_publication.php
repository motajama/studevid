<?php
require_once dirname(__DIR__) . '/_private/bootstrap.php';
$student_id = require_role('student');
require_post();
require_once __DIR__ . '/db.php';
$id=input_id($_POST,'id');
$stmt=$pdo->prepare('SELECT 1 FROM publications WHERE id=? AND student_id=?');
$stmt->execute([$id,$student_id]);
if (!$stmt->fetchColumn()) app_error(404,'Publications nebyla nalezena.');
$title=input_text($_POST,'title'); $type=input_text($_POST,'type');
if ($title==='' || !in_array($type,['article','article_wos','article_scopus','chapter'],true)) app_error(422,'Please fill in název a typ publikace.');
$stmt=$pdo->prepare('UPDATE publications SET title=?,type=? WHERE id=? AND student_id=?');
$stmt->execute([$title,$type,$id,$student_id]);
json_response(['success'=>true]);
