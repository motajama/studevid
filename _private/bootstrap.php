<?php
declare(strict_types=1);
const STUDENTS_APP = true;
date_default_timezone_set('Europe/Prague');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_name('students_session');
    session_set_cookie_params(['path' => '/', 'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax']);
    session_start();
}
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: SAMEORIGIN');
function h(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function app_error_context(): array {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? $_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH) ?: '';
    if (str_starts_with($path, '/studevid/diaries/')) {
        return ['/studevid/diaries/', 'study diaryy'];
    }
    if (str_starts_with($path, '/studevid/adhoc/')) {
        return ['/studevid/adhoc/', 'ad hoc consultations'];
    }
    if (str_starts_with($path, '/studevid/scheduler/')) {
        return ['/studevid/scheduler/', 'přihlašování na prezentace'];
    }
    if (str_starts_with($path, '/konzultace/')) {
        return ['/konzultace/', 'booking consultations'];
    }
    return ['/studevid/', 'aplikace Students'];
}

function app_error_heading(int $status): string {
    return match ($status) {
        400 => 'Request nebyl rozpoznán',
        401 => 'Sign-in required',
        403 => 'Access denied',
        404 => 'Požadovaný content was not found',
        405 => 'This action is not available',
        409 => 'Data changed',
        422 => 'Submitted data are invalid',
        503 => 'Service temporarily unavailable',
        default => 'Request se nepodařilo dokončit',
    };
}

function app_error_explanation(int $status): string {
    return match ($status) {
        400 => 'System nerozuměl požadované operaci nebo jejím parametrům.',
        401 => 'Pro pokračování se přihlaste do této části aplikace.',
        403 => 'Nemáte oprávnění tuto operaci provést, případně formulář již není platný.',
        404 => 'Odkaz může být zastaralý, záznam mohl být odstraněn nebo již není dostupný.',
        405 => 'Tato stránka přijímá jiný typ request, než jaký byl odeslán.',
        409 => 'Požadovaný date nebo záznam mezitím použil někdo jiný. Obnovte stránku a zkuste to znovu.',
        422 => 'Zkontrolujte zadané údaje a odešlete formulář znovu.',
        503 => 'Aplikace se nyní nedokáže připojit ke své službě nebo databázi.',
        default => 'System narazil na neočekávaný problém. Zkuste to prosím znovu později.',
    };
}

function app_error(int $status, string $message): never {
    http_response_code($status);
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $message, 'message' => $message]);
    } else {
        [$homeUrl, $homeLabel] = app_error_context();
        $heading = app_error_heading($status);
        $explanation = app_error_explanation($status);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . h($heading) . '</title><link rel="stylesheet" href="/studevid/css/global.css"></head><body><main class="presentations-main"><section class="standardCard presentations-card error-card"><p class="code-font muted">Error ' . (int)$status . '</p><h1>' . h($heading) . '</h1><p class="alertBox">' . h($message) . '</p><p>' . h($explanation) . '</p><p><a class="button-link primary" href="' . h($homeUrl) . '">Back na hlavní stránku: ' . h($homeLabel) . '</a></p></section></main></body></html>';
    }
    exit;
}
set_exception_handler(function (Throwable $e): void {
    error_log('Students: ' . $e->getMessage());
    app_error(500, 'Request se nepodařilo dokončit. Please try again later.');
});
function input_text(array $source, string $key, string $default = ''): string {
    $value = $source[$key] ?? $default;
    if (!is_string($value)) app_error(400, 'Invalid vstup.');
    return trim($value);
}
function input_id(array $source, string $key): int {
    $value = filter_var($source[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($value === false || $value === null) app_error(400, 'Neplatné ID.');
    return $value;
}
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}
function require_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Allow: POST'); app_error(405, 'Tato akce vyžaduje odeslání formuláře.');
    }
    $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) app_error(403, 'Platnost formuláře vypršela. Obnovte stránku a zkuste to znovu.');
}
function require_role(string $role): int {
    $id = (int)($_SESSION[$role . '_id'] ?? 0);
    if (!$id) app_error(401, 'Nejprve se přihlaste.');
    return $id;
}
function page_role(string $role): int {
    if (empty($_SESSION[$role . '_id'])) {
        header('Location: ' . ($role === 'teacher' ? '/studevid/login.php' : '/studevid/diaries/login.php')); exit;
    }
    return (int)$_SESSION[$role . '_id'];
}
function login_identity(string $role, int $id, string $name): void {
    $_SESSION = []; session_regenerate_id(true);
    $_SESSION[$role . '_id'] = $id; $_SESSION[$role . '_name'] = $name; csrf_token();
}
function require_student_access(PDO $pdo, int $studentId): void {
    $stmt = $pdo->prepare('SELECT 1 FROM student_teacher WHERE teacher_id = ? AND student_id = ?');
    $stmt->execute([require_role('teacher'), $studentId]);
    if (!$stmt->fetchColumn()) app_error(404, 'Student was not found.');
}
function json_response(mixed $data): never {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); exit;
}
function booking_token(int $id, string $email): string {
    $secret = getenv('STUDENTS_BOOKING_SECRET');
    if (!is_string($secret) || strlen($secret) < 32) app_error(503, 'Rezervace jsou dočasně nedostupné.');
    return hash_hmac('sha256', $id . ':' . strtolower(trim($email)), $secret);
}
function booking_slot(PDO $pdo, int $id, string $token): array {
    if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) app_error(404, 'Rezervace nebyla nalezena.');
    $stmt = $pdo->prepare('SELECT id,date,time,name,email,form FROM scheduler WHERE id=?');
    $stmt->execute([$id]);
    $slot = $stmt->fetch();
    if (!$slot || trim((string)$slot['email']) === '' ||
        !hash_equals(booking_token($id, (string)$slot['email']), $token)) {
        app_error(404, 'Rezervace nebyla nalezena.');
    }
    return $slot;
}
