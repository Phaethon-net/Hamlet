<?php
// Hamlet patient/exam helpers. Filename + folder parsing for HVF data.
//
// Folder convention:
//   LASTNAME_Firstname_YYYYMMDD       (DoB at the end; no MRN in folder)
//
// Exam filename convention (PDF + XML pair, extension may be any case):
//   MRN_YYYYMMDD_HHMMSS_EYE_SERIAL_STRATEGY[_optional suffix].pdf
//
//   [0] MRN     (3 letters, e.g. "TES")
//   [1] date    YYYYMMDD
//   [2] time    HHMMSS
//   [3] eye     OD | OS | OU
//   [4] serial  device serial number (may contain spaces, e.g. "HFA 3")
//   [5] stgy    SCR (screening) | SFA (threshold)
//   [6+]        free-text suffix (optional)

function parse_folder($folderName) {
    // LAST_First_YYYYMMDD — but the surname may itself contain a space, and
    // first names occasionally contain spaces too. Anchor on the trailing
    // 8-digit DoB and split the rest on the first underscore.
    $dob = '';
    $stem = $folderName;
    if (preg_match('/^(.*)_(\d{8})$/', $folderName, $m)) {
        $stem = $m[1];
        $dob  = $m[2];
    }
    $parts = explode('_', $stem, 2);
    return [
        'last'  => $parts[0] ?? '',
        'first' => $parts[1] ?? '',
        'dob'   => $dob,
    ];
}

// Parse an exam basename. Returns null if it doesn't match.
function parse_exam($basename) {
    $stem = pathinfo($basename, PATHINFO_FILENAME);
    $ext  = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
    if ($ext !== 'pdf' && $ext !== 'xml') return null;

    $re = '/^(?<mrn>[^_]+)_(?<date>\d{8})_(?<time>\d{6})_(?<eye>OD|OS|OU)_(?<serial>[^_]+)_(?<strategy>SCR|SFA)(?:_(?<suffix>.*))?$/';
    if (!preg_match($re, $stem, $m)) return null;

    return [
        'mrn'      => $m['mrn'],
        'date'     => $m['date'],
        'time'     => $m['time'],
        'eye'      => $m['eye'],
        'serial'   => $m['serial'],
        'strategy' => $m['strategy'],
        'suffix'   => $m['suffix'] ?? '',
        'stem'     => $stem,
        'ext'      => $ext,
    ];
}

// Format the human label used on exam pills / menu rows.
function exam_label(array $info) {
    $d = $info['date'];
    $t = $info['time'];
    $dateFmt = substr($d, 0, 4) . '-' . substr($d, 4, 2) . '-' . substr($d, 6, 2);
    $timeFmt = substr($t, 0, 2) . ':' . substr($t, 2, 2);
    return $dateFmt . ' ' . $timeFmt . ' ' . $info['eye'] . ' ' . $info['strategy'];
}

// Case-insensitive directory listing of files matching a single extension.
function _list_ext($folderPath, $ext) {
    $out = [];
    if (!is_dir($folderPath)) return $out;
    $dh = @opendir($folderPath);
    if (!$dh) return $out;
    while (($f = readdir($dh)) !== false) {
        if ($f === '.' || $f === '..') continue;
        if (strtolower(pathinfo($f, PATHINFO_EXTENSION)) === $ext) {
            $out[] = $folderPath . DIRECTORY_SEPARATOR . $f;
        }
    }
    closedir($dh);
    return $out;
}

// Folders that contain at least one PDF whose filename date field == today.
function today_patients($path, $today) {
    $hits = [];
    $folders = glob(rtrim($path, "/\\") . '/*', GLOB_ONLYDIR);
    if (!is_array($folders)) return $hits;
    foreach ($folders as $f) {
        foreach (_list_ext($f, 'pdf') as $pdf) {
            $info = parse_exam(basename($pdf));
            if ($info && $info['date'] === $today) {
                $hits[] = $f;
                break;
            }
        }
    }
    natcasesort($hits);
    return $hits;
}

// Case-insensitive substring match on the folder basename. Folder name
// includes LASTNAME, Firstname and YYYYMMDD DoB so a single substring search
// covers all three.
function search_patients($path, $q) {
    $q = trim($q);
    if ($q === '') return [];
    $qLower = mb_strtolower($q);
    $hits = [];
    foreach (glob(rtrim($path, "/\\") . '/*', GLOB_ONLYDIR) as $f) {
        $base  = basename($f);
        $baseL = mb_strtolower($base);
        if (strpos($baseL, $qLower) !== false) {
            $hits[] = $f;
        }
    }
    natcasesort($hits);
    return $hits;
}

// All exams in a patient folder, sorted newest-first. Each entry contains
// both PDF and XML paths (xml may be null if absent) plus parsed metadata.
function list_patient_exams($folderPath) {
    $out = [];
    foreach (_list_ext($folderPath, 'pdf') as $pdfPath) {
        $base = basename($pdfPath);
        $info = parse_exam($base);
        if (!$info) continue;

        // Find the matching XML (same stem, .xml in any case).
        $xmlPath = null;
        foreach (['xml', 'XML', 'Xml'] as $cand) {
            $candPath = $folderPath . DIRECTORY_SEPARATOR . $info['stem'] . '.' . $cand;
            if (is_file($candPath)) { $xmlPath = $candPath; break; }
        }

        $out[] = [
            'pdf'      => $pdfPath,
            'xml'      => $xmlPath,
            'base'     => $base,
            'xmlBase'  => $xmlPath ? basename($xmlPath) : null,
            'date'     => $info['date'],
            'time'     => $info['time'],
            'eye'      => $info['eye'],
            'strategy' => $info['strategy'],
            'serial'   => $info['serial'],
            'label'    => exam_label($info),
            'sortkey'  => $info['date'] . $info['time'],
        ];
    }
    // Newest first
    usort($out, function ($a, $b) { return strcmp($b['sortkey'], $a['sortkey']); });
    return $out;
}

// Pick the OD and OS exams from the most recent visit (latest date) to
// auto-load into the dual panes.
function find_paired_exams(array $exams) {
    if (empty($exams)) return ['OD' => null, 'OS' => null];
    $latestDate = $exams[0]['date'];
    $od = null;
    $os = null;
    foreach ($exams as $ex) {
        if ($ex['date'] !== $latestDate) continue;
        if ($od === null && $ex['eye'] === 'OD') $od = $ex;
        if ($os === null && $ex['eye'] === 'OS') $os = $ex;
        if ($od && $os) break;
    }
    // Fallbacks: if only one eye on the latest date, fill the other slot
    // with the most recent exam of the missing eye from any date.
    if (!$od) {
        foreach ($exams as $ex) { if ($ex['eye'] === 'OD') { $od = $ex; break; } }
    }
    if (!$os) {
        foreach ($exams as $ex) { if ($ex['eye'] === 'OS') { $os = $ex; break; } }
    }
    return ['OD' => $od, 'OS' => $os];
}
