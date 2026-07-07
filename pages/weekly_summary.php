<?php
$pageTitle   = 'Weekly Summary';
$currentPage = 'weekly';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<style>
/* ── Layout ──────────────────────────────────────────────────── */
.summary-wrap {
    max-width: 960px;
    margin: 0 auto;
}

/* ── Table ──────────────────────────────────────────────────── */
#summaryTable {
    border-collapse: separate;
    border-spacing: 0;
    border: 2px solid #dee2e6;
    border-radius: .4rem;
    overflow: hidden;
    font-size: .85rem;
}
#summaryTable thead tr {
    background: #ffc107;
    color: #212529;
}
#summaryTable thead th {
    padding: .55rem .7rem;
    border-bottom: 2px solid #e6a800;
    white-space: nowrap;
    vertical-align: middle;
    font-weight: 700;
    font-size: .78rem;
    text-transform: uppercase;
    letter-spacing: .02em;
}
#summaryTable thead th.col-type {
    text-align: center;
}

/* ── Data rows ───────────────────────────────────────────────── */
#summaryTable tbody td {
    padding: .4rem .7rem;
    vertical-align: middle;
    border-bottom: 1px solid #dee2e6;
}
#summaryTable tbody tr.row-normal {
    cursor: pointer;
    transition: background .12s;
}
#summaryTable tbody tr.row-normal:hover {
    background: #fffbea !important;
}
#summaryTable tbody tr.row-current td {
    background: #fff3cd !important;
    font-weight: 600;
}
#summaryTable tbody tr.row-waiting {
    background: #fff8e1;
    cursor: default;
}
#summaryTable tbody tr.row-hold {
    background: #fdecea;
    cursor: default;
}

/* ── Total row ───────────────────────────────────────────────── */
#summaryTable tfoot tr td {
    padding: .5rem .7rem;
    font-weight: 700;
    font-size: .88rem;
    background: #343a40;
    color: #fff;
    border-top: 2px solid #212529;
}

/* ── Coloured number cells ───────────────────────────────────── */
.num-cell {
    text-align: center;
    font-weight: 600;
    font-size: .82rem;
    border-radius: 4px;
}
.num-zero {
    color: #adb5bd;
    font-weight: 400;
}

/* ── Week badge ──────────────────────────────────────────────── */
.week-badge {
    display: inline-block;
    background: #0d6efd;
    color: #fff;
    font-size: .7rem;
    padding: .1rem .45rem;
    border-radius: 1rem;
    font-weight: 700;
    margin-right: .3rem;
}
.week-arrow {
    color: #6c757d;
    font-size: .7rem;
    margin-left: .2rem;
    opacity: 0;
    transition: opacity .12s;
}
tr.row-normal:hover .week-arrow { opacity: 1; }

/* ── Legend ──────────────────────────────────────────────────── */
.legend-dot {
    display: inline-block;
    width: 10px; height: 10px;
    border-radius: 50%;
    margin-right: 4px;
}

/* ── Loading / error ─────────────────────────────────────────── */
#loadingRow td, #errorRow td {
    padding: 2rem;
    text-align: center;
    color: #6c757d;
}
</style>

