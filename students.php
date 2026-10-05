<?php
require_once __DIR__ . '/_private/students-controller.php';

$selectedStudentName = $selectedStudentName ?? null;

// Shared header configuration
$globalCssPath = "/studevid/css/global.css";
$dashboardCssPath = "/studevid/css/dashboard.css";
$extraCssFiles = [];
$loadJquery = true;
$loadChartJs = true;
$loadDesignJs = true;
$designJsPath = "js/design.js";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Studentské consultations</title>
    <?php include __DIR__ . "/_private/views/header.php"; ?>
</head>
<body>

<?php include __DIR__ . "/_private/views/site-header.php"; ?>

<h1 class="text-font">Studentské consultations</h1>

<?php if (!empty($studentsToContact) && count($studentsToContact) > 0): ?>
    <header class="code-font" style="max-width: var(--content-width); margin: 0 auto; padding: 0 1rem;">
        <div class="standardCard" style="margin-top: 1rem; margin-bottom: 1rem;">
            <p style="margin: 0 0 .75rem;"><strong>Students to contact:</strong></p>
            <div class="contact-list" data-unbooked="<?= h($studentsToContactUnbookedString) ?>" data-booked="<?= h($studentsToContactBookedString) ?>" data-emails-unbooked="<?= h($studentsEmailsUnbookedString) ?>" data-emails-booked="<?= h($studentsEmailsBookedString) ?>">
                <div><span id="contact-list-label" class="status-pill" style="background:#fff3cd">⚠ bez consultations a bez booking</span> <strong id="contact-list-text"><?= htmlspecialchars($studentsToContactUnbookedString ?: 'Nikdo') ?></strong></div>
                <button
                    type="button"
                    class="button-link subtle"
                    style="margin-left: 0.75rem;"
                    id="contact-copy"
                    data-copy="<?= h($studentsEmailsUnbookedString) ?>"
                >
                    📩 Copy emails
                </button>
                <button type="button" class="button-link subtle" id="contact-toggle" style="margin-left:.5rem">s booking</button>
            </div>
        </div>
    </header>
<?php endif; ?>

<?php if (!empty($showHomepage)): ?>
    <?php include __DIR__ . "/_private/views/homepage.php"; ?>
<?php endif; ?>

<?php if (!empty($showInactive)): ?>
    <?php include __DIR__ . "/_private/views/homepage-inactive.php"; ?>
<?php endif; ?>

<?php if (!empty($showAddStudentForm)): ?>
    <?php include __DIR__ . "/_private/views/add-student-form.php"; ?>
<?php elseif (!empty($showEditStudentForm)): ?>
    <?php include __DIR__ . "/_private/views/edit-student-form.php"; ?>
<?php elseif (!empty($selectedStudentId)): ?>
    <?php include __DIR__ . "/_private/views/selected-student-card.php"; ?>
<?php endif; ?>

</main>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert('Emails copied to clipboard.');
    }).catch(function() {
        alert('Copying failed.');
    });
}
const contactToggle = document.getElementById('contact-toggle');
if (contactToggle) contactToggle.addEventListener('click', function () {
    const box = this.closest('.contact-list'); const text = document.getElementById('contact-list-text');
    const label = document.getElementById('contact-list-label'); const copy = document.getElementById('contact-copy');
    const showBooked = this.dataset.mode !== 'booked';
    text.textContent = box.dataset[showBooked ? 'booked' : 'unbooked'] || 'Nikdo';
    label.textContent = showBooked ? '📅 bez consultations, ale s booking' : '⚠ bez consultations a bez booking';
    copy.dataset.copy = box.dataset[showBooked ? 'emailsBooked' : 'emailsUnbooked'] || '';
    this.dataset.mode = showBooked ? 'booked' : 'unbooked';
    this.textContent = showBooked ? 'bez booking' : 's booking';
});
</script>

</body>
</html>
