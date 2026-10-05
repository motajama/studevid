<?php
require_once __DIR__ . '/../studevid/_private/database.php';
header('Referrer-Policy: no-referrer');
$helper_image = "/studevid/img/pikachu.png";
$helper_on = false;

function generateGoogleCalendarLink($date, $time, $form = "Online", $cancel = "")
{
    $start = new DateTime("$date $time", new DateTimeZone("Europe/Prague"));
    $end = clone $start;
    $end->add(new DateInterval("PT45M"));

    $startStr = $start->setTimezone(new DateTimeZone("UTC"))->format("Ymd\THis\Z");
    $endStr = $end->setTimezone(new DateTimeZone("UTC"))->format("Ymd\THis\Z");

    $params = http_build_query([
        "action" => "TEMPLATE",
        "text" => "Consultations (Studevid Maintainers)",
        "dates" => "$startStr/$endStr",
        "details" => "Rezervováno přes example.org. Date můžete cancel, ale informujte mne o tom, prosím, by email (maintainer@example.org) – použijte adresu $cancel",
        "location" =>
            $form === "zoom"
                ? "Zoom – https://tinyurl.com/konzumotal-zoom/"
                : ($form === "teams"
                    ? "MS Teams"
                    : "Kancelář 5.45, Joštova 10, 602 00, Brno"),
    ]);

    return "https://calendar.google.com/calendar/render?$params";
}

// This file is deployed at /konzultace/index.php alongside /students.
$urlOfScript = '/konzultace/index.php';
$bookingMessage = '';
$bookedSlot = null;
$cancelSlot = null;
if (isset($_GET['cancel'])) {
    $cancelSlot = booking_slot($pdo, input_id($_GET, 'cancel'), input_text($_GET, 'token'));
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post();
    if (isset($_POST['cancel_id'])) {
        $id = input_id($_POST, 'cancel_id');
        $token = input_text($_POST, 'token');
        $slot = booking_slot($pdo, $id, $token);
        $stmt = $pdo->prepare("UPDATE scheduler SET name='',email='',form='' WHERE id=? AND email=?");
        $stmt->execute([$id, $slot['email']]);
        if ($stmt->rowCount() !== 1) app_error(409, 'Date již byl změněn.');
        $bookingMessage = 'Date byl zrušen.';
    } elseif (isset($_POST['slot_id'])) {
        $id = input_id($_POST, 'slot_id');
        $firstname = input_text($_POST, 'firstname');
        $surname = input_text($_POST, 'name');
        $email = input_text($_POST, 'email');
        $form = input_text($_POST, 'form');
        if ($firstname === '' || $surname === '' || mb_strlen($firstname) > 100 || mb_strlen($surname) > 100 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($form, ['osobne','zoom','teams'], true)) {
            app_error(422, 'Please fill in platné name, e-mail a způsob consultations.');
        }
        // Verify the secret before changing a row, so every booking has a usable cancellation link.
        booking_token($id, $email);
        $stmt = $pdo->prepare("UPDATE scheduler SET name=?,email=?,form=? WHERE id=? AND (name IS NULL OR TRIM(name)='') AND (email IS NULL OR TRIM(email)='') AND TIMESTAMP(date,time)>NOW() AND (COALESCE(onlyonline,0)=0 OR ?<>'osobne')");
        $stmt->execute([trim($surname . ' ' . $firstname),$email,$form,$id,$form]);
        if ($stmt->rowCount() !== 1) app_error(409, 'Date již není free. Obnovte stránku a vyberte jiný.');
        $stmt = $pdo->prepare('SELECT id,date,time,form,email FROM scheduler WHERE id=?');
        $stmt->execute([$id]);
        $bookedSlot = $stmt->fetch();
        $bookedSlot['token'] = booking_token($id, $email);
        $bookedSlot['surname'] = $surname;
    } else app_error(400, 'Unknown action.');
}

// ---------- FETCH DATA ----------
try {
    $result = $pdo->query("SELECT * FROM scheduler WHERE date >= CURRENT_DATE AND COALESCE(active,1)=1 ORDER BY date ASC, time ASC");
} catch (Throwable $schemaError) {
    $result = $pdo->query("SELECT * FROM scheduler WHERE date >= CURRENT_DATE ORDER BY date ASC, time ASC");
}
$scheduler = [];
$times = [];

$now = new DateTime("now", new DateTimeZone("Europe/Prague"));

