<?php
/**
 * HOMEPAGE.PHP - opravená a robustnější verze
 */

$today = date("Y-m-d");

// Bezpečné defaulty
$schedulerData = is_array($schedulerData ?? null) ? $schedulerData : [];
$activeStudents = is_array($activeStudents ?? null) ? $activeStudents : [];
$consultationStatYear = is_array($consultationStatYear ?? null) ? $consultationStatYear : [];

// --- Příprava dat pro consultations ---
$allDates = [];
$allTimes = [];
$matrix = [];

foreach ($schedulerData as $entry) {
    $date = $entry["date"] ?? "";
    $time = $entry["time"] ?? "";

    if ($date >= $today && $date !== "" && $time !== "") {
        $allDates[$date] = true;
        $allTimes[$time] = true;
        $matrix[$time][$date][] = [
            "name" => $entry["name"] ?? "",
            "form" => $entry["form"] ?? "",
            "id"   => $entry["id"] ?? null,
        ];
    }
}

$dates = array_keys($allDates);
sort($dates);

$utilizationDates = $dates;
$dates = array_slice($dates, 0, 5);

$times = array_keys($allTimes);
sort($times);

$activeMap = [];
foreach ($activeStudents as $student) {
    if (!empty($student["name"]) && isset($student["id"])) {
        $activeMap[$student["name"]] = $student["id"];
    }
}

// Výpočet workloadi
$totalSlots = 0;
$bookedSlots = 0;

foreach ($times as $time) {
    foreach ($utilizationDates as $date) {
        if (!empty($matrix[$time][$date])) $totalSlots++;
        if (!empty($matrix[$time][$date])) {
            $validNames = array_filter($matrix[$time][$date], function ($entry) {
                return trim((string)($entry["name"] ?? "")) !== "";
            });
            if (count($validNames) > 0) {
                $bookedSlots++;
            }
        }
    }
}

$utilization = $totalSlots > 0 ? round(($bookedSlots / $totalSlots) * 100) : 0;

// --- Overview deníčků PhD ---

$phdDiaryCards = [];
$diaryError = null;
$sql = "
SELECT
  s.id          AS student_id,
  s.name        AS student_name,
  le.entry_id, le.week_start, le.teacher_feedback, le.notes_to_teacher
FROM students s
INNER JOIN student_teacher st
  ON st.student_id = s.id
  AND st.teacher_id = ?
LEFT JOIN (
  SELECT de.id AS entry_id,
         de.student_id,
         de.week_start, de.teacher_feedback, de.notes_to_teacher
  FROM diary_entries de
  INNER JOIN (
    SELECT student_id, MAX(created_at) AS max_created
    FROM diary_entries
    WHERE visible_to_teacher = 1
    GROUP BY student_id
  ) m ON m.student_id = de.student_id AND m.max_created = de.created_at
  WHERE de.visible_to_teacher = 1
) AS le
  ON le.student_id = s.id
WHERE s.degree = 'phd'
  AND s.active = 1
ORDER BY s.name
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$teacher_id]);
    $phdDiaryCards = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Students diary summary: ' . $e->getMessage());
    $diaryError = 'Diaries se nepodařilo načíst.';
}

?>

<div class="subject-tabs actions">
    <button class="goToSec button-link" data-jump="secConsultations">📅 Consultations</button>
    <button class="goToSec button-link" data-jump="secDiaries">📔 Diaries</button>
    <button class="goToSec button-link" data-jump="secPresentations">🎤 Prezentace</button>
    <button class="goToSec button-link" data-jump="secMessages">📩 Úkoly</button>
    <button class="goToSec button-link" data-jump="secAdhoc">🟠 Ad hoc</button>
    <button class="goToSec button-link" data-jump="secStats">📊 Statistiky</button>
</div>

