<?php
require_once dirname(__DIR__) . '/_private/presentations.php';
$presentationSetupError = null;
try {
    $subjects = $pdo->query('SELECT id,code,name,university,slots_per_date FROM presentation_subjects WHERE is_active=1 AND slots_per_date BETWEEN 1 AND 100 ORDER BY university,name')->fetchAll();
} catch (PDOException $e) {
    // Nezobrazujeme SQL chybu návštěvníkům, ale do logu zapíšeme databázi,
    // na kterou se aplikace skutečně připojila. To odhalí záměnu konfigurace
    // nebo chybějící oprávnění po nasazení.
    $databaseName = 'unknown';
    try { $databaseName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn(); } catch (Throwable $ignored) {}
    error_log(sprintf(
        'Students presentation scheduler: config=%s database=%s error=%s',
        (string)($GLOBALS['students_database_config'] ?? 'unknown'),
        $databaseName !== '' ? $databaseName : 'none',
        $e->getMessage()
    ));
    $subjects = [];
    $presentationSetupError = 'Databáze prezentací není dostupná. Ověřte připojení aplikace ke správné databázi a existenci tabulek presentation_subjects, presentation_event_dates a presentation_registrations.';
}
$globalCssPath='/studevid/css/global.css'; $dashboardCssPath=null; $extraCssFiles=['presentations.css']; $loadJquery=true; $loadChartJs=false; $loadDesignJs=false;
?><!doctype html><html lang="en"><head><meta charset="utf-8"><title>Prezentace v seminářích</title><?php require dirname(__DIR__).'/_private/views/header.php'; ?></head><body>
<main class="presentations-main"><section class="standardCard presentations-card presentations-card--hero"><h1 class="centered">Prezentace v seminářích</h1><p class="code-font centered muted">Zvolte subject a date prezentace.</p>
<?php if ($presentationSetupError): ?><p class="alertBox centered"><?= h($presentationSetupError) ?></p><?php elseif (!$subjects): ?><p class="centered muted">Momentálně nejsou vypsány no active subjecty.</p><?php else: ?><ul class="subject-list"><?php foreach($subjects as $s): ?><li class="code-font"><a class="subject-list-link" href="subject.php?id=<?= (int)$s['id'] ?>"><span class="subject-list-title"><?= h($s['name']) ?></span><span class="subject-list-meta"><?= h($s['university']) ?><?= $s['code'] ? ' · '.h($s['code']) : '' ?></span></a></li><?php endforeach; ?></ul><?php endif; ?></section></main></body></html>
