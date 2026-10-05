<?php
require_once dirname(__DIR__) . '/_private/bootstrap.php';
$student_id = require_role('student');
require_post();
require_once __DIR__ . '/db.php';
$id=input_id($_POST,'id');
$stmt=$pdo->prepare('SELECT 1 FROM publications WHERE id=? AND student_id=?');
$stmt->execute([$id,$student_id]);
if (!$stmt->fetchColumn()) app_error(404,'Publications nebyla nalezena.');
$status=input_text($_POST,'status');
if (!in_array($status,['not_started','data_collection','draft','submitted','review','accepted','printed'],true)) app_error(422,'Neplatná fáze publikace.');
$stmt=$pdo->prepare('UPDATE publications SET status=?,updated_at=NOW() WHERE id=? AND student_id=?');
$stmt->execute([$status,$id,$student_id]);
json_response(['success'=>true]);
