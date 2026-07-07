<?php
$pageTitle   = 'Die Completed';
$currentPage = 'die-completed';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<style>
/* ── Layout ────────────────────────────────────────────────────── */
.table-container {
    max-height: calc(100vh - 220px);
    overflow: auto;
    isolation: isolate;
}
.table-container thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #198754;
    color: #fff;
    font-size: .73rem;
    white-space: nowrap;
    padding: .38rem .45rem;
    vertical-align: middle;
}
.table-container tbody td {
    font-size: .78rem;
    padding: .28rem .45rem;
    vertical-align: middle;
    white-space: nowrap;
}

/* ── Thumbnail ─────────────────────────────────────────────────── */
.thumb-wrap {
    width: 52px; height: 52px;
    display: flex; align-items: center; justify-content: center;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    overflow: hidden;
    cursor: pointer;
    background: #f8f9fa;
    transition: box-shadow .15s;
}
.thumb-wrap:hover { box-shadow: 0 0 0 2px #198754; }
.thumb-wrap img   { width: 52px; height: 52px; object-fit: cover; }
.thumb-empty      { border: 1px dashed #ced4da !important; }

/* ── Filter select in header ───────────────────────────────────── */
.filter-select {
    background: #146c43;
    color: #fff;
    border: 1px solid #0f5132;
    border-radius: 4px;
    font-size: .63rem;
    padding: .1rem .2rem;
    width: 100%;
    margin-top: 3px;
    cursor: pointer;
}
.filter-select:focus { outline: none; border-color: #86efac; }
.filter-select option { background: #198754; }

/* ── Stat cards ────────────────────────────────────────────────── */
.stat-card {
    border-radius: 8px;
    padding: .5rem .9rem;
    font-size: .8rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
}

/* ── Print ─────────────────────────────────────────────────────── */
@media print {
    .navbar, .toolbar-row, footer, .modal { display: none !important; }
    .table-container { max-height: none; overflow: visible; }
    #printHeader     { display: block !important; }
    body             { font-size: 10px; }
    .thumb-wrap img  { width: 40px; height: 40px; }
    .table-container thead th {
        background: #198754 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
#printHeader { display: none; }
</style>

<!-- ── Toast ──────────────────────────────────────────────────── -->
<div id="cmpToastWrap" class="position-fixed top-0 end-0 p-3" style="z-index:9999">
    <div id="cmpToast" class="toast align-items-center border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="cmpToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                    data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<main class="container-fluid py-2">

    <!-- ── Print-only header ─────────────────────────────────────── -->
    <div id="printHeader" class="mb-3">
        <h4 id="printTitle" class="fw-bold mb-0"></h4>
        <p class="small mb-0">Printed: <?= date('d/m/Y H:i') ?></p>
    </div>

    <!-- ── Toolbar ───────────────────────────────────────────────── -->
    <div class="d-flex flex-wrap align-items-center gap-2 toolbar-row mb-2">

        <!-- Page title -->
        <div>
            <h6 class="mb-0 fw-bold">
                <i class="bi bi-patch-check me-1 text-success"></i>
                Die Completed
                <span class="badge bg-success ms-1" id="totalBadge">—</span>
            </h6>
            <small class="text-muted" id="weekRange">Loading…</small>
        </div>

        <!-- Week selector -->
        <div class="d-flex align-items-center gap-1 ms-2">
            <label class="form-label mb-0 small fw-semibold text-nowrap">Week:</label>
            <select id="weekSelect" class="form-select form-select-sm" style="min-width:140px">
                <option value="">Loading…</option>
            </select>
        </div>

        <!-- Record count -->
        <span id="recordCount" class="text-secondary small text-nowrap"></span>

        <!-- Right buttons -->
        <div class="ms-auto d-flex gap-1 flex-wrap">
            <button class="btn btn-outline-secondary btn-sm" id="btnPrint">
                <i class="bi bi-printer me-1"></i>Print
            </button>
            <button class="btn btn-success btn-sm" id="btnExcelExport">
                <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
            </button>
        </div>
    </div>

    <!-- ── Table ──────────────────────────────────────────────────── -->
    <div class="table-container border rounded shadow-sm">
        <table class="table table-bordered table-hover mb-0" id="cmpTable">
            <thead>
                <tr>
                    <th style="min-width:38px">#</th>
                    <th style="min-width:60px">รูป</th>
                    <th style="min-width:90px">
                        Week
                        <select id="filterWeek" class="filter-select">
                            <option value="all">ทั้งหมด</option>
                        </select>
                    </th>
                    <th style="min-width:80px">เครื่องรีด</th>
                    <th style="min-width:100px">เบอร์แม่พิมพ์</th>
                    <th style="min-width:110px">Tech DWG</th>
                    <th style="min-width:55px">ทับ</th>
                    <th style="min-width:90px">กำหนดเสร็จ</th>
                    <th style="min-width:95px">ประเภทพิมพ์</th>
                    <th style="min-width:90px">
                        สถานะ DMK
                        <select id="filterDmk" class="filter-select">
                            <option value="all">ทั้งหมด</option>
                        </select>
                    </th>
                    <th style="min-width:100px">ส่งจริง</th>
                    <th style="min-width:100px">Die No</th>
                </tr>
            </thead>
            <tbody id="cmpTbody">
                <tr><td colspan="12" class="text-center py-4 text-secondary">
                    <div class="spinner-border spinner-border-sm me-2"></div>Loading…
                </td></tr>
            </tbody>
        </table>
    </div>

</main>

<!-- ═══════════════════════════════════════════════════════════════
     MODAL — Lightbox
════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="lightboxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header py-2 border-secondary">
                <span class="text-white small" id="lightboxLabel">Image</span>
                <div class="ms-auto d-flex gap-2 align-items-center">
                    <button class="btn btn-outline-light btn-sm" id="lightboxPrev">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <span class="text-secondary small" id="lightboxCounter"></span>
                    <button class="btn btn-outline-light btn-sm" id="lightboxNext">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                    <button type="button" class="btn-close btn-close-white ms-2"
                            data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body text-center p-2" style="min-height:300px">
                <img id="lightboxImg" src="" alt=""
                     style="max-width:100%; max-height:70vh; object-fit:contain; border-radius:4px">
            </div>
        </div>
    </div>
</div>

<script>
(function () {
'use strict';

const DMK_API   = '<?= BASE_URL ?>/api/dmk_plan.php';
const WEEKS_API = '<?= BASE_URL ?>/api/weeks.php';
const EXCEL_API = '<?= BASE_URL ?>/api/export_excel.php';
const DIES_API  = '<?= BASE_URL ?>/api/dies.php';

const state = {
    week:           'all',
    dies:           [],
    weeksData:      [],
    filterDmk:      'all',
    filterWeek:     'all',
    lightboxImages: [],
    lightboxIdx:    0,
};

// ── Utility ───────────────────────────────────────────────────
function esc(s) {
    return (s ?? '').toString()
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmtDate(iso) {
    if (!iso) return '';
    const [y, m, d] = iso.split('-');
    return (y && m && d) ? `${d}/${m}/${y}` : iso;
}
function showToast(msg, type = 'success') {
    const el  = document.getElementById('cmpToast');
    const txt = document.getElementById('cmpToastMsg');
    el.className = `toast align-items-center border-0 text-white bg-${type}`;
    txt.textContent = msg;
    bootstrap.Toast.getOrCreateInstance(el, { delay: 3500 }).show();
}

// ── Badge helpers ─────────────────────────────────────────────
const DMK_MAP   = { 'W/C':'bg-primary','CNC':'bg-warning text-dark','Pending':'bg-secondary','กลึง':'bg-secondary','กลึงเหวี่ยง':'bg-info text-dark' };
const DMK_STYLE = { 'กัดเช็ค':'background:#fd7e14','ชุบแข็ง':'background:#6f42c1' };
function dmkBadge(v) {
    if (!v) return '<span class="text-muted small">—</span>';
    const cls   = DMK_MAP[v]   ?? 'bg-secondary';
    const style = DMK_STYLE[v] ? ` style="${DMK_STYLE[v]}"` : '';
    return `<span class="badge ${cls}"${style}>${esc(v)}</span>`;
}

const HOLLOW_LEVEL_LABEL = { easy:'ง่าย', medium:'กลาง', hard:'ยาก' };
const HOLLOW_LEVEL_BADGE = { easy:'bg-success', medium:'bg-warning text-dark', hard:'bg-danger' };
const TYPE_BADGE = { Solid:'bg-success', Hollow:'bg-warning text-dark', Heatsink:'bg-primary' };
function typeBadge(dieType, hollowLevel) {
    if (!dieType) return '<span class="text-muted small">—</span>';
    if (dieType === 'Hollow' && hollowLevel) {
        const cls   = HOLLOW_LEVEL_BADGE[hollowLevel] ?? 'bg-warning text-dark';
        const label = 'Hollow ' + (HOLLOW_LEVEL_LABEL[hollowLevel] ?? hollowLevel);
        return `<span class="badge ${cls}">${esc(label)}</span>`;
    }
    return `<span class="badge ${TYPE_BADGE[dieType] ?? 'bg-secondary'}">${esc(dieType)}</span>`;
}

// ── Thumb cell (read-only, lightbox only) ─────────────────────
function thumbCell(die, origIdx) {
    if (!die.image_path) {
        return `<td>
            <div class="thumb-wrap thumb-empty" title="ไม่มีรูป">
                <i class="bi bi-image" style="font-size:1rem;color:#ced4da"></i>
            </div>
        </td>`;
    }
    return `<td>
        <div class="thumb-wrap" data-row="${origIdx}" data-idx="0" title="คลิกดูรูปใหญ่">
            <img src="${esc(die.image_path)}" alt="${esc(die.section)}" loading="lazy"
                 onerror="this.parentElement.className='thumb-wrap thumb-empty';this.parentElement.innerHTML='<i class=\\'bi bi-image\\' style=\\'font-size:1rem;color:#ced4da\\'></i>'">
        </div>
    </td>`;
}

// ── Update DMK filter dropdown options ────────────────────────
function updateDmkFilter() {
    const sel = document.getElementById('filterDmk');
    const currentVal = sel.value;

    // Count by dmk_status
    const counts = {};
    for (const d of state.dies) {
        const v = d.dmk_status || '—';
        counts[v] = (counts[v] ?? 0) + 1;
    }

    sel.innerHTML = `<option value="all">ทั้งหมด (${state.dies.length})</option>` +
        Object.entries(counts)
            .sort((a,b) => b[1]-a[1])
            .map(([v, n]) => `<option value="${esc(v)}">${esc(v || '—')} (${n})</option>`)
            .join('');

    // Restore selection if still valid
    if ([...sel.options].some(o => o.value === currentVal)) {
        sel.value = currentVal;
    }
}

// ── Update Week filter dropdown options ───────────────────────
function updateWeekFilter() {
    const sel = document.getElementById('filterWeek');
    const currentVal = sel.value;

    const weeks = [...new Set(state.dies.map(d => d.die_finish_plan ?? ''))].filter(Boolean);
    weeks.sort((a, b) => {
        const [wa, ya] = a.split('/').map(Number);
        const [wb, yb] = b.split('/').map(Number);
        return ya !== yb ? yb - ya : wb - wa;
    });

    sel.innerHTML = `<option value="all">ทั้งหมด</option>` +
        weeks.map(w => `<option value="${esc(w)}">${esc(w)}</option>`).join('');

    if ([...sel.options].some(o => o.value === currentVal)) {
        sel.value = currentVal;
    }
}

// ── Render table ──────────────────────────────────────────────
function renderTable() {
    const tbody = document.getElementById('cmpTbody');

    updateDmkFilter();
    updateWeekFilter();

    let filtered = state.dies;
    if (state.filterWeek !== 'all') {
        filtered = filtered.filter(d => (d.die_finish_plan ?? '') === state.filterWeek);
    }
    if (state.filterDmk !== 'all') {
        filtered = filtered.filter(d => (d.dmk_status || '—') === state.filterDmk);
    }

    document.getElementById('totalBadge').textContent  = state.dies.length;
    document.getElementById('recordCount').textContent =
        (state.filterDmk === 'all' && state.filterWeek === 'all')
            ? `${state.dies.length} record${state.dies.length !== 1 ? 's' : ''}`
            : `${filtered.length} records (จาก ${state.dies.length})`;

    if (!filtered.length) {
        tbody.innerHTML = `<tr><td colspan="12" class="text-center py-5 text-secondary">
            <i class="bi bi-inbox fs-3 d-block mb-1"></i>ไม่มีรายการ Delivered</td></tr>`;
        return;
    }

    tbody.innerHTML = filtered.map((d, i) => {
        const origIdx = state.dies.indexOf(d);
        return `<tr>
            <td class="text-muted text-center">${i + 1}</td>
            ${thumbCell(d, origIdx)}
            <td class="text-center text-muted small">${esc(d.die_finish_plan ?? '')}</td>
            <td>${esc(d.machine)}</td>
            <td class="fw-semibold">${esc(d.section)}</td>
            <td class="text-muted small">${esc(d.tech_dwg_no)}</td>
            <td class="text-center">${esc(d.index_tab)}</td>
            <td>${fmtDate(d.die_pc_due_date)}</td>
            <td class="text-center">${typeBadge(d.die_type, d.hollow_level)}</td>
            <td class="text-center">${dmkBadge(d.dmk_status)}</td>
            <td class="fw-semibold text-success">${fmtDate(d.die_pc_actual_date)}</td>
            <td class="text-info fw-semibold">${esc(d.die_no)}</td>
        </tr>`;
    }).join('');
}

// ── Load data ─────────────────────────────────────────────────
async function loadData(week) {
    state.week       = week;
    state.filterWeek = 'all';
    const tbody = document.getElementById('cmpTbody');
    tbody.innerHTML = `<tr><td colspan="12" class="text-center py-4 text-secondary">
        <div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>`;

    try {
        const res  = await fetch(`${DMK_API}?week=${encodeURIComponent(week)}`);
        const json = await res.json();
        if (!res.ok) throw new Error(json.error ?? `HTTP ${res.status}`);

        // Only keep delivered items
        state.dies = (json.data ?? []).filter(d => d.die_pc_actual_date);
        renderTable();
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="12" class="text-center text-danger py-3">
            <i class="bi bi-exclamation-triangle me-1"></i>${esc(err.message)}</td></tr>`;
    }
}

// ── Load week dropdown ────────────────────────────────────────
async function loadWeeks() {
    const sel = document.getElementById('weekSelect');
    try {
        const res   = await fetch(WEEKS_API);
        const rows  = (await res.json()) ?? [];
        const weeks = rows.filter(r => r.week !== 'waiting' && r.week !== 'hold');
        state.weeksData = weeks;

        const urlWeek = new URLSearchParams(location.search).get('week') ?? 'all';

        weeks.sort((a, b) => {
            const [wa,ya] = a.week.split('/').map(Number);
            const [wb,yb] = b.week.split('/').map(Number);
            return ya !== yb ? yb - ya : wb - wa;   // newest first
        });

        sel.innerHTML =
            `<option value="all" ${urlWeek === 'all' ? 'selected' : ''}>— ทุก Week —</option>` +
            weeks.map(w =>
                `<option value="${esc(w.week)}" ${w.week === urlWeek ? 'selected' : ''}>Week ${esc(w.week)}</option>`
            ).join('');

        const selectedWeek = sel.value || 'all';
        const wData = weeks.find(r => r.week === selectedWeek);
        document.getElementById('weekRange').textContent =
            selectedWeek === 'all' ? 'แสดงทุก Week — เฉพาะรายการ Delivered'
            : (wData?.date_range || `Week ${selectedWeek}`);

        return selectedWeek;
    } catch {
        sel.innerHTML = `<option value="all" selected>— ทุก Week —</option>`;
        document.getElementById('weekRange').textContent = 'แสดงทุก Week — เฉพาะรายการ Delivered';
        return 'all';
    }
}

// ── Lightbox ──────────────────────────────────────────────────
const lbModal   = new bootstrap.Modal(document.getElementById('lightboxModal'));
const lbImg     = document.getElementById('lightboxImg');
const lbLabel   = document.getElementById('lightboxLabel');
const lbCounter = document.getElementById('lightboxCounter');

function openLightbox(rowIdx, imgIdx) {
    const die = state.dies[rowIdx];
    if (!die?.images?.length) return;
    state.lightboxImages = die.images;
    state.lightboxIdx    = imgIdx;
    updateLightbox();
    lbModal.show();
}
function updateLightbox() {
    const imgs = state.lightboxImages;
    const idx  = state.lightboxIdx;
    lbImg.src  = imgs[idx] ?? '';
    lbLabel.textContent   = `Image ${idx + 1}`;
    lbCounter.textContent = `${idx + 1} / ${imgs.length}`;
    document.getElementById('lightboxPrev').disabled = idx === 0;
    document.getElementById('lightboxNext').disabled = idx === imgs.length - 1;
}
document.getElementById('lightboxPrev').addEventListener('click', () => {
    if (state.lightboxIdx > 0) { state.lightboxIdx--; updateLightbox(); }
});
document.getElementById('lightboxNext').addEventListener('click', () => {
    if (state.lightboxIdx < state.lightboxImages.length - 1) {
        state.lightboxIdx++; updateLightbox();
    }
});

document.getElementById('cmpTbody').addEventListener('click', e => {
    const tw = e.target.closest('.thumb-wrap[data-row]');
    if (tw) openLightbox(+tw.dataset.row, 0);
});

// ── Week selector ─────────────────────────────────────────────
document.getElementById('weekSelect').addEventListener('change', function () {
    const week = this.value;
    history.replaceState(null, '', `?week=${encodeURIComponent(week)}`);
    const wData = state.weeksData.find(r => r.week === week);
    document.getElementById('weekRange').textContent =
        week === 'all' ? 'แสดงทุก Week — เฉพาะรายการ Delivered'
        : (wData?.date_range || `Week ${week}`);
    loadData(week);
});

// ── DMK filter ────────────────────────────────────────────────
document.getElementById('filterDmk').addEventListener('change', function () {
    state.filterDmk = this.value;
    renderTable();
});

// ── Week column filter ────────────────────────────────────────
document.getElementById('filterWeek').addEventListener('change', function () {
    state.filterWeek = this.value;
    renderTable();
});

// ── Toolbar: Print ────────────────────────────────────────────
document.getElementById('btnPrint').addEventListener('click', () => {
    const weekLabel = state.week === 'all' ? 'ทุก Week' : `WEEK ${state.week}`;
    document.getElementById('printTitle').textContent = `Die Completed — ${weekLabel}`;
    window.print();
});

// ── Toolbar: Export Excel ─────────────────────────────────────
document.getElementById('btnExcelExport').addEventListener('click', () => {
    const btn = document.getElementById('btnExcelExport');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating…';
    const a = document.createElement('a');
    a.href     = `${EXCEL_API}?week=${encodeURIComponent(state.week)}&status=delivered`;
    a.download = `DieCompleted_Week_${state.week.replace('/', '-')}.xlsx`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-file-earmark-excel me-1"></i>Export Excel';
    }, 4000);
});

// ── Init ──────────────────────────────────────────────────────
(async () => {
    const week = await loadWeeks();
    await loadData(week);
})();

})();
</script>

<?php require_once '../includes/footer.php'; ?>