while ($row = $result->fetch()) {
    $slotDate = trim((string)($row["date"] ?? ""));
    $slotTime = trim((string)($row["time"] ?? ""));

    if ($slotDate === "" || $slotTime === "") {
        continue;
    }

    // funguje pro HH:MM i HH:MM:SS
    $dateTimeString = $slotDate . " " . $slotTime;

    try {
        $slotStart = new DateTime($dateTimeString, new DateTimeZone("Europe/Prague"));
    } catch (Exception $e) {
        continue;
    }

    // zobrazit jen budoucí / ještě neproběhlé sloty
    if ($slotStart < $now) {
        continue;
    }

    $scheduler[$slotDate][$slotTime] = $row;
    $times[$slotTime] = true;
}

ksort($times);
ksort($scheduler);

// Overview workloadi: obsazené sloty / všechny vypsané sloty v daném měsíci.
$monthlyLoad = [];
foreach ($scheduler as $date => $slots) {
    $monthKey = date('Y-m', strtotime($date));
    if (!isset($monthlyLoad[$monthKey])) $monthlyLoad[$monthKey] = ['total' => 0, 'booked' => 0];
    foreach ($slots as $slot) {
        $monthlyLoad[$monthKey]['total']++;
        if (trim((string)($slot['name'] ?? '')) !== '') $monthlyLoad[$monthKey]['booked']++;
    }
}
foreach ($monthlyLoad as $key => &$load) {
    $load['percent'] = $load['total'] > 0 ? (int)round(($load['booked'] / $load['total']) * 100) : 0;
}
unset($load);

