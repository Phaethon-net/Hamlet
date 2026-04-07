// Hamlet PDF.js wrapper. Loads as a module so it can `import` PDF.js itself.
// Exposes its API on window.HamletPDF so the non-module exam.js can call it.

import * as pdfjsLib from './pdfjs/build/pdf.mjs';

// PDF.js needs the worker URL configured before any getDocument call.
// header.php emits the cache-busted path on window.HAMLET.
pdfjsLib.GlobalWorkerOptions.workerSrc =
    (window.HAMLET && window.HAMLET.worker) || 'js/pdfjs/build/pdf.worker.mjs';

const dpr = Math.max(1, window.devicePixelRatio || 1);
const cache = new Map();   // url -> PDFDocumentProxy

// Per-pane zoom multiplier on top of the fit-to-height base scale.
// Stored by pane element so it survives resizes and exam swaps inside the
// same pane, but resets when a different exam is loaded.
const paneZoom = new WeakMap();   // paneEl -> number

const MIN_ZOOM = 0.4;
const MAX_ZOOM = 6.0;
const ZOOM_STEP = 1.15;

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

// Render every page of the doc into the pane. Uses fit-to-height (with
// fit-to-width fallback for oblong pages) as the base scale, multiplied
// by the per-pane zoom factor. When the resulting canvas exceeds the pane
// on either axis, .pdf-pane { overflow: auto } lets the user scroll or
// click-drag to pan (see the wire-up below).
async function renderDocIntoPane(paneEl, doc) {
    clearPaneCanvases(paneEl);

    // Available viewport inside the pane (excluding the 3px border).
    const paneH = paneEl.clientHeight - 6;
    const paneW = paneEl.clientWidth  - 6;
    if (paneH <= 0 || paneW <= 0) return;

    const zoom = paneZoom.get(paneEl) || 1.0;

    for (let pageNum = 1; pageNum <= doc.numPages; pageNum++) {
        const page = await doc.getPage(pageNum);
        const v1   = page.getViewport({ scale: 1 });

        // Fit-by-height first; drop to fit-by-width if that would overflow
        // horizontally at zoom 1.0. Zoom then scales the result up/down.
        let baseScale = paneH / v1.height;
        if (v1.width * baseScale > paneW) {
            baseScale = paneW / v1.width;
        }
        const scale = baseScale * zoom;
        const vp = page.getViewport({ scale });

        const canvas = document.createElement('canvas');
        canvas.dataset.hamlet = '1';
        canvas.width  = Math.floor(vp.width  * dpr);
        canvas.height = Math.floor(vp.height * dpr);
        canvas.style.width  = Math.floor(vp.width)  + 'px';
        canvas.style.height = Math.floor(vp.height) + 'px';
        canvas.style.display = 'block';
        canvas.style.margin  = '0 auto';

        const ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr);
        await page.render({ canvasContext: ctx, viewport: vp }).promise;
        paneEl.appendChild(canvas);
    }
}

async function loadIntoPane(paneEl, url) {
    if (!paneEl || !url) return;
    const prev = paneEl.dataset.pdf;
    paneEl.dataset.pdf = url;
    // Reset zoom when switching to a different PDF.
    if (prev !== url) {
        paneZoom.set(paneEl, 1.0);
        paneEl.scrollTop = 0;
        paneEl.scrollLeft = 0;
    }
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
        paneEl.appendChild(msg);
    }
}

async function rerenderPane(paneEl) {
    const url = paneEl.dataset.pdf;
    if (!url) return;
    try {
        const doc = await getDoc(url);
        await renderDocIntoPane(paneEl, doc);
    } catch (err) {
        console.error('[Hamlet] PDF re-render failed:', url, err);
    }
}

function loadAllPanes() {
    document.querySelectorAll('.pdf-pane[data-pdf]').forEach(p => {
        const url = p.dataset.pdf;
        if (url) loadIntoPane(p, url);
    });
}

