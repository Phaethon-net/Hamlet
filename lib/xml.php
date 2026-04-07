<?php
// Hamlet XML reader. Handles both Zeiss "CZM-XML" pre-Forum format and the
// DICOM/Forum tag-pair format. Lifted from HVF_Viewer/MD.php and tidied.

function _xml_format_dicom_date($s) {
    if (strlen($s) < 8) return $s;
    return substr($s, 0, 4) . '-' . substr($s, 4, 2) . '-' . substr($s, 6, 2);
}

// Parse a single exam XML. Returns null on failure.
// Returned shape: ['date','md','psd','vfi','fp','fn']
//   date is YYYY-MM-DD; numeric fields are floats or null.
function read_exam_xml($xmlPath) {
    if (!is_file($xmlPath) || !is_readable($xmlPath)) return null;

    // Sniff the second line for the "CZM-XML" marker (mirrors MD.php).
    $fh = @fopen($xmlPath, 'r');
    if (!$fh) return null;
    $line0 = fgets($fh);
    $line1 = (string) fgets($fh);
    fclose($fh);
    $isCzm = (strpos($line1, 'CZM-XML') !== false);

    libxml_use_internal_errors(true);
    $xml = @simplexml_load_file($xmlPath);
    if ($xml === false) {
        libxml_clear_errors();
        return null;
    }

    $date = null; $md = null; $psd = null; $vfi = null; $fp = null; $fn = null;

    if ($isCzm && isset($xml->DataSet->CZM_HFA_EMR_IOD)) {
        $iod    = $xml->DataSet->CZM_HFA_EMR_IOD;
        $series = $iod->CZM_HFA_Series_M ?? null;
        $anal   = $iod->CZM_HFA_Analysis_M ?? null;
        if ($series) {
            $date = (string) $series->test_date;
            $fpRaw = (string) ($series->false_positives->false_positive_percent ?? '');
            $fnRaw = (string) ($series->false_negatives->false_negative_percent ?? '');
            $fp = ($fpRaw === '' || (float)$fpRaw < 0) ? null : (float)$fpRaw;
            $fn = ($fnRaw === '' || (float)$fnRaw < 0) ? null : (float)$fnRaw;
        }
        if ($anal) {
            $mdRaw  = (string) $anal->mean_deviation;
            $psdRaw = (string) $anal->pattern_standard_deviation;
            $vfiRaw = (string) $anal->visual_field_index;
            $md  = ($mdRaw  === '') ? null : (float)$mdRaw;
            $psd = ($psdRaw === '') ? null : (float)$psdRaw;
            $vfi = ($vfiRaw === '') ? null : (float)$vfiRaw;
        }
    } else {
        // DICOM / Forum tag-pair format
        $els = $xml->{'data-set'}->element ?? null;
        if ($els) {
            foreach ($els as $el) {
                $tag = (string) $el['tag'];
                $val = (string) $el;
                switch ($tag) {
                    case '0040,0244': $date = _xml_format_dicom_date($val); break;
                    case '7717,1010': $fp   = $val === '' ? null : (float)$val; break;
                    case '7717,1013': $fn   = $val === '' ? null : (float)$val; break;
                    case '7717,1016': $md   = $val === '' ? null : (float)$val; break;
                    case '7717,1034': $vfi  = $val === '' ? null : (float)$val; break;
                }
            }
        }
    }

    libxml_clear_errors();
    return [
        'date' => $date,
        'md'   => $md,
        'psd'  => $psd,
        'vfi'  => $vfi,
        'fp'   => $fp,
        'fn'   => $fn,
    ];
}

// Build OD/OS time series for a patient folder, filtered to SFA (threshold)
// exams only — matches HVF_Viewer's MD.php behaviour.
// Each row: [dateString, md, fp, fn, vfi]
function series_for_patient($folderPath, array $exams) {
    $od = [];
    $os = [];
    foreach ($exams as $ex) {
        if ($ex['strategy'] !== 'SFA') continue;
        if (!$ex['xml']) continue;
        $r = read_exam_xml($ex['xml']);
        if ($r === null) continue;
        $row = [$r['date'], $r['md'], $r['fp'], $r['fn'], $r['vfi']];
        if ($ex['eye'] === 'OD') $od[] = $row;
        if ($ex['eye'] === 'OS') $os[] = $row;
    }
    // Series should read oldest -> newest along the X axis.
    $byDate = function ($a, $b) { return strcmp((string)$a[0], (string)$b[0]); };
    usort($od, $byDate);
    usort($os, $byDate);
    return ['OD' => $od, 'OS' => $os];
}

// Optional NB.txt note for a patient folder. Returns escaped HTML or ''.
function read_nb_text($folderPath) {
    $nb = $folderPath . DIRECTORY_SEPARATOR . 'NB.txt';
    if (!is_file($nb)) return '';
    $raw = @file_get_contents($nb);
    if ($raw === false) return '';
    $safe = htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');
    return nl2br($safe);
}
