<?php
// HTML emitters. Kept dumb on purpose so index.php remains a thin controller.

function render_index_row($file, $currentIdx, $currentQ) {
    echo "<div id='index'>";
    echo "<hr>";
    echo "<a class='index0" . ($currentIdx === '0' ? ' active' : '') . "' href='" . htmlspecialchars($file) . "?id=0'><strong>TODAY</strong></a>";
    foreach (range('A', 'Z') as $L) {
        $cls = ($currentIdx === $L) ? 'index active' : 'index';
        echo "<a class='$cls' href='" . htmlspecialchars($file) . "?id=$L'><strong>$L</strong></a>";
    }
    $atCls = ($currentIdx === '@') ? 'index active' : 'index';
    echo "<a class='$atCls' href='" . htmlspecialchars($file) . "?id=@'><strong>&#64;</strong></a>";
    echo "<form class='searchform' method='get' action='" . htmlspecialchars($file) . "'>";
    echo "<input type='text' name='q' id='searchq' placeholder='search name or DoB&hellip;' value='" . htmlspecialchars($currentQ) . "' autocomplete='off'>";
    echo "<button type='button' class='clearq' title='Clear'>&times;</button>";
    echo "</form>";
    echo "<hr>";
    echo "</div>";
}

// Patient list cards used by TODAY / alphabetic / search results.
function render_patient_list(array $folders, $file) {
    if (empty($folders)) {
        echo "<div class='emptylist'>No patients found.</div>";
        return;
    }
    foreach ($folders as $folder) {
        $base = basename($folder);
        $info = parse_folder($base);
        $dobFmt = '';
        if ($info['dob'] !== '') {
            $dt = DateTime::createFromFormat('Ymd', $info['dob']);
            if ($dt) $dobFmt = $dt->format('d-M-Y');
        }
        echo "<div class='div_wrap'>";
        echo "  <div class='div_pat'>";
        echo "    <span class='namelabel'>" . htmlspecialchars($info['last']) . ", " . htmlspecialchars($info['first']) . "</span><br/>";
        echo "    <span class='idlabel'>" . htmlspecialchars($dobFmt) . "</span>";
        echo "  </div>";

        $exams = list_patient_exams($folder);
        if (!empty($exams)) {
            $href = htmlspecialchars($file) . "?id=" . urlencode($base);
            echo "  <div class='div_list'>";
            echo "    <a class='datepill' href='$href'>" . count($exams) . " exam" . (count($exams) === 1 ? '' : 's') . " &raquo;</a>";
            // Show the most recent few labels as a hint
            $hint = [];
            foreach (array_slice($exams, 0, 4) as $ex) $hint[] = htmlspecialchars($ex['label']);
            echo "    <span class='hint'>" . implode(' &middot; ', $hint) . (count($exams) > 4 ? ' &hellip;' : '') . "</span>";
            echo "  </div>";
        }
        echo "</div>";
        echo "<hr>";
    }
}

