<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_private/bootstrap.php';
page_role('teacher');
require_once dirname(__DIR__) . '/_private/database.php';

$tokenSchemaError = null;
$flash = [];
$flashToken = null;
$today = new DateTimeImmutable('today');
$editSubject = null;

function elearning_token_words(): array {
    return ['atelier','akord','balet','barva','bas','buben','cech','cinema','cirkus','dekor','dilo','disko','drama','duna','echo','film','forma','freska','gesto','grafik','harfa','hledi','inkoust','jeviste','kamera','kulis','loutka','maska','melodie','mural','nota','obraz','opera','paleta','papir','partitura','plakat','plano','plot','poema','porta','prizma','ritmus','refren','scena','skica','sonda','sonet','souzvuk','sperk','spona','statista','stetec','strih','svetlo','takt','tango','teze','text','tichy','tisk','ton','tvar','ulice','umeni','vitr','vzor','zaves','zvuk','zupan','luna','kometa','runa','orbit','spona','vesmir','jiskra'];
}

function elearning_token(string $year, string $subject, string $label): string {
    $clean = static function (string $value, string $fallback): string {
        $value = strtoupper((string)(preg_replace('/[^A-Za-z0-9]+/', '', $value) ?? ''));
        return $value !== '' ? substr($value, 0, 16) : $fallback;
    };
    $words = elearning_token_words();
    $parts = [];
    for ($i = 0; $i < 4; $i++) $parts[] = $words[random_int(0, count($words) - 1)];
    return $clean($year, date('Y')) . '-' . $clean($subject, 'EL') . '-' . $clean($label, 'REC') . '-' . implode('-', $parts);
}

try {
    $pdo->query('SELECT 1 FROM `e-learning-tokens` LIMIT 1');
} catch (PDOException $e) {
    $tokenSchemaError = 'Management tokenů vyžaduje jednorázově spustit sql/elearning_management.sql.';
    error_log('Students e-learning token schema: ' . $e->getMessage());
}

