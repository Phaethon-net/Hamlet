// Hamlet PDF.js wrapper. Loads as a module so it can `import` PDF.js itself.
// Exposes its API on window.HamletPDF so the non-module exam.js can call it.

import * as pdfjsLib from './pdfjs/build/pdf.mjs';

// PDF.js needs the worker URL configured before any getDocument call.
// header.php emits the cache-busted path on window.HAMLET.
pdfjsLib.GlobalWorkerOptions.workerSrc =
    (window.HAMLET && window.HAMLET.worker) || 'js/pdfjs/build/pdf.worker.mjs';

const dpr = Math.max(1, window.devicePixelRatio || 1);
const cache = new Map();   // url -> PDFDocumentProxy

async function getDoc(url) {
    if (cache.has(url)) return cache.get(url);
    const task = pdfjsLib.getDocument({ url });
    const doc  = await task.promise;
    cache.set(url, doc);
    return doc;
}

function clearPaneCanvases(paneEl) {
    paneEl.querySelectorAll('canvas[data-hamlet]').forEach(c => c.remove());
}

// Render every page of the doc into the pane, fit-to-height for page 1
// (HVF reports are virtually always single-page). The label overlay
// rendered server-side stays in place; we just append canvas siblings.
async function renderDocIntoPane(paneEl, doc) {
    clearPaneCanvases(paneEl);

    const paneH = paneEl.clientHeight - 4;
    const paneW = paneEl.clientWidth  - 4;
    if (paneH <= 0 || paneW <= 0) return;

    for (let pageNum = 1; pageNum <= doc.numPages; pageNum++) {
        const page = await doc.getPage(pageNum);
        const v1   = page.getViewport({ scale: 1 });

        // Fit-by-height first; if that overflows the pane width, drop to
        // fit-by-width so the user always sees the full page width without
        // horizontal scroll. The user explicitly asked for "fill the
        // available height", so this prefers height when there's room.
        let scale = paneH / v1.height;
        if (v1.width * scale > paneW) {
            scale = paneW / v1.width;
        }
        const vp = page.getViewport({ scale });

        const canvas = document.createElement('canvas');
        canvas.dataset.hamlet = '1';
        canvas.width  = Math.floor(vp.width  * dpr);
        canvas.height = Math.floor(vp.height * dpr);
        canvas.style.width  = Math.floor(vp.width)  + 'px';
        canvas.style.height = Math.floor(vp.height) + 'px';

        const ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr);
        await page.render({ canvasContext: ctx, viewport: vp }).promise;
        paneEl.appendChild(canvas);
    }
}

async function loadIntoPane(paneEl, url) {
    if (!paneEl || !url) return;
    paneEl.dataset.pdf = url;
    try {
        const doc = await getDoc(url);
        await renderDocIntoPane(paneEl, doc);
    } catch (err) {
        console.error('[Hamlet] PDF load failed:', url, err);
        clearPaneCanvases(paneEl);
        const msg = document.createElement('div');
        msg.dataset.hamlet = '1';
        msg.textContent = 'Failed to load PDF';
        msg.style.cssText = 'color:#ff8;padding:20px;text-align:center;';
        msg.tagName === 'CANVAS' || (paneEl.appendChild(msg));
    }
}

function loadAllPanes() {
    document.querySelectorAll('.pdf-pane[data-pdf]').forEach(p => {
        const url = p.dataset.pdf;
        if (url) loadIntoPane(p, url);
    });
}

// Re-render on resize so the fit-to-height stays correct.
let resizeTimer = null;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(loadAllPanes, 150);
});

window.HamletPDF = { loadIntoPane, loadAllPanes };

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadAllPanes);
} else {
    loadAllPanes();
}
