<?php
require_once dirname(__DIR__) . '/_private/bootstrap.php';

if (!isset($_SESSION["student_id"])) {
    header("Location: login.php");
    exit();
}

page_role('student');
require_once __DIR__ . "/db.php";

$student_id = $_SESSION["student_id"] ?? 0;

$stmt = $pdo->prepare("SELECT name FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$name = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT tasks, date
    FROM consultations
    WHERE student_id = ?
    ORDER BY date DESC
    LIMIT 1
");
$stmt->execute([$student_id]);
$taskInfo = $stmt->fetch(PDO::FETCH_ASSOC);

// shared header config
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
    <title>[ <?= htmlspecialchars($name ?? "Neznámý student") ?> | study diary ]</title>
    <?php require_once dirname(__DIR__) . "/_private/views/header.php"; ?>

    <script src="https://code.jquery.com/ui/1.14.1/jquery-ui.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.1/themes/base/jquery-ui.css">

    <style>
        label {
            display: inline-block;
            width: 5em;
        }
        #admin-btns {
            margin: 1em auto;
            text-align: center;
        }
    </style>

    <script>
    $(function() {
        $(document).tooltip();
    });
    </script>
</head>

<body>
   <aside class="diary-sidebar">
    <div class="diary-sidebar-card">
        <div class="diary-sidebar-head">
            <h2 class="text-font-bold">Timeová osa</h2>
            <p class="muted code-font">Vaše zápisy po měsících a letech</p>
        </div>

        <div class="diary-sidebar-body">
            <ul id="timeline-menu" class="timeline-menu"></ul>
        </div>

        <div class="diary-sidebar-foot code-font">
            <a href="set_student_pass.php" class="button-link subtle">🔐 Change password</a>
            <a href="logout.php" class="button-link subtle">↩ Sign out</a>
        </div>
    </div>
</aside>

    <main>
        <h1 class="text-font"><?= htmlspecialchars($name ?? "Neznámý student") ?> – study diary</h1>

        <section class="standardCard">
            <h2 class="text-font-bold">
                Úkoly z latest consultations
                (<?= $taskInfo && isset($taskInfo["date"]) ? date("j. n. Y", strtotime($taskInfo["date"])) : "–" ?>)
            </h2>
            <div id="last-task" class="formRow code-font">
                <?= $taskInfo ? nl2br(htmlspecialchars($taskInfo["tasks"])) : "No úkol not found." ?>
            </div>
        </section>

        <section class="standardCard">
            <h2 class="text-font-bold">
                Moje publikace
                <span style="cursor:pointer;" title="Je potřeba mít minimálně dvě prvoautorské akceptované publikace: 1) WOS/Scopus, 2) WOS Q1–Q3 (SCIE + SSCI, ne ESCI)">
                    <sup>ℹ️</sup>
                </span>
            </h2>
            <div id="pub-list" class="formRow"></div>
        </section>

        <section id="novy_zapis" class="standardCard">
            <h2 id="novy_zapis_title" class="text-font-bold">New zápis</h2>
            <div id="infoBox"></div>

            <form class="formEdit" method="POST" action="submit_entry.php">
                <?= csrf_field() ?>
                <input type="hidden" name="entry_id" value="">

                <div class="formRow">
                    <label for="what_did_you_do">Co jsem udělal/a:</label>
                    <textarea name="what_did_you_do" rows="5"></textarea>
                </div>

                <div class="formRow">
                    <label for="doubts_or_questions">Otázky / problémy:</label>
                    <textarea name="doubts_or_questions" rows="5"></textarea>
                </div>

                <div class="formRow">
                    <label for="private_notes">Notes k práci v týdnu:</label>
                    <textarea name="private_notes" rows="5" placeholder="Tyto poznámky vidíte jen vy."></textarea>
                    <div class="alertBox">Teacher tyto poznámky nevidí. Tlačítkem níže můžete vybranou poznámku odeslat vyučujícímu k nahlédnutí.</div>
                    <label for="notes_to_teacher">Odeslat vyučujícímu:</label>
                    <input type="checkbox" name="notes_to_teacher" value="1"/>
                </div>

                <div class="formRow">
                    <label for="visible_to_teacher">Poslat školiteli:</label>
                    <input type="checkbox" name="visible_to_teacher" value="1"/>

                    <div class="alertBox">
                        Školitel zápis nevidí, dokud nezaškrtnete „Poslat školiteli“. Nemůže tedy ani napsat backnou vazbu.
                        Můžete takto ukládat rozpracovaný zápis a vracet se k němu, dokud jej neodešlete.
                    </div>
                </div>

                <div class="formRow">
                    <button type="submit" class="button-link centered">💾 Save zápis</button>
                </div>
            </form>
        </section>

        <section class="standardCard" id="minule-zapisy">
            <h2 class="text-font-bold">Minulé zápisy</h2>
            <ul id="entry-list" class="code-font"></ul>
            <div id="entry-detail" class="formEdit"></div>
        </section>
    </main>

    <script src="../js/dashboard.js"></script>
</body>
</html>
