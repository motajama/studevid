<?php
require_once dirname(__DIR__) . '/studevid/_private/bootstrap.php';
$teacher_id = page_role('teacher');
require_once dirname(__DIR__) . '/studevid/_private/database.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post();
    $action = input_text($_POST, 'action');
    if ($action === 'add') {
        $date = input_text($_POST, 'date'); $time = input_text($_POST, 'time'); $only = isset($_POST['onlyonline']) ? 1 : 0;
        $dt = DateTime::createFromFormat('Y-m-d H:i', "$date $time");
        if (!$dt || $dt <= new DateTime('now', new DateTimeZone('Europe/Prague'))) app_error(422, 'Enter budoucí platný date.');
        $stmt=$pdo->prepare('INSERT INTO scheduler (`date`,`time`,name,email,form,onlyonline,active) VALUES (?,?,NULL,NULL,NULL,?,1)'); $stmt->execute([$date,$time,$only]);
    } elseif (in_array($action, ['deactivate','activate','delete'], true)) {
        $id=input_id($_POST,'id');
        if ($action==='delete') $stmt=$pdo->prepare('DELETE FROM scheduler WHERE id=? AND (name IS NULL OR TRIM(name)=\'\')');
        else $stmt=$pdo->prepare('UPDATE scheduler SET active=? WHERE id=?');
        $stmt->execute($action==='delete' ? [$id] : [$action==='activate'?1:0,$id]);
    } else app_error(400,'Unknown action.');
    header('Location: /konzultace/manage.php', true, 303); exit;
}
$rows=$pdo->query('SELECT * FROM scheduler WHERE date>=CURRENT_DATE ORDER BY date,time')->fetchAll();
?><!doctype html><html lang="en"><head><meta charset="utf-8"><title>Management dates consultations</title><?php $globalCssPath='/studevid/css/global.css';$dashboardCssPath=null;$extraCssFiles=[];$loadJquery=false;$loadChartJs=false;$loadDesignJs=false;require dirname(__DIR__).'/studevid/_private/views/header.php'; ?></head><body><main style="max-width:var(--content-width);margin:auto"><section class="standardCard"><h1>Management dates consultations</h1><p class="muted">Datey od dneška do budoucnosti. Obsazený date lze deactivate, aby zůstal v historii.</p><form method="post" class="formEdit"><input type="hidden" name="action" value="add"><?= csrf_field() ?><div class="formRow"><label>Date <input type="date" name="date" required></label><label>Time <input type="time" name="time" required></label><label><input type="checkbox" name="onlyonline" value="1"> online only</label><button class="button-link primary" type="submit">Add date</button></div></form></section><section class="standardCard"><table class="schedulerTable"><thead><tr><th>Date</th><th>Time</th><th>Status</th><th>Rezervace</th><th>Actions</th></tr></thead><tbody><?php foreach($rows as $r): $active=(int)($r['active']??1);$booked=trim((string)($r['email']??''))!==''; ?><tr><td><?= h($r['date']) ?></td><td><?= h(substr($r['time'],0,5)) ?></td><td><?= $active?'active':'inactive' ?></td><td><?= $booked?h($r['name']??'booked'):'free' ?></td><td><?php if($active): ?><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="deactivate"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="button-link subtle" type="submit">Deactivate</button></form><?php else: ?><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="button-link subtle" type="submit">Activate</button></form><?php endif; ?><?php if(!$booked): ?><form method="post" style="display:inline" onsubmit="return confirm('Delete date?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="button-link subtle" type="submit">Delete</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></section><p><a class="button-link" href="/studevid/students.php">← Back na student homepage</a></p></main></body></html>
