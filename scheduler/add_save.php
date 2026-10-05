<?php
require_once dirname(__DIR__) . '/_private/presentations.php';
require_post();
$dateId=input_id($_POST,'date_id'); $slot=input_id($_POST,'slot');
$presenter=input_text($_POST,'presenter'); $responsible=input_text($_POST,'responsible_name'); $email=input_text($_POST,'email');
if ($presenter==='' || $responsible==='' || mb_strlen($presenter)>500 || mb_strlen($responsible)>255 || !filter_var($email,FILTER_VALIDATE_EMAIL) || mb_strlen($email)>255) app_error(422,'Please fill in platná data prezentace.');
$pdo->beginTransaction();
try {
    $date=presentation_date($pdo,$dateId,null,true);
    if ($slot>(int)$date['slots_per_date']) throw new DomainException('Slot neexistuje.');
    presentation_slot_free($pdo,$dateId,$slot);
    $token=presentation_token();
    $stmt=$pdo->prepare('INSERT INTO presentation_registrations (date_id,slot_number,presenter,responsible_name,email,token_hash,status) VALUES (?,?,?,?,?,?,"active")');
    $stmt->execute([$dateId,$slot,$presenter,$responsible,$email,presentation_token_hash($token)]);
    $pdo->commit();
} catch (DomainException $e) { if ($pdo->inTransaction()) $pdo->rollBack(); app_error(409,$e->getMessage()); }
  catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
header('Location: /studevid/scheduler/change.php?token='.urlencode($token).'&created=1',true,303); exit;