<main class="container-fluid py-3">
<div class="summary-wrap">

    <!-- ── Page header ──────────────────────────────────────── -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-calendar-week me-2 text-warning"></i>Weekly Summary
            </h5>
            <p class="text-muted small mb-0">Die Finish Plan — grouped by week</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <!-- Refresh -->
            <button class="btn btn-outline-secondary btn-sm" id="btnRefresh" title="Refresh">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
            <!-- Legend -->
            <div class="d-none d-md-flex gap-3 small ms-2">
                <span><span class="legend-dot" style="background:#198754"></span>Solid</span>
                <span><span class="legend-dot" style="background:#0dcaf0"></span>H-Easy</span>
                <span><span class="legend-dot" style="background:#0d6efd"></span>H-Med</span>
                <span><span class="legend-dot" style="background:#6f42c1"></span>H-Hard</span>
                <span><span class="legend-dot" style="background:#fd7e14"></span>Heatsink</span>
            </div>
        </div>
    </div>

    <!-- ── Table ─────────────────────────────────────────────── -->
    <div class="table-responsive shadow-sm">
        <table class="table table-bordered mb-0" id="summaryTable">
            <thead>
                <tr>
                    <th style="min-width:115px">Week</th>
                    <th style="min-width:130px">Date Range</th>
                    <th class="col-type" style="min-width:72px; color:#145a32">
                        <i class="bi bi-square-fill me-1" style="color:#198754"></i>Solid
                    </th>
                    <th class="col-type" style="min-width:80px; color:#0a6c7a">
                        <i class="bi bi-square-fill me-1" style="color:#0dcaf0"></i>H-Easy
                    </th>
                    <th class="col-type" style="min-width:80px; color:#084298">
                        <i class="bi bi-square-fill me-1" style="color:#0d6efd"></i>H-Med
                    </th>
                    <th class="col-type" style="min-width:80px; color:#4a2680">
                        <i class="bi bi-square-fill me-1" style="color:#6f42c1"></i>H-Hard
                    </th>
                    <th class="col-type" style="min-width:82px; color:#7c3e00">
                        <i class="bi bi-square-fill me-1" style="color:#fd7e14"></i>Heatsink
                    </th>
                    <th class="col-type" style="min-width:65px">Total</th>
                </tr>
            </thead>
            <tbody id="summaryBody">
                <tr id="loadingRow">
                    <td colspan="8">
                        <div class="spinner-border spinner-border-sm me-2"></div>Loading…
                    </td>
                </tr>
            </tbody>
            <tfoot id="summaryFoot"></tfoot>
        </table>
    </div>

    <p class="text-muted small mt-2 mb-0">
        <i class="bi bi-info-circle me-1"></i>
        Click any week row to view its dies in the planning table.
    </p>

</div>
</main>

