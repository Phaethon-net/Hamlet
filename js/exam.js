/* Hamlet exam page interactions:
 *  - left-click on .exam-row → load into left pane
 *  - right-click on .exam-row → load into right pane (no global contextmenu
 *    suppression — only the row itself stops the menu, fixing the
 *    HVF_Viewer document-wide block)
 *  - #btn-md / #btn-nb toggle their overlays
 *  - #btn-export POSTs the two visible PDFs to export.php
 */
(function () {
    'use strict';

    function init() {
        const rows = document.querySelectorAll('.exam-row');
        const paneL = document.getElementById('paneL');
        const paneR = document.getElementById('paneR');

        function loadInto(pane, row) {
            if (!pane || !row) return;
            const url  = row.dataset.pdf;
            const name = row.dataset.name || '';
            const eye  = (row.classList.contains('eye-od') ? 'OD'
                       : row.classList.contains('eye-os') ? 'OS' : 'OU');
            pane.dataset.pdf = url;
            const lbl = pane.querySelector('.pdf-label');
            if (lbl) lbl.textContent = eye + ' · ' + name;
            if (window.HamletPDF) window.HamletPDF.loadIntoPane(pane, url);
        }

        rows.forEach(row => {
            row.addEventListener('click', function (e) {
                if (e.button !== 0) return;
                e.preventDefault();
                loadInto(paneL, row);
            });
            row.addEventListener('contextmenu', function (e) {
                e.preventDefault();
                e.stopPropagation();
                loadInto(paneR, row);
            });
        });

        // Overlay toggles
        function showOverlay(id) {
            const el = document.getElementById(id);
            if (el) el.classList.add('show');
        }
        function hideOverlay(id) {
            const el = document.getElementById(id);
            if (el) el.classList.remove('show');
        }

        const btnMd = document.getElementById('btn-md');
        const btnNb = document.getElementById('btn-nb');
        if (btnMd) btnMd.addEventListener('click', () => showOverlay('md-overlay'));
        if (btnNb) btnNb.addEventListener('click', () => showOverlay('nb-overlay'));

        document.querySelectorAll('.overlay-close').forEach(x => {
            x.addEventListener('click', function () {
                const id = x.dataset.overlay;
                if (id) hideOverlay(id);
            });
        });
        // Click backdrop to dismiss
        document.querySelectorAll('.overlay').forEach(o => {
            o.addEventListener('click', function (e) {
                if (e.target === o) o.classList.remove('show');
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.overlay.show').forEach(o => o.classList.remove('show'));
            }
        });

        // Export the two currently displayed PDFs
        const btnExport = document.getElementById('btn-export');
        if (btnExport) {
            btnExport.addEventListener('click', function () {
                const idx = (window.HAMLET && window.HAMLET.idx) || '';
                const baseURL = (window.HAMLET && window.HAMLET.baseURL) || '/Hamlet/';
                if (!idx) return;
                const files = [];
                [paneL, paneR].forEach(p => {
                    if (!p || !p.dataset.pdf) return;
                    // The URL ends in /<encoded basename>; strip the path.
                    const u = p.dataset.pdf;
                    const base = decodeURIComponent(u.substring(u.lastIndexOf('/') + 1));
                    if (base) files.push(base);
                });
                if (!files.length) return;

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = baseURL.replace(/\/+$/, '/') + 'export.php';
                const idxIn = document.createElement('input');
                idxIn.type  = 'hidden'; idxIn.name = 'idx'; idxIn.value = idx;
                form.appendChild(idxIn);
                files.forEach(name => {
                    const f = document.createElement('input');
                    f.type = 'hidden'; f.name = 'files[]'; f.value = name;
                    form.appendChild(f);
                });
                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
