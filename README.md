# Hamlet

Second-generation Humphrey Visual Field browser. PHP front controller +
vanilla JS + bundled PDF.js. Rewrite of `HVF_Viewer` in the same pattern as
[Othello](https://github.com/Phaethon-net/Othello), and a sibling under
`G:\Viewers\` on the clinical workstation.

## What it does

- Browses patient folders under `D:\HVF_Data\`.
- TODAY / A–Z / search index across patient folders.
- Patient detail page = HVF-style left menu of all exams + two PDF panes
  side-by-side. Left-click an exam → loads into the left pane. Right-click →
  loads into the right pane. The most recent OD/OS pair auto-loads.
- PDFs render via bundled **PDF.js** (Apache 2.0), fit-to-height so each
  report fills the available pane without vertical scrolling.
- **MD trend** button: parses each `.xml` companion file and plots the OD
  and OS series of MD, false-positive %, false-negative %, and VFI for SFA
  threshold exams via Google Charts (loaded from `gstatic.com`).
- **NB notes** button: shows the contents of an optional `NB.txt` per-folder
  note (HTML-escaped).
- **Export pair**: downloads the two currently displayed PDFs (and their XML
  companions) as a ZIP.

## Layout

```
config.ini                 site/paths
config.php                 session boot, ini parse, per-server resolution
index.php                  router (TODAY, A-Z, search, patient detail)
header.php                 <head> + asset() cache-bust + window.HAMLET globals
banner.php                 brand title + Phaethon credit
footer.php                 closing tags
export.php                 ZIP of selected PDF+XML pairs
lib/patient.php            folder/filename parsing
lib/xml.php                CZM-XML + DICOM/Forum XML reader, NB.txt reader
lib/render.php             every HTML emitter
css/styles.css             palette, layout, dual-pane styling, overlays
js/search.js               search box UX (verbatim from Othello)
js/pdfviewer.js            PDF.js wrapper, fit-to-height renderer
js/exam.js                 exam-row click/contextmenu, MD/NB toggles, export
js/pdfjs/build/pdf.mjs     vendored PDF.js (4.7.76)
js/pdfjs/build/pdf.worker.mjs
.htaccess / web.config     proxy hardening, immutable static assets, .mjs MIME
```

## Configuration

Edit `config.ini`. Each server gets its own `[paths.<SERVER_NAME>]` section
(matched against `$_SERVER['SERVER_NAME']`):

```ini
[paths.eyeclinic]
data_fs  = "D:/HVF_Data/"
data_url = "/HVF_Data/"
base_url = "/Hamlet/"
```

`data_fs` is the on-disk path the PHP code reads from; `data_url` is the
HTTP URL alias your web server (IIS / Apache) maps onto the same folder so
the browser can fetch PDFs directly.

## Filename convention

Patient folder: `LASTNAME_Firstname_YYYYMMDD`

Exam file (PDF + XML pair, any case extension):

```
MRN_YYYYMMDD_HHMMSS_EYE_SERIAL_STRATEGY[_optional suffix].pdf
 0    1        2     3     4       5            6+
```

Eye is `OD`, `OS`, or `OU`. Strategy is `SCR` (screening) or `SFA`
(threshold).

## Requirements

- PHP 7.4 or newer with `ext-zip`, `ext-simplexml`, `ext-libxml`.
- Modern Edge (or any browser with native ES module support).
- IIS or Apache hosting Hamlet at a virtual directory and the data folder
  at the matching `data_url`.

## Local testing

```
php -S 127.0.0.1:8766 .claude/test_router.php
```

The test router (gitignored) maps `/HVF_Data/...` to `D:/HVF_Data/...` so
the built-in server can serve PDFs alongside Hamlet's own files.

## Licence / credits

PDF.js is bundled under the Apache 2.0 licence (Mozilla Foundation).
Google Charts is loaded from `gstatic.com` (Google's terms apply).