<section class="standardCard" id="secConsultations">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap"><h2 class="text-font-bold" style="margin:0">Plán consultations</h2><a class="button-link primary" href="/konzultace/manage.php">⚙ Spravovat dates</a></div>

    <div class="table-responsive">
        <table class="schedulerTable code-font">
            <thead>
                <tr>
                    <th>Time</th>
                    <?php foreach ($dates as $date): ?>
                        <th><?= date("d.m.", strtotime($date)) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($times) && !empty($dates)): ?>
                    <?php foreach ($times as $time): ?>
                        <tr>
                            <td class="text-font-bold"><?= htmlspecialchars($time) ?></td>
                            <?php foreach ($dates as $date): ?>
                                <td>
                                    <?php if (!empty($matrix[$time][$date])): ?>
                                        <?php foreach ($matrix[$time][$date] as $entry): ?>
                                            <?php
                                            $fullName = trim((string)($entry["name"] ?? ""));
                                            $cancelId = $entry["id"] ?? null;
                                            $form = strtolower(trim((string)($entry["form"] ?? "")));

                                            if ($fullName === "") {
                                                $surname = "–";
                                            } else {
                                                $nameParts = preg_split('/\s+/', $fullName);
                                                $surname = htmlspecialchars($nameParts[0] ?? "");
                                            }

                                            $icon = "";
                                            if ($form === "zoom") {
                                                $icon = " 🔵 Zoom";
                                            } elseif ($form === "teams") {
                                                $icon = " 🟣 Teams";
                                            }
                                            ?>
                                            <div class="consultation-entry">
                                                <?php if ($surname !== "–" && !empty($cancelId)): ?>
                                                    <form method="post" action="/studevid/cancel-consultation.php" onsubmit="return confirm('Cancel tuto konzultaci?');" style="display:inline">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="id" value="<?= (int)$cancelId ?>">
                                                        <button type="submit" title="Cancel">🚫</button>
                                                    </form>
                                                <?php endif; ?>

                                                <?php if ($fullName !== "" && isset($activeMap[$fullName])): ?>
                                                    <a href="?student_id=<?= urlencode((string)$activeMap[$fullName]) ?>" class="text-font-bold">
                                                        <?= $surname ?>
                                                    </a><?= $icon ?>
                                                <?php else: ?>
                                                    <?= $surname . $icon ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= max(2, count($dates) + 1) ?>" class="centered muted">
                            No budoucí consultations.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="centered muted code-font" style="margin-top: 1rem;">
        Vytíženost dates: <?= $utilization ?> %
    </div>
</section>

<section class="standardCard" id="secDiaries">
    <h2 class="text-font-bold">Studijní deníčky (PhD)</h2>

    <?php if ($diaryError): ?>
        <div class="alertBox"><?= htmlspecialchars($diaryError) ?></div>
    <?php elseif (empty($phdDiaryCards)): ?>
        <div class="centered muted">Žádní active PhD students nebo zatím nemají odeslaný deníček.</div>
    <?php else: ?>
        <div class="info-grid">
            <?php foreach ($phdDiaryCards as $row): ?>
                <?php
                $studentName = $row["student_name"] ?? "Bez jména";
                $hasEntry = !empty($row['entry_id']);
                $waiting = $hasEntry && trim((string)($row['teacher_feedback'] ?? '')) === '';
                ?>
                <a class="info-item" style="text-decoration:none; border-left:4px solid <?= $waiting ? 'var(--alert-color)' : 'var(--success-color)' ?>;" href="diaries/dashboard_teacher.php?student_id=<?= (int)$row['student_id'] ?><?= $hasEntry ? '&entry_id=' . (int)$row['entry_id'] : '' ?>">
                    <strong><?= htmlspecialchars($studentName) ?></strong><br>
                    <?php if ($hasEntry): ?><span class="muted code-font">Deník z <?= htmlspecialchars(date('j. n. Y', strtotime($row['week_start']))) ?></span><br><?php endif; ?>
                    <span><?= $waiting ? '⚠ čeká na odpověď' : ($hasEntry ? '✓ zodpovězeno' : '○ bez odeslaného deníčku') ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="centered" style="margin-top: 2rem;">
        <a href="diaries/dashboard_teacher.php" class="button-link">👨🏻‍🏫 Kompletní management deníčků</a>
    </div>
</section>