// Patient detail page: header + left menu of exams + a PDF presentation
// area that can be in any of five modes:
//   dual  - two pdf panes side by side (OD left, OS right)
//   all   - horizontal strip of every exam, newest-first, OD-before-OS
//   allR  - OD only strip
//   allL  - OS only strip
//   allO  - OU only strip (binocular / unknown laterality)
function render_patient_detail($folder, $file, $dataURL, $idx, $view = 'dual') {
    $base = basename($folder);
    $info = parse_folder($base);
    $dobFmt = '';
    if ($info['dob'] !== '') {
        $dt = DateTime::createFromFormat('Ymd', $info['dob']);
        if ($dt) $dobFmt = $dt->format('d-M-Y');
    }

    $exams = list_patient_exams($folder);
    $pair  = find_paired_exams($exams);
    $encIdx = rawurlencode($idx);

    $pdfUrlOf = function ($ex) use ($dataURL, $encIdx) {
        if (!$ex) return '';
        return $dataURL . $encIdx . '/' . rawurlencode($ex['base']);
    };
    $leftPdf  = $pdfUrlOf($pair['OD']); // OD on left pane
    $rightPdf = $pdfUrlOf($pair['OS']); // OS on right pane

    echo "<div class='detail'>";

    // Left menu — patient identity sits at the top, then the view-mode
    // buttons (including MD and NB), then the scrolling list of exams.
    echo "<div class='menu-pane'>";
    echo "  <div class='patient-header'>";
    echo "    <div class='namelabel'>" . htmlspecialchars($info['last']) . ", " . htmlspecialchars($info['first']) . "</div>";
    echo "    <div class='idlabel'>" . htmlspecialchars($dobFmt) . "</div>";
    echo "  </div>";

    // View-mode + MD/NB/Export toolbar
    $modes = [
        'dual' => 'Dual',
        'all'  => 'All',
        'allR' => 'All OD',
        'allL' => 'All OS',
        'allO' => 'All OU',
    ];
    $base = htmlspecialchars($file) . "?id=" . urlencode($idx);
    echo "  <div class='menu-toolbar'>";
    foreach ($modes as $m => $label) {
        $cls = ($view === $m) ? 'mode-btn active' : 'mode-btn';
        echo "    <a class='$cls' href='$base&amp;view=$m'>$label</a>";
    }
    echo "    <button id='btn-md' class='menu-btn'>MD</button>";
    echo "    <button id='btn-nb' class='menu-btn'>NB</button>";
    echo "    <button id='btn-export' class='menu-btn'>Export</button>";
    echo "  </div>";
    if (empty($exams)) {
        echo "  <div class='emptylist'>No exams.</div>";
    } else {
        echo "  <div class='exam-list'>";
        foreach ($exams as $ex) {
            $url = $pdfUrlOf($ex);
            $eyeCls = strtolower($ex['eye']);
            $inPane = '';
            // Only in dual mode does the "currently shown in pane L/R" cue
            // make sense — the strip modes show every exam at once.
            if ($view === 'dual') {
                if ($pair['OD'] && $ex['base'] === $pair['OD']['base']) $inPane .= ' in-pane-l';
                if ($pair['OS'] && $ex['base'] === $pair['OS']['base']) $inPane .= ' in-pane-r';
            }
            $title = ($view === 'dual')
                ? 'Left-click: load left pane &nbsp;&nbsp; Right-click: load right pane'
                : 'Click to scroll the strip to this exam';
            echo "    <div class='exam-row eye-$eyeCls$inPane'"
               . " data-pdf='" . htmlspecialchars($url, ENT_QUOTES)
               . "' data-name='" . htmlspecialchars($ex['base'], ENT_QUOTES) . "'"
               . " title='$title'>";
            echo      "<span class='ex-eye'>" . htmlspecialchars($ex['eye']) . "</span>";
            echo      "<span class='ex-strat'>" . htmlspecialchars($ex['strategy']) . "</span>";
            echo      "<span class='ex-when'>" . htmlspecialchars(substr($ex['date'],0,4) . '-' . substr($ex['date'],4,2) . '-' . substr($ex['date'],6,2) . ' ' . substr($ex['time'],0,2) . ':' . substr($ex['time'],2,2)) . "</span>";
            echo    "</div>";
        }
        echo "  </div>";
    }
    echo "</div>"; // .menu-pane

    // Right-hand presentation area — full height, no chrome above it.
    echo "<div class='pdf-area'>";

    if ($view === 'dual') {
        echo "  <div class='pdf-panes mode-dual'>";
        echo "    <div class='pdf-pane' id='paneL' data-pdf='" . htmlspecialchars($leftPdf, ENT_QUOTES) . "'>";
        echo "      <div class='pdf-label'>" . ($pair['OD'] ? 'OD &middot; ' . htmlspecialchars($pair['OD']['label']) : 'No OD') . "</div>";
        echo "    </div>";
        echo "    <div class='pdf-pane' id='paneR' data-pdf='" . htmlspecialchars($rightPdf, ENT_QUOTES) . "'>";
        echo "      <div class='pdf-label'>" . ($pair['OS'] ? 'OS &middot; ' . htmlspecialchars($pair['OS']['label']) : 'No OS') . "</div>";
        echo "    </div>";
        echo "  </div>";
    } else {
        $stripExams = exams_for_mode($exams, $view);
        echo "  <div class='pdf-panes mode-strip'>";
        if (empty($stripExams)) {
            echo "    <div class='emptylist'>No exams in this view.</div>";
        } else {
            foreach ($stripExams as $ex) {
                $url = $pdfUrlOf($ex);
                echo "    <div class='pdf-pane strip-pane' data-pdf='" . htmlspecialchars($url, ENT_QUOTES)
                   . "' data-name='" . htmlspecialchars($ex['base'], ENT_QUOTES) . "'>";
                echo "      <div class='pdf-label'>" . htmlspecialchars($ex['eye'] . ' · ' . $ex['label']) . "</div>";
                echo "    </div>";
            }
        }
        echo "  </div>";
    }

    echo "</div>"; // .pdf-area
    echo "</div>"; // .detail

    // MD overlay (Google Charts)
    render_md_overlay($folder, $exams);

    // NB overlay
    $nb = read_nb_text($folder);
    echo "<div id='nb-overlay' class='overlay'>";
    echo "  <div class='overlay-inner'>";
    echo "    <div class='overlay-header'>NB notes <span class='overlay-close' data-overlay='nb-overlay'>&times;</span></div>";
    echo "    <div class='overlay-body'>" . ($nb !== '' ? $nb : "<em>No NB.txt for this patient.</em>") . "</div>";
    echo "  </div>";
    echo "</div>";

    // Hand the patient idx to JS for export
    echo "<script>window.HAMLET = window.HAMLET || {}; window.HAMLET.idx = " . json_encode($idx) . ";</script>";
}

