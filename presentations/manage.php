<?php
require_once dirname(__DIR__) . '/_private/bootstrap.php';
page_role('teacher');
require_once dirname(__DIR__) . '/_private/database.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post();
    $action = input_text($_POST, 'action');
    if ($action === 'save_subject') {
        $id = input_id($_POST, 'id');
        $code = input_text($_POST, 'code'); $name = input_text($_POST, 'name');
        $university = input_text($_POST, 'university');
        $slots = filter_var($_POST['slots_per_date'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>100]]);
        if ($name === '' || $slots === false || mb_strlen($name)>255 || mb_strlen($code)>64 || mb_strlen($university)>255) app_error(422, 'Please fill in název a platný počet slotů.');
        if ($id) $stmt=$pdo->prepare('UPDATE presentation_subjects SET code=?,name=?,university=?,slots_per_date=?,updated_at=NOW() WHERE id=?');
        else $stmt=$pdo->prepare('INSERT INTO presentation_subjects (code,name,university,slots_per_date,is_active) VALUES (?,?,?,?,1)');
        $params=$id?[$code,$name,$university,$slots,$id]:[$code,$name,$university,$slots];
        try { $stmt->execute($params); } catch (PDOException $e) { app_error(409, 'Subject s tímto kódem již existuje nebo nelze změnit.'); }
    } elseif ($action === 'toggle_subject') {
        $id=input_id($_POST,'id'); $active=(int)($_POST['active'] ?? 0) ? 0 : 1;
        $stmt=$pdo->prepare('UPDATE presentation_subjects SET is_active=?,updated_at=NOW() WHERE id=?'); $stmt->execute([$active,$id]);
    } elseif ($action === 'add_date') {
        $subjectId=input_id($_POST,'subject_id'); $date=input_text($_POST,'presentation_date'); $note=input_text($_POST,'note');
        $valid=DateTime::createFromFormat('Y-m-d',$date);
        if (!$valid || $valid->format('Y-m-d') !== $date || $date < date('Y-m-d')) app_error(422,'Enter platné dnešní nebo budoucí date.');
        try { $stmt=$pdo->prepare('INSERT INTO presentation_event_dates (subject_id,presentation_date,note,is_active) VALUES (?,?,?,1)'); $stmt->execute([$subjectId,$date,$note]); } catch (PDOException $e) { app_error(409,'Pro tento subject a date již date existuje.'); }
    } elseif ($action === 'toggle_date') {
        $id=input_id($_POST,'id'); $active=(int)($_POST['active'] ?? 0) ? 0 : 1;
        $stmt=$pdo->prepare('UPDATE presentation_event_dates SET is_active=?,updated_at=NOW() WHERE id=?'); $stmt->execute([$active,$id]);
    } else app_error(400,'Unknown action.');
    header('Location: /studevid/presentations/manage.php', true, 303); exit;
}
$subjects=$pdo->query('SELECT * FROM presentation_subjects ORDER BY is_active DESC,name')->fetchAll();
$dates=$pdo->query('SELECT d.*,s.name AS subject_name FROM presentation_event_dates d JOIN presentation_subjects s ON s.id=d.subject_id ORDER BY d.presentation_date DESC,s.name')->fetchAll();
$editId=isset($_GET['edit_subject']) ? input_id($_GET,'edit_subject') : 0; $editSubject=null;
foreach($subjects as $s) if ((int)$s['id']===$editId) {$editSubject=$s;break;}
$globalCssPath='/studevid/css/global.css'; $dashboardCssPath=null; $extraCssFiles=[]; $loadJquery=false; $loadChartJs=false; $loadDesignJs=false;
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Management prezentačních dates</title><?php require dirname(__DIR__).'/_private/views/header.php'; ?><style>.manage{max-width:var(--content-width);margin:auto}.manage form.inline{display:inline}.manage .muted{font-size:.9em}</style></head><body><main class="manage"><section class="standardCard"><h1>Management prezentačních dates</h1><p class="muted">Subjecty a dates lze deactivate bez mazání registrací. Deaktivovaný záznam se student nezobrazuje.</p><h2><?= $editSubject ? 'Edit subject' : 'New subject' ?></h2><form method="post" class="formEdit"><?= csrf_field() ?><input type="hidden" name="action" value="save_subject"><input type="hidden" name="id" value="<?= (int)($editSubject['id']??0) ?>"><div class="formRow"><label>Code <input name="code" maxlength="64" value="<?= h($editSubject['code']??'') ?>"></label><label>Name <input name="name" maxlength="255" required value="<?= h($editSubject['name']??'') ?>"></label><label>University <input name="university" maxlength="255" value="<?= h($editSubject['university']??'') ?>"></label><label>Slots na date <input name="slots_per_date" type="number" min="1" max="100" required value="<?= (int)($editSubject['slots_per_date']??1) ?>"></label></div><button class="button-link primary" type="submit">Save subject</button><?php if($editSubject): ?> <a class="button-link subtle" href="manage.php">Cancel</a><?php endif; ?></form></section><section class="standardCard"><h2>Subjecty</h2><table class="schedulerTable"><thead><tr><th>Code</th><th>Name</th><th>University</th><th>Slots</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($subjects as $s): ?><tr><td><?= h($s['code']) ?></td><td><?= h($s['name']) ?></td><td><?= h($s['university']) ?></td><td><?= (int)$s['slots_per_date'] ?></td><td><?= $s['is_active']?'active':'inactive' ?></td><td><a class="button-link subtle" href="?edit_subject=<?= (int)$s['id'] ?>">Edit</a> <form class="inline" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_subject"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="active" value="<?= (int)$s['is_active'] ?>"><button class="button-link subtle" type="submit"><?= $s['is_active']?'Deactivate':'Activate' ?></button></form></td></tr><?php endforeach; ?></tbody></table></section><section class="standardCard"><h2>New date</h2><form method="post" class="formEdit"><?= csrf_field() ?><input type="hidden" name="action" value="add_date"><div class="formRow"><label>Subject <select name="subject_id" required><?php foreach($subjects as $s): if(!$s['is_active']) continue; ?><option value="<?= (int)$s['id'] ?>"><?= h($s['name']) ?></option><?php endforeach; ?></select></label><label>Date <input type="date" name="presentation_date" min="<?= date('Y-m-d') ?>" required></label><label>Note <input name="note" maxlength="255"></label></div><button class="button-link primary" type="submit">Add date</button></form></section><section class="standardCard"><h2>Datey</h2><table class="schedulerTable"><thead><tr><th>Date</th><th>Subject</th><th>Note</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($dates as $d): ?><tr><td><?= h(date('j. n. Y',strtotime($d['presentation_date']))) ?></td><td><?= h($d['subject_name']) ?></td><td><?= h($d['note']??'') ?></td><td><?= $d['is_active']?'active':'inactive' ?></td><td><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_date"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="active" value="<?= (int)$d['is_active'] ?>"><button class="button-link subtle" type="submit"><?= $d['is_active']?'Deactivate':'Activate' ?></button></form></td></tr><?php endforeach; ?></tbody></table></section><p><a class="button-link" href="/studevid/students.php">← Back na student homepage</a></p></main></body></html>