<section class="standardCard" id="secAdhoc">
    <style>.adhoc-status-dot{display:inline-block;width:.7rem;height:.7rem;border-radius:50%;margin-right:.35rem}.adhoc-status-dot.unresolved{background:#c62828}.adhoc-status-dot.ongoing{background:#f9a825}.adhoc-status-dot.resolved{background:#2e7d32}</style>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap"><h2 class="text-font-bold" style="margin:0">Ad hoc consultations</h2><div><a class="button-link primary" href="adhoc/#new-consultation">＋ Rychle add konzultaci</a> <a class="button-link" href="adhoc/">Management ad hoc consultations</a></div></div>
    <?php if (empty($adhocStudentsSummary)): ?><p class="muted">No none recorded no ad hoc consultations.</p><?php else: ?><div class="info-grid" style="margin-top:1rem"><?php foreach ($adhocStudentsSummary as $adhoc): $statusClass=$adhoc['status']??'unresolved'; ?><a class="info-item" style="text-decoration:none" href="adhoc/?student_id=<?= (int)$adhoc['id'] ?>"><span class="adhoc-status-dot <?= h($statusClass) ?>"></span><strong><?= h($adhoc['name']) ?></strong><br><span class="muted"><?= h($adhoc['university'] ?: 'University not specified') ?> · <?= (int)$adhoc['consultation_count'] ?> consultations</span></a><?php endforeach; ?></div><?php endif; ?>
</section>

<section class="standardCard" id="secStats">
    <h2 class="text-font-bold">Statistics and workload</h2>

    <div class="chart-flex" style="display: flex; flex-wrap: wrap; gap: 2rem;">
        <div style="flex: 1; min-width: 300px;">
            <h3 class="centered">Monthly overview</h3>
            <div style="position: relative; height: 300px;">
                <canvas id="consultationChart"></canvas>
            </div>
        </div>

        <div style="flex: 1; min-width: 300px;">
            <h3 class="centered">Most and least consulted students</h3>
            <div style="position: relative; height: 300px;"><canvas id="consultationRankingChart"></canvas></div>
        </div>
    </div>
</section>

<?php
$presentationData = [];
$presentationError = null;

if (isset($pdo)) {
    try {
    $stmt = $pdo->query("
        SELECT s.id AS subject_id, s.name AS subject_name, s.slots_per_date,
               d.id AS date_id, d.presentation_date,
               r.id AS pres_id, r.slot_number, r.presenter, r.responsible_name, r.email
        FROM presentation_subjects s
        INNER JOIN presentation_event_dates d
          ON d.subject_id = s.id
         AND d.presentation_date >= CURRENT_DATE AND d.is_active=1
        LEFT JOIN presentation_registrations r ON r.date_id = d.id AND r.status='active'
        WHERE s.is_active = 1 AND s.slots_per_date BETWEEN 1 AND 100
        ORDER BY s.name, d.presentation_date DESC, r.slot_number
    ");

    if ($stmt) {
        $presentationRows = $stmt->fetchAll();

        foreach ($presentationRows as $r) {
            $sid = $r["subject_id"];

            if (!isset($presentationData[$sid])) {
                $presentationData[$sid] = [
                    "name" => $r["subject_name"],
                    "slots_per_date" => $r["slots_per_date"],
                    "years" => [],
                ];
            }

            $did = $r["date_id"];

            if ($did) {
                $year = date("Y", strtotime($r["presentation_date"]));

                if (!isset($presentationData[$sid]["years"][$year])) {
                    $presentationData[$sid]["years"][$year] = [];
                }

                if (!isset($presentationData[$sid]["years"][$year][$did])) {
                    $presentationData[$sid]["years"][$year][$did] = [
                        "date" => $r["presentation_date"],
                        "presentations" => [],
                    ];
                }

                if (!empty($r["pres_id"])) {
                    $presentationData[$sid]["years"][$year][$did]["presentations"][] = [
                        "id" => $r["pres_id"],
                        "slot" => $r["slot_number"],
                        "presenter" => $r["presenter"],
                        "responsible_name" => $r["responsible_name"],
                        "email" => $r["email"],
                    ];
                }
            }
        }

        foreach ($presentationData as $sid => $subjectData) {
            if (!empty($presentationData[$sid]["years"])) {
                krsort($presentationData[$sid]["years"], SORT_NUMERIC);
            }
        }
        }
    } catch (PDOException $e) {
        $presentationError = 'Overview prezentací není dostupný. Zkontrolujte instalaci sql/presentations_reset.sql.';
        error_log('Students presentations summary: ' . $e->getMessage());
    }
}
?>

<section class="standardCard" id="secPresentations">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap"><h2 class="text-font-bold" style="margin:0">Overview prezentací</h2><a class="button-link primary" href="presentations/manage.php">⚙ Management prezentačních dates</a></div>

    <?php if ($presentationError): ?>
        <div class="alertBox"><?= htmlspecialchars($presentationError) ?></div>
    <?php elseif (empty($presentationData)): ?>
        <div class="centered muted">Prezentace nejsou available.</div>
    <?php else: ?>
        <div class="presentation-accordion">
            <?php foreach ($presentationData as $sid => $s): ?>
                <details class="presentation-subject">
                    <summary class="presentation-subject-summary">
                        <span><strong><?= htmlspecialchars($s["name"] ?? "Subject") ?></strong></span>
                        <span class="muted code-font">
                            <?= count($s["years"] ?? []) ?> roků
                        </span>
                    </summary>

                    <div class="presentation-subject-body">
                        <?php if (empty($s["years"])): ?>
                            <div class="muted">Žádná data.</div>
                        <?php else: ?>
                            <?php foreach (($s["years"] ?? []) as $year => $datesInYear): ?>
                                <details class="presentation-year">
                                    <summary class="presentation-year-summary">
                                        <span><strong>Rok <?= htmlspecialchars((string)$year) ?></strong></span>
                                        <span class="muted code-font">
                                            <?= count($datesInYear) ?> dates
                                        </span>
                                    </summary>

                                    <div class="presentation-year-body">
                                        <?php foreach ($datesInYear as $did => $d): ?>
                                            <?php
                                            $presentations = is_array($d["presentations"] ?? null) ? $d["presentations"] : [];
                                            $emails = array_column($presentations, "email");
                                            $emails = array_filter($emails);
                                            $allEmails = implode(",", $emails);
                                            ?>
                                            <div class="date-block" style="margin-bottom: 2rem; border-top: 1px solid #eee; padding-top: 1rem;">
                                                <h3 class="copy-all" data-emails="<?= htmlspecialchars($allEmails) ?>" style="cursor:pointer;">
                                                    📅 <?= !empty($d["date"]) ? date("j. n. Y", strtotime($d["date"])) : "Bez data" ?>
                                                    <small>(kopírovat e-maily 📋)</small>
                                                </h3>

                                                <div class="table-responsive">
                                                    <table class="schedulerTable code-font">
                                                        <thead>
                                                            <tr>
                                                                <th>Slot</th>
                                                                <th>Kdo vystoupí</th>
                                                                <th>Email</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php for ($i = 1; $i <= (int)($s["slots_per_date"] ?? 0); $i++): ?>
                                                                <?php
                                                                $found = null;
                                                                foreach ($presentations as $p) {
                                                                    if ((int)($p["slot"] ?? 0) === $i) {
                                                                        $found = $p;
                                                                        break;
                                                                    }
                                                                }
                                                                ?>
                                                                <tr>
                                                                    <td><?= $i ?></td>
                                                                    <?php if ($found): ?>
                                                                        <td><?= htmlspecialchars($found["presenter"] ?? "") ?></td>
                                                                        <td>
                                                                            <span class="copy-email" data-email="<?= htmlspecialchars($found["email"] ?? "") ?>" style="cursor:pointer;">
                                                                                <?= htmlspecialchars($found["email"] ?? "") ?> 📋
                                                                            </span>
                                                                        </td>
                                                                        <td>
                                                                            <form method="post" action="scheduler/teacher_delete.php" onsubmit="return confirm('Opravdu odhlásit?');">
                <?= csrf_field() ?>
                                                                                <input type="hidden" name="id" value="<?= (int)($found["id"] ?? 0) ?>">
                                                                                <button type="submit" class="red" style="background:none; border:none; cursor:pointer;">❌ Sign out</button>
                                                                            </form>
                                                                        </td>
                                                                    <?php else: ?>
                                                                        <td colspan="3" class="muted"><em>Volný slot</em></td>
                                                                    <?php endif; ?>
                                                                </tr>
                                                            <?php endfor; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="standardCard" id="secMessages">
    <h2 class="text-font-bold">Latest úkoly z EL</h2>

    <div class="table-responsive">
        <table class="messageTable code-font">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Příjmení</th>
                    <th>Zadání</th>
                    <th>Zpráva</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($taskMessages)): ?>
                    <?php foreach ($taskMessages as $row): ?>
                        <tr>
                            <td><?= !empty($row["created_at"]) ? date("j. n.", strtotime($row["created_at"])) : "—" ?></td>
                            <td class="text-font-bold"><?= htmlspecialchars($row["surname"] ?: "—") ?></td>
                            <td><small><?= htmlspecialchars($row["form_slug"] ?? "") ?></small></td>
                            <?php $messagePreview = trim((string)($row["message"] ?? '')); $messagePreview = preg_replace('/\s+/u', ' ', $messagePreview) ?? $messagePreview; $messagePreview = mb_strimwidth($messagePreview, 0, 180, '…', 'UTF-8'); ?>
                            <td class="message-body" title="<?= htmlspecialchars((string)($row["message"] ?? ""), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:36rem;"><?= htmlspecialchars($messagePreview, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="centered muted"><?= $messagesUnavailable ? 'Messages z e-learningu nejsou dostupné.' : 'No úkoly k zobrazení.' ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="form-actions form-actions-centered" style="margin-top:1rem;">
        <a class="button-link primary" href="elearning/">Spravovat e-learningové zprávy, tokeny a subjecty</a>
    </p>
</section>

<script>
$(document).ready(function () {
    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        }
    };

    const consultationData = <?= json_encode($consultationStatYear) ?>;
    const adhocData = <?= json_encode($adhocConsultationStatYear ?? []) ?>;
    const chart1El = document.getElementById("consultationChart");
    if (chart1El && typeof Chart !== "undefined") {
        const ctx1 = chart1El.getContext("2d");
        const keys = [...new Set(consultationData.concat(adhocData).map(e => `${e.year}-${e.month}`))].sort((a, b) => {
            const [ay, am] = a.split('-').map(Number); const [by, bm] = b.split('-').map(Number);
            return ay - by || am - bm;
        });
        const regularByMonth = Object.fromEntries(consultationData.map(e => [`${e.year}-${e.month}`, Number(e.consultation_count)]));
        const adhocByMonth = Object.fromEntries(adhocData.map(e => [`${e.year}-${e.month}`, Number(e.consultation_count)]));
        new Chart(ctx1, {
            type: "line",
            data: {
                labels: keys.map(k => { const [y,m] = k.split('-'); return `${m}/${y}`; }),
                datasets: [{
                    label: "Count consultations",
                    data: keys.map(k => regularByMonth[k] || 0),
                    borderColor: "#d8071f",
                    tension: 0.3,
                    fill: false
                }, { label: "Ad hoc consultations", data: keys.map(k => adhocByMonth[k] || 0), borderColor: "#1769aa", tension: 0.3, fill: false }]
            },
            options: chartOptions
        });
    }

    const ranking = <?= json_encode(array_values($consultationRanking ?? []), JSON_UNESCAPED_UNICODE) ?>;
    const rankingEl = document.getElementById("consultationRankingChart");
    if (rankingEl && typeof Chart !== "undefined") {
        const top = ranking.slice(0, 5), bottom = ranking.slice(-5);
        const rows = top.map(r => ({...r, group: 'top'})).concat(bottom.filter(b => !top.some(t => t.id === b.id)).map(r => ({...r, group: 'bottom'})));
        new Chart(rankingEl.getContext("2d"), { type: "bar", data: { labels: rows.map(r => `${r.name} (${Number(r.consultation_rate).toFixed(2)}/měs.)`), datasets: [{ label: "Consultations za měsíc od první consultations", data: rows.map(r => Number(r.consultation_rate)), backgroundColor: rows.map(r => r.group === 'top' ? "#1769aa" : "#c62828") }] }, options: Object.assign({}, chartOptions, { indexAxis: 'y', scales: { x: { beginAtZero: true, ticks: { precision: 2 } } } }) });
    }

    $('.copy-email, .copy-all').on('click', function () {
        var val = $(this).data('email') || $(this).data('emails');
        if (!val) return;

        navigator.clipboard.writeText(val).then(function () {
            alert('Zkopírováno do schránky.');
        }).catch(function () {
            alert('Kopírování selhalo.');
        });
    });

    $(".goToSec").on("click", function(e) {
        e.preventDefault();
        const target = $("#" + $(this).data("jump"));
        if (target.length) {
            $('html, body').animate({ scrollTop: target.offset().top - 80 }, 500);
        }
    });
});
</script>