// MD overlay: emits the Google Charts loader + chart init for OD and OS.
// Only rendered on the patient detail page so other pages stay offline.
function render_md_overlay($folder, array $exams) {
    $series = series_for_patient($folder, $exams);
    $od = $series['OD'];
    $os = $series['OS'];

    echo "<div id='md-overlay' class='overlay'>";
    echo "  <div class='overlay-inner'>";
    echo "    <div class='overlay-header'>MD trend (SFA only) <span class='overlay-close' data-overlay='md-overlay'>&times;</span></div>";
    echo "    <div class='overlay-body'>";
    echo "      <div id='OD_chart' class='md-chart'></div>";
    echo "      <div id='OS_chart' class='md-chart'></div>";
    if (empty($od) && empty($os)) {
        echo "    <div class='emptylist'>No SFA threshold exams with parsed XML.</div>";
    }
    echo "    </div>";
    echo "  </div>";
    echo "</div>";

    // Only load Google Charts if there's something to draw
    if (empty($od) && empty($os)) return;

    $rows = function ($series) {
        $out = [];
        foreach ($series as $row) {
            $cells = [];
            $cells[] = "'" . addslashes((string)$row[0]) . "'";
            for ($i = 1; $i <= 4; $i++) {
                $cells[] = ($row[$i] === null || $row[$i] === '') ? 'null' : (string)$row[$i];
            }
            $out[] = '[' . implode(',', $cells) . ']';
        }
        return implode(',', $out);
    };
    $odRows = $rows($od);
    $osRows = $rows($os);
    ?>
    <script src="https://www.gstatic.com/charts/loader.js"></script>
    <script>
    (function () {
        if (typeof google === 'undefined') return;
        google.charts.load('current', { packages: ['corechart'] });
        google.charts.setOnLoadCallback(drawHamletCharts);

        function buildOptions(title) {
            return {
                title: title,
                width: 800,
                height: 360,
                pointsVisible: true,
                legend: { position: 'bottom' },
                crosshair: { trigger: 'both' },
                interpolateNulls: true,
                backgroundColor: '#fff',
                series: {
                    0: { targetAxisIndex: 0, color: 'darkgreen' },
                    1: { targetAxisIndex: 1, color: 'red',    lineDashStyle: [2, 2] },
                    2: { targetAxisIndex: 1, color: 'blue',   lineDashStyle: [2, 2] },
                    3: { targetAxisIndex: 1, color: 'orange', lineDashStyle: [2, 2] }
                },
                vAxes: {
                    0: { title: 'Mean Deviation', maxValue: 10, minValue: -20 },
                    1: { title: 'Errors / VFI',   maxValue: 100, minValue: 0  }
                }
            };
        }

        function buildTable(rows) {
            var d = new google.visualization.DataTable();
            d.addColumn('string', 'Date');
            d.addColumn('number', 'Mean Deviation');
            d.addColumn('number', 'False(+)');
            d.addColumn('number', 'False(-)');
            d.addColumn('number', 'VFI');
            if (rows.length) d.addRows(rows);
            return d;
        }

        function drawHamletCharts() {
            var odRows = [<?= $odRows ?>];
            var osRows = [<?= $osRows ?>];
            if (odRows.length) {
                new google.visualization.LineChart(document.getElementById('OD_chart'))
                    .draw(buildTable(odRows), buildOptions('Right Eye (OD)'));
            }
            if (osRows.length) {
                new google.visualization.LineChart(document.getElementById('OS_chart'))
                    .draw(buildTable(osRows), buildOptions('Left Eye (OS)'));
            }
        }
    })();
    </script>
    <?php
}