if (isset($_GET['edit_subject'])) {
    $stmt = $pdo->prepare('SELECT id,subject_acronym,year,semester,firstweek,start_year,deadline_day FROM `e-learning-subjects` WHERE id=?');
    $stmt->execute([input_id($_GET, 'edit_subject')]);
    $editSubject = $stmt->fetch();
    if (!$editSubject) app_error(404, 'Subject was not found.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post();
    $action = input_text($_POST, 'action');
    try {
        if ($action === 'delete_message') {
            $stmt = $pdo->prepare('DELETE FROM `e-learning-messages` WHERE id=?');
            $stmt->execute([input_id($_POST, 'id')]);
            $flash[] = 'Zpráva byla odstraněna.';
        } elseif ($action === 'save_subject') {
            $id = (int)($_POST['id'] ?? 0);
            $acronym = input_text($_POST, 'subject_acronym');
            $year = filter_var($_POST['year'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>2000,'max_range'=>2200]]);
            $semester = input_text($_POST, 'semester');
            $firstweek = filter_var($_POST['firstweek'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>53]]);
            $startYear = filter_var($_POST['start_year'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>2000,'max_range'=>2200]]);
            $deadlineDay = filter_var($_POST['deadline_day'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0,'max_range'=>31]]);
            if ($acronym === '' || mb_strlen($acronym) > 64 || $year === false || $firstweek === false || $startYear === false || $deadlineDay === false || !in_array($semester, ['ZS','LS','FALL','SPRING'], true)) {
                app_error(422, 'Please fill in platné údaje subjectu.');
            }
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE `e-learning-subjects` SET subject_acronym=?,year=?,semester=?,firstweek=?,start_year=?,deadline_day=? WHERE id=?');
                $stmt->execute([$acronym,$year,$semester,$firstweek,$startYear,$deadlineDay,$id]);
                $flash[] = 'Subject byl upraven.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO `e-learning-subjects` (subject_acronym,year,semester,firstweek,start_year,deadline_day) VALUES (?,?,?,?,?,?)');
                $stmt->execute([$acronym,$year,$semester,$firstweek,$startYear,$deadlineDay]);
                $flash[] = 'Subject byl přidán.';
            }
        } elseif ($action === 'delete_subject') {
            $stmt = $pdo->prepare('DELETE FROM `e-learning-subjects` WHERE id=?');
            $stmt->execute([input_id($_POST, 'id')]);
            $flash[] = 'Subject byl odstraněn.';
        } elseif ($action === 'save_student') {
            $id = (int)($_POST['id'] ?? 0);
            $surname = input_text($_POST, 'surname');
            $subject = input_text($_POST, 'subject_id');
            $year = filter_var($_POST['student_year'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>2000,'max_range'=>2200]]);
            if ($surname === '' || mb_strlen($surname) > 128 || $subject === '' || mb_strlen($subject) > 64 || $year === false) app_error(422, 'Please fill in platné údaje student.');
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE `e-learning-students` SET surname=?,subject_id=?,year=? WHERE id=?');
                $stmt->execute([$surname,$subject,$year,$id]);
                $flash[] = 'Students byl upraven.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO `e-learning-students` (surname,subject_id,year) VALUES (?,?,?)');
                $stmt->execute([$surname,$subject,$year]);
                $flash[] = 'Students byl přidán.';
            }
        } elseif ($action === 'create_token') {
            if ($tokenSchemaError) app_error(503, $tokenSchemaError);
            $studentId = input_id($_POST, 'student_id');
            $label = input_text($_POST, 'label');
            $label = $label !== '' ? $label : 'REC';
            $expires = input_text($_POST, 'expires_at');
            $expiresValue = null;
            if ($expires !== '') {
                $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $expires);
                if (!$parsed || $parsed->format('Y-m-d') !== $expires) app_error(422, 'Date expirace tokenu není platné.');
                $expiresValue = $expires . ' 23:59:59';
            }
            $check = $pdo->prepare('SELECT surname,subject_id,year FROM `e-learning-students` WHERE id=?');
            $check->execute([$studentId]);
            $student = $check->fetch();
            if (!$student) app_error(404, 'Students was not found.');
            $rawToken = null;
            $stmt = $pdo->prepare('INSERT INTO `e-learning-tokens` (student_id,token_hash,label,expires_at) VALUES (?,?,?,?)');
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $candidate = elearning_token((string)$student['year'], (string)$student['subject_id'], $label);
                try {
                    $stmt->execute([$studentId, hash('sha256', $candidate), $label, $expiresValue]);
                    $rawToken = $candidate;
                    break;
                } catch (PDOException $tokenError) {
                    if (!str_contains(strtolower($tokenError->getMessage()), 'duplicate')) throw $tokenError;
                }
            }
            if ($rawToken === null) app_error(409, 'Nepodařilo se vytvořit jedinečný token. Zkuste to znovu.');
            $flashToken = $rawToken;
            $flash[] = 'Token byl vytvořen. Zkopírujte jej nyní; z bezpečnostních důvodů se později znovu nezobrazí.';
        } elseif ($action === 'revoke_token') {
            if ($tokenSchemaError) app_error(503, $tokenSchemaError);
            $stmt = $pdo->prepare('UPDATE `e-learning-tokens` SET revoked_at=NOW() WHERE id=? AND revoked_at IS NULL');
            $stmt->execute([input_id($_POST, 'id')]);
            $flash[] = 'Token byl zneplatněn.';
        } else {
            app_error(400, 'Unknown operation správy e-learningu.');
        }
    } catch (PDOException $e) {
        error_log('Students e-learning admin: ' . $e->getMessage());
        app_error(409, 'Změnu se nepodařilo save. Subject nebo jiný údaj může již existovat, případně databáze necontentuje potřebné tabulky.');
    }
}

