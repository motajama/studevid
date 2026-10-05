<?php
require_once __DIR__ . '/_private/bootstrap.php';
require_role('teacher');
require_post();
$id = input_id($_POST, 'id');
require_once __DIR__ . '/_private/database.php';
$stmt = $pdo->prepare("UPDATE scheduler SET name='', email='', form='' WHERE id=?");
$stmt->execute([$id]);
header('Location: /studevid/students.php');
exit;