// ======================================================================
// Per-pane ctrl+wheel zoom and click-drag pan.
// ======================================================================

function setZoom(paneEl, newZoom, anchorClientX, anchorClientY) {
    newZoom = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, newZoom));
    const oldZoom = paneZoom.get(paneEl) || 1.0;
    if (Math.abs(newZoom - oldZoom) < 1e-4) return;

    // Anchor the zoom at the cursor: compute the document-space point
    // under the cursor before the zoom, then after rendering at the new
    // zoom scroll the pane so the same point is still under the cursor.
    const rect = paneEl.getBoundingClientRect();
    const cx = (anchorClientX !== undefined) ? (anchorClientX - rect.left) : (paneEl.clientWidth  / 2);
    const cy = (anchorClientY !== undefined) ? (anchorClientY - rect.top)  : (paneEl.clientHeight / 2);
    const docX = paneEl.scrollLeft + cx;
    const docY = paneEl.scrollTop  + cy;
    const ratio = newZoom / oldZoom;

    paneZoom.set(paneEl, newZoom);
    rerenderPane(paneEl).then(() => {
        paneEl.scrollLeft = Math.max(0, docX * ratio - cx);
        paneEl.scrollTop  = Math.max(0, docY * ratio - cy);
    });
}

function wirePaneInteraction(paneEl) {
    if (paneEl._hamletWired) return;
    paneEl._hamletWired = true;

    // ctrl+wheel = zoom in/out anchored at the cursor. Without ctrl the
    // default scroll behaviour runs — the pane is overflow:auto so that
    // scrolls the zoomed canvas inside the pane rather than the page.
    paneEl.addEventListener('wheel', function (e) {
        if (!e.ctrlKey) return;                // let normal scroll work
        e.preventDefault();
        e.stopPropagation();
        const cur  = paneZoom.get(paneEl) || 1.0;
        const next = (e.deltaY < 0) ? cur * ZOOM_STEP : cur / ZOOM_STEP;
        setZoom(paneEl, next, e.clientX, e.clientY);
    }, { passive: false });

    // Click-drag pan. Any mousedown that doesn't hit the pdf-label starts
    // a drag; mousemove scrolls the pane; mouseup/leave ends it.
    let dragging = false;
    let startX = 0, startY = 0, startLeft = 0, startTop = 0;

    paneEl.addEventListener('mousedown', function (e) {
        if (e.button !== 0) return;
        // Don't start a drag when clicking on the floating label overlay.
        if (e.target.closest && e.target.closest('.pdf-label')) return;
        dragging = true;
        startX = e.clientX; startY = e.clientY;
        startLeft = paneEl.scrollLeft; startTop = paneEl.scrollTop;
        paneEl.classList.add('dragging');
        e.preventDefault();
    });
    window.addEventListener('mousemove', function (e) {
        if (!dragging) return;
        paneEl.scrollLeft = startLeft - (e.clientX - startX);
        paneEl.scrollTop  = startTop  - (e.clientY - startY);
    });
    window.addEventListener('mouseup', function () {
        if (!dragging) return;
        dragging = false;
        paneEl.classList.remove('dragging');
    });

    // Double-click resets zoom to fit.
    paneEl.addEventListener('dblclick', function (e) {
        if (e.target.closest && e.target.closest('.pdf-label')) return;
        paneZoom.set(paneEl, 1.0);
        rerenderPane(paneEl);
    });
}

function wireAllPanes() {
    document.querySelectorAll('.pdf-pane').forEach(wirePaneInteraction);
}

// Re-render on resize so the fit-to-height base stays correct. The
// per-pane zoom is preserved because it lives on the WeakMap, not on
// the pane's DOM attributes.
let resizeTimer = null;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
        document.querySelectorAll('.pdf-pane[data-pdf]').forEach(rerenderPane);
    }, 150);
});

window.HamletPDF = { loadIntoPane, loadAllPanes };

function init() {
    wireAllPanes();
    loadAllPanes();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
