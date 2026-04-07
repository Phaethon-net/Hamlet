<?php
require __DIR__ . '/config.php';

// Real HTTP cache headers for the HTML page itself. Proxies honour these
// (the <meta http-equiv> tags in header.php are only hints to the browser,
// not the proxy). Static assets are versioned in their URL via header.php's
// asset() helper and cached as immutable by web.config / .htaccess.
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$idx  = isset($_GET['id'])   ? (string) $_GET['id'] : '0';
$q    = isset($_GET['q'])    ? trim((string) $_GET['q']) : '';
$view = isset($_GET['view']) ? (string) $_GET['view'] : 'dual';
if (!in_array($view, ['dual', 'all', 'allR', 'allL', 'allO'], true)) $view = 'dual';

include __DIR__ . '/header.php';
include __DIR__ . '/banner.php';

render_index_row($file, $idx, $q);

// Routing
if ($q !== '') {
    render_patient_list(search_patients($path, $q), $file);

} elseif (strlen($idx) > 2) {
    // Patient detail (menu + dual PDF panes). Validate against $path with
    // realpath to prevent directory traversal.
    $folderPath = $path . $idx;
    $real = realpath($folderPath);
    $root = realpath($path);
    if ($real && $root && strpos($real, $root) === 0 && is_dir($real)) {
        render_patient_detail($real, $file, $dataURL, $idx, $view);
    } else {
        echo "<div class='emptylist'>Unknown patient.</div>";
    }

} elseif ($idx === '0' || $idx === '') {
    // Default = TODAY
    render_patient_list(today_patients($path, $today), $file);

} else {
    // Alphabetic subgroup
    $folders = glob(rtrim($path, "/\\") . '/' . $idx . '*', GLOB_ONLYDIR);
    if (!is_array($folders)) $folders = [];
    natcasesort($folders);
    render_patient_list($folders, $file);
}

include __DIR__ . '/footer.php';