// ---------- SLOT EDIT FLAG ----------
$slotEdited = $bookedSlot !== null || $bookingMessage !== '' || $cancelSlot !== null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sign in na consultations</title>

    <link rel="stylesheet" href="/studevid/css/global.css" />
    <link rel="stylesheet" href="/studevid/css/konzultace.css" />

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@300..700&family=Vollkorn:ital,wght@0,400..900;1,400..900&display=swap"
        rel="stylesheet"
    />

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="/studevid/js/design.js"></script>
    <script src="/studevid/js/konzultace.js"></script>

 <style>
    .consultation-page {
        max-width: var(--content-width);
        margin: 0 auto;
        padding: 1rem 1rem 2.5rem 1rem;
    }

    .consultation-hero h1 {
        margin-bottom: 0.75rem;
    }

    .consultation-content {
        padding-top: 0.25rem;
    }

    .consultation-note {
        color: var(--text-color);
        font-family: var(--font-body);
    }

    .consultation-note p:last-child {
        margin-bottom: 0;
    }

    .consultation-note ul {
        margin-top: 0.6rem;
        margin-bottom: 0.6rem;
        padding-left: 1.2rem;
    }

    .consultation-note li + li {
        margin-top: 0.45rem;
    }

    .consultation-table-wrap {
        margin-top: 1rem;
        border: 1px solid var(--line-color);
        border-radius: 14px;
        overflow: auto;
        background: #fff;
        box-shadow: 5px 5px var(--shadow-color);
        -webkit-overflow-scrolling: touch;
    }

    .load-overview { display:flex; flex-wrap:wrap; gap:.75rem; margin:1rem 0; }
    .load-month { flex:1 1 10rem; min-width:10rem; padding:.75rem; border:1px solid var(--line-color); border-radius:10px; background:#fff; }
    .load-meter { height:9px; border-radius:99px; background:#e5e7eb; overflow:hidden; margin:.45rem 0; }
    .load-meter > span { display:block; height:100%; border-radius:99px; }
    .load-green { background:#2e7d32; } .load-yellow { background:#f9a825; } .load-red { background:#c62828; }
    .slot-load { display:inline-block; width:9px; height:9px; border-radius:50%; margin-left:.35rem; vertical-align:middle; }

    .consultation-table {
        min-width: 760px;
        border-collapse: separate;
        border-spacing: 0;
        font-family: var(--font-body);
    }

    .consultation-table th,
    .consultation-table td {
        text-align: center;
        vertical-align: middle;
        padding: 0.85rem 0.7rem;
        min-width: 92px;
        border-bottom: 1px solid #ece5d9;
    }

    .consultation-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #f6f2e8;
        font-family: var(--font-heading);
        font-weight: 700;
    }

    .consultation-table .date-col {
        position: sticky;
        left: 0;
        z-index: 1;
        background: #fcfaf5;
        text-align: left;
        white-space: nowrap;
        min-width: 115px;
        font-family: var(--font-heading);
        font-weight: 700;
    }

    .consultation-table thead .date-col {
        z-index: 3;
        background: #eee7d8;
    }

    .consultation-table td.empty {
        background: #fbfbfb;
    }

    .consultation-table td.empty a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.2rem;
        min-height: 2.2rem;
        border-radius: 999px;
        text-decoration: none;
        background: #fff;
        border: 1px solid var(--line-color);
        color: var(--text-color);
        font-family: var(--font-heading);
        font-weight: 700;
        font-size: 1rem;
        transition: background-color 0.18s ease, border-color 0.18s ease, transform 0.08s ease;
    }

    .consultation-table td.empty a:hover,
    .consultation-table td.empty a:focus-visible {
        background: #f7faff;
        border-color: var(--link-color);
        color: var(--text-color);
        text-decoration: none;
    }

    .consultation-table td.empty a:active {
        transform: translateY(1px);
    }

    .consultation-table td.disabled {
        background: #f1f1f1;
    }

    .consultation-table td.occupied {
        background: #d8d6d1;
        color: #4b4b4b;
        font-family: var(--font-mono);
        font-size: 0.86rem;
        font-weight: 600;
        text-align: center;
    }

    .consultation-table td.occupied span {
        display: inline-block;
        padding: 0.22rem 0.42rem;
        border-radius: 999px;
        background: rgba(255,255,255,0.52);
    }

    .consultation-popup-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.28);
        display: none;
        z-index: 1500;
    }

    .form-popup {
        position: fixed;
        top: 50%;
        left: 50%;
        width: min(92vw, 560px);
        max-height: 90vh;
        overflow-y: auto;
        transform: translate(-50%, -50%);
        z-index: 1600;
        display: none;
        padding: 1.1rem;
        border: 1px solid var(--line-color);
        border-radius: 16px;
        background: var(--block-color);
        box-shadow: 5px 5px var(--shadow-color);
    }

    .form-popup form {
        margin: 0;
    }

    .form-popup h2 {
        margin-top: 0;
    }

    .consultation-success a {
        word-break: break-word;
    }

    .consultation-empty {
        text-align: center;
        padding: 1.2rem;
        color: var(--muted-color);
        font-family: var(--font-mono);
    }

    .consultation-message {
        padding: 0.95rem 1rem;
        border-radius: 12px;
        background: rgba(255,255,255,0.72);
        border: 1px solid #e7e0d2;
    }

    .consultation-message-alert {
        background: #fff6f5;
        border-color: #f0c9c3;
        color: var(--alert-color);
    }

    .mobile-hint {
        margin-top: 0.75rem;
        font-size: 0.88rem;
    }

    @media (max-width: 768px) {
        .consultation-page {
            padding: 0.8rem 0.8rem 2rem 0.8rem;
        }

        .consultation-table {
            min-width: 680px;
        }

        .consultation-table th,
        .consultation-table td {
            padding: 0.8rem 0.65rem;
            min-width: 84px;
            font-size: 0.92rem;
        }

        .consultation-table .date-col {
            min-width: 105px;
            font-size: 0.9rem;
        }

        .form-popup {
            width: min(94vw, 560px);
            padding: 1rem;
        }
    }

    @media (max-width: 480px) {
        .consultation-page {
            padding: 0.7rem 0.7rem 1.5rem 0.7rem;
        }

        .consultation-table {
            min-width: 620px;
        }

        .consultation-table th,
        .consultation-table td {
            padding: 0.72rem 0.55rem;
            min-width: 78px;
            font-size: 0.88rem;
        }

        .consultation-table .date-col {
            min-width: 98px;
        }

        .mobile-hint {
            font-size: 0.82rem;
        }
    }
</style>
</head>
<body>

