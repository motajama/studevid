<?php
/**
 * Students dashboard layout header
 * Používat po <body>.
 * Očekává:
 * - $students (pole students)
 * - volitelně $studentsToContact (pole jmen nebo ID students)
 */

$studentList = $students ?? [];
$studentsToContactRaw = $studentsToContact ?? [];

// normalizace seznamu "kontaktovat"
$contactNames = [];
$contactIds = [];

if (is_array($studentsToContactRaw)) {
    foreach ($studentsToContactRaw as $item) {
        if (is_array($item)) {
            if (!empty($item["name"])) {
                $contactNames[] = mb_strtolower(trim((string)$item["name"]));
            }
            if (isset($item["id"])) {
                $contactIds[] = (string)$item["id"];
            }
        } else {
            $value = trim((string)$item);
            if ($value !== "") {
                $contactNames[] = mb_strtolower($value);
                $contactIds[] = $value;
            }
        }
    }
}

$contactNames = array_unique($contactNames);
$contactIds = array_unique($contactIds);

// přidej flag
foreach ($studentList as &$student) {
    $studentNameNormalized = mb_strtolower(trim((string)($student["name"] ?? "")));
    $studentIdNormalized = (string)($student["id"] ?? "");

    $student["_needs_contact"] =
        in_array($studentNameNormalized, $contactNames, true) ||
        in_array($studentIdNormalized, $contactIds, true);
}
unset($student);

// seřaď: nejdřív ti ke kontaktování, pak abecedně
usort($studentList, function ($a, $b) {
    $aFlag = !empty($a["_needs_contact"]) ? 1 : 0;
    $bFlag = !empty($b["_needs_contact"]) ? 1 : 0;

    if ($aFlag !== $bFlag) {
        return $bFlag <=> $aFlag;
    }

    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
});

$currentStudentName = $selectedStudentName ?? null;
?>

<nav class="main-nav">
    <div class="nav-brand">
        <a href="?" class="text-font-bold">🎓 Students</a>
    </div>

    <div class="nav-search-container">
        <input
            type="text"
            id="studentSearch"
            class="code-font"
            placeholder="Hledat name nebo Student ID…"
            autocomplete="off"
            aria-label="Hledat students"
        >
        <div id="searchResults" class="search-results code-font"></div>
    </div>

    <div class="nav-actions">
        <button type="button" class="button-link subtle student-directory-toggle" id="toggleStudentDirectory">
            ☰ Seznam students
        </button>

        <a href="?add_student_form=1" class="button-link">➕ Add</a>

        <?php if (isset($_GET['student_id']) || isset($_GET['add_student_form']) || isset($_GET['edit_student_form']) || isset($_GET['inactive'])): ?>
            <a href="?" class="button-link">🏠 Home</a>
        <?php endif; ?>
    </div>
</nav>

<div class="header-meta code-font">
    <?php
        echo "Dnes je " . date("j. n. Y");
        if (!empty($currentStudentName)) {
            echo " | Prohlížíte: " . htmlspecialchars($currentStudentName);
        }
    ?>
</div>

<section class="student-directory" id="studentDirectory" hidden>
    <div class="student-directory-inner">
        <div class="student-directory-grid">
            <?php foreach ($studentList as $student): ?>
                <a
                    href="?student_id=<?= urlencode($student['id']) ?>"
                    class="student-directory-link<?= !empty($student["_needs_contact"]) ? ' student-directory-link-alert' : '' ?>"
                >
                    <strong>
                        <?= htmlspecialchars($student['name'] ?? 'Bez jména') ?>
                        <?php if (!empty($student["_needs_contact"])): ?>
                            <span aria-hidden="true"> •</span>
                        <?php endif; ?>
                    </strong>
                    <small>
                        <?php if ((string)($student['uco'] ?? '') !== '0' && trim((string)($student['uco'] ?? '')) !== ''): ?>Student ID: <?= htmlspecialchars($student['uco']) ?><?php endif; ?>
                        <?php if (!empty($student["_needs_contact"])): ?>
                            | dlouho bez consultations
                        <?php endif; ?>
                    </small>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<main>
<script>
$(document).ready(function() {
    const students = <?= json_encode($students ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const $searchInput = $('#studentSearch');
    const $resultsDiv = $('#searchResults');
    const $directory = $('#studentDirectory');
    const $toggleDirectory = $('#toggleStudentDirectory');

    function escapeHtml(text) {
        return $('<div>').text(text ?? '').html();
    }

    $toggleDirectory.on('click', function() {
        const isHidden = $directory.prop('hidden');
        $directory.prop('hidden', !isHidden);
    });

    $searchInput.on('input', function() {
        const term = ($(this).val() || '').toLowerCase().trim();
        $resultsDiv.empty();

        if (term.length < 2) {
            $resultsDiv.hide();
            return;
        }

        const filtered = students.filter(function(s) {
            const nameMatch = s.name && s.name.toLowerCase().includes(term);
            const ucoMatch = s.uco && s.uco.toString().includes(term);
            return nameMatch || ucoMatch;
        }).slice(0, 20);

        if (filtered.length > 0) {
            filtered.forEach(function(s) {
                $resultsDiv.append(`
                    <div class="search-result-item" data-id="${escapeHtml(String(s.id))}">
                        <strong>${escapeHtml(s.name || 'Bez jména')}</strong>
                        <small>(${escapeHtml(String(s.uco || '–'))})</small>
                    </div>
                `);
            });
            $resultsDiv.show();
        } else {
            $resultsDiv.append('<div style="cursor:default; color:#777;">Nenalezeno</div>');
            $resultsDiv.show();
        }
    });

    $(document).on('click', '.search-result-item[data-id]', function() {
        const id = $(this).data('id');
        window.location.href = '?student_id=' + encodeURIComponent(id);
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.nav-search-container').length) {
            $resultsDiv.hide();
        }
    });
});
</script>
