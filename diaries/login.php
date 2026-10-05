<?php
require_once dirname(__DIR__) . '/_private/bootstrap.php';
require_once __DIR__ . "/db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_post();
    $email = input_text($_POST, 'email');
    $password = input_text($_POST, 'password');

    $stmt = $pdo->prepare("SELECT * FROM students WHERE email = ? AND active = 1");
    $stmt->execute([$email]);
    $student = $stmt->fetch();

    if ($student) {
        $stmt = $pdo->prepare("SELECT * FROM student_teacher WHERE student_id = ? ORDER BY teacher_id");
        $stmt->execute([$student["id"]]);
        $link = null;
        foreach ($stmt->fetchAll() as $candidate) {
            if (password_verify($password, (string)($candidate['password_hash'] ?? ''))) { $link = $candidate; break; }
        }

        if ($link && password_verify($password, $link["password_hash"])) {
            login_identity('student', (int)$student['id'], $student['name']);
            $_SESSION['student_teacher_id'] = (int)$link['teacher_id'];
            header("Location: dashboard.php");
            exit();
        }
    }

    $error = "Invalid e-mail nebo heslo.";
}

$globalCssPath = "/studevid/css/global.css";
$dashboardCssPath = "/studevid/css/dashboard.css";
$extraCssFiles = [];
$loadJquery = true;
$loadChartJs = false;
$loadDesignJs = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Sign in do studijního deníku</title>
    <?php include dirname(__DIR__) . "/_private/views/header.php"; ?>
    <style>
        .loginForm {
            width: min(100%, 480px);
            margin: 2rem auto;
        }
    </style>
</head>
<body>
    <main>
        <h1>Sign in</h1>

        <div class="infoBox loginForm ashBox">
            <?php if ($error): ?>
                <p class="alertBox"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form method="post" class="formEdit">
                <?= csrf_field() ?>
                <span class="centered">
                    <label for="email">Email:</label><br>
                    <input type="email" name="email" required><br><br>
                </span>

                <span class="centered">
                    <label for="password">Password:</label><br>
                    <input type="password" name="password" required><br><br>
                </span>

                <button type="submit" class="button-link centered">Sign in</button>
            </form>
        </div>
    </main>
</body>
</html>
