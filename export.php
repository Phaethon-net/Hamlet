<?php
require __DIR__ . '/config.php';   // gives us $path

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('ZipArchive extension not enabled. Enable php_zip in php.ini.');
}

$idx   = isset($_POST['idx']) ? (string) $_POST['idx'] : '';
$files = isset($_POST['files']) && is_array($_POST['files']) ? $_POST['files'] : [];

if ($idx === '' || count($files) === 0) {
    http_response_code(400);
    exit('No selection');
}

// Sanitise: $idx must resolve to a real folder under $path with no traversal.
$folder = realpath($path . $idx);
$root   = realpath($path);
if (!$folder || !$root || strpos($folder, $root) !== 0 || !is_dir($folder)) {
    http_response_code(400);
    exit('Bad patient');
}

$safeIdx = preg_replace('/[^A-Za-z0-9_-]/', '_', $idx);
$zipName = 'Hamlet_export_' . $safeIdx . '_' . date('Ymd_His') . '.zip';
$tmp = tempnam(sys_get_temp_dir(), 'ham');
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('Could not create ZIP');
}

$added = 0;
foreach ($files as $name) {
    if (!is_string($name)) continue;
    $base = basename($name);
    if ($base === '' || $base[0] === '.') continue;
    // Add the PDF (if any) and its matching XML companion (any case).
    $src = $folder . DIRECTORY_SEPARATOR . $base;
    if (is_file($src) && is_readable($src)) {
        $zip->addFile($src, $base);
        $added++;
        $stem = pathinfo($base, PATHINFO_FILENAME);
        foreach (['xml', 'XML', 'Xml'] as $cand) {
            $xmlPath = $folder . DIRECTORY_SEPARATOR . $stem . '.' . $cand;
            if (is_file($xmlPath) && is_readable($xmlPath)) {
                $zip->addFile($xmlPath, $stem . '.' . $cand);
                $added++;
                break;
            }
        }
    }
}
$zip->close();

if ($added === 0) {
    @unlink($tmp);
    http_response_code(404);
    exit('No matching files');
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: no-store');
header('Pragma: no-cache');
readfile($tmp);
@unlink($tmp);