<main class="consultation-page">
    <section class="standardCard consultation-hero">
        <h1>Sign in na consultations (Studevid Maintainers)</h1>
        <div class="consultation-note consultation-content">
            <p>Prostřednictvím následující tabulky se můžete závazně přihlašovat na moje oficiální konzultační hodiny, a to jak in person na Fakultě sociálních studií MUNI v Brně, anebo on-line přes MS Teams nebo Zoom.</p>
            <ul>
                <li><em>Pokud si zvolíte osobní konzultaci</em>, budu Vás v daný čas čekat v kanceláři 5.45 na Joštově 10 v Brně <a href="https://www.fss.muni.cz/o-nas/kontakt/kancelar-20771">🗺️</a>. Je možné, že se consultations před Vámi může trochu protáhnout, anebo že skončí dříve, proto počítejte prosím s ± deseti minutami.</li>
                <li><em>Pokud si zvolíte on-line konzultaci</em>, students z Masarykovy univerzity a JAMU si vyberou z nabídky MS Teams. V daný čas mi přes Teams zavolejte, budu čekat, až se ozvete Vy. Pokud studujete jinou školu (např. University Palackého v Olomouci), anebo nejste students, zvolte variantu Zoom. Budu Vás pak v daný čas čekat na <a href="https://tinyurl.com/konzumotal-zoom">https://tinyurl.com/konzumotal-zoom</a>.</li>
            </ul>
            <p>Selection dateu is binding, máte ji ale možnost v závažných případech cancel pod odkazem, který se Vám ukáže po potvrzení. Uložte si ho a můžete jej případně využít, ale předtím se mi ozvěte <a href="mailto:maintainer@example.org">na e-mail</a>, že date rušíte, případně se můžeme domluvit jinak.</p>
        </div>
    </section>

<?php if ($bookingMessage): ?>
    <section class="standardCard consultation-success"><p><?= h($bookingMessage) ?></p></section>
<?php elseif ($cancelSlot): ?>
    <section class="standardCard consultation-success">
        <h2>Cancel rezervaci</h2>
        <p><?= h($cancelSlot['date']) ?> v <?= h($cancelSlot['time']) ?></p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="cancel_id" value="<?= (int)$cancelSlot['id'] ?>">
            <input type="hidden" name="token" value="<?= h(input_text($_GET, 'token')) ?>">
            <button type="submit">Potvrdit zrušení</button>
        </form>
    </section>
<?php elseif ($bookedSlot):
    $cancelLink = '/konzultace/?' . http_build_query(['cancel'=>$bookedSlot['id'],'token'=>$bookedSlot['token']]);
    $absoluteCancelLink = 'https://www.example.org' . $cancelLink;
    $calendarLink = generateGoogleCalendarLink($bookedSlot['date'], $bookedSlot['time'], $bookedSlot['form'], $absoluteCancelLink);
    $icsLink = '/studevid/download_ics.php?' . http_build_query(['id'=>$bookedSlot['id'],'token'=>$bookedSlot['token']]);
?>
    <section class="standardCard consultation-success"><div class="code-font consultation-message">
        Děkuji <?= h($bookedSlot['surname']) ?>! Date je rezervován. Odkaz pro případné zrušení si uložte:
        <a href="<?= h($cancelLink) ?>"><?= h($absoluteCancelLink) ?></a>.
        Add do <a href="<?= h($calendarLink) ?>" target="_blank" rel="noopener noreferrer">Google kalendáře</a>
        nebo stáhnout <a href="<?= h($icsLink) ?>">.ics</a>.
    </div></section>
<?php endif; ?>

