<?php
require_once __DIR__ . '/_private/bootstrap.php';
header('Location: ' . (!empty($_SESSION['teacher_id']) ? '/studevid/students.php' : '/studevid/login.php'));
exit;
