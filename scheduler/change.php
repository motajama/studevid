<?php
require_once dirname(__DIR__) . '/_private/presentations.php';
$token=input_text($_GET,'token');
try { $registration=presentation_registration($pdo,$token); } catch (DomainException $e) { app_error(404,$e->getMessage()); }
$error='';
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    require_post();
    $pdo->beginTransaction();
    try {
        $current=presentation_registration($pdo,$token,true);
        if (isset($_POST['delete'])) {
            $stmt=$pdo->prepare('UPDATE presentation_registrations SET status="cancelled",updated_at=NOW() WHERE id=? AND status="active"'); $stmt->execute([$current['id']]);
            $pdo->commit();
            $globalCssPath='/studevid/css/global.css'; $dashboardCssPath=null; $extraCssFiles=['presentations.css']; $loadJquery=true; $loadChartJs=false; $loadDesignJs=false;
            ?><!doctype html><html lang="en"><head><meta charset="utf-8"><title>Registrace zrušena</title><?php require dirname(__DIR__).'/_private/views/header.php'; ?></head><body><main class="presentations-main"><section class="standardCard presentations-card centered"><h1>Registrace zrušena</h1><p>Vaše registrace byla zrušena.</p><p><a href="index.php">Back na subjecty</a></p></section></main></body></html><?php exit;
        }
        $targetDate=input_id($_POST,'move_date_id'); $targetSlot=input_id($_POST,'move_slot');
        $date=presentation_date($pdo,$targetDate,(int)$current['subject_id'],true);
        if ($targetSlot>(int)$date['slots_per_date']) throw new DomainException('Slot neexistuje.');
        presentation_slot_free($pdo,$targetDate,$targetSlot,(int)$current['id']);
        $stmt=$pdo->prepare('UPDATE presentation_registrations SET date_id=?,slot_number=?,updated_at=NOW() WHERE id=? AND status="active"'); $stmt->execute([$targetDate,$targetSlot,$current['id']]);
        $pdo->commit(); header('Location: /studevid/scheduler/change.php?token='.urlencode($token).'&ok=1',true,303); exit;
    } catch (DomainException $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $error=$e->getMessage(); http_response_code(409); }
      catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
$stmt=$pdo->prepare('SELECT d.id,d.presentation_date,d.note FROM presentation_event_dates d WHERE d.subject_id=? AND d.is_active=1 AND d.presentation_date>=CURRENT_DATE ORDER BY d.presentation_date'); $stmt->execute([$registration['subject_id']]); $dates=$stmt->fetchAll();
$stmt=$pdo->prepare('SELECT date_id,slot_number,presenter,responsible_name FROM presentation_registrations WHERE status="active" AND date_id IN (SELECT id FROM presentation_event_dates WHERE subject_id=? AND is_active=1 AND presentation_date>=CURRENT_DATE)'); $stmt->execute([$registration['subject_id']]); $occupied=[]; foreach($stmt->fetchAll() as $r) $occupied[(int)$r['date_id']][(int)$r['slot_number']]=$r;
$globalCssPath='/studevid/css/global.css'; $dashboardCssPath=null; $extraCssFiles=['presentations.css']; $loadJquery=true; $loadChartJs=false; $loadDesignJs=false;
?><!doctype html><html lang="en"><head><meta charset="utf-8"><title>Změna registrace prezentace</title><?php require dirname(__DIR__).'/_private/views/header.php'; ?></head><body><main class="presentations-main"><section class="standardCard presentations-card"><h1 class="centered">Změna registrace prezentace</h1><p>Subject: <strong><?= h($registration['subject_name']) ?></strong></p><p>Aktuálně: <strong><?= h(date('j. n. Y',strtotime($registration['presentation_date']))) ?></strong>, slot <?= (int)$registration['slot_number'] ?></p><?php if(isset($_GET['created'])): ?><p>Registrace byla uložena. Tento odkaz si uschovejte.</p><?php endif; ?><?php if(isset($_GET['ok'])): ?><p class="ok-message">Date byl změněn.</p><?php endif; ?><?php if($error): ?><p class="alertBox"><?= h($error) ?></p><?php endif; ?></section><section class="standardCard presentations-card"><h2>Vyberte new date</h2><table class="schedulerTable presentations-table"><tbody><?php foreach($dates as $d): ?><tr><th><?= h(date('j. n. Y',strtotime($d['presentation_date']))) ?></th><td><?= h($d['note']??'') ?></td><td><?php for($i=1;$i<=(int)$registration['slots_per_date'];$i++): ?><?php $current=((int)$d['id']===(int)$registration['date_id']&&$i===(int)$registration['slot_number']); if($current): ?><span class="slot slot-current">Váš slot</span><?php elseif(isset($occupied[(int)$d['id']][$i])): ?><span class="slot slot-occupied">Obsazeno</span><?php else: ?><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="move_date_id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="move_slot" value="<?= $i ?>"><button class="slot slot-free" type="submit">Volno (<?= $i ?>)</button></form><?php endif; ?><?php endfor; ?></td></tr><?php endforeach; ?></tbody></table></section><section class="standardCard presentations-card centered"><h2>Cancel registraci</h2><form method="post" onsubmit="return confirm('Opravdu cancel registraci?');"><?= csrf_field() ?><input type="hidden" name="delete" value="1"><button class="button-link red" type="submit">Cancel registraci</button></form></section></main></body></html>
