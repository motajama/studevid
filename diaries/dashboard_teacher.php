<?php
ob_start();
require_once dirname(__DIR__) . '/_private/bootstrap.php';

page_role('teacher');
require_once __DIR__ . "/db.php";

if (!isset($_SESSION["teacher_id"])) {
    header("Location: /studevid/login.php");
    exit();
}

$teacher_id = $_SESSION["teacher_id"];

$stmt = $pdo->prepare("
    SELECT s.id, s.name
    FROM students s
    JOIN student_teacher st ON s.id = st.student_id
    WHERE s.active = 1 AND s.degree = 'phd' AND st.teacher_id = ?
    ORDER BY s.name
");
$stmt->execute([$teacher_id]);
$students = $stmt->fetchAll();

if (!$students) {
    app_error(404, 'K tomuto teacherskému účtu nejsou přiřazeni žádní studenti.');
}

$selected_student_id = isset($_GET['student_id']) ? input_id($_GET, 'student_id') : (int)$students[0]['id'];
require_student_access($pdo, $selected_student_id);
if (!$selected_student_id) {
    app_error(404, 'Not available no student, kterého by bylo možné zobrazit.');
}

$entry_id = isset($_GET['entry_id']) ? input_id($_GET, 'entry_id') : 0;
$stmt = $entry_id
    ? $pdo->prepare("SELECT * FROM diary_entries WHERE id = ? AND student_id = ? AND visible_to_teacher = 1")
    : $pdo->prepare("SELECT * FROM diary_entries WHERE student_id = ? AND visible_to_teacher = 1 ORDER BY week_start DESC, id DESC LIMIT 1");
$stmt->execute($entry_id ? [$entry_id, $selected_student_id] : [$selected_student_id]);
$last_entry = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM publications WHERE student_id = ?");
$stmt->execute([$selected_student_id]);
$publications = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT name FROM teachers WHERE id = ?");
$stmt->execute([$teacher_id]);
$name = $stmt->fetchColumn();

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
    <title>[ <?= htmlspecialchars($name ?? "Neznámý teacher") ?> | teacherský zápisník ]</title>
    <?php include dirname(__DIR__) . "/_private/views/header.php"; ?>

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
        #student-selector {
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
    <div class="main-container">
       <aside class="diary-sidebar">
    <div class="diary-sidebar-card">
        <div class="diary-sidebar-head">
            <h2 class="text-font-bold">Overview student</h2>
            <p class="muted code-font">Výběr student a časová osa zápisů</p>
        </div>

        <div class="diary-sidebar-section">
            <label for="student_id" class="code-font">Student</label>
            <form method="get" action="">
                <select name="student_id" id="student_id" onchange="this.form.submit()">
                    <?php foreach ($students as $student): ?>
                        <option value="<?= $student["id"] ?>" <?= $student["id"] == $selected_student_id ? "selected" : "" ?>>
                            <?= htmlspecialchars($student["name"]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div class="diary-sidebar-body">
            <h3 class="code-font muted">Timeline</h3>
            <ul id="timeline-menu" class="timeline-menu"></ul>
        </div>

        <div class="diary-sidebar-foot code-font">
            <a href="set_teacher_pass.php" class="button-link subtle">🔐 Change password</a>
            <a href="logout.php" class="button-link subtle">↩ Sign out</a>
        </div>
    </div>
</aside>

        <main>
            <section class="standardCard">
                <h2 class="text-font-bold">Task assignment</h2>
                <div id="last-task" class="infoBox">
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT tasks
                        FROM consultations
                        WHERE student_id = ?
                        ORDER BY date DESC
                        LIMIT 1
                    ");
                    $stmt->execute([$selected_student_id]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);

                    echo $row && !empty($row["tasks"])
                        ? nl2br(htmlspecialchars($row["tasks"]))
                        : "No úkol not found.";
                    ?>
                </div>
            </section>

            <section class="standardCard">
                <h2 class="text-font-bold">Publications</h2>
                <div id="pub-list">
                    <?php $pubSteps = ['not_started'=>0,'data_collection'=>20,'draft'=>40,'submitted'=>60,'review'=>80,'accepted'=>100,'printed'=>100]; foreach ($publications as $pub): $pct = $pubSteps[$pub['status'] ?? 'not_started'] ?? 0; ?>
                        <div class="formRow" style="border-bottom:1px solid #eee;padding-bottom:.8rem;margin-bottom:.8rem">
                            <strong><?= htmlspecialchars($pub["title"] ?: "(untitled)") ?></strong> <em class="muted"><?= htmlspecialchars($pub["type"] ?: "(bez typu)") ?></em>
                            <div style="height:8px;background:#e5e7eb;border-radius:5px;margin-top:.45rem"><div style="height:100%;width:<?= $pct ?>%;background:var(--success-color);border-radius:5px"></div></div>
                            <small class="muted">Stage: <?= htmlspecialchars($pub["status"] ?? "not specified") ?> · <?= $pct ?> %</small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="standardCard">
                <h2 class="text-font-bold">Latest zápis</h2>
                <div id="entry-detail">
                    <?php if ($last_entry): ?>
                        <fieldset>
                            <legend><?= htmlspecialchars($last_entry["week_start"]) ?></legend>

                            <?php if ($last_entry["visible_to_teacher"] == 0): ?>
                                <div class="alertBox">Tento záznam byl označen jako soukromý.</div>
                            <?php endif; ?>

                            <p><strong>Co dělal/a:</strong><br><?= nl2br(htmlspecialchars($last_entry["what_did_you_do"])) ?></p>
                            <?php if (trim((string)($last_entry["what_did_it_give_me"] ?? '')) !== ''): ?><p><strong>Co mu/jí to dalo:</strong><br><?= nl2br(htmlspecialchars($last_entry["what_did_it_give_me"])) ?></p><?php endif; ?>
                            <?php if (trim((string)($last_entry["doubts_or_questions"] ?? '')) !== ''): ?><p><strong>Otázky:</strong><br><?= nl2br(htmlspecialchars($last_entry["doubts_or_questions"])) ?></p><?php endif; ?>
                            <?php if (trim((string)($last_entry["task_reflection"] ?? '')) !== ''): ?><p><strong>Reflexe na úkol:</strong><br><?= nl2br(htmlspecialchars($last_entry["task_reflection"])) ?></p><?php endif; ?>
                            <?php if (trim((string)($last_entry["publications_reflection"] ?? '')) !== ''): ?><p><strong>Reflexe na publikace:</strong><br><?= nl2br(htmlspecialchars($last_entry["publications_reflection"])) ?></p><?php endif; ?>
                            <?php if (!empty($last_entry['notes_to_teacher']) && !empty($last_entry['private_notes'])): ?>
                                <p><strong>Notes k práci v týdnu (odeslané student):</strong><br><?= nl2br(htmlspecialchars($last_entry['private_notes'])) ?></p>
                            <?php endif; ?>

                            <form class="feedback-form">
                                <input type="hidden" name="entry_id" value="<?= $last_entry["id"] ?>">
                                <label for="teacher_feedback"><strong>Feedback teachere:</strong></label><br>
                                <textarea name="teacher_feedback" rows="5" style="width: 100%;"><?= htmlspecialchars($last_entry["teacher_feedback"] ?? "") ?></textarea><br>
                                <button type="submit">Save feedback</button>
                                <div class="feedback-status" style="margin-top: 0.5em;"></div>
                            </form>
                        </fieldset>
                    <?php else: ?>
                        <p>No záznam available.</p>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <script>
    const studentId = <?= (int) $selected_student_id ?>;

    $(function () {
        $.getJSON("get_diary_timeline.php", { student_id: studentId }, function (data) {
            const grouped = {};
            data.forEach((entry) => {
                const date = new Date(entry.week_start);
                const year = date.getFullYear();
                const month = date.toLocaleString("cs-CZ", { month: "long" });
                const day = date.getDate();

                if (!grouped[year]) grouped[year] = {};
                if (!grouped[year][month]) grouped[year][month] = [];

                grouped[year][month].push({
                    id: entry.id,
                    label: `${day}. ${month}`,
                    isPrivate: entry.visible_to_teacher === "0" || entry.visible_to_teacher === 0,
                });
            });

            let html = "";
            const sortedYears = Object.keys(grouped).map(Number).sort((a, b) => b - a);

            sortedYears.forEach((year) => {
                html += `<li><strong class="tl-year">${year}</strong><div class="accordion">`;

                for (const month in grouped[year]) {
                    html += `<h4>${month}</h4><div><ul>`;
                    grouped[year][month].forEach((item) => {
                        html += `<li><a href="#" class="entry-link" data-id="${item.id}" data-private="${item.isPrivate}">${item.label}${item.isPrivate ? " 🕵️‍♂️" : ""}</a></li>`;
                    });
                    html += `</ul></div>`;
                }

                html += `</div></li>`;
            });

            $("#timeline-menu").html(html);

            $(".accordion").accordion({
                collapsible: true,
                heightStyle: "content",
                active: false,
            });
        });

        $(document).on("click", ".entry-link", function (e) {
            e.preventDefault();
            const entryId = $(this).data("id");

            $.getJSON("get_diary_entry.php", { id: entryId }, function (entry) {
                if (!entry || entry.error) {
                    $("#entry-detail").html(`<p class="red">Záznam was not found.</p>`);
                    return;
                }

                const isPrivate = entry.visible_to_teacher == "0";
                const legendText = `${escapeHtml(entry.week_start)}${isPrivate ? " (rozpracovaný)" : ""}`;

                const detail = `
                    <fieldset><legend>${legendText}</legend>
                    ${isPrivate ? '<div class="alertBox">Tento záznam je zatím neveřejný a teacher ho nevidí.</div>' : ""}
                    <p><strong>Co dělal/a:</strong><br>${escapeHtml(entry.what_did_you_do)}</p>
                    ${entry.what_did_it_give_me && entry.what_did_it_give_me.trim() ? `<p><strong>Co mu/jí to dalo:</strong><br>${escapeHtml(entry.what_did_it_give_me)}</p>` : ""}
                    ${entry.doubts_or_questions && entry.doubts_or_questions.trim() ? `<p><strong>Otázky:</strong><br>${escapeHtml(entry.doubts_or_questions)}</p>` : ""}
                    ${entry.task_reflection && entry.task_reflection.trim() ? `<p><strong>Reflexe na úkol:</strong><br>${escapeHtml(entry.task_reflection)}</p>` : ""}
                    ${entry.publications_reflection && entry.publications_reflection.trim() ? `<p><strong>Reflexe na publikace:</strong><br>${escapeHtml(entry.publications_reflection)}</p>` : ""}
                    ${entry.notes_to_teacher == "1" && entry.private_notes ? `<p><strong>Notes k práci v týdnu (odeslané student):</strong><br>${escapeHtml(entry.private_notes)}</p>` : ""}
                    <form class='feedback-form'>
                        <input type='hidden' name='entry_id' value='${escapeHtml(entry.id)}'>
                        <label for='teacher_feedback'><strong>Feedback teachere:</strong></label><br>
                        <textarea name='teacher_feedback' rows='5' style='width:100%'>${escapeHtml(entry.teacher_feedback)}</textarea><br>
                        <button type='submit'>Save feedback</button>
                        <div class='feedback-status' style='margin-top: 0.5em;'></div>
                    </form>
                    </fieldset>
                `;

                $("#entry-detail").html(detail);
            });
        });
    });

    $(document).on("submit", ".feedback-form", function (e) {
        e.preventDefault();
        const $form = $(this);
        const data = $form.serialize();

        $.post("submit_feedback.php", data, function (response) {
            if (response.success) {
                $form.find(".feedback-status").html(`<span style="color: green;">✔ ${response.message}</span>`);
            } else {
                $form.find(".feedback-status").html(`<span style="color: red;">✖ ${response.message}</span>`);
            }
        }, "json").fail(function () {
            $form.find(".feedback-status").html(`<span style="color: red;">✖ Error při odesílání request.</span>`);
        });
    });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