<?php if (!$slotEdited): ?>
    <section class="standardCard">
        <h2 class="text-font-bold">Volné dates</h2>
        <p class="muted code-font">Barometr ukazuje podíl obsazených dates. Zelená = nízká workload, yellow = střední, red = vysoká.</p>

        <?php if (!empty($monthlyLoad)): ?><div class="load-overview" aria-label="Měsíční workload">
            <?php foreach ($monthlyLoad as $monthKey => $load): $monthLabel = date('m/Y', strtotime($monthKey . '-01')); $loadClass = $load['percent'] >= 75 ? 'load-red' : ($load['percent'] >= 40 ? 'load-yellow' : 'load-green'); ?>
                <div class="load-month"><strong><?= h($monthLabel) ?></strong><span class="muted code-font" style="float:right"><?= (int)$load['percent'] ?> %</span><div class="load-meter"><span class="<?= $loadClass ?>" style="width:<?= (int)$load['percent'] ?>%"></span></div><small class="muted"><?= (int)$load['booked'] ?> z <?= (int)$load['total'] ?> booked</small></div>
            <?php endforeach; ?>
        </div><?php endif; ?>

        <?php if (empty($scheduler)): ?>
            <div class="consultation-empty code-font">Momentálně nejsou vypsány no budoucí konzultační dates.</div>
        <?php else: ?>
            <div class="consultation-table-wrap">
                <table class="schedulerTable consultation-table text-font">
                    <thead>
                        <tr>
                            <th class="date-col">Date</th>
                            <?php foreach ($times as $time => $_): ?>
                                <th class="time-col"><?= htmlspecialchars($time) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scheduler as $date => $slots): ?>
                            <tr>
                                <?php $dateTotal=count($slots); $dateBooked=0; foreach($slots as $dateSlot) if (trim((string)($dateSlot['name'] ?? '')) !== '') $dateBooked++; $datePercent=$dateTotal ? (int)round($dateBooked/$dateTotal*100) : 0; $dateClass=$datePercent >= 75 ? 'load-red' : ($datePercent >= 40 ? 'load-yellow' : 'load-green'); ?>
                                <td class="date-col"><?= date("d.m.Y", strtotime($date)) ?><span class="slot-load <?= $dateClass ?>" title="<?= $datePercent ?> % dates v tento den je booked"></span><small class="muted code-font" style="display:block;font-size:.7em"><?= $datePercent ?> % booked</small></td>
                                <?php foreach ($times as $time => $_): ?>
                                    <?php if (!isset($slots[$time])): ?>
                                        <td class="time-col disabled"></td>
                                    <?php elseif (trim($slots[$time]["name"]) === ""): ?>
                                        <td class="time-col empty">
                                            <a href="#popup-form" onclick="openForm('<?= (int)$slots[$time]["id"] ?>', '<?= (int)($slots[$time]["onlyonline"] ?? 0) ?>'); return false;">+</a>
                                            <?php if ((int) ($slots[$time]["onlyonline"] ?? 0) !== 0): ?>
                                                <span title="Online only">🌍</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php else: ?>
                                        <td class="time-col occupied"><span>booked</span></td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mobile-hint centered muted code-font">
                Na mobilu lze tabulku posouvat vodorovně.
            </div>
        <?php endif; ?>
    </section>

    <section class="standardCard">
        <div class="text-font consultation-content">
            <p><em>Pro students na Masarykově univerzitě:</em> Pokud v tomto semestru odevzdáváte bakalářskou/diplomovou práci, v mém semináři III platí pravidlo, že hrubou verzi je nutné v draftu konzultovat měsíc předem. Berte prosím v úvahu, že
            <?php
            $month = (int) date("n");
            if ($month >= 7 && $month <= 12) {
                echo " na přelomu listopadu a prosince ";
            } else {
                echo " na přelomu dubna a května ";
            }
            ?>
            se uzavírají subjecty a nemůžu věnovat podrobnou pozornost závěrečnému přepisování práce. Na
            <?= $month >= 7 && $month <= 12 ? "listopad/prosinec" : "duben/květen" ?>
            je pro končící dobré počítat s jednou consultations draftu hrubé verze práce a jednou consultations (lze i by email) hrubé práce po odevzdání k případnému zapracování připomínek do finální podoby.</p>
        </div>
    </section>
<?php else: ?>
    <section class="standardCard">
        <div class="consultation-content">
            <a href="?">Rezervovat new date</a>
        </div>
    </section>
<?php endif; ?>

</main>

<div id="popup-backdrop" class="consultation-popup-backdrop" onclick="closeForm()"></div>

<div id="popup-form" class="form-popup code-font">
    <h2 class="text-font-bold">Rezervace dateu</h2>
    <form method="post" class="formEdit">
        <?= csrf_field() ?>
        <input type="hidden" name="slot_id" id="slot_id">
        <input type="hidden" name="onlyonline" id="onlyonline">

        <div class="formRow">
            <label for="firstname">Name:</label>
            <input id="firstname" type="text" name="firstname" required>
        </div>

        <div class="formRow">
            <label for="name">Příjmení:</label>
            <input id="name" type="text" name="name" required>
        </div>

        <div class="formRow">
            <label for="email">Email:</label>
            <input id="email" type="email" name="email" required>
        </div>

        <div class="formRow">
            <label for="form-select">Method consultations:</label>
            <select id="form-select" name="form">
                <option value="osobne">Osobně</option>
                <option value="zoom">Zoom</option>
                <option value="teams">MS Teams</option>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" class="button-link primary">Rezervovat</button>
            <button type="button" class="button-link" onclick="closeForm()">Cancel</button>
        </div>
    </form>
</div>

