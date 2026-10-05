<?php
declare(strict_types=1);
require_once __DIR__ . '/database.php';

function presentation_subject(PDO $pdo, int $id, bool $forUpdate = false): array {
    $sql = 'SELECT id,code,name,university,slots_per_date,is_active FROM presentation_subjects WHERE id=?';
    if ($forUpdate) $sql .= ' FOR UPDATE';
    $stmt = $pdo->prepare($sql); $stmt->execute([$id]); $subject = $stmt->fetch();
    $slots = $subject ? filter_var($subject['slots_per_date'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>100]]) : false;
    if (!$subject || (int)$subject['is_active'] !== 1 || $slots === false) throw new DomainException('Subject není dostupný.');
    $subject['slots_per_date'] = $slots; return $subject;
}

function presentation_date(PDO $pdo, int $id, ?int $subjectId = null, bool $forUpdate = false): array {
    $sql = 'SELECT d.id,d.subject_id,d.presentation_date,d.note,s.name AS subject_name,s.code AS subject_code,s.university,s.slots_per_date
            FROM presentation_event_dates d JOIN presentation_subjects s ON s.id=d.subject_id
            WHERE d.id=? AND d.is_active=1 AND s.is_active=1 AND d.presentation_date>=CURRENT_DATE';
    $params = [$id]; if ($subjectId !== null) { $sql .= ' AND d.subject_id=?'; $params[] = $subjectId; }
    if ($forUpdate) $sql .= ' FOR UPDATE';
    $stmt = $pdo->prepare($sql); $stmt->execute($params); $date = $stmt->fetch();
    if (!$date) throw new DomainException('Date není dostupný.');
    $slots = filter_var($date['slots_per_date'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>100]]);
    if ($slots === false) throw new DomainException('Subject nemá platný počet slotů.');
    $date['slots_per_date'] = $slots; return $date;
}

function presentation_token(): string { return bin2hex(random_bytes(32)); }
function presentation_token_hash(string $token): string { return hash('sha256', $token); }

function presentation_registration(PDO $pdo, string $token, bool $forUpdate = false): array {
    if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) throw new DomainException('Invalid odkaz registrace.');
    $sql = 'SELECT r.*,d.subject_id,d.presentation_date,d.note,s.name AS subject_name,s.code AS subject_code,s.university,s.slots_per_date
            FROM presentation_registrations r JOIN presentation_event_dates d ON d.id=r.date_id
            JOIN presentation_subjects s ON s.id=d.subject_id
            WHERE r.token_hash=? AND r.status="active" AND d.is_active=1 AND s.is_active=1 AND d.presentation_date>=CURRENT_DATE';
    if ($forUpdate) $sql .= ' FOR UPDATE';
    $stmt = $pdo->prepare($sql); $stmt->execute([presentation_token_hash($token)]); $registration = $stmt->fetch();
    if (!$registration) throw new DomainException('Registrace nebyla nalezena.'); return $registration;
}

function presentation_slot_free(PDO $pdo, int $dateId, int $slot, ?int $exceptId = null): void {
    $sql = 'SELECT id FROM presentation_registrations WHERE date_id=? AND slot_number=? AND status="active"';
    $params = [$dateId,$slot]; if ($exceptId !== null) { $sql .= ' AND id<>?'; $params[] = $exceptId; }
    $stmt = $pdo->prepare($sql.' FOR UPDATE'); $stmt->execute($params);
    if ($stmt->fetch()) throw new DomainException('Slot mezitím obsadil někdo jiný.');
}
