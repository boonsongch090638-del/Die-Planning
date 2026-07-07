<?php
$pageTitle   = 'DMK Plan';
$currentPage = 'dmk';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<style>
/* ── Layout ────────────────────────────────────────────────────── */
.table-container {
    max-height: calc(100vh - 210px);
    overflow: auto;
    isolation: isolate;
}
.table-container thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #343a40;
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
    width: 60px; height: 60px;
    display: flex; align-items: center; justify-content: center;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    overflow: hidden;
    cursor: pointer;
    background: #f8f9fa;
    transition: box-shadow .15s, border-color .15s, background .15s;
}
.thumb-wrap:hover { box-shadow: 0 0 0 2px #0d6efd; }
.thumb-wrap img { width: 60px; height: 60px; object-fit: cover; }

/* Empty thumbnail (no profile image from the Section API, read-only) */
.thumb-empty { border: 1px dashed #ced4da !important; cursor: default; }
.thumb-empty:hover { box-shadow: none; }

/* Empty / uploadable thumbnail (manual fallback — only when API has no image) */
.thumb-upload {
    border: 1px dashed #adb5bd !important;
    flex-direction: column;
    gap: 2px;
}
.thumb-upload:hover {
    border-color: #0d6efd !important;
    background: #e8f0fe !important;
}
.thumb-upload .upload-hint { font-size: .6rem; color: #6c757d; line-height: 1; }
.thumb-upload:hover .upload-hint { color: #0d6efd; }
.thumb-uploading { opacity: .45; pointer-events: none; }

/* ── Row status highlights ─────────────────────────────────────── */
tr.status-waiting td { background: #fff8e1 !important; }
tr.status-hold    td { background: #fdecea !important; }

/* ── Inline date input ─────────────────────────────────────────── */
.actual-date-input {
    border: none;
    background: transparent;
    font-size: .78rem;
    color: inherit;
    padding: 0;
    width: 108px;
    cursor: pointer;
}
.actual-date-input:hover, .actual-date-input:focus {
    background: #fff;
    border: 1px solid #86b7fe;
    border-radius: 4px;
    outline: none;
    padding: 0 2px;
}

/* ── Drop zones ────────────────────────────────────────────────── */
#dropZone, #zipDropZone {
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: border-color .2s, background .2s;
    color: #6c757d;
}
#dropZone.drag-over {
    border-color: #0d6efd;
    background: #e8f0fe;
    color: #0d6efd;
}
#zipDropZone.drag-over {
    border-color: #198754;
    background: #e8f5ec;
    color: #198754;
}
#dropZone input[type=file],
#zipDropZone input[type=file] { display: none; }

/* ── Text search inputs in sticky header ──────────────────────── */
.filter-input {
    background: #3d444b;
    color: #fff;
    border: 1px solid #6c757d;
    border-radius: 4px;
    font-size: .63rem;
    padding: .1rem .3rem;
    width: 100%;
    margin-top: 3px;
}
.filter-input::placeholder { color: #9ba4ad; }
.filter-input:focus { outline: none; border-color: #86b7fe; background: #343a40; }

/* ── Delivery filter select in sticky header ───────────────────── */
.filter-select {
    background: #495057;
    color: #fff;
    border: 1px solid #6c757d;
    border-radius: 4px;
    font-size: .63rem;
    padding: .1rem .2rem;
    width: 100%;
    margin-top: 3px;
    cursor: pointer;
}
.filter-select:focus { outline: none; border-color: #86b7fe; }
.filter-select option { background: #343a40; }

/* ── Thumb cell wrapper & delete button ───────────────────────── */
.thumb-cell-wrap { position: relative; display: inline-block; }
.btn-del-img {
    position: absolute; top: 0; right: 0;
    padding: 0 4px; font-size: .78rem; line-height: 1.35;
    background: rgba(220,53,69,.85); color: #fff;
    border: none; border-radius: 0 4px 0 4px;
    cursor: pointer; display: none; z-index: 2;
}
.btn-del-img:hover { background: #dc3545; }
.thumb-cell-wrap:hover .btn-del-img { display: block; }

/* ── Print ─────────────────────────────────────────────────────── */
@media print {
    @page { size: A4 landscape; margin: 7mm; }

    /* Hide non-printable UI */
    .navbar, .toolbar-row, footer, .modal,
    #btnPrint, #btnZip, #btnUpload,
    .filter-input, .filter-select,
    .btn-del-img                            { display: none !important; }

    /* Table container: full scroll removed */
    .table-container {
        max-height: none !important;
        overflow: visible !important;
        border: none !important;
        box-shadow: none !important;
    }

    /* Print header */
    #printHeader { display: block !important; }

    /* Scale down everything to fit 12 columns in A4 landscape */
    #dmkTable {
        font-size: 7px !important;
        width: 100% !important;
        table-layout: fixed;
        border-collapse: collapse;
    }
    .table-container thead th {
        font-size: 7px !important;
        padding: 2px 3px !important;
        background: #343a40 !important;
        color: #fff !important;
        white-space: normal !important;
        word-break: break-word;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .table-container tbody td {
        font-size: 7px !important;
        padding: 2px 3px !important;
        white-space: nowrap;
    }

    /* Thumbnail: shrink for print */
    .thumb-wrap, .thumb-cell-wrap { width: 34px !important; height: 34px !important; }
    .thumb-wrap img                { width: 34px !important; height: 34px !important; }

    /* Badges: shrink */
    .badge { font-size: 6px !important; padding: 1px 3px !important; }

    /* Date input */
    .actual-date-input { font-size: 7px !important; width: auto !important; }

    /* Fixed column widths for 12 cols in ~281 mm printable width */
    #dmkTable th:nth-child(1),  #dmkTable td:nth-child(1)  { width: 4%  !important; } /* # */
    #dmkTable th:nth-child(2),  #dmkTable td:nth-child(2)  { width: 5%  !important; } /* รูป */
    #dmkTable th:nth-child(3),  #dmkTable td:nth-child(3)  { width: 7%  !important; } /* เครื่องรีด */
    #dmkTable th:nth-child(4),  #dmkTable td:nth-child(4)  { width: 9%  !important; } /* เบอร์แม่พิมพ์ */
    #dmkTable th:nth-child(5),  #dmkTable td:nth-child(5)  { width: 9%  !important; } /* Tech DWG */
    #dmkTable th:nth-child(6),  #dmkTable td:nth-child(6)  { width: 5%  !important; } /* ทับ */
    #dmkTable th:nth-child(7),  #dmkTable td:nth-child(7)  { width: 8%  !important; } /* กำหนดเสร็จ */
    #dmkTable th:nth-child(8),  #dmkTable td:nth-child(8)  { width: 9%  !important; } /* ประเภทพิมพ์ */
    #dmkTable th:nth-child(9),  #dmkTable td:nth-child(9)  { width: 8%  !important; } /* สถานะ DMK */
    #dmkTable th:nth-child(10), #dmkTable td:nth-child(10) { width: 9%  !important; } /* Status */
    #dmkTable th:nth-child(11), #dmkTable td:nth-child(11) { width: 9%  !important; } /* ส่งจริง */
    #dmkTable th:nth-child(12), #dmkTable td:nth-child(12) { width: 18% !important; } /* Die No */

    /* Row colours */
    tr.status-waiting td { background: #fff8e1 !important;
                           -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    tr.status-hold    td { background: #fdecea !important;
                           -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
#printHeader { display: none; }
</style>

<!-- ── Toast ──────────────────────────────────────────────────── -->
<div id="dmkToastWrap" class="position-fixed top-0 end-0 p-3" style="z-index:9999">
    <div id="dmkToast" class="toast align-items-center border-0" role="alert" aria-live="assertive">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="dmkToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                    data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<!-- ── Quick upload input (hidden, for manual fallback upload) ── -->
<input type="file" id="quickUploadInput"
       accept="image/jpeg,image/png,image/gif,image/webp"
       style="display:none">

<main class="container-fluid py-2">

    <!-- ── Print-only header ──────────────────────────────────────── -->
    <div id="printHeader" class="mb-3">
        <h4 id="printTitle" class="fw-bold mb-0"></h4>
        <p id="printSubtitle" class="text-muted small mb-0"></p>
        <p class="small mb-0">Printed: <?= date('d/m/Y H:i') ?></p>
    </div>

    <!-- ── Toolbar ─────────────────────────────────────────────────── -->
    <div class="d-flex flex-wrap align-items-center gap-2 toolbar-row mb-2">

        <!-- Page title -->
        <div>
            <h6 class="mb-0 fw-bold" id="pageHeading">
                <i class="bi bi-clipboard-data me-1 text-primary"></i>
                แผนผลิตแม่พิมพ์ <span id="weekLabel" class="text-primary">—</span>
            </h6>
            <small class="text-muted" id="weekRange">Loading…</small>
        </div>

        <!-- Week selector -->
        <div class="d-flex align-items-center gap-1 ms-2">
            <label class="form-label mb-0 small fw-semibold text-nowrap">Week:</label>
            <select id="weekSelect" class="form-select form-select-sm" style="min-width:130px">
                <option value="">Loading…</option>
            </select>
        </div>

        <!-- Record count -->
        <span id="recordCount" class="text-secondary small text-nowrap"></span>

        <!-- Right buttons -->
        <div class="ms-auto d-flex gap-1 flex-wrap">
            <button class="btn btn-outline-secondary btn-sm" id="btnPrint" title="Print">
                <i class="bi bi-printer me-1"></i>Print
            </button>
            <button class="btn btn-success btn-sm" id="btnExcelExport" title="Export Excel (.xlsx)">
                <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
            </button>
            <button class="btn btn-outline-success btn-sm" id="btnZip" title="Export ZIP">
                <i class="bi bi-file-earmark-zip me-1"></i>Export ZIP
            </button>
            <?php if (isAdmin()): ?>
            <button class="btn btn-outline-warning btn-sm" id="btnImportActual" title="อัพเดตวันส่งจริงจาก Excel">
                <i class="bi bi-file-earmark-arrow-up me-1"></i>Update ส่งจริง
            </button>
            <button class="btn btn-outline-primary btn-sm" id="btnUpload" title="Upload Images">
                <i class="bi bi-cloud-upload me-1"></i>Upload Images
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Table ────────────────────────────────────────────────────── -->
    <div class="table-container border rounded shadow-sm">
        <table class="table table-bordered table-hover mb-0" id="dmkTable">
            <thead>
                <tr>
                    <th style="min-width:38px">#</th>
                    <th style="min-width:68px">
                        รูป Section
                        <select id="filterImage" class="filter-select">
                            <option value="all">ทั้งหมด</option>
                            <option value="no_image">❌ ไม่มีรูป</option>
                            <option value="has_image">✅ มีรูป</option>
                        </select>
                    </th>
                    <th style="min-width:72px">Week</th>
                    <th style="min-width:80px">เครื่องรีด</th>
                    <th style="min-width:120px">
                        เบอร์แม่พิมพ์
                        <input type="text" id="filterSection" class="filter-input"
                               placeholder="ค้นหา…" autocomplete="off">
                    </th>
                    <th style="min-width:130px">
                        Tech DWG
                        <input type="text" id="filterTechDwg" class="filter-input"
                               placeholder="ค้นหา…" autocomplete="off">
                    </th>
                    <th style="min-width:55px">ทับ</th>
                    <th style="min-width:90px">กำหนดเสร็จ</th>
                    <th style="min-width:95px">ประเภทพิมพ์</th>
                    <th style="min-width:90px">สถานะ DMK</th>
                    <th style="min-width:115px">
                        Status
                        <select id="filterDelivery" class="filter-select">
                            <option value="all">ทั้งหมด</option>
                            <option value="pending">🟡 Pending</option>
                            <option value="delivered">🟢 Delivered</option>
                        </select>
                    </th>
                    <th style="min-width:110px">ส่งจริง</th>
                    <th style="min-width:100px">Die No</th>
                </tr>
            </thead>
            <tbody id="dmkTbody">
                <tr><td colspan="13" class="text-center py-4 text-secondary">
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

<!-- ═══════════════════════════════════════════════════════════════
     MODAL — Upload Images
════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0">
                    <i class="bi bi-cloud-upload me-2 text-primary"></i>Upload Section Images
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-0">
                <!-- Tab nav -->
                <ul class="nav nav-tabs px-3 pt-2" id="uploadTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-single-btn"
                                data-bs-toggle="tab" data-bs-target="#tabSingle"
                                type="button" role="tab">
                            <i class="bi bi-images me-1"></i>Single / Multiple
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-zip-btn"
                                data-bs-toggle="tab" data-bs-target="#tabZip"
                                type="button" role="tab">
                            <i class="bi bi-file-earmark-zip me-1 text-success"></i>Upload ZIP
                        </button>
                    </li>
                </ul>

                <div class="tab-content">

                    <!-- ── Tab 1: Single / Multiple ───────────────────── -->
                    <div class="tab-pane fade show active p-3" id="tabSingle" role="tabpanel">

                        <div id="dropZone">
                            <input type="file" id="fileInput" multiple
                                   accept="image/jpeg,image/png,image/gif,image/webp">
                            <i class="bi bi-cloud-arrow-up fs-2 d-block mb-2"></i>
                            <p class="mb-1 fw-semibold">Drag &amp; drop images here</p>
                            <p class="small text-muted mb-2">JPG, PNG, GIF, WebP — files are stored in per-die folders automatically</p>
                            <button type="button" class="btn btn-outline-primary btn-sm"
                                    onclick="document.getElementById('fileInput').click()">
                                Choose Files
                            </button>
                        </div>

                        <ul class="list-group mt-3 d-none" id="fileQueue"></ul>

                        <div class="mt-3 d-none" id="uploadProgress">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>Uploading…</span>
                                <span id="uploadPct">0%</span>
                            </div>
                            <div class="progress" style="height:6px">
                                <div class="progress-bar progress-bar-striped progress-bar-animated"
                                     id="uploadBar" role="progressbar" style="width:0%"></div>
                            </div>
                        </div>

                        <div class="mt-3 d-none" id="uploadResults"></div>

                        <div class="text-end mt-3">
                            <button type="button" class="btn btn-primary btn-sm d-none" id="btnUploadSubmit">
                                <i class="bi bi-upload me-1"></i>Upload <span id="uploadCount"></span>
                            </button>
                        </div>
                    </div>

                    <!-- ── Tab 2: Upload ZIP ───────────────────────────── -->
                    <div class="tab-pane fade p-3" id="tabZip" role="tabpanel">

                        <div id="zipDropZone">
                            <input type="file" id="zipFileInput" accept=".zip">
                            <i class="bi bi-file-earmark-zip fs-2 d-block mb-2 text-success"></i>
                            <p class="mb-1 fw-semibold">Drag &amp; drop a ZIP file here</p>
                            <p class="small text-muted mb-1">
                                Images are auto-organized into <code>/uploads/images/{die-no}/</code> folders
                            </p>
                            <p class="small mb-2">
                                <span class="badge bg-warning text-dark">Max 200 MB</span>
                                &nbsp;Supports: JPG, PNG, GIF, WebP, BMP — nested folders in ZIP are handled
                            </p>
                            <button type="button" class="btn btn-outline-success btn-sm"
                                    onclick="document.getElementById('zipFileInput').click()">
                                Choose ZIP File
                            </button>
                        </div>

                        <!-- Selected file info -->
                        <div class="d-none mt-2" id="zipFileInfo">
                            <div class="d-flex align-items-center gap-2 p-2 border rounded bg-light">
                                <i class="bi bi-file-earmark-zip fs-4 text-success flex-shrink-0"></i>
                                <div class="flex-grow-1 overflow-hidden">
                                    <div id="zipFileName" class="fw-semibold small text-truncate"></div>
                                    <div id="zipFileSize" class="text-muted small"></div>
                                </div>
                                <button type="button" class="btn-close flex-shrink-0"
                                        id="btnClearZip" style="font-size:.65rem"></button>
                            </div>
                        </div>

                        <!-- Progress -->
                        <div class="mt-3 d-none" id="zipProgress">
                            <div class="d-flex justify-content-between small mb-1">
                                <span id="zipProgressLabel">Uploading…</span>
                                <span id="zipPct">0%</span>
                            </div>
                            <div class="progress" style="height:6px">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                                     id="zipBar" role="progressbar" style="width:0%"></div>
                            </div>
                        </div>

                        <!-- Results -->
                        <div class="d-none mt-3" id="zipResults"></div>

                        <div class="text-end mt-3">
                            <button type="button" class="btn btn-success btn-sm d-none" id="btnZipSubmit">
                                <i class="bi bi-cloud-upload me-1"></i>Upload &amp; Extract ZIP
                            </button>
                        </div>
                    </div>

                </div><!-- /tab-content -->
            </div><!-- /modal-body -->

            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     MODAL — Update ส่งจริง (Import Actual Date from Excel)
════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="importActualModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0">
                    <i class="bi bi-file-earmark-arrow-up me-2 text-warning"></i>
                    Update วันส่งจริง จากไฟล์ Excel
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">

                <!-- Info -->
                <div class="alert alert-info py-2 small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    ไฟล์ต้องมี <strong>2 คอลัมน์</strong> เท่านั้น:
                    <span class="badge bg-secondary ms-1">A: Die No</span>
                    <span class="badge bg-secondary ms-1">B: ส่งจริง (วันที่)</span>
                    <br>รองรับ <code>.xlsx</code> และ <code>.csv</code>
                    &nbsp;|&nbsp; รายการที่ตรง Die No และมีวันที่ จะเปลี่ยนเป็น 🟢 Delivered อัตโนมัติ
                </div>

                <!-- Drop zone -->
                <div id="actualDropZone" style="border:2px dashed #dee2e6;border-radius:8px;padding:1.5rem;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;color:#6c757d">
                    <input type="file" id="actualFileInput" accept=".xlsx,.csv" style="display:none">
                    <i class="bi bi-file-earmark-spreadsheet fs-2 d-block mb-2 text-warning"></i>
                    <p class="mb-1 fw-semibold">Drag &amp; drop ไฟล์ Excel / CSV ที่นี่</p>
                    <p class="small text-muted mb-2">หรือคลิกเพื่อเลือกไฟล์</p>
                    <button type="button" class="btn btn-outline-warning btn-sm"
                            onclick="document.getElementById('actualFileInput').click()">
                        เลือกไฟล์
                    </button>
                </div>

                <!-- Skip first row checkbox -->
                <div class="form-check mt-2 ms-1">
                    <input class="form-check-input" type="checkbox" id="actualSkipFirst" checked>
                    <label class="form-check-label small" for="actualSkipFirst">
                        ข้ามแถวแรก (Header row)
                    </label>
                </div>

                <!-- Preview table -->
                <div class="d-none mt-3" id="actualPreviewWrap">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="small fw-semibold">
                            <i class="bi bi-eye me-1"></i>ตัวอย่างข้อมูล (5 แถวแรก)
                            &nbsp;<span id="actualTotalRows" class="text-muted"></span>
                        </span>
                        <button type="button" class="btn btn-success btn-sm" id="btnActualConfirm">
                            <i class="bi bi-cloud-upload me-1"></i>ยืนยัน Import
                        </button>
                    </div>
                    <div class="table-responsive" style="max-height:200px;overflow-y:auto">
                        <table class="table table-sm table-bordered mb-0 small">
                            <thead class="table-dark">
                                <tr>
                                    <th>Die No</th>
                                    <th>ส่งจริง</th>
                                </tr>
                            </thead>
                            <tbody id="actualPreviewBody"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Progress -->
                <div class="d-none mt-3" id="actualProgress">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>กำลัง Import…</span>
                        <span id="actualProgressPct">0%</span>
                    </div>
                    <div class="progress" style="height:6px">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning"
                             id="actualProgressBar" role="progressbar" style="width:0%"></div>
                    </div>
                </div>

                <!-- Results -->
                <div class="d-none mt-3" id="actualResults"></div>

            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     JavaScript
════════════════════════════════════════════════════════════════ -->
<script>
const APP_IS_ADMIN = <?= isAdmin() ? 'true' : 'false' ?>;

(function () {
'use strict';

const DMK_API          = '<?= BASE_URL ?>/api/dmk_plan.php';
const WEEKS_API        = '<?= BASE_URL ?>/api/weeks.php';
const ZIP_API          = '<?= BASE_URL ?>/api/export_zip.php';
const EXCEL_API        = '<?= BASE_URL ?>/api/export_excel.php';
const IMG_API          = '<?= BASE_URL ?>/api/upload_images.php';
const ZIP_IMG_API      = '<?= BASE_URL ?>/api/upload_zip.php';
const UPLOAD_DIE_IMG   = '<?= BASE_URL ?>/api/upload_die_image.php';

// ── State ──────────────────────────────────────────────────────
const state = {
    week:           '',
    dies:           [],
    weeksData:      [],
    lightboxImages: [],
    lightboxIdx:    0,
    quickDieId:     null,
    quickThumbEl:   null,
    filterDelivery: 'pending',
    filterSection:  '',
    filterTechDwg:  '',
    filterImage:    'all',
};

// ── Utility ────────────────────────────────────────────────────
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
    const el  = document.getElementById('dmkToast');
    const txt = document.getElementById('dmkToastMsg');
    el.className = `toast align-items-center border-0 text-white bg-${type}`;
    txt.textContent = msg;
    bootstrap.Toast.getOrCreateInstance(el, { delay: 3500 }).show();
}

function currentISOWeek() {
    const now = new Date();
    const d   = new Date(Date.UTC(now.getFullYear(), now.getMonth(), now.getDate()));
    const day = d.getUTCDay() || 7;
    d.setUTCDate(d.getUTCDate() + 4 - day);
    const jan1 = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
    return `${Math.ceil((((d - jan1) / 86400000) + 1) / 7)}/${d.getUTCFullYear()}`;
}

// ── Delivery status badge ──────────────────────────────────────
function deliveryBadge(actualDate) {
    return actualDate
        ? '<span class="badge bg-success">🟢 Delivered</span>'
        : '<span class="badge" style="background:#ffc107;color:#212529">🟡 Pending</span>';
}

function updateFilterCounts() {
    const pending   = state.dies.filter(d => !d.die_pc_actual_date).length;
    const delivered = state.dies.filter(d =>  d.die_pc_actual_date).length;
    const sel = document.getElementById('filterDelivery');
    if (sel) {
        sel.options[0].text = `ทั้งหมด (${state.dies.length})`;
        sel.options[1].text = `🟡 Pending (${pending})`;
        sel.options[2].text = `🟢 Delivered (${delivered})`;
    }
    const noImg  = state.dies.filter(d => !d.image_path).length;
    const hasImg = state.dies.filter(d =>  d.image_path).length;
    const imgSel = document.getElementById('filterImage');
    if (imgSel) {
        imgSel.options[0].text = `ทั้งหมด (${state.dies.length})`;
        imgSel.options[1].text = `❌ ไม่มีรูป (${noImg})`;
        imgSel.options[2].text = `✅ มีรูป (${hasImg})`;
    }
}


// ── Badge helpers ──────────────────────────────────────────────
const DMK_MAP = {
    'W/C':              'bg-primary',
    'CNC':              'bg-warning text-dark',
    'Pending':          'bg-secondary',
    'กลึง':            'bg-secondary',
    'กลึงเหวี่ยง':    'bg-info text-dark',
    'รอดำเนินการ':     'bg-secondary',
    '#N/A':             'bg-secondary',
};
const DMK_STYLE = {
    'กัดเช็ค':          'background:#fd7e14',
    'ชุบแข็ง':          'background:#6f42c1',
    'ตัดเหล็ก':         'background:#6c757d',
    'EDM':               'background:#343a40',
    'ระหว่างดำเนินการ': 'background:#0ea5e9',
};

function dmkBadge(v) {
    if (!v) return '<span class="text-muted small">—</span>';
    const cls   = DMK_MAP[v]   ?? 'bg-secondary';
    const style = DMK_STYLE[v] ? ` style="${DMK_STYLE[v]}"` : '';
    return `<span class="badge ${cls}"${style}>${esc(v)}</span>`;
}

const HOLLOW_LEVEL_LABEL = { easy: 'ง่าย', medium: 'กลาง', hard: 'ยาก' };
const HOLLOW_LEVEL_BADGE = { easy: 'bg-success', medium: 'bg-warning text-dark', hard: 'bg-danger' };
const TYPE_BADGE = { Solid: 'bg-success', Hollow: 'bg-warning text-dark', Heatsink: 'bg-primary' };

function typeBadge(dieType, hollowLevel) {
    if (!dieType) return '<span class="text-muted small">—</span>';
    if (dieType === 'Hollow' && hollowLevel) {
        const cls   = HOLLOW_LEVEL_BADGE[hollowLevel] ?? 'bg-warning text-dark';
        const label = 'Hollow ' + (HOLLOW_LEVEL_LABEL[hollowLevel] ?? hollowLevel);
        return `<span class="badge ${cls}">${esc(label)}</span>`;
    }
    return `<span class="badge ${TYPE_BADGE[dieType] ?? 'bg-secondary'}">${esc(dieType)}</span>`;
}

function pcBadge(v) {
    const cls = v === 'Finish' ? 'bg-success' : 'bg-warning text-dark';
    return `<span class="badge ${cls}">${esc(v)}</span>`;
}

// ── Thumbnail ──────────────────────────────────────────────────
// image_source === 'api': image comes from the Section Profile API — read-only.
// image_source === 'local' / null: no API image for this section — admin can
// upload/delete a fallback image manually (stored locally, compressed server-side).
function thumbCell(die, rowIdx) {
    const canEdit = APP_IS_ADMIN && die.image_source !== 'api';

    if (!die.image_path) {
        if (!canEdit) {
            return `<td><div class="thumb-wrap thumb-empty" title="ไม่มีรูป">
                <i class="bi bi-image" style="font-size:1.2rem;color:#ced4da"></i>
            </div></td>`;
        }
        return `<td>
            <div class="thumb-wrap thumb-upload"
                 data-die-id="${die.id}"
                 title="คลิกเพื่ออัพโหลดรูป Section">
                <i class="bi bi-image" style="font-size:1.2rem;color:#ced4da"></i>
                <span class="upload-hint"><i class="bi bi-cloud-upload me-1"></i>Upload</span>
            </div>
        </td>`;
    }

    const onerror = canEdit
        ? `(function(w){w.removeAttribute('data-row');w.className='thumb-wrap thumb-upload';w.title='คลิกเพื่ออัพโหลดรูป Section';w.innerHTML='<i class=\\'bi bi-image\\' style=\\'font-size:1.2rem;color:#ced4da\\'></i><span class=\\'upload-hint\\'><i class=\\'bi bi-cloud-upload me-1\\'></i>Upload</span>';})(this.parentElement)`
        : `this.parentElement.className='thumb-wrap thumb-empty';this.parentElement.title='ไม่มีรูป';this.parentElement.innerHTML='<i class=\\'bi bi-image\\' style=\\'font-size:1.2rem;color:#ced4da\\'></i>'`;

    const delBtn = canEdit
        ? `<button class="btn-del-img" data-die-id="${die.id}" title="ลบรูป">&times;</button>`
        : '';

    return `<td>
        <div class="thumb-cell-wrap">
            <div class="thumb-wrap" data-row="${rowIdx}" data-idx="0"
                 data-die-id="${die.id}"
                 title="คลิกดูรูปใหญ่">
                <img src="${esc(die.image_path)}"
                     alt="${esc(die.section ?? '')}"
                     loading="lazy"
                     onerror="${onerror}">
            </div>
            ${delBtn}
        </div>
    </td>`;
}

// ── Render table ───────────────────────────────────────────────
function renderTable(dies) {
    const tbody = document.getElementById('dmkTbody');

    updateFilterCounts();

    // Apply all filters
    let filtered = dies;
    if (state.filterSection) {
        const q = state.filterSection.toLowerCase();
        filtered = filtered.filter(d => (d.section ?? '').toLowerCase().includes(q));
    }
    if (state.filterTechDwg) {
        const q = state.filterTechDwg.toLowerCase();
        filtered = filtered.filter(d => (d.tech_dwg_no ?? '').toLowerCase().includes(q));
    }
    if (state.filterDelivery === 'pending')   filtered = filtered.filter(d => !d.die_pc_actual_date);
    if (state.filterDelivery === 'delivered') filtered = filtered.filter(d =>  d.die_pc_actual_date);
    if (state.filterImage === 'no_image')     filtered = filtered.filter(d => !d.image_path);
    if (state.filterImage === 'has_image')    filtered = filtered.filter(d =>  d.image_path);

    const pendingCount = dies.filter(d => !d.die_pc_actual_date).length;
    const isFiltered = state.filterSection || state.filterTechDwg || state.filterDelivery !== 'all' || state.filterImage !== 'all';
    const countTxt = isFiltered
        ? `${filtered.length} record${filtered.length !== 1 ? 's' : ''} (จาก ${dies.length})`
        : `${dies.length} record${dies.length !== 1 ? 's' : ''}`;
    const pendingTxt = pendingCount > 0 ? ` · <span style="color:#ffc107">🟡 ${pendingCount} Pending</span>` : '';
    document.getElementById('recordCount').innerHTML = countTxt + pendingTxt;

    if (!filtered.length) {
        tbody.innerHTML = `<tr><td colspan="13" class="text-center py-5 text-secondary">
            <i class="bi bi-inbox fs-3 d-block mb-1"></i>ไม่มีรายการในกลุ่มที่เลือก</td></tr>`;
        return;
    }

    tbody.innerHTML = filtered.map((d, i) => {
        const origIdx = dies.indexOf(d);
        const rowCls = d.plan_status === 'waiting' ? ' status-waiting'
                     : d.plan_status === 'hold'    ? ' status-hold' : '';
        return `<tr class="${rowCls}">
            <td class="text-muted text-center">${i + 1}</td>
            ${thumbCell(d, origIdx)}
            <td class="text-center small text-secondary fw-semibold">${esc(d.die_finish_plan ?? '')}</td>
            <td>${esc(d.machine)}</td>
            <td class="fw-semibold">${esc(d.section)}</td>
            <td class="text-muted small">${esc(d.tech_dwg_no)}</td>
            <td class="text-center">${esc(d.index_tab)}</td>
            <td>${fmtDate(d.die_pc_due_date)}</td>
            <td class="text-center">${typeBadge(d.die_type, d.hollow_level)}</td>
            <td class="text-center">${dmkBadge(d.dmk_status)}</td>
            <td class="text-center delivery-status-cell">${deliveryBadge(d.die_pc_actual_date)}</td>
            <td>${APP_IS_ADMIN
                ? `<input type="date" class="actual-date-input"
                       data-id="${d.id}"
                       value="${esc(d.die_pc_actual_date ?? '')}"
                       title="คลิกเพื่อกรอกวันที่ส่งจริง">`
                : `<span class="small">${esc(d.die_pc_actual_date ?? '—')}</span>`
            }</td>
            <td class="text-info fw-semibold">${esc(d.die_no)}</td>
        </tr>`;
    }).join('');
}

// ── Propagate manually-uploaded fallback images to sibling rows sharing
//    the same section (เบอร์แม่พิมพ์). Never touches API-sourced images —
//    those already arrive identical for every die in the same section. ──
function propagateSectionImages() {
    // Reset any previously shared flags
    for (const d of state.dies) {
        if (d._sharedImage) {
            d.image_path   = null;
            d.images       = [];
            d._sharedImage = false;
        }
    }
    // Build map: section → first die that owns a real local fallback image
    const map = {};
    for (const d of state.dies) {
        if (d.image_source === 'local' && d.image_path && !map[d.section]) {
            map[d.section] = { image_path: d.image_path, images: d.images };
        }
    }
    // Apply to rows that have no image (and aren't API-sourced) but share a section
    for (const d of state.dies) {
        if (d.image_source !== 'api' && !d.image_path && map[d.section]) {
            d.image_path   = map[d.section].image_path;
            d.images       = map[d.section].images;
            d.image_source = 'local';
            d._sharedImage = true;
        }
    }
}

// ── Delete image (manual fallback only) ─────────────────────────
const DELETE_IMG_API = '<?= BASE_URL ?>/api/delete_die_image.php';

async function deleteImage(dieId) {
    const clickedDie = state.dies.find(d => d.id === dieId);
    if (!clickedDie) return;

    // If clicked die shows a shared image, resolve to the actual owner
    let ownerDie = clickedDie;
    if (clickedDie._sharedImage) {
        ownerDie = state.dies.find(d => d.section === clickedDie.section && d.image_source === 'local' && !d._sharedImage && d.image_path)
                   ?? clickedDie;
    }

    if (!confirm('ต้องการลบรูปของแม่พิมพ์นี้ใช่ไหม?')) return;
    try {
        const res  = await fetch(`${DELETE_IMG_API}?die_id=${ownerDie.id}`, { method: 'DELETE' });
        const json = await res.json();
        if (!res.ok || json.error) throw new Error(json.error ?? 'Delete failed');

        // Clear the owner die's images
        ownerDie.image_path   = null;
        ownerDie.images       = [];
        ownerDie.image_source = null;
        ownerDie._sharedImage = false;

        propagateSectionImages();
        renderTable(state.dies);
        showToast('ลบรูปสำเร็จ');
    } catch (err) {
        showToast('ลบรูปไม่สำเร็จ: ' + err.message, 'danger');
    }
}

// ── Load DMK data ──────────────────────────────────────────────
async function loadDMK(week) {
    state.week = week;
    const tbody = document.getElementById('dmkTbody');
    tbody.innerHTML = `<tr><td colspan="13" class="text-center py-4 text-secondary">
        <div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>`;

    // Update heading
    document.getElementById('weekLabel').textContent =
        week === 'all' ? 'ทุก Week' : (week ? `WEEK ${week}` : '—');

    try {
        const res  = await fetch(`${DMK_API}?week=${encodeURIComponent(week)}`);
        const json = await res.json();
        if (!res.ok) throw new Error(json.error ?? `HTTP ${res.status}`);

        state.dies            = json.data ?? [];
        state.filterSection   = '';
        state.filterTechDwg   = '';
        state.filterImage     = 'all';
        state.filterDelivery  = 'pending';
        document.getElementById('filterSection').value  = '';
        document.getElementById('filterTechDwg').value  = '';
        document.getElementById('filterImage').value    = 'all';
        document.getElementById('filterDelivery').value = 'pending';
        propagateSectionImages();
        renderTable(state.dies);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="13" class="text-center text-danger py-3">
            <i class="bi bi-exclamation-triangle me-1"></i>${esc(err.message)}</td></tr>`;
    }
}

// ── Load week dropdown ─────────────────────────────────────────
async function loadWeeks() {
    const sel = document.getElementById('weekSelect');
    try {
        const res  = await fetch(WEEKS_API);
        const data = await res.json();
        const rows = Array.isArray(data) ? data : [];

        // Real week rows only (exclude waiting/hold pseudo-rows and fully-completed weeks)
        const weeks = rows.filter(r => r.week !== 'waiting' && r.week !== 'hold' && (r.pending ?? 1) > 0);
        state.weeksData = weeks;

        const urlWeek   = new URLSearchParams(location.search).get('week');
        const thisWeek  = urlWeek ?? 'all';
        const available = new Set(weeks.map(r => r.week));

        // Collect specific weeks; add current if not yet in DB
        const weekList = [...available];
        if (thisWeek && thisWeek !== 'all' && !available.has(thisWeek)) weekList.push(thisWeek);
        weekList.sort((a, b) => {
            const [wa, ya] = a.split('/').map(Number);
            const [wb, yb] = b.split('/').map(Number);
            return ya !== yb ? ya - yb : wa - wb;
        });

        // "All Weeks" first, then specific weeks
        sel.innerHTML =
            `<option value="all" ${thisWeek === 'all' ? 'selected' : ''}>— ทุก Week —</option>` +
            weekList.map(w =>
                `<option value="${esc(w)}" ${w === thisWeek ? 'selected' : ''}>Week ${esc(w)}</option>`
            ).join('');

        // Set subtitle
        if (thisWeek === 'all') {
            document.getElementById('weekRange').textContent = 'แสดงข้อมูลทุก Week';
        } else {
            const current = weeks.find(r => r.week === thisWeek);
            if (current) document.getElementById('weekRange').textContent = current.date_range || '';
        }

        return sel.value || thisWeek;
    } catch {
        sel.innerHTML =
            `<option value="all">— ทุก Week —</option>` +
            `<option value="${esc(currentISOWeek())}" selected>Week ${esc(currentISOWeek())}</option>`;
        return currentISOWeek();
    }
}

// ── Lightbox ───────────────────────────────────────────────────
const lbModal   = new bootstrap.Modal(document.getElementById('lightboxModal'));
const lbImg     = document.getElementById('lightboxImg');
const lbLabel   = document.getElementById('lightboxLabel');
const lbCounter = document.getElementById('lightboxCounter');

function openLightbox(rowIdx, imgIdx) {
    const die    = state.dies[rowIdx];
    if (!die || !die.images?.length) return;
    state.lightboxImages = die.images;
    state.lightboxIdx    = imgIdx;
    updateLightbox();
    lbModal.show();
}

function updateLightbox() {
    const imgs = state.lightboxImages;
    const idx  = state.lightboxIdx;
    lbImg.src  = imgs[idx] ?? '';
    lbLabel.textContent = `${state.dies.find(d => d.images?.includes(imgs[idx]))?.no ?? ''} — Image ${idx + 1}`;
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

// Thumbnail click via event delegation
document.getElementById('dmkTbody').addEventListener('click', e => {
    // Delete button takes priority
    const delBtn = e.target.closest('.btn-del-img');
    if (delBtn) {
        e.stopPropagation();
        deleteImage(+delBtn.dataset.dieId);
        return;
    }

    const tw = e.target.closest('.thumb-wrap');
    if (!tw) return;

    if (tw.classList.contains('thumb-upload') && tw.dataset.dieId) {
        if (!APP_IS_ADMIN) return; // guest cannot upload
        // Upload mode — open file picker
        state.quickDieId   = +tw.dataset.dieId;
        state.quickThumbEl = tw;
        document.getElementById('quickUploadInput').click();
    } else if (tw.dataset.row !== undefined) {
        // Lightbox mode
        openLightbox(+tw.dataset.row, +tw.dataset.idx);
    }
});

// ── Quick upload from thumbnail (manual fallback) ───────────────
document.getElementById('quickUploadInput').addEventListener('change', async function () {
    const file    = this.files[0];
    const dieId   = state.quickDieId;
    const thumbEl = state.quickThumbEl;
    this.value = '';  // reset so same file can be re-selected

    if (!file || !dieId) return;

    thumbEl?.classList.add('thumb-uploading');

    const fd = new FormData();
    fd.append('die_id', dieId);
    fd.append('image',  file);

    try {
        const res  = await fetch(UPLOAD_DIE_IMG, { method: 'POST', body: fd });
        const json = await res.json();
        if (!res.ok || json.error) throw new Error(json.error ?? 'Upload failed');

        const dieIdx = state.dies.findIndex(d => d.id === dieId);
        if (dieIdx >= 0) {
            state.dies[dieIdx].image_path   = json.url;
            state.dies[dieIdx].images       = [json.url];
            state.dies[dieIdx].image_source = 'local';
            state.dies[dieIdx]._sharedImage = false;
        }
        propagateSectionImages();
        renderTable(state.dies);
        showToast('อัพโหลดรูปสำเร็จ');
    } catch (err) {
        thumbEl?.classList.remove('thumb-uploading');
        showToast('อัพโหลดไม่สำเร็จ: ' + err.message, 'danger');
    } finally {
        state.quickDieId   = null;
        state.quickThumbEl = null;
    }
});

// ── Inline ส่งจริง date save ────────────────────────────────────
const DIES_API = '<?= BASE_URL ?>/api/dies.php';

document.getElementById('dmkTbody').addEventListener('change', async e => {
    const input = e.target.closest('input.actual-date-input');
    if (!input) return;

    const id    = +input.dataset.id;
    const value = input.value;  // "YYYY-MM-DD" or ""

    input.disabled = true;
    try {
        const res = await fetch(`${DIES_API}?id=${id}`, {
            method:  'PUT',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ die_pc_actual_date: value }),
        });
        const json = await res.json();
        if (!res.ok) throw new Error(json.error ?? `HTTP ${res.status}`);

        const die = state.dies.find(d => d.id === id);
        if (die) {
            die.die_pc_actual_date = value;
            // Update delivery badge in the same row
            const statusCell = input.closest('tr')?.querySelector('.delivery-status-cell');
            if (statusCell) statusCell.innerHTML = deliveryBadge(value);
            updateFilterCounts();
            // Re-render if filter would hide this row now
            if ((state.filterDelivery === 'pending' && value) ||
                (state.filterDelivery === 'delivered' && !value)) {
                renderTable(state.dies);
            } else {
                const pendingCount = state.dies.filter(d => !d.die_pc_actual_date).length;
                const countTxt = state.filterDelivery !== 'all'
                    ? `${document.querySelectorAll('#dmkTbody tr').length} records (จาก ${state.dies.length})`
                    : `${state.dies.length} record${state.dies.length !== 1 ? 's' : ''}`;
                const pendingTxt = pendingCount > 0 ? ` · <span style="color:#ffc107">🟡 ${pendingCount} Pending</span>` : '';
                document.getElementById('recordCount').innerHTML = countTxt + pendingTxt;
            }
        }
    } catch (err) {
        alert('บันทึกไม่สำเร็จ: ' + err.message);
        // Revert input to previous value from state
        const die = state.dies.find(d => d.id === id);
        input.value = die?.die_pc_actual_date ?? '';
    } finally {
        input.disabled = false;
    }
});

// ── Week selector change ───────────────────────────────────────
document.getElementById('weekSelect').addEventListener('change', function () {
    const week = this.value;
    history.replaceState(null, '', `?week=${encodeURIComponent(week)}`);
    if (week === 'all') {
        document.getElementById('weekRange').textContent = 'แสดงข้อมูลทุก Week';
    } else {
        const wData = state.weeksData.find(r => r.week === week);
        document.getElementById('weekRange').textContent = wData?.date_range || '';
    }
    loadDMK(week);
});

// ── Column filters ─────────────────────────────────────────────
document.getElementById('filterDelivery').addEventListener('change', function () {
    state.filterDelivery = this.value;
    renderTable(state.dies);
});
document.getElementById('filterSection').addEventListener('input', function () {
    state.filterSection = this.value.trim();
    renderTable(state.dies);
});
document.getElementById('filterTechDwg').addEventListener('input', function () {
    state.filterTechDwg = this.value.trim();
    renderTable(state.dies);
});
document.getElementById('filterImage').addEventListener('change', function () {
    state.filterImage = this.value;
    renderTable(state.dies);
});

// ── Toolbar: Print ─────────────────────────────────────────────
document.getElementById('btnPrint').addEventListener('click', () => {
    const weekLabel = state.week === 'all' ? 'ทุก Week' : `WEEK ${state.week}`;
    document.getElementById('printTitle').textContent    = `แผนผลิตแม่พิมพ์ ${weekLabel}`;
    document.getElementById('printSubtitle').textContent =
        document.getElementById('weekRange').textContent;
    window.print();
});

// ── Toolbar: Export ZIP ────────────────────────────────────────
document.getElementById('btnZip').addEventListener('click', () => {
    if (!state.week) return;
    const btn = document.getElementById('btnZip');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Preparing…';

    // Trigger download via anchor element
    const a = document.createElement('a');
    a.href  = `${ZIP_API}?week=${encodeURIComponent(state.week)}`;
    a.download = `DMK_Week_${state.week.replace('/', '-')}.zip`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);

    setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-file-earmark-zip me-1"></i>Export ZIP';
    }, 2000);
});

// ── Toolbar: Export Excel ──────────────────────────────────────
document.getElementById('btnExcelExport').addEventListener('click', () => {
    if (!state.week) return;
    const btn = document.getElementById('btnExcelExport');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating…';

    const a = document.createElement('a');
    a.href     = `${EXCEL_API}?week=${encodeURIComponent(state.week)}`;
    a.download = `DMK_Week_${state.week.replace('/', '-')}.xlsx`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);

    setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-file-earmark-excel me-1"></i>Export Excel';
    }, 4000);
});

// ── Upload modal ────────────────────────────────────────────────
const uploadModal = new bootstrap.Modal(document.getElementById('uploadModal'));
let pendingFiles  = [];
let pendingZip    = null;

function resetUploadModal() {
    // Single/multiple tab
    pendingFiles = [];
    document.getElementById('fileQueue').innerHTML = '';
    document.getElementById('fileQueue').classList.add('d-none');
    document.getElementById('uploadProgress').classList.add('d-none');
    document.getElementById('uploadResults').classList.add('d-none');
    document.getElementById('btnUploadSubmit').classList.add('d-none');
    document.getElementById('uploadBar').style.width = '0%';
    document.getElementById('uploadPct').textContent = '0%';
    // ZIP tab
    resetZipTab();
}

function resetZipTab() {
    pendingZip = null;
    document.getElementById('zipFileInfo').classList.add('d-none');
    document.getElementById('zipProgress').classList.add('d-none');
    document.getElementById('zipResults').classList.add('d-none');
    document.getElementById('btnZipSubmit').classList.add('d-none');
    document.getElementById('zipBar').style.width = '0%';
    document.getElementById('zipPct').textContent = '0%';
    document.getElementById('zipProgressLabel').textContent = 'Uploading…';
}

document.getElementById('btnUpload')?.addEventListener('click', () => {
    resetUploadModal();
    uploadModal.show();
});

function addFiles(fileList) {
    for (const f of fileList) {
        if (!f.type.startsWith('image/')) continue;
        if (!pendingFiles.find(p => p.name === f.name && p.size === f.size)) {
            pendingFiles.push(f);
        }
    }
    renderFileQueue();
}

function renderFileQueue() {
    const ul  = document.getElementById('fileQueue');
    const btn = document.getElementById('btnUploadSubmit');
    const cnt = document.getElementById('uploadCount');

    if (!pendingFiles.length) {
        ul.classList.add('d-none');
        btn.classList.add('d-none');
        return;
    }

    ul.classList.remove('d-none');
    btn.classList.remove('d-none');
    cnt.textContent = `(${pendingFiles.length})`;

    ul.innerHTML = pendingFiles.map((f, i) => `
        <li class="list-group-item d-flex justify-content-between align-items-center py-1 px-2 small">
            <span><i class="bi bi-image me-1 text-primary"></i>${esc(f.name)}</span>
            <span class="d-flex align-items-center gap-2">
                <span class="text-muted">${(f.size / 1024).toFixed(1)} KB</span>
                <button type="button" class="btn-close btn-sm" data-i="${i}"
                        style="font-size:.6rem"></button>
            </span>
        </li>`).join('');
}

// Remove file from queue
document.getElementById('fileQueue').addEventListener('click', e => {
    const btn = e.target.closest('.btn-close[data-i]');
    if (btn) {
        pendingFiles.splice(+btn.dataset.i, 1);
        renderFileQueue();
    }
});

// Drop zone events
const dropZone = document.getElementById('dropZone');
dropZone.addEventListener('click',     () => document.getElementById('fileInput').click());
dropZone.addEventListener('dragover',  e  => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop',      e  => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    addFiles(e.dataTransfer.files);
});
document.getElementById('fileInput').addEventListener('change', function () {
    addFiles(this.files);
    this.value = '';   // reset so same files can be re-added after removal
});

// Submit upload using XHR for real progress
document.getElementById('btnUploadSubmit').addEventListener('click', () => {
    if (!pendingFiles.length) return;

    const progressEl = document.getElementById('uploadProgress');
    const barEl      = document.getElementById('uploadBar');
    const pctEl      = document.getElementById('uploadPct');
    const resultsEl  = document.getElementById('uploadResults');
    const submitBtn  = document.getElementById('btnUploadSubmit');

    progressEl.classList.remove('d-none');
    submitBtn.disabled = true;

    const fd = new FormData();
    pendingFiles.forEach(f => fd.append('images[]', f));

    const xhr = new XMLHttpRequest();

    xhr.upload.onprogress = e => {
        if (e.lengthComputable) {
            const pct = Math.round(e.loaded / e.total * 100);
            barEl.style.width = pct + '%';
            pctEl.textContent = pct + '%';
        }
    };

    xhr.onload = () => {
        const json = JSON.parse(xhr.responseText);
        progressEl.classList.add('d-none');
        submitBtn.disabled = false;

        const rows = (json.results ?? []).map(r =>
            `<li class="list-group-item py-1 px-2 small">
                <i class="bi bi-${r.status==='ok'?'check-circle text-success':'x-circle text-danger'} me-1"></i>
                ${esc(r.name)} ${r.error ? `<span class="text-danger">(${esc(r.error)})</span>` : ''}
             </li>`
        ).join('');

        resultsEl.innerHTML = `
            <p class="small fw-semibold mb-1">
                Uploaded: ${json.uploaded ?? 0} &nbsp;|&nbsp;
                Failed: ${json.failed ?? 0}
            </p>
            <ul class="list-group">${rows}</ul>`;
        resultsEl.classList.remove('d-none');

        if ((json.uploaded ?? 0) > 0) {
            pendingFiles = [];
            document.getElementById('fileQueue').innerHTML = '';
            document.getElementById('fileQueue').classList.add('d-none');
            document.getElementById('btnUploadSubmit').classList.add('d-none');
            loadDMK(state.week);  // Refresh to show new images
        }
    };

    xhr.onerror = () => {
        progressEl.classList.add('d-none');
        submitBtn.disabled = false;
        resultsEl.innerHTML = '<p class="text-danger small">Upload request failed.</p>';
        resultsEl.classList.remove('d-none');
    };

    xhr.open('POST', IMG_API);
    xhr.send(fd);
});

// ── ZIP upload tab ──────────────────────────────────────────────
const zipDropZone = document.getElementById('zipDropZone');

zipDropZone.addEventListener('click',     () => document.getElementById('zipFileInput').click());
zipDropZone.addEventListener('dragover',  e  => { e.preventDefault(); zipDropZone.classList.add('drag-over'); });
zipDropZone.addEventListener('dragleave', () => zipDropZone.classList.remove('drag-over'));
zipDropZone.addEventListener('drop', e => {
    e.preventDefault();
    zipDropZone.classList.remove('drag-over');
    const f = e.dataTransfer.files[0];
    if (f) setZipFile(f);
});

document.getElementById('zipFileInput').addEventListener('change', function () {
    if (this.files[0]) setZipFile(this.files[0]);
    this.value = '';
});

function setZipFile(f) {
    if (!f.name.toLowerCase().endsWith('.zip')) {
        alert('Please select a .zip file.');
        return;
    }
    if (f.size > 200 * 1024 * 1024) {
        alert('File is larger than 200 MB. Please use a smaller ZIP.');
        return;
    }
    pendingZip = f;
    document.getElementById('zipFileName').textContent = f.name;
    document.getElementById('zipFileSize').textContent = (f.size / 1048576).toFixed(1) + ' MB';
    document.getElementById('zipFileInfo').classList.remove('d-none');
    document.getElementById('btnZipSubmit').classList.remove('d-none');
    document.getElementById('zipProgress').classList.add('d-none');
    document.getElementById('zipResults').classList.add('d-none');
}

document.getElementById('btnClearZip').addEventListener('click', resetZipTab);

document.getElementById('btnZipSubmit').addEventListener('click', () => {
    if (!pendingZip) return;

    const progressEl = document.getElementById('zipProgress');
    const barEl      = document.getElementById('zipBar');
    const pctEl      = document.getElementById('zipPct');
    const labelEl    = document.getElementById('zipProgressLabel');
    const resultsEl  = document.getElementById('zipResults');
    const submitBtn  = document.getElementById('btnZipSubmit');

    progressEl.classList.remove('d-none');
    resultsEl.classList.add('d-none');
    submitBtn.disabled = true;
    labelEl.textContent = 'Uploading…';
    barEl.style.width   = '0%';
    pctEl.textContent   = '0%';

    const fd = new FormData();
    fd.append('zip_file', pendingZip);

    const xhr = new XMLHttpRequest();

    xhr.upload.onprogress = e => {
        if (e.lengthComputable) {
            const pct = Math.round(e.loaded / e.total * 100);
            barEl.style.width = pct + '%';
            pctEl.textContent = pct + '%';
            if (pct === 100) {
                labelEl.textContent = 'Extracting & processing…';
            }
        }
    };

    xhr.onload = () => {
        progressEl.classList.add('d-none');
        submitBtn.disabled = false;

        let json;
        try { json = JSON.parse(xhr.responseText); }
        catch (_) {
            resultsEl.innerHTML = '<p class="text-danger small"><i class="bi bi-exclamation-triangle me-1"></i>Invalid server response.</p>';
            resultsEl.classList.remove('d-none');
            return;
        }

        if (json.error) {
            resultsEl.innerHTML = `<p class="text-danger small"><i class="bi bi-exclamation-triangle me-1"></i>${esc(json.error)}</p>`;
            resultsEl.classList.remove('d-none');
            return;
        }

        const matchedRows = (json.matched_list ?? []).slice(0, 30).map(m =>
            `<li class="list-group-item py-1 px-2 small">
                <i class="bi bi-check-circle-fill text-success me-1"></i>${esc(m)}
             </li>`
        ).join('');

        const hasMore = (json.matched_list ?? []).length > 30
            ? `<li class="list-group-item py-1 px-2 small text-muted">… and ${json.matched_list.length - 30} more</li>`
            : '';

        resultsEl.innerHTML = `
            <div class="alert alert-success py-2 small mb-2">
                <strong><i class="bi bi-check-circle me-1"></i>Done!</strong>
                &nbsp;${json.total_files} images found &mdash;
                <span class="text-success fw-semibold">${json.matched} matched</span>,
                ${json.unmatched} unmatched
                ${json.errors?.length ? `, <span class="text-warning">${json.errors.length} error(s)</span>` : ''}
            </div>
            ${matchedRows ? `
            <p class="small fw-semibold mb-1 mt-2">Matched Dies:</p>
            <ul class="list-group list-group-flush" style="max-height:160px;overflow-y:auto">
                ${matchedRows}${hasMore}
            </ul>` : ''}
            ${(json.matched ?? 0) > 0 || (json.unmatched ?? 0) > 0 ? `
            <button class="btn btn-sm btn-outline-primary mt-3 w-100" id="btnRefreshAfterZip">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh DMK Plan
            </button>` : ''}`;
        resultsEl.classList.remove('d-none');

        document.getElementById('btnRefreshAfterZip')?.addEventListener('click', () => {
            loadDMK(state.week);
        });
    };

    xhr.onerror = () => {
        progressEl.classList.add('d-none');
        submitBtn.disabled = false;
        resultsEl.innerHTML = '<p class="text-danger small"><i class="bi bi-exclamation-triangle me-1"></i>Upload failed. Check your network connection.</p>';
        resultsEl.classList.remove('d-none');
    };

    xhr.open('POST', ZIP_IMG_API);
    xhr.send(fd);
});

// ── Import Actual Date (ส่งจริง) ──────────────────────────────
const UPLOAD_EXCEL_API     = '<?= BASE_URL ?>/api/upload_excel.php';
const importActualModal    = new bootstrap.Modal(document.getElementById('importActualModal'));
let   actualTempKey        = null;

function resetImportActualModal() {
    actualTempKey = null;
    document.getElementById('actualDropZone').style.borderColor = '#dee2e6';
    document.getElementById('actualDropZone').style.background  = '';
    document.getElementById('actualFileInput').value = '';
    document.getElementById('actualPreviewWrap').classList.add('d-none');
    document.getElementById('actualProgress').classList.add('d-none');
    document.getElementById('actualResults').classList.add('d-none');
    document.getElementById('actualProgressBar').style.width = '0%';
    document.getElementById('actualProgressPct').textContent  = '0%';
}

document.getElementById('btnImportActual')?.addEventListener('click', () => {
    resetImportActualModal();
    importActualModal.show();
});

// Drop zone
const actualDropZone = document.getElementById('actualDropZone');
actualDropZone.addEventListener('click', () => document.getElementById('actualFileInput').click());
actualDropZone.addEventListener('dragover', e => {
    e.preventDefault();
    actualDropZone.style.borderColor = '#ffc107';
    actualDropZone.style.background  = '#fffbea';
});
actualDropZone.addEventListener('dragleave', () => {
    actualDropZone.style.borderColor = '#dee2e6';
    actualDropZone.style.background  = '';
});
actualDropZone.addEventListener('drop', e => {
    e.preventDefault();
    actualDropZone.style.borderColor = '#dee2e6';
    actualDropZone.style.background  = '';
    const f = e.dataTransfer.files[0];
    if (f) uploadActualPreview(f);
});
document.getElementById('actualFileInput').addEventListener('change', function () {
    if (this.files[0]) uploadActualPreview(this.files[0]);
    this.value = '';
});

async function uploadActualPreview(file) {
    const ext = file.name.split('.').pop().toLowerCase();
    if (!['xlsx', 'csv'].includes(ext)) {
        showToast('รองรับเฉพาะไฟล์ .xlsx และ .csv', 'danger');
        return;
    }

    document.getElementById('actualPreviewWrap').classList.add('d-none');
    document.getElementById('actualResults').classList.add('d-none');
    document.getElementById('actualProgress').classList.remove('d-none');
    document.getElementById('actualProgressBar').style.width = '50%';
    document.getElementById('actualProgressPct').textContent = 'กำลังอ่านไฟล์…';

    const fd = new FormData();
    fd.append('action',     'preview_actual');
    fd.append('skip_first', document.getElementById('actualSkipFirst').checked ? '1' : '');
    fd.append('file',       file);

    try {
        const res  = await fetch(UPLOAD_EXCEL_API, { method: 'POST', body: fd });
        const json = await res.json();
        if (!res.ok || json.error) throw new Error(json.error ?? `HTTP ${res.status}`);

        actualTempKey = json.temp_key;

        // Render preview
        const tbody = document.getElementById('actualPreviewBody');
        tbody.innerHTML = (json.rows ?? []).map(r =>
            `<tr><td>${esc(r[0])}</td><td>${esc(r[1])}</td></tr>`
        ).join('') || '<tr><td colspan="2" class="text-center text-muted">ไม่มีข้อมูล</td></tr>';

        document.getElementById('actualTotalRows').textContent =
            `(${json.total_rows} แถวทั้งหมด)`;

        document.getElementById('actualProgress').classList.add('d-none');
        document.getElementById('actualPreviewWrap').classList.remove('d-none');

    } catch (err) {
        document.getElementById('actualProgress').classList.add('d-none');
        showToast('อ่านไฟล์ไม่ได้: ' + err.message, 'danger');
    }
}

document.getElementById('btnActualConfirm').addEventListener('click', async () => {
    if (!actualTempKey) return;

    const btn = document.getElementById('btnActualConfirm');
    btn.disabled = true;
    document.getElementById('actualResults').classList.add('d-none');
    document.getElementById('actualProgress').classList.remove('d-none');
    document.getElementById('actualProgressBar').style.width = '70%';
    document.getElementById('actualProgressPct').textContent = 'กำลัง Import…';

    const fd = new FormData();
    fd.append('action',     'import_actual');
    fd.append('skip_first', document.getElementById('actualSkipFirst').checked ? '1' : '');
    fd.append('temp_key',   actualTempKey);

    try {
        const res  = await fetch(UPLOAD_EXCEL_API, { method: 'POST', body: fd });
        const json = await res.json();
        if (!res.ok || json.error) throw new Error(json.error ?? `HTTP ${res.status}`);

        document.getElementById('actualProgress').classList.add('d-none');

        const errRows = (json.error_list ?? []).map(e =>
            `<li class="list-group-item py-1 px-2 small text-danger">
                <i class="bi bi-x-circle me-1"></i>${esc(e)}
             </li>`
        ).join('');

        document.getElementById('actualResults').innerHTML = `
            <div class="alert ${json.updated > 0 ? 'alert-success' : 'alert-warning'} py-2 small mb-2">
                <strong><i class="bi bi-check-circle me-1"></i>เสร็จสิ้น!</strong>
                &nbsp;อัพเดต <span class="fw-bold">${json.updated}</span> รายการ
                ${json.not_found > 0 ? ` &nbsp;|&nbsp; ไม่พบ Die No: <span class="text-danger fw-bold">${json.not_found}</span>` : ''}
                ${json.skipped  > 0 ? ` &nbsp;|&nbsp; ข้าม (ไม่มีวันที่): ${json.skipped}` : ''}
                ${json.errors   > 0 ? ` &nbsp;|&nbsp; Error: <span class="text-danger">${json.errors}</span>` : ''}
            </div>
            ${errRows ? `<ul class="list-group" style="max-height:140px;overflow-y:auto">${errRows}</ul>` : ''}
            ${json.updated > 0 ? `
            <button class="btn btn-sm btn-outline-success mt-2 w-100" id="btnRefreshAfterActual">
                <i class="bi bi-arrow-clockwise me-1"></i>รีเฟรชตาราง DMK Plan
            </button>` : ''}`;

        document.getElementById('actualResults').classList.remove('d-none');
        actualTempKey = null;

        document.getElementById('btnRefreshAfterActual')?.addEventListener('click', () => {
            loadDMK(state.week);
            importActualModal.hide();
        });

        if (json.updated > 0) {
            showToast(`อัพเดตวันส่งจริง ${json.updated} รายการเรียบร้อย`, 'success');
            loadDMK(state.week);
        }

    } catch (err) {
        document.getElementById('actualProgress').classList.add('d-none');
        showToast('Import ไม่สำเร็จ: ' + err.message, 'danger');
    } finally {
        btn.disabled = false;
    }
});

// ── Init ───────────────────────────────────────────────────────
(async () => {
    const week = await loadWeeks();
    if (week) await loadDMK(week);
})();

})();
</script>

<?php require_once '../includes/footer.php'; ?>