<script>
function openForm(slotId, onlyonline) {
    document.getElementById("slot_id").value = slotId;
    document.getElementById("onlyonline").value = onlyonline;

    const popup = document.getElementById("popup-form");
    const backdrop = document.getElementById("popup-backdrop");
    const select = document.getElementById("form-select");
    const personalOption = select.querySelector('option[value="osobne"]');

    if (parseInt(onlyonline, 10) !== 0) {
        select.value = "zoom";
        personalOption.disabled = true;
    } else {
        personalOption.disabled = false;
    }

    popup.style.display = "block";
    backdrop.style.display = "block";
}

function closeForm() {
    document.getElementById("popup-form").style.display = "none";
    document.getElementById("popup-backdrop").style.display = "none";
}
</script>

<?php if ($helper_on): ?>
<div id="helper-bot">
    <div id="chat-box" class="hidden"><div id="chat-content"></div></div>
    <img src="<?= htmlspecialchars($helper_image) ?>" alt="Helper" id="helper-img" />
</div>

<script>
$(function () {
    const universities = ["MUNI", "JAMU", "UPOL"];
    const degrees = ["Bc", "Mgr", "PhD"];
    const basic = ["S consultationsmi", "S psaním práce"];
    let selectedUniversity = null;
    let currentStep = "start";

    function showStartMessage() {
        $("#chat-content").html(`<div class="chat-question">Hledáš pomoc? Klikni na pikačua!</div>`);
    }

    function showBasicQuestion() {
        currentStep = "basic";
        const buttons = basic.map(u => `<button class="option-btn" data-value="${u}">${u}</button>`).join("");
        $("#chat-content").html(`
            <div class="chat-question">S čím ti můžu pomoci?</div>
            <div class="chat-options">${buttons}</div>
            <button class="back-btn">⬅ Back</button>
        `);
    }

    function showUniversityQuestion() {
        currentStep = "university";
        const buttons = universities.map(u => `<button class="option-btn" data-value="${u}">${u}</button>`).join("");
        $("#chat-content").html(`
            <div class="chat-question">Abych mohl pomoci, řekni mi, kde studuješ?</div>
            <div class="chat-options">${buttons}</div>
            <button class="back-btn">⬅ Back</button>
        `);
    }

    function showDegreeQuestion() {
        currentStep = "degree";
        const buttons = degrees.map(d => `<button class="option-btn" data-value="${d}">${d}</button>`).join("");
        $("#chat-content").html(`
            <div class="chat-question">Už jsme skoro tam! Ještě – které studium?</div>
            <div class="chat-options">${buttons}</div>
            <button class="back-btn">⬅ Back</button>
        `);
    }

    function resetChat() {
        selectedUniversity = null;
        currentStep = "start";
        showStartMessage();
    }

    setTimeout(() => {
        if ($("#chat-box").hasClass("hidden")) {
            $("#chat-box").removeClass("hidden");
            showStartMessage();
        }
    }, 10000);

    $("#helper-img").on("mouseenter", function () {
        if ($("#chat-box").hasClass("hidden")) {
            $("#chat-box").removeClass("hidden");
            showStartMessage();
        }
    });

    $("#helper-img").click(function (e) {
        e.stopPropagation();
        if (currentStep === "start") {
            showBasicQuestion();
        }
    });

    $(document).on("click", ".option-btn", function () {
        const value = $(this).data("value");

        if (currentStep === "basic") {
            switch (value) {
                case "S consultationsmi":
                    window.location.href = '/studevid/faq.php?school=konzu';
                    break;
                case "S psaním práce":
                    showUniversityQuestion();
                    break;
                default:
                    showUniversityQuestion();
            }
        } else if (currentStep === "university") {
            selectedUniversity = value;
            showDegreeQuestion();
        } else if (currentStep === "degree") {
            const degree = value;
            window.location.href = `/studevid/faq.php?school=${encodeURIComponent(selectedUniversity)}&degree=${encodeURIComponent(degree)}`;
        }
    });

    $(document).on("click", ".back-btn", function () {
        if (currentStep === "degree") {
            showUniversityQuestion();
        } else if (currentStep === "university") {
            showBasicQuestion();
        } else {
            resetChat();
        }
    });

    $(document).on("click", function (e) {
        if (!$(e.target).closest("#helper-bot").length) {
            if (!$(e.target).closest(".option-btn, .back-btn").length) {
                $("#chat-box").addClass("hidden");
                resetChat();
            }
        }
    });
});
</script>
<?php endif; ?>

</body>
</html>
