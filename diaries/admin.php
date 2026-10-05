<?php
require_once dirname(__DIR__) . '/_private/bootstrap.php';
require_once __DIR__ . "/db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_post();
    $email = input_text($_POST, 'email');
    $password = input_text($_POST, 'password');

    $stmt = $pdo->prepare("SELECT * FROM teachers WHERE email = ?");
    $stmt->execute([$email]);
    $teacher = $stmt->fetch();

    if ($teacher && password_verify($password, $teacher["password_hash"])) {
        login_identity('teacher', (int)$teacher['id'], $teacher['name'] ?? $email);
        header("Location: dashboard_teacher.php");
        exit();
    } else {
        $error = "Invalid e-mail nebo heslo.";
    }
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
    <title>Sign in do teacherské aplikace ke studijním deníkům</title>
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
        <h1>Teacher sign in</h1>

        <div class="infoBox loginForm ashBox">
            <?php if ($error): ?>
                <p class="alertBox"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form method="post">
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
