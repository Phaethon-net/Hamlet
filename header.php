<?php
// Emits the document <head> and opens <body>. Assumes config.php has run.
//
// Cache-bust each static asset by appending its file mtime as a query string.
// The HTML page itself uses Cache-Control: no-cache (set in index.php) so the
// browser always re-fetches it, but linked CSS/JS files are cached
// aggressively — without a version parameter a CSS update can land in the
// browser while the matching JS is still stale, breaking handlers.
function asset($rel) {
    $fs = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $v  = file_exists($fs) ? filemtime($fs) : '0';
    return htmlspecialchars($rel . '?v=' . $v, ENT_QUOTES);
}
?><!doctype html>
<html lang='en'>
<head>
    <meta charset='utf-8'>
    <title><?= htmlspecialchars($Sitle) ?></title>
    <meta name='description' content='<?= htmlspecialchars($Sitle) ?>'>
    <meta name='author' content='Phaethon'>
    <meta http-equiv='Cache-Control' content='no-cache, no-store, must-revalidate'>
    <meta http-equiv='Pragma' content='no-cache'>
    <meta http-equiv='Expires' content='0'>
    <link rel='stylesheet' href='<?= asset('css/styles.css') ?>'>
    <link rel='icon' href='<?= htmlspecialchars($favicon) ?>'>
    <script>
      window.HAMLET = {
        idx:     <?= json_encode($_GET['id'] ?? '') ?>,
        baseURL: <?= json_encode($baseURL) ?>,
        dataURL: <?= json_encode($dataURL) ?>,
        pdfjs:   <?= json_encode('js/pdfjs/build/pdf.mjs?v=' . (file_exists(__DIR__ . '/js/pdfjs/build/pdf.mjs') ? filemtime(__DIR__ . '/js/pdfjs/build/pdf.mjs') : '0')) ?>,
        worker:  <?= json_encode('js/pdfjs/build/pdf.worker.mjs?v=' . (file_exists(__DIR__ . '/js/pdfjs/build/pdf.worker.mjs') ? filemtime(__DIR__ . '/js/pdfjs/build/pdf.worker.mjs') : '0')) ?>
      };
    </script>
    <script type='module' src='<?= asset('js/pdfviewer.js') ?>'></script>
    <script defer src='<?= asset('js/exam.js') ?>'></script>
    <script defer src='<?= asset('js/search.js') ?>'></script>
</head>
<body>
