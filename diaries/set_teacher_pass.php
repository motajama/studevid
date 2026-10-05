<?php
require_once dirname(__DIR__) . '/_private/bootstrap.php';
page_role('teacher');
require_once __DIR__ . "/db.php";

if (!isset($_SESSION["teacher_id"])) {
    header("Location: /studevid/login.php");
    exit();
}

$teacher_id = $_SESSION["teacher_id"];
$error = "";

$stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1");
$stmt->execute([$teacher_id]);
$link = $stmt->fetch();

$jmeno = $link["name"] ?? "Teacher";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_post();
    $old_password = $_POST["old_password"] ?? "";
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if ($link && password_verify($old_password, $link["password_hash"])) {
        if (is_string($new_password) && strlen($new_password) >= 12 && $new_password === $confirm_password) {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE teachers SET password_hash = ? WHERE id = ?");
            $update->execute([$new_hash, $teacher_id]);

            session_regenerate_id(true);
            header("Location: dashboard_teacher.php");
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
    <title>Change password – <?= htmlspecialchars($jmeno) ?></title>
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