$messages = $pdo->query("SELECT m.id,m.created_at,m.form_slug,m.message,COALESCE(s.surname,'—') AS surname,s.subject_id,s.year,COALESCE(es.subject_acronym,s.subject_id,'Nezařazené') AS subject_label FROM `e-learning-messages` m LEFT JOIN `e-learning-students` s ON s.id=m.student_id LEFT JOIN `e-learning-subjects` es ON es.subject_acronym=s.subject_id AND es.year=s.year ORDER BY m.created_at DESC,m.id DESC LIMIT 500")->fetchAll();
$messageTree = [];
foreach ($messages as $message) {
    $subjectKey = (string)($message['subject_label'] ?: 'Nezařazené') . '|' . (string)($message['year'] ?: '');
    $taskKey = (string)($message['form_slug'] ?: 'Bez zadání');
    $messageTree[$subjectKey]['label'] = (string)($message['subject_label'] ?: 'Nezařazené');
    $messageTree[$subjectKey]['year'] = (string)($message['year'] ?: '');
    $messageTree[$subjectKey]['tasks'][$taskKey][] = $message;
}
$subjects = $pdo->query('SELECT id,subject_acronym,year,semester,firstweek,start_year,deadline_day FROM `e-learning-subjects` ORDER BY year DESC,subject_acronym')->fetchAll();
$students = $pdo->query('SELECT id,surname,subject_id,year FROM `e-learning-students` ORDER BY surname,id')->fetchAll();
$studentTree = [];
foreach ($students as $student) {
    $studentKey = (string)$student['subject_id'] . '|' . (string)$student['year'];
    $studentTree[$studentKey]['subject'] = (string)$student['subject_id'];
    $studentTree[$studentKey]['year'] = (string)$student['year'];
    $studentTree[$studentKey]['students'][] = $student;
}
$tokens = [];
if (!$tokenSchemaError) {
    $tokens = $pdo->query('SELECT t.id,t.student_id,t.label,t.created_at,t.expires_at,t.revoked_at,s.surname,s.subject_id FROM `e-learning-tokens` t JOIN `e-learning-students` s ON s.id=t.student_id ORDER BY t.created_at DESC')->fetchAll();
}
$globalCssPath='/studevid/css/global.css'; $dashboardCssPath='/studevid/css/dashboard.css'; $extraCssFiles=[]; $loadJquery=true; $loadChartJs=false; $loadDesignJs=false;
?><!doctype html><html lang="en"><head><meta charset="utf-8"><title>E-learning management</title><?php require dirname(__DIR__).'/_private/views/header.php'; ?><style>
.student-tree { display:grid; gap:1rem; }
.student-tree .diary-item { margin:0; }
</style></head><body>
<main class="presentations-main"><section class="standardCard presentations-card"><p><a href="/studevid/">← Home page teachere</a></p><h1>E-learning management</h1><p>Management zpráv students, subjectů a přístupových tokenů.</p><nav class="subject-tabs actions" aria-label="Sekce e-learningu"><a class="button-link" href="#messages">📩 Messages</a><a class="button-link" href="#subjects">📚 Subjecty</a><a class="button-link" href="#students">👥 Students</a><a class="button-link" href="#issued-tokens">🔑 Tokens</a></nav>
<?php foreach($flash as $notice): ?><p class="ok-message"><?= h($notice) ?></p><?php endforeach; ?>
<?php if($flashToken): ?><p class="alertBox"><strong>New token:</strong><br><code><?= h($flashToken) ?></code></p><?php endif; ?>
<?php if($tokenSchemaError): ?><p class="alertBox"><?= h($tokenSchemaError) ?></p><?php endif; ?></section>

<section class="standardCard presentations-card" id="messages"><h2>Messages students</h2><p class="muted">Messages jsou seskupené podle subjectu a zadání. Větve jsou ve výchozím stavu sbalené.</p>
<?php if (!$messageTree): ?><p class="muted centered">No zprávy.</p><?php endif; ?>
<div class="diary-accordion">
<?php foreach($messageTree as $subjectBranch): $subjectMessages = []; foreach($subjectBranch['tasks'] as $taskMessages) $subjectMessages = array_merge($subjectMessages, $taskMessages); $subjectNewest = $subjectMessages[0]['created_at'] ?? null; ?>
    <details class="diary-item"><summary class="diary-summary"><span><strong><?= h($subjectBranch['label']) ?></strong><?= $subjectBranch['year'] !== '' ? ' · '.h($subjectBranch['year']) : '' ?></span><span class="muted"><?= count($subjectMessages) ?> zpráv · latest <?= $subjectNewest ? h(date('j. n. Y', strtotime($subjectNewest))) : '—' ?></span></summary><div class="diary-panel">
    <?php foreach($subjectBranch['tasks'] as $taskLabel => $taskMessages): $newest = $taskMessages[0]['created_at'] ?? null; ?>
        <details class="diary-item"><summary class="diary-summary"><span><strong><?= h($taskLabel) ?></strong></span><span class="muted"><?= count($taskMessages) ?> zpráv · latest <?= $newest ? h(date('j. n. Y', strtotime($newest))) : '—' ?></span></summary><div class="diary-panel"><table class="schedulerTable"><thead><tr><th>Date</th><th>Students</th><th>Zpráva</th><th>Actions</th></tr></thead><tbody>
        <?php foreach($taskMessages as $m): ?><tr><td><?= h(date('j. n. Y H:i',strtotime($m['created_at']))) ?></td><td><?= h($m['surname']) ?></td><td style="white-space:pre-wrap;word-break:break-word;"><?= nl2br(h($m['message'])) ?></td><td><form method="post" onsubmit="return confirm('Delete tuto zprávu?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete_message"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="button-link subtle" type="submit">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div></details>
    <?php endforeach; ?></div></details>
<?php endforeach; ?></div></section>

