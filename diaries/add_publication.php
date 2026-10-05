<?php
require_once dirname(__DIR__) . '/_private/bootstrap.php';
$student_id = require_role('student');
require_post();
require_once __DIR__ . '/db.php';
$title=input_text($_POST,'title'); $type=input_text($_POST,'type');
if ($title==='' || !in_array($type,['article','article_wos','article_scopus','chapter'],true)) app_error(422,'Please fill in název a typ publikace.');
$stmt=$pdo->prepare("INSERT INTO publications (student_id,title,type,status) VALUES (?,?,?,'not_started')");
$stmt->execute([$student_id,$title,$type]);
json_response(['success'=>true]);
