<?php
require_once dirname(__DIR__) . '/_private/bootstrap.php';
page_role('student');
require_once __DIR__ . "/db.php";

if (!isset($_SESSION["student_id"])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION["student_id"];
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_post();
    $old_password = $_POST["old_password"] ?? "";
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    $stmt = $pdo->prepare("SELECT * FROM student_teacher WHERE student_id = ? AND teacher_id = ?");
    $stmt->execute([$student_id, (int)($_SESSION['student_teacher_id'] ?? 0)]);
    $link = $stmt->fetch();

    if ($link && password_verify($old_password, $link["password_hash"])) {
        if (is_string($new_password) && strlen($new_password) >= 12 && $new_password === $confirm_password) {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE student_teacher SET password_hash = ? WHERE student_id = ? AND teacher_id = ?");
            $update->execute([$new_hash, $student_id, (int)($_SESSION['student_teacher_id'] ?? 0)]);

            session_regenerate_id(true);
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Nové heslo musí mít alespoň 12 znaků a obě zadání se musí shodovat.";
        }
    } else {
        $error = "Původní heslo není správné.";
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
    <title>Change password</title>
    <?php include dirname(__DIR__) . "/_private/views/header.php"; ?>
    <style>
        .formBox {
            width: min(100%, 560px);
            margin: 2rem auto;
        }
    </style>
</head>
<body>
    <main>
        <h1>Change password</h1>
        <div class="infoBox formBox ashBox">
            <?php if ($error): ?>
                <p class="alertBox"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form method="post" class="formEdit">
                <?= csrf_field() ?>
                <label for="old_password">Původní heslo:</label><br>
                <input type="password" name="old_password" required><br><br>

                <label for="new_password">Nové heslo:</label><br>
                <input type="password" name="new_password" required><br><br>

                <label for="confirm_password">Potvrzení nového hesla:</label><br>
                <input type="password" name="confirm_password" required><br><br>

                <button type="submit" class="button-link">Změnit heslo</button>
            </form>
        </div>
    </main>
</body>
</html>