<section class="standardCard presentations-card" id="subjects"><h2>Subjecty</h2><form method="post" class="formEdit"><input type="hidden" name="action" value="save_subject"><input type="hidden" name="id" value="<?= (int)($editSubject['id'] ?? 0) ?>"><?= csrf_field() ?><div class="formRow"><label>Code <input name="subject_acronym" required maxlength="64" value="<?= h($editSubject['subject_acronym'] ?? '') ?>"></label><label>Rok <input name="year" type="number" min="2000" max="2200" value="<?= (int)($editSubject['year'] ?? date('Y')) ?>" required></label><label>Semestr <select name="semester"><option <?= (($editSubject['semester'] ?? 'ZS')==='ZS')?'selected':'' ?>>ZS</option><option <?= (($editSubject['semester'] ?? '')==='LS')?'selected':'' ?>>LS</option><option <?= (($editSubject['semester'] ?? '')==='FALL')?'selected':'' ?>>FALL</option><option <?= (($editSubject['semester'] ?? '')==='SPRING')?'selected':'' ?>>SPRING</option></select></label><label>1. týden <input name="firstweek" type="number" min="1" max="53" value="<?= (int)($editSubject['firstweek'] ?? 1) ?>" required></label><label>Začátek roku <input name="start_year" type="number" min="2000" max="2200" value="<?= (int)($editSubject['start_year'] ?? date('Y')) ?>" required></label><label>Den deadlinu <input name="deadline_day" type="number" min="0" max="31" value="<?= (int)($editSubject['deadline_day'] ?? 1) ?>" required></label></div><button class="button-link primary" type="submit"><?= $editSubject ? 'Save změny' : 'Add subject' ?></button><?php if($editSubject): ?> <a class="button-link subtle" href="./">Cancel úpravu</a><?php endif; ?></form><table class="schedulerTable"><thead><tr><th>Code</th><th>Rok</th><th>Semestr</th><th>Parametry</th><th>Actions</th></tr></thead><tbody><?php foreach($subjects as $s): ?><tr><td><?= h($s['subject_acronym']) ?></td><td><?= (int)$s['year'] ?></td><td><?= h($s['semester']) ?></td><td>týden <?= (int)$s['firstweek'] ?>, deadline <?= (int)$s['deadline_day'] ?></td><td><a class="button-link subtle" href="?edit_subject=<?= (int)$s['id'] ?>">Edit</a> <form method="post" onsubmit="return confirm('Delete subject?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete_subject"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="button-link subtle" type="submit">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></section>

<section class="standardCard presentations-card" id="students"><h2>Students a tokeny</h2><p class="muted">Students jsou seskupení podle subjectu a roku. Token má zapamatovatelný tvar například <code>2026-USOC-REC-gesto-luna-maska-buben</code> a zobrazí se pouze při vytvoření.</p><form method="post" class="formEdit"><input type="hidden" name="action" value="save_student"><?= csrf_field() ?><div class="formRow"><label>Příjmení <input name="surname" required maxlength="128"></label><label>Subject <input name="subject_id" required maxlength="64" placeholder="USOC"></label><label>Rok <input name="student_year" type="number" min="2000" max="2200" value="<?= date('Y') ?>" required></label></div><button class="button-link primary" type="submit">Add student</button></form>
<?php if(!$studentTree): ?><p class="muted">Žádní students.</p><?php endif; ?><div class="student-tree">
<?php foreach($studentTree as $branch): ?><details class="diary-item"><summary class="diary-summary"><span><strong><?= h($branch['subject']) ?></strong> · <?= h($branch['year']) ?></span><span class="muted"><?= count($branch['students']) ?> students</span></summary><div class="diary-panel"><table class="schedulerTable"><thead><tr><th>Students</th><th>Token</th></tr></thead><tbody>
<?php foreach($branch['students'] as $student): ?><tr><td><?= h($student['surname']) ?></td><td><?php if(!$tokenSchemaError): ?><form method="post" style="display:inline-flex;gap:.4rem;align-items:center;flex-wrap:wrap"><?= csrf_field() ?><input type="hidden" name="action" value="create_token"><input type="hidden" name="student_id" value="<?= (int)$student['id'] ?>"><input name="label" maxlength="128" placeholder="REC / zadání"><input name="expires_at" type="date"><button class="button-link subtle" type="submit">Vydat token</button></form><?php else: ?><span class="muted">Nejdříve vytvořte tabulku tokenů.</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></details><?php endforeach; ?></div></section>

<?php if(!$tokenSchemaError): ?><section class="standardCard presentations-card" id="issued-tokens"><h2>Vydané tokeny</h2><table class="schedulerTable"><thead><tr><th>Students</th><th>Popisek</th><th>Vytvořen</th><th>Expirace</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($tokens as $t): ?><tr><td><?= h($t['surname']) ?></td><td><?= h($t['label']) ?></td><td><?= h($t['created_at']) ?></td><td><?= h($t['expires_at'] ?? 'bez expirace') ?></td><td><?= $t['revoked_at'] ? 'zneplatněný' : 'active' ?></td><td><?php if(!$t['revoked_at']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="revoke_token"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="button-link subtle" type="submit">Zneplatnit</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></section><?php endif; ?>
</main></body></html>
