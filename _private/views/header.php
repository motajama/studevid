<?php
/**
 * Shared HEAD partial
 * Používat uvnitř <head> ... </head>
 * Necontentuje <html>, <body>, navigaci ani <main>.
 */

$globalCssPath = $globalCssPath ?? '/studevid/css/global.css';
$dashboardCssPath = $dashboardCssPath ?? null;
$extraCssFiles = $extraCssFiles ?? [];
$loadJquery = $loadJquery ?? true;
$loadChartJs = $loadChartJs ?? false;
$loadDesignJs = $loadDesignJs ?? false;
$designJsPath = $designJsPath ?? '/studevid/js/design.js';
?>
<meta charset="UTF-8" />
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link
    href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@300..700&family=Vollkorn:ital,wght@0,400..900;1,400..900&display=swap"
    rel="stylesheet"
/>

<link rel="stylesheet" href="<?= htmlspecialchars($globalCssPath, ENT_QUOTES) ?>" />

<?php if (!empty($dashboardCssPath)): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($dashboardCssPath, ENT_QUOTES) ?>" />
<?php endif; ?>

<?php foreach ($extraCssFiles as $cssFile): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($cssFile, ENT_QUOTES) ?>" />
<?php endforeach; ?>

<?php if ($loadJquery): ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<?php endif; ?>

<?php if ($loadChartJs): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php endif; ?>

<?php if ($loadDesignJs): ?>
    <script src="<?= htmlspecialchars($designJsPath, ENT_QUOTES) ?>"></script>
<?php endif; ?>

<script src="/studevid/js/security.js"></script>
