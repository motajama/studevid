<?php
require_once __DIR__ . '/_private/bootstrap.php';
require_once __DIR__ . "/diaries/db.php";

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
        header("Location: students.php");
        exit();
    } else {
        $error = "Invalid e-mail nebo heslo.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
<?php include __DIR__ . "/_private/views/header.php"; ?>
<title>Sign in do teacherské aplikace ke studijním deníkům</title>
<link rel="stylesheet" href="/studevid/css/konzultace.css">
<style>
.loginForm {
    width: 40%;
    margin: auto;
}

.main-container {
    width: 100%;
    height: 100vh;
}

</style>
    </head>
<body>

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
</body>
</html>