<script>
(function () {
'use strict';

// ── Config ────────────────────────────────────────────────────
const API          = '<?= BASE_URL ?>/api/weeks.php';
const PLANNING_URL = '<?= BASE_URL ?>/pages/planning.php';

// Colour palette for each type column (matches legend)
const COLS = [
    { key: 'solid',         color: '#198754', label: 'Solid'    },
    { key: 'hollow_easy',   color: '#0dcaf0', label: 'H-Easy'  },
    { key: 'hollow_medium', color: '#0d6efd', label: 'H-Med'   },
    { key: 'hollow_hard',   color: '#6f42c1', label: 'H-Hard'  },
    { key: 'heatsink',      color: '#fd7e14', label: 'Heatsink'},
];

// ── Utilities ──────────────────────────────────────────────────
function esc(s) {
    return (s ?? '').toString()
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/** Convert "#rrggbb" + alpha 0–1 → "rgba(r,g,b,a)" */
function hexRgba(hex, a) {
    const r = parseInt(hex.slice(1,3), 16);
    const g = parseInt(hex.slice(3,5), 16);
    const b = parseInt(hex.slice(5,7), 16);
    return `rgba(${r},${g},${b},${a})`;
}

function currentISOWeek() {
    const now = new Date();
    const d   = new Date(Date.UTC(now.getFullYear(), now.getMonth(), now.getDate()));
    const day = d.getUTCDay() || 7;
    d.setUTCDate(d.getUTCDate() + 4 - day);
    const jan1   = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
    const weekNo = Math.ceil((((d - jan1) / 86400000) + 1) / 7);
    return `${weekNo}/${d.getUTCFullYear()}`;
}

// ── Cell builders ──────────────────────────────────────────────

/** Coloured number cell — tinted background when > 0 */
function numTd(n, color) {
    if (!n || n === 0) {
        return `<td class="num-cell num-zero">—</td>`;
    }
    const bg = hexRgba(color, 0.13);
    return `<td class="num-cell" style="background:${bg};color:${color}">${n}</td>`;
}

/** Total cell — always shown, bold dark */
function totalTd(n, bold = false) {
    if (!n || n === 0) {
        return `<td class="num-cell num-zero">—</td>`;
    }
    const w = bold ? 'font-weight:800' : 'font-weight:600';
    return `<td class="num-cell" style="color:#212529;${w}">${n}</td>`;
}

// ── Row builders ───────────────────────────────────────────────

function buildNormalRow(row, isCurrentWeek) {
    const rowCls  = 'row-normal' + (isCurrentWeek ? ' row-current' : '');
    const weekBadge = `<span class="week-badge">${esc(row.week)}</span>`;
    const arrow     = `<i class="bi bi-arrow-right week-arrow"></i>`;
    let html = `<tr class="${rowCls}" data-week="${esc(row.week)}">`;
    html += `<td>${weekBadge}${arrow}</td>`;
    html += `<td class="text-muted">${esc(row.date_range)}</td>`;
    COLS.forEach(c => { html += numTd(row[c.key], c.color); });
    html += totalTd(row.total);
    html += `</tr>`;
    return html;
}

function buildSpecialRow(row) {
    const isWaiting = row.week === 'waiting';
    const rowCls    = isWaiting ? 'row-waiting' : 'row-hold';
    const icon      = isWaiting
        ? '<i class="bi bi-clock-history me-1 text-warning"></i>'
        : '<i class="bi bi-pause-circle me-1 text-danger"></i>';

    let html = `<tr class="${rowCls}">`;
    html += `<td colspan="2" class="fw-semibold">${icon}${esc(row.label || row.week)}</td>`;
    COLS.forEach(c => { html += numTd(row[c.key], c.color); });
    html += totalTd(row.total);
    html += `</tr>`;
    return html;
}

function buildGrandTotalRow(rows) {
    const grand = { solid:0, hollow_easy:0, hollow_medium:0, hollow_hard:0, heatsink:0, total:0 };
    rows.forEach(r => {
        Object.keys(grand).forEach(k => { grand[k] += (r[k] ?? 0); });
    });

    let html = `<tr>`;
    html += `<td colspan="2"><i class="bi bi-sigma me-1"></i>Grand Total</td>`;
    COLS.forEach(c => {
        const n  = grand[c.key];
        const bg = hexRgba(c.color, 0.25);
        html += n > 0
            ? `<td class="num-cell" style="background:${bg}">${n}</td>`
            : `<td class="num-cell num-zero" style="color:#adb5bd">—</td>`;
    });
    html += `<td class="num-cell" style="font-size:.95rem">${grand.total}</td>`;
    html += `</tr>`;
    return html;
}

// ── Render ─────────────────────────────────────────────────────

function render(rows) {
    const thisWeek = currentISOWeek();
    const tbody    = document.getElementById('summaryBody');
    const tfoot    = document.getElementById('summaryFoot');

    if (!rows.length) {
        tbody.innerHTML = `<tr id="loadingRow"><td colspan="8" class="text-center text-muted py-4">
            <i class="bi bi-inbox fs-4 d-block mb-1"></i>No data yet
        </td></tr>`;
        tfoot.innerHTML = '';
        return;
    }

    const normalRows  = rows.filter(r => r.week !== 'waiting' && r.week !== 'hold');
    const specialRows = rows.filter(r => r.week === 'waiting' || r.week === 'hold');

    let bodyHtml = '';
    normalRows.forEach(r => {
        bodyHtml += buildNormalRow(r, r.week === thisWeek);
    });
    specialRows.forEach(r => {
        bodyHtml += buildSpecialRow(r);
    });

    tbody.innerHTML = bodyHtml;
    tfoot.innerHTML = buildGrandTotalRow(rows);
}

// ── Load ───────────────────────────────────────────────────────

async function load() {
    const tbody = document.getElementById('summaryBody');
    const tfoot = document.getElementById('summaryFoot');
    tbody.innerHTML = `<tr id="loadingRow"><td colspan="8">
        <div class="spinner-border spinner-border-sm me-2"></div>Loading…
    </td></tr>`;
    tfoot.innerHTML = '';

    try {
        const res  = await fetch(API);
        const data = await res.json();

        if (!res.ok) throw new Error(data.error ?? `HTTP ${res.status}`);
        render(Array.isArray(data) ? data : []);

    } catch (err) {
        tbody.innerHTML = `<tr id="errorRow"><td colspan="8">
            <i class="bi bi-exclamation-triangle text-danger me-1"></i>
            ${esc(err.message)}
        </td></tr>`;
    }
}

// ── Click: navigate to planning.php?week=XX/YYYY ───────────────

document.getElementById('summaryBody').addEventListener('click', e => {
    const tr = e.target.closest('tr.row-normal');
    if (!tr) return;
    const week = tr.dataset.week;
    if (week) {
        window.location.href = `${PLANNING_URL}?week=${encodeURIComponent(week)}`;
    }
});

document.getElementById('btnRefresh').addEventListener('click', load);

// ── Init ───────────────────────────────────────────────────────
load();

})();
</script>

<?php require_once '../includes/footer.php'; ?>
