<?php
$pageTitle   = 'Die Planning';
$currentPage = 'die-planning';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<style>
/* ── Table container ─────────────────────────────────────────────── */
.table-container {
    max-height: calc(100vh - 210px);
    overflow-y: auto;
    overflow-x: auto;
    isolation: isolate;
}
.table-container thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    background-color: #343a40 !important;
    color: #fff;
    white-space: nowrap;
    font-size: .75rem;
    padding: .35rem .45rem;
    vertical-align: middle;
}
.table-container tbody td {
    font-size: .78rem;
    padding: .3rem .45rem;
    vertical-align: middle;
    white-space: nowrap;
}
/* ── Row status colours ──────────────────────────────────────────── */
tr.status-waiting td { background-color: #fff8e1 !important; }
tr.status-hold    td { background-color: #fdecea !important; }

/* ── Action buttons ──────────────────────────────────────────────── */
.btn-action { padding: .15rem .4rem; font-size: .72rem; }

/* ── Toolbar ─────────────────────────────────────────────────────── */
.toolbar-row { gap: .4rem; }
</style>

<main class="container-fluid py-2">

    <!-- ── Toast alert ──────────────────────────────────────────── -->
    <div id="toastWrap" class="position-fixed top-0 end-0 p-3" style="z-index:9999">
        <div id="appToast" class="toast align-items-center border-0" role="alert" aria-live="assertive">
            <div class="d-flex">
                <div class="toast-body fw-semibold" id="toastMsg"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto"
                        data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <!-- ── Toolbar ─────────────────────────────────────────────── -->
    <div class="d-flex flex-wrap align-items-center toolbar-row mb-2">

        <!-- Week filter -->
        <div class="d-flex align-items-center gap-1">
            <label class="form-label mb-0 small fw-semibold text-nowrap">Week:</label>
            <select id="weekFilter" class="form-select form-select-sm" style="min-width:130px">
                <option value="">All Weeks</option>
            </select>
        </div>

        <!-- Live search -->
        <div class="flex-grow-1" style="max-width:280px">
            <input id="searchBox" type="search" class="form-control form-control-sm"
                   placeholder="Search customer / section / DWG No…">
        </div>

        <!-- Record count -->
        <span id="recordCount" class="text-secondary small ms-1 text-nowrap"></span>

        <!-- Right-side buttons -->
        <div class="ms-auto d-flex gap-1 flex-wrap">
            <?php if (isAdmin()): ?>
            <button class="btn btn-success btn-sm" id="btnAddDie">
                <i class="bi bi-plus-lg me-1"></i>Add Die
            </button>
            <button class="btn btn-outline-secondary btn-sm" id="btnImport">
                <i class="bi bi-folder2-open me-1"></i>Import Excel
            </button>
            <button class="btn btn-teal btn-sm" id="btnAddImport"
                    style="background-color:#0d9488;color:#fff;border-color:#0d9488">
                <i class="bi bi-file-earmark-arrow-up me-1"></i>นำเข้าข้อมูล
            </button>
            <button class="btn btn-sm" id="btnUpdateDates"
                    style="background-color:#7c3aed;color:#fff;border-color:#7c3aed"
                    title="อัปเดตวันที่จ่ายแบบ / DPC / Finish Plan จากไฟล์ Excel โดยใช้ Die No เป็นตัวอ้างอิง">
                <i class="bi bi-calendar-check me-1"></i>อัปเดตวันที่
            </button>
            <button class="btn btn-sm" id="btnSyncDMK"
                    style="background-color:#0ea5e9;color:#fff;border-color:#0ea5e9"
                    title="ดึงสถานะ DMK จาก API และอัปเดตทั้งหมด">
                <i class="bi bi-cloud-download me-1"></i>Update API DMK
            </button>
            <button class="btn btn-outline-danger btn-sm" id="btnDeleteAll">
                <i class="bi bi-trash3 me-1"></i>ลบข้อมูลทั้งหมด
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Table ───────────────────────────────────────────────── -->
    <div class="table-container border rounded">
        <table class="table table-bordered table-hover mb-0" id="diesTable">
            <thead>
                <tr>
                    <th style="min-width:45px">No</th>
                    <th style="min-width:110px">สาเหตุขอสร้างแม่พิมพ์</th>
                    <th style="min-width:150px">Customer</th>
                    <th style="min-width:110px">Tech DWG</th>
                    <th style="min-width:95px">วันที่จ่ายแบบ</th>
                    <th style="min-width:100px">วันที่ต้องส่ง DPC</th>
                    <th style="min-width:90px">Die Finish Plan</th>
                    <th style="min-width:70px">Machine</th>
                    <th style="min-width:100px">Die No</th>
                    <th style="min-width:95px">สถานะ DMK</th>
                    <th style="min-width:85px">ประเภทพิมพ์</th>
                    <th style="min-width:120px">หมายเหตุ</th>
                    <?php if (isAdmin()): ?>
                    <th style="min-width:80px" class="text-center">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="diesTbody">
                <tr><td colspan="<?= isAdmin() ? 13 : 12 ?>" class="text-center py-4 text-secondary">
                    <div class="spinner-border spinner-border-sm me-2"></div>Loading…
                </td></tr>
            </tbody>
        </table>
    </div>

</main>

<!-- ═══════════════════════════════════════════════════════════════
     MODAL — Add / Edit Die
════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="dieModal" tabindex="-1" aria-labelledby="dieModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header py-2 bg-dark text-white">
                <h6 class="modal-title mb-0" id="dieModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>Add New Die
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form id="dieForm" novalidate>
                    <input type="hidden" id="f_id">
                    <!-- No และ die_count ไม่กรอก — auto จาก server -->
                    <input type="hidden" id="f_no"        name="no">
                    <input type="hidden" id="f_die_count" name="die_count" value="1">

                    <!-- Row 1: Identity -->
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold mb-1">
                                Customer <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control form-control-sm" id="f_customer"
                                   name="customer" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold mb-1">Section</label>
                            <input type="text" class="form-control form-control-sm" id="f_section" name="section">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold mb-1">Index/ทับ</label>
                            <input type="text" class="form-control form-control-sm" id="f_index_tab" name="index_tab">
                        </div>
                    </div>

                    <!-- Row 2: Drawing, Reason (dropdown), Machine (dropdown) -->
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold mb-1">Tech DWG No</label>
                            <input type="text" class="form-control form-control-sm" id="f_tech_dwg_no" name="tech_dwg_no">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold mb-1">สาเหตุการสร้างแม่พิมพ์</label>
                            <select class="form-select form-select-sm" id="f_reason" name="reason">
                                <option value="">-- เลือกสาเหตุ --</option>
                                <option value="Replacement">Replacement</option>
                                <option value="New die">New die</option>
                                <option value="งานแก้ไขQTR">งานแก้ไขQTR</option>
                                <option value="งานเชื่อม">งานเชื่อม</option>
                                <option value="เปลี่ยนProfile">เปลี่ยนProfile</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold mb-1">Machine (เครื่องรีด)</label>
                            <select class="form-select form-select-sm" id="f_machine" name="machine">
                                <option value="">-- เลือกเครื่อง --</option>
                                <option value="EXT880">EXT880</option>
                                <option value="EXT2000">EXT2000</option>
                                <option value="EXT2200">EXT2200</option>
                                <option value="EXT2400">EXT2400</option>
                                <option value="EXT3000">EXT3000</option>
                            </select>
                        </div>
                    </div>

                    <!-- Row 3: Dates (วันส่งจริงซ่อนตอน Add / แสดงตอน Edit) -->
                    <div class="row g-2 mb-2">
                        <div class="col-md-3">
                            <label class="form-label form-label-sm fw-semibold mb-1">วันที่จ่ายแบบ</label>
                            <input type="date" class="form-control form-control-sm" id="f_plan_send_date" name="plan_send_date">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm fw-semibold mb-1">วันต้องส่ง Die Pc</label>
                            <input type="date" class="form-control form-control-sm" id="f_die_pc_due_date" name="die_pc_due_date">
                        </div>
                        <div class="col-md-3" id="actualDateGroup">
                            <label class="form-label form-label-sm fw-semibold mb-1">
                                วันส่งจริง
                                <span class="badge bg-success ms-1" style="font-size:.65rem">ส่งแล้ว</span>
                            </label>
                            <input type="date" class="form-control form-control-sm" id="f_die_pc_actual_date" name="die_pc_actual_date">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm fw-semibold mb-1">
                                Die Finish Plan
                                <small class="text-muted fw-normal">(wk/yyyy)</small>
                            </label>
                            <input type="text" class="form-control form-control-sm" id="f_die_finish_plan"
                                   name="die_finish_plan" placeholder="e.g. 16/2026"
                                   pattern="^\d{1,2}/\d{4}$"
                                   list="weekOptionsList" autocomplete="off">
                            <datalist id="weekOptionsList"></datalist>
                        </div>
                    </div>

                    <!-- Row 4: Type, Hollow Level, Plan Status -->
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold mb-1">ประเภท (Die Type)</label>
                            <select class="form-select form-select-sm" id="f_die_type" name="die_type">
                                <option value="">-- Select --</option>
                                <option value="Solid">Solid</option>
                                <option value="Hollow">Hollow</option>
                                <option value="Heatsink">Heatsink</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="hollowLevelGroup">
                            <label class="form-label form-label-sm fw-semibold mb-1">Hollow Level</label>
                            <select class="form-select form-select-sm" id="f_hollow_level" name="hollow_level">
                                <option value="">-- n/a --</option>
                                <option value="easy">Easy</option>
                                <option value="medium">Medium</option>
                                <option value="hard">Hard</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold mb-1">Plan Status</label>
                            <select class="form-select form-select-sm" id="f_plan_status" name="plan_status">
                                <option value="normal">Normal</option>
                                <option value="waiting">Waiting</option>
                                <option value="hold">Hold</option>
                            </select>
                        </div>
                    </div>

                    <!-- Row 5: Remarks -->
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label form-label-sm fw-semibold mb-1">หมายเหตุ (Remarks)</label>
                            <textarea class="form-control form-control-sm" id="f_remarks"
                                      name="remarks" rows="2"></textarea>
                        </div>
                    </div>

                    <!-- หมายเหตุ: สถานะ DMK และ Forecast จะอัปเดตอัตโนมัติจาก API -->

                </form>
            </div>

            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnSaveDie">
                    <i class="bi bi-save me-1"></i>Save Die
                </button>
            </div>

        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     MODAL — Delete Confirmation
════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2 bg-danger text-white">
                <h6 class="modal-title mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Delete Die</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-3">
                <p class="mb-1">Delete die record:</p>
                <strong id="deleteLabel" class="text-danger"></strong>
                <p class="text-muted small mt-2 mb-0">This action cannot be undone.</p>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="btnConfirmDelete">
                    <i class="bi bi-trash me-1"></i>Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     MODAL — Import Excel
════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0">
                    <i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i>Import from Excel
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <!-- Step 1: Select file -->
                <div id="importStep1">
                    <div class="mb-2">
                        <label class="form-label fw-semibold mb-1">เลือกไฟล์ Excel / CSV</label>
                        <input type="file" class="form-control form-control-sm" id="importFile"
                               accept=".xlsx,.csv">
                        <div class="form-text">รองรับ .xlsx และ .csv (UTF-8) — แสดง 5 แถวแรกเป็น Preview</div>
                    </div>
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" id="importSkipFirst" checked>
                        <label class="form-check-label small" for="importSkipFirst">
                            ข้ามแถวแรก (header row)
                        </label>
                    </div>
                    <!-- Clear All & Re-import -->
                    <div class="border rounded p-2 mt-2 border-danger-subtle bg-danger-subtle">
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" id="importClearAll">
                            <label class="form-check-label small fw-semibold text-danger" for="importClearAll">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                ลบข้อมูลเดิมทั้งหมดก่อนนำเข้า (Clear All &amp; Re-import)
                            </label>
                        </div>
                        <div id="clearAllPasswordGroup" class="d-none ms-4">
                            <input type="password" class="form-control form-control-sm" id="importClearPassword"
                                   placeholder="กรอกรหัสผ่านเพื่อยืนยันการลบ" autocomplete="off">
                        </div>
                    </div>
                    <div id="importStep1Error" class="d-none alert alert-danger py-2 small mt-2"></div>
                </div>

                <!-- Step 2: Preview -->
                <div id="importStep2" class="d-none">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-success">Preview</span>
                        <span id="previewInfo" class="small text-muted"></span>
                        <button type="button" class="btn btn-link btn-sm p-0 ms-auto" id="btnPreviewBack">
                            <i class="bi bi-arrow-left me-1"></i>เลือกไฟล์ใหม่
                        </button>
                    </div>
                    <div class="table-responsive border rounded" style="max-height:260px;overflow-y:auto">
                        <table class="table table-sm table-bordered mb-0" id="previewTable"
                               style="font-size:.72rem">
                            <thead class="table-dark sticky-top" id="previewHead"></thead>
                            <tbody id="previewBody"></tbody>
                        </table>
                    </div>
                    <div id="importStep2Error" class="d-none alert alert-danger py-2 small mt-2"></div>
                    <div id="importStep2Result" class="d-none alert py-2 small mt-2"></div>
                </div>

            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <!-- Step 1 button -->
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnPreview">
                    <i class="bi bi-eye me-1"></i>Preview
                </button>
                <!-- Step 2 button (hidden until preview loads) -->
                <button type="button" class="btn btn-success btn-sm d-none" id="btnImportSubmit">
                    <i class="bi bi-check-lg me-1"></i>Confirm Import
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     MODAL — นำเข้าข้อมูล (Add Import — ไม่ลบข้อมูลเดิม)
════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="addImportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2" style="background:#0d9488;color:#fff">
                <h6 class="modal-title mb-0">
                    <i class="bi bi-file-earmark-arrow-up me-2"></i>นำเข้าข้อมูลจาก Excel
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <!-- Safe-import notice -->
                <div class="alert alert-success py-2 small mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-shield-check fs-5"></i>
                    <span>
                        <strong>ข้อมูลเดิมยังคงอยู่ทั้งหมด</strong> —
                        รายการที่ตรงกัน (section + index) จะถูก <em>อัปเดต</em>,
                        รายการใหม่จะถูก <em>เพิ่ม</em> เท่านั้น ไม่มีการลบ
                    </span>
                </div>

                <!-- Step 1: Select file -->
                <div id="aiStep1">
                    <div class="mb-2">
                        <label class="form-label fw-semibold mb-1">เลือกไฟล์ Excel</label>
                        <input type="file" class="form-control form-control-sm" id="aiFile"
                               accept=".xlsx,.csv">
                        <div class="form-text">รองรับ .xlsx และ .csv (UTF-8)</div>
                    </div>
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" id="aiSkipFirst" checked>
                        <label class="form-check-label small" for="aiSkipFirst">
                            ข้ามแถวแรก (header row)
                        </label>
                    </div>
                    <div id="aiStep1Error" class="d-none alert alert-danger py-2 small mt-2"></div>
                </div>

                <!-- Step 2: Preview -->
                <div id="aiStep2" class="d-none">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge" style="background:#0d9488">Preview</span>
                        <span id="aiPreviewInfo" class="small text-muted"></span>
                        <button type="button" class="btn btn-link btn-sm p-0 ms-auto" id="btnAiBack">
                            <i class="bi bi-arrow-left me-1"></i>เลือกไฟล์ใหม่
                        </button>
                    </div>
                    <div class="table-responsive border rounded" style="max-height:280px;overflow-y:auto">
                        <table class="table table-sm table-bordered mb-0" style="font-size:.72rem">
                            <thead class="table-dark sticky-top" id="aiPreviewHead"></thead>
                            <tbody id="aiPreviewBody"></tbody>
                        </table>
                    </div>
                    <div id="aiStep2Error" class="d-none alert alert-danger py-2 small mt-2"></div>
                    <div id="aiStep2Result" class="d-none alert py-2 small mt-2"></div>
                </div>

            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-sm" id="btnAiPreview"
                        style="background:#0d9488;color:#fff;border-color:#0d9488">
                    <i class="bi bi-eye me-1"></i>ดูตัวอย่าง
                </button>
                <button type="button" class="btn btn-success btn-sm d-none" id="btnAiSubmit">
                    <i class="bi bi-check-lg me-1"></i>ยืนยันนำเข้าข้อมูล
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     MODAL — อัปเดตวันที่ by Die No
════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="updateDatesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2" style="background:#7c3aed;color:#fff">
                <h6 class="modal-title mb-0">
                    <i class="bi bi-calendar-check me-2"></i>อัปเดตวันที่จากไฟล์ Excel (อ้างอิงจาก Die No)
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <!-- Info banner -->
                <div class="alert alert-info py-2 small mb-3 d-flex align-items-start gap-2">
                    <i class="bi bi-info-circle-fill fs-5 mt-1 flex-shrink-0"></i>
                    <div>
                        <strong>รูปแบบไฟล์ Excel ที่รองรับ (5 คอลัมน์):</strong><br>
                        <span class="font-monospace">A: Die No &nbsp;|&nbsp; B: วันที่จ่ายแบบ &nbsp;|&nbsp; C: วันที่ต้องส่ง DPC &nbsp;|&nbsp; D: Die Finish Plan (เช่น 18/2026) &nbsp;|&nbsp; E: หมายเหตุ</span><br>
                        ใช้ <strong>Die No</strong> เป็นตัวค้นหา — เฉพาะช่องที่มีข้อมูลจะถูกอัปเดต ช่องว่างไม่มีการเปลี่ยนแปลง
                    </div>
                </div>

                <!-- Step 1: Select file -->
                <div id="udStep1">
                    <div class="mb-2">
                        <label class="form-label fw-semibold mb-1">เลือกไฟล์ Excel</label>
                        <input type="file" class="form-control form-control-sm" id="udFile"
                               accept=".xlsx,.csv">
                        <div class="form-text">รองรับ .xlsx และ .csv (UTF-8)</div>
                    </div>
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" id="udSkipFirst" checked>
                        <label class="form-check-label small" for="udSkipFirst">
                            ข้ามแถวแรก (header row)
                        </label>
                    </div>
                    <div id="udStep1Error" class="d-none alert alert-danger py-2 small mt-2"></div>
                </div>

                <!-- Step 2: Preview -->
                <div id="udStep2" class="d-none">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge" style="background:#7c3aed">Preview</span>
                        <span id="udPreviewInfo" class="small text-muted"></span>
                        <button type="button" class="btn btn-link btn-sm p-0 ms-auto" id="btnUdBack">
                            <i class="bi bi-arrow-left me-1"></i>เลือกไฟล์ใหม่
                        </button>
                    </div>
                    <div class="table-responsive border rounded" style="max-height:280px;overflow-y:auto">
                        <table class="table table-sm table-bordered mb-0" style="font-size:.72rem">
                            <thead class="table-dark sticky-top" id="udPreviewHead"></thead>
                            <tbody id="udPreviewBody"></tbody>
                        </table>
                    </div>
                    <div id="udStep2Error" class="d-none alert alert-danger py-2 small mt-2"></div>
                    <div id="udStep2Result" class="d-none alert py-2 small mt-2"></div>
                </div>

            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-sm" id="btnUdPreview"
                        style="background:#7c3aed;color:#fff;border-color:#7c3aed">
                    <i class="bi bi-eye me-1"></i>ดูตัวอย่าง
                </button>
                <button type="button" class="btn btn-success btn-sm d-none" id="btnUdSubmit">
                    <i class="bi bi-check-lg me-1"></i>ยืนยันอัปเดตวันที่
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     MODAL — Delete All
════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="deleteAllModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-danger">
            <div class="modal-header py-2 bg-danger text-white">
                <h6 class="modal-title mb-0">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>ลบข้อมูลทั้งหมด
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-danger fw-semibold mb-2">
                    ⚠ การดำเนินการนี้จะลบข้อมูล Die <strong>ทั้งหมด</strong> และไม่สามารถกู้คืนได้
                </p>
                <div class="mb-2">
                    <label class="form-label small fw-semibold mb-1">กรอกรหัสผ่านเพื่อยืนยัน</label>
                    <input type="password" class="form-control form-control-sm" id="deleteAllPassword"
                           placeholder="รหัสผ่าน" autocomplete="off">
                </div>
                <div id="deleteAllError" class="d-none alert alert-danger py-1 small"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-danger btn-sm" id="btnDeleteAllConfirm">
                    <i class="bi bi-trash3 me-1"></i>ยืนยันลบทั้งหมด
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     JavaScript
════════════════════════════════════════════════════════════════ -->
<script>
const APP_IS_ADMIN = <?= isAdmin() ? 'true' : 'false' ?>;

document.addEventListener('DOMContentLoaded', function () {
'use strict';

// ── Constants ────────────────────────────────────────────────────
const API = '<?= BASE_URL ?>/api/dies.php';

const DMK_BADGE = {
    'W/C':              { cls: 'bg-primary' },
    'CNC':              { cls: 'bg-warning text-dark' },
    'กัดเช็ค':         { cls: 'text-white', style: 'background-color:#fd7e14' },
    'ชุบแข็ง':         { cls: 'text-white', style: 'background-color:#6f42c1' },
    'กลึงเหวี่ยง':    { cls: 'bg-info text-dark' },
    'กลึง':             { cls: 'bg-secondary' },
    'ตัดเหล็ก':        { cls: 'text-white', style: 'background-color:#6c757d' },
    'EDM':              { cls: 'text-white', style: 'background-color:#343a40' },
    'รอดำเนินการ':     { cls: 'bg-secondary' },
    'ระหว่างดำเนินการ': { cls: 'text-white', style: 'background-color:#0ea5e9' },
    'Pending':          { cls: 'bg-secondary' },
    '#N/A':             { cls: 'bg-secondary' },
};

const TYPE_BADGE = {
    'Solid':    'bg-success',
    'Hollow':   'bg-warning text-dark',
    'Heatsink': 'bg-primary',
};

const HOLLOW_LEVEL_LABEL = { easy: 'ง่าย', medium: 'กลาง', hard: 'ยาก' };
const HOLLOW_LEVEL_BADGE = { easy: 'bg-success', medium: 'bg-warning text-dark', hard: 'bg-danger' };

// ── State ────────────────────────────────────────────────────────
const state = {
    allDies:  [],
    diesMap:  new Map(),   // id → die object
    deleteId: null,
};

// ── Utility ──────────────────────────────────────────────────────
function esc(str) {
    return (str ?? '').toString()
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function fmtDate(iso) {
    if (!iso) return '';
    const [y, m, d] = iso.split('-');
    return (y && m && d) ? `${d}/${m}/${y}` : iso;
}

/** ISO week number → "ww/yyyy" */
function currentISOWeek() {
    const now = new Date();
    const d   = new Date(Date.UTC(now.getFullYear(), now.getMonth(), now.getDate()));
    const day = d.getUTCDay() || 7;
    d.setUTCDate(d.getUTCDate() + 4 - day);
    const jan1    = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
    const weekNo  = Math.ceil((((d - jan1) / 86400000) + 1) / 7);
    return `${weekNo}/${d.getUTCFullYear()}`;
}

// ── Toast ────────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const el  = document.getElementById('appToast');
    const txt = document.getElementById('toastMsg');
    el.className = `toast align-items-center border-0 text-white bg-${type}`;
    txt.textContent = msg;
    bootstrap.Toast.getOrCreateInstance(el, { delay: 3000 }).show();
}

// ── Badges ───────────────────────────────────────────────────────
function diePcBadge(status) {
    const cls = status === 'Finish' ? 'bg-success' : 'bg-warning text-dark';
    return `<span class="badge ${cls}">${esc(status)}</span>`;
}

function dmkBadge(status) {
    if (!status) return '<span class="text-muted small">—</span>';
    const cfg = DMK_BADGE[status] ?? { cls: 'bg-secondary' };
    const style = cfg.style ? ` style="${cfg.style}"` : '';
    return `<span class="badge ${cfg.cls}"${style}>${esc(status)}</span>`;
}

function typeBadge(type, hollowLevel) {
    if (!type) return '<span class="text-muted small">—</span>';
    if (type === 'Hollow' && hollowLevel) {
        const cls   = HOLLOW_LEVEL_BADGE[hollowLevel] ?? 'bg-warning text-dark';
        const label = 'Hollow ' + (HOLLOW_LEVEL_LABEL[hollowLevel] ?? hollowLevel);
        return `<span class="badge ${cls}">${esc(label)}</span>`;
    }
    const cls = TYPE_BADGE[type] ?? 'bg-secondary';
    return `<span class="badge ${cls}">${esc(type)}</span>`;
}

// ── Render table ─────────────────────────────────────────────────
function renderTable(dies) {
    const tbody = document.getElementById('diesTbody');
    document.getElementById('recordCount').textContent =
        `${dies.length} record${dies.length !== 1 ? 's' : ''}`;

    if (dies.length === 0) {
        tbody.innerHTML =
            `<tr><td colspan="${APP_IS_ADMIN ? 13 : 12}" class="text-center py-4 text-secondary">
                <i class="bi bi-inbox fs-4 d-block mb-1"></i>No records found
             </td></tr>`;
        return;
    }

    tbody.innerHTML = dies.map(d => {
        const rowCls = d.plan_status === 'waiting' ? ' status-waiting'
                     : d.plan_status === 'hold'    ? ' status-hold' : '';
        return `<tr class="${rowCls}" data-id="${d.id}">
            <td>${esc(d.no)}</td>
            <td>${esc(d.reason)}</td>
            <td>${esc(d.customer)}</td>
            <td>${esc(d.tech_dwg_no)}</td>
            <td>${fmtDate(d.plan_send_date)}</td>
            <td>${fmtDate(d.die_pc_due_date)}</td>
            <td class="fw-semibold">${esc(d.die_finish_plan)}</td>
            <td>${esc(d.machine)}</td>
            <td class="text-primary fw-semibold">${esc(d.die_no)}</td>
            <td class="text-center">${dmkBadge(d.dmk_status)}</td>
            <td class="text-center">${typeBadge(d.die_type, d.hollow_level)}</td>
            <td>${esc(d.remarks)}</td>
            ${APP_IS_ADMIN ? `<td class="text-center">
                <button class="btn btn-outline-warning btn-action btn-edit me-1"
                        data-id="${d.id}" title="Edit">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-outline-danger btn-action btn-delete"
                        data-id="${d.id}"
                        data-label="${esc(d.die_no || d.no || '#' + d.id)}"
                        title="Delete">
                    <i class="bi bi-trash"></i>
                </button>
            </td>` : ''}
        </tr>`;
    }).join('');
}

// ── Filters ──────────────────────────────────────────────────────
function applyFilters() {
    const week   = document.getElementById('weekFilter').value.trim();
    const search = document.getElementById('searchBox').value.trim().toLowerCase();

    // Exclude delivered dies (those go to Die Completed page)
    let dies = state.allDies.filter(d => !d.die_pc_actual_date);

    if (week) {
        dies = dies.filter(d => d.die_finish_plan === week);
    }

    if (search) {
        dies = dies.filter(d =>
            (d.customer    ?? '').toLowerCase().includes(search) ||
            (d.section     ?? '').toLowerCase().includes(search) ||
            (d.tech_dwg_no ?? '').toLowerCase().includes(search) ||
            (d.no          ?? '').toLowerCase().includes(search)
        );
    }

    // Sort by กำหนดเสร็จ (die_pc_due_date) ascending — empty/null dates go to the bottom
    dies = dies.slice().sort((a, b) => {
        const da = a.die_pc_due_date || '';
        const db = b.die_pc_due_date || '';
        if (!da && !db) return 0;
        if (!da) return 1;
        if (!db) return -1;
        return da < db ? -1 : da > db ? 1 : 0;
    });

    renderTable(dies);
}

function populateWeekFilter(allDies) {
    const weeks = [...new Set(
        allDies.map(d => d.die_finish_plan).filter(w => w && w.trim())
    )].sort((a, b) => {
        const [wa, ya] = a.split('/').map(Number);
        const [wb, yb] = b.split('/').map(Number);
        return ya !== yb ? ya - yb : wa - wb;
    });

    const sel     = document.getElementById('weekFilter');
    const urlWeek = new URLSearchParams(location.search).get('week');
    const cur     = urlWeek ?? '';

    sel.innerHTML = '<option value="">All Weeks</option>';
    weeks.forEach(w => {
        const opt = document.createElement('option');
        opt.value = w;
        opt.textContent = `Week ${w}`;
        if (w === cur) opt.selected = true;
        sel.appendChild(opt);
    });
}

// ── API calls ────────────────────────────────────────────────────
async function apiRequest(method, params = {}, body = null) {
    const url = new URL(API, location.origin);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));

    const opts = {
        method,
        headers: { 'Content-Type': 'application/json' },
    };
    if (body !== null) opts.body = JSON.stringify(body);

    const res  = await fetch(url, opts);
    const json = await res.json();

    if (!res.ok) throw new Error(json.error ?? `HTTP ${res.status}`);
    return json;
}

async function loadDies() {
    try {
        const json = await apiRequest('GET');
        state.allDies = json.data ?? [];
        state.diesMap = new Map(state.allDies.map(d => [d.id, d]));
        populateWeekFilter(state.allDies);
        applyFilters();
    } catch (err) {
        showToast('Failed to load dies: ' + err.message, 'danger');
        document.getElementById('diesTbody').innerHTML =
            `<tr><td colspan="${APP_IS_ADMIN ? 13 : 12}" class="text-center text-danger py-3">
                <i class="bi bi-exclamation-triangle me-1"></i>${esc(err.message)}
             </td></tr>`;
    }
}

// ── Hollow Level visibility ───────────────────────────────────────
function toggleHollowLevel(type) {
    const grp = document.getElementById('hollowLevelGroup');
    const sel = document.getElementById('f_hollow_level');
    if (type === 'Hollow') {
        grp.style.display = '';
    } else {
        grp.style.display = 'none';
        sel.value = '';   // clear ค่าเมื่อซ่อน
    }
}

document.getElementById('f_die_type').addEventListener('change', function () {
    toggleHollowLevel(this.value);
});

// ── Add / Edit Modal ─────────────────────────────────────────────
const dieModal = new bootstrap.Modal(document.getElementById('dieModal'));

function populateWeekDatalist() {
    const weeks = [...new Set(
        state.allDies.map(d => d.die_finish_plan).filter(w => w && w.trim())
    )].sort((a, b) => {
        const [wa, ya] = a.split('/').map(Number);
        const [wb, yb] = b.split('/').map(Number);
        return ya !== yb ? ya - yb : wa - wb;
    });
    document.getElementById('weekOptionsList').innerHTML =
        weeks.map(w => `<option value="${esc(w)}">`).join('');
}

const FIELDS = [
    'no','reason','customer','section','index_tab','tech_dwg_no',
    'plan_send_date','die_pc_due_date','die_pc_actual_date',
    'die_finish_plan','machine','die_count','dmk_status','forecast',
    'die_type','hollow_level','plan_status','remarks',
];

function openAddModal() {
    document.getElementById('dieModalLabel').innerHTML =
        '<i class="bi bi-plus-circle me-2"></i>Add New Die';
    document.getElementById('f_id').value = '';
    document.getElementById('dieForm').reset();
    document.getElementById('f_plan_status').value = 'normal';
    document.getElementById('f_die_count').value   = '1';
    document.getElementById('f_no').value           = '';   // server จะ auto-assign
    // ซ่อน "วันส่งจริง" ตอน Add (กรอกได้ตอน Edit เท่านั้น)
    document.getElementById('actualDateGroup').style.display = 'none';
    toggleHollowLevel('');
    populateWeekDatalist();
    dieModal.show();
}

function openEditModal(id) {
    const die = state.diesMap.get(id);
    if (!die) return;

    document.getElementById('dieModalLabel').innerHTML =
        `<i class="bi bi-pencil me-2"></i>Edit Die — ${esc(die.die_no || die.no || '#' + id)}`;
    document.getElementById('f_id').value = id;

    FIELDS.forEach(key => {
        const el = document.getElementById('f_' + key);
        if (el) el.value = die[key] ?? '';
    });

    // แสดง "วันส่งจริง" ตอน Edit
    document.getElementById('actualDateGroup').style.display = '';
    toggleHollowLevel(die.die_type ?? '');
    populateWeekDatalist();
    dieModal.show();
}

function collectFormData() {
    const data = {};
    FIELDS.forEach(key => {
        const el = document.getElementById('f_' + key);
        if (el) data[key] = el.value;
    });
    return data;
}

document.getElementById('btnSaveDie').addEventListener('click', async () => {
    const form = document.getElementById('dieForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }

    const id   = document.getElementById('f_id').value;
    const data = collectFormData();
    const btn  = document.getElementById('btnSaveDie');

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

    try {
        if (id) {
            await apiRequest('PUT', { id }, data);
            showToast('Die updated successfully.');
        } else {
            await apiRequest('POST', {}, data);
            showToast('Die added successfully.');
        }
        dieModal.hide();
        await loadDies();
    } catch (err) {
        showToast('Save failed: ' + err.message, 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-save me-1"></i>Save Die';
    }
});

// ── Delete Modal ─────────────────────────────────────────────────
const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

function openDeleteModal(id, label) {
    state.deleteId = id;
    document.getElementById('deleteLabel').textContent = label;
    deleteModal.show();
}

document.getElementById('btnConfirmDelete').addEventListener('click', async () => {
    const id  = state.deleteId;
    const btn = document.getElementById('btnConfirmDelete');
    if (!id) return;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deleting…';

    try {
        await apiRequest('DELETE', { id });
        showToast('Die deleted.');
        deleteModal.hide();
        await loadDies();
    } catch (err) {
        showToast('Delete failed: ' + err.message, 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-trash me-1"></i>Delete';
    }
});

// ── Import Modal — 2-step: Preview → Confirm ─────────────────────
const importModal = new bootstrap.Modal(document.getElementById('importModal'));
let importTempKey = null;

function importReset() {
    importTempKey = null;
    document.getElementById('importFile').value = '';
    document.getElementById('importStep1').classList.remove('d-none');
    document.getElementById('importStep2').classList.add('d-none');
    document.getElementById('importStep1Error').classList.add('d-none');
    document.getElementById('importStep2Error').classList.add('d-none');
    document.getElementById('importStep2Result').classList.add('d-none');
    document.getElementById('btnPreview').classList.remove('d-none');
    document.getElementById('btnImportSubmit').classList.add('d-none');
    document.getElementById('importClearAll').checked = false;
    document.getElementById('importClearPassword').value = '';
    document.getElementById('clearAllPasswordGroup').classList.add('d-none');
}

document.getElementById('importClearAll').addEventListener('change', function () {
    document.getElementById('clearAllPasswordGroup').classList.toggle('d-none', !this.checked);
});

// Step 1: Preview
document.getElementById('btnPreview').addEventListener('click', async () => {
    const fileEl = document.getElementById('importFile');
    if (!fileEl.files.length) { showImportErr1('กรุณาเลือกไฟล์ก่อน'); return; }

    const btn = document.getElementById('btnPreview');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>กำลังอ่าน…';
    document.getElementById('importStep1Error').classList.add('d-none');

    try {
        const fd = new FormData();
        fd.append('action',     'preview');
        fd.append('file',       fileEl.files[0]);
        fd.append('skip_first', document.getElementById('importSkipFirst').checked ? '1' : '');

        const res  = await fetch('<?= BASE_URL ?>/api/upload_excel.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (!res.ok || json.error) { showImportErr1(json.error ?? `HTTP ${res.status}`); return; }

        importTempKey = json.temp_key;

        // Use server-returned headers (include col index + DB field mapping)
        const headers = json.headers ?? [];
        document.getElementById('previewHead').innerHTML =
            '<tr>' + headers.map(h =>
                `<th style="white-space:nowrap;font-size:.68rem;padding:.25rem .4rem">${esc(h)}</th>`
            ).join('') + '</tr>';

        document.getElementById('previewBody').innerHTML =
            (json.rows ?? []).map(row =>
                '<tr>' + row.map(cell =>
                    `<td style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding:.25rem .4rem"
                         title="${esc(cell??'')}">${esc(cell??'')}</td>`
                ).join('') + '</tr>'
            ).join('');

        document.getElementById('previewInfo').textContent =
            `แสดง ${json.rows?.length ?? 0} แถวแรก / ทั้งหมด ${json.total_rows} แถว`;

        document.getElementById('importStep1').classList.add('d-none');
        document.getElementById('importStep2').classList.remove('d-none');
        document.getElementById('btnPreview').classList.add('d-none');
        document.getElementById('btnImportSubmit').classList.remove('d-none');
    } catch (err) {
        showImportErr1('เกิดข้อผิดพลาด: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-eye me-1"></i>Preview';
    }
});

// Step 2: Confirm Import
document.getElementById('btnImportSubmit').addEventListener('click', async () => {
    if (!importTempKey) return;
    const btn = document.getElementById('btnImportSubmit');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Importing…';

    try {
        const fd = new FormData();
        fd.append('action',     'import');
        fd.append('temp_key',   importTempKey);
        fd.append('skip_first', document.getElementById('importSkipFirst').checked ? '1' : '');
        const clearAll = document.getElementById('importClearAll').checked;
        if (clearAll) {
            fd.append('clear_all',      '1');
            fd.append('clear_password', document.getElementById('importClearPassword').value);
        }

        const res  = await fetch('<?= BASE_URL ?>/api/upload_excel.php', { method: 'POST', body: fd });
        const json = await res.json();
        const el   = document.getElementById('importStep2Result');

        if (!res.ok || json.error) {
            el.className = 'alert alert-danger py-2 small mt-2';
            el.textContent = json.error ?? 'Import failed.';
        } else {
            const hasErrors = (json.errors ?? 0) > 0;
            el.className = hasErrors
                ? 'alert alert-warning py-2 small mt-2'
                : 'alert alert-success py-2 small mt-2';

            let html = `<strong>✓ นำเข้าสำเร็จ ${json.imported} แถว</strong>`;
            if ((json.updated ?? 0) > 0) html += `, อัปเดต ${json.updated} แถว`;
            if (hasErrors) {
                html += `<br>⚠ ข้ามข้อผิดพลาด ${json.errors} แถว`;
                const errs = json.error_list ?? [];
                if (errs.length > 0) {
                    html += '<ul class="mb-0 mt-1">'
                        + errs.slice(0, 5).map(e => `<li>${esc(e)}</li>`).join('')
                        + (errs.length > 5 ? `<li>...และอื่นๆ อีก ${errs.length - 5} รายการ</li>` : '')
                        + '</ul>';
                }
            }
            el.innerHTML = html;
            if (json.imported > 0 || (json.updated ?? 0) > 0) await loadDies();
        }
        el.classList.remove('d-none');
        document.getElementById('btnImportSubmit').classList.add('d-none');
        importTempKey = null;
    } catch (err) {
        const el = document.getElementById('importStep2Error');
        el.textContent = 'Error: ' + err.message;
        el.classList.remove('d-none');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Confirm Import';
    }
});

document.getElementById('btnPreviewBack').addEventListener('click', importReset);
function showImportErr1(msg) {
    const el = document.getElementById('importStep1Error');
    el.textContent = msg; el.classList.remove('d-none');
}

// ── นำเข้าข้อมูล (Add Import — safe, no clear-all) ───────────────
const addImportModal = new bootstrap.Modal(document.getElementById('addImportModal'));
let aiTempKey = null;

function aiReset() {
    aiTempKey = null;
    document.getElementById('aiFile').value = '';
    document.getElementById('aiStep1').classList.remove('d-none');
    document.getElementById('aiStep2').classList.add('d-none');
    document.getElementById('aiStep1Error').classList.add('d-none');
    document.getElementById('aiStep2Error').classList.add('d-none');
    document.getElementById('aiStep2Result').classList.add('d-none');
    document.getElementById('btnAiPreview').classList.remove('d-none');
    document.getElementById('btnAiSubmit').classList.add('d-none');
}

document.getElementById('btnAddImport')?.addEventListener('click', () => {
    aiReset();
    addImportModal.show();
});

document.getElementById('btnAiBack').addEventListener('click', aiReset);

document.getElementById('btnAiPreview').addEventListener('click', async () => {
    const fileEl = document.getElementById('aiFile');
    if (!fileEl.files.length) {
        const el = document.getElementById('aiStep1Error');
        el.textContent = 'กรุณาเลือกไฟล์ก่อน';
        el.classList.remove('d-none');
        return;
    }
    const btn = document.getElementById('btnAiPreview');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>กำลังอ่าน…';
    document.getElementById('aiStep1Error').classList.add('d-none');

    try {
        const fd = new FormData();
        fd.append('action',     'preview');
        fd.append('file',       fileEl.files[0]);
        fd.append('skip_first', document.getElementById('aiSkipFirst').checked ? '1' : '');

        const res  = await fetch('<?= BASE_URL ?>/api/upload_excel.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (!res.ok || json.error) {
            const el = document.getElementById('aiStep1Error');
            el.textContent = json.error ?? `HTTP ${res.status}`;
            el.classList.remove('d-none');
            return;
        }

        aiTempKey = json.temp_key;

        document.getElementById('aiPreviewHead').innerHTML =
            '<tr>' + (json.headers ?? []).map(h =>
                `<th style="white-space:nowrap;font-size:.68rem;padding:.25rem .4rem">${esc(h)}</th>`
            ).join('') + '</tr>';

        document.getElementById('aiPreviewBody').innerHTML =
            (json.rows ?? []).map(row =>
                '<tr>' + row.map(cell =>
                    `<td style="max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
                                padding:.25rem .4rem" title="${esc(cell??'')}">${esc(cell??'')}</td>`
                ).join('') + '</tr>'
            ).join('');

        document.getElementById('aiPreviewInfo').textContent =
            `แสดง ${json.rows?.length ?? 0} แถวแรก / ทั้งหมด ${json.total_rows} แถว`;

        document.getElementById('aiStep1').classList.add('d-none');
        document.getElementById('aiStep2').classList.remove('d-none');
        document.getElementById('btnAiPreview').classList.add('d-none');
        document.getElementById('btnAiSubmit').classList.remove('d-none');
    } catch (err) {
        const el = document.getElementById('aiStep1Error');
        el.textContent = 'เกิดข้อผิดพลาด: ' + err.message;
        el.classList.remove('d-none');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-eye me-1"></i>ดูตัวอย่าง';
    }
});

document.getElementById('btnAiSubmit').addEventListener('click', async () => {
    if (!aiTempKey) return;
    const btn = document.getElementById('btnAiSubmit');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>กำลังนำเข้า…';

    try {
        const fd = new FormData();
        fd.append('action',     'import');
        fd.append('temp_key',   aiTempKey);
        fd.append('skip_first', document.getElementById('aiSkipFirst').checked ? '1' : '');
        // NO clear_all — existing data is always preserved

        const res  = await fetch('<?= BASE_URL ?>/api/upload_excel.php', { method: 'POST', body: fd });
        const json = await res.json();
        const el   = document.getElementById('aiStep2Result');

        if (!res.ok || json.error) {
            el.className   = 'alert alert-danger py-2 small mt-2';
            el.textContent = json.error ?? 'นำเข้าล้มเหลว';
        } else {
            const hasErrors = (json.errors ?? 0) > 0;
            el.className = hasErrors
                ? 'alert alert-warning py-2 small mt-2'
                : 'alert alert-success py-2 small mt-2';

            let html = `<i class="bi bi-check-circle me-1"></i><strong>นำเข้าสำเร็จ ${json.imported} รายการ</strong>`;
            if ((json.updated ?? 0) > 0) html += ` &nbsp;|&nbsp; อัปเดต ${json.updated} รายการ`;
            if (hasErrors) {
                html += `<br><i class="bi bi-exclamation-triangle me-1"></i>ข้ามข้อผิดพลาด ${json.errors} แถว`;
                const errs = json.error_list ?? [];
                if (errs.length) {
                    html += '<ul class="mb-0 mt-1">'
                        + errs.slice(0, 5).map(e => `<li>${esc(e)}</li>`).join('')
                        + (errs.length > 5 ? `<li>...และอื่นๆ อีก ${errs.length - 5} รายการ</li>` : '')
                        + '</ul>';
                }
            }
            el.innerHTML = html;
            if (json.imported > 0 || (json.updated ?? 0) > 0) await loadDies();
        }
        el.classList.remove('d-none');
        document.getElementById('btnAiSubmit').classList.add('d-none');
        aiTempKey = null;
    } catch (err) {
        const el = document.getElementById('aiStep2Error');
        el.textContent = 'Error: ' + err.message;
        el.classList.remove('d-none');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>ยืนยันนำเข้าข้อมูล';
    }
});

// ── อัปเดตวันที่ by Die No ───────────────────────────────────────
const updateDatesModal = new bootstrap.Modal(document.getElementById('updateDatesModal'));
let udTempKey = null;

function udReset() {
    udTempKey = null;
    document.getElementById('udFile').value = '';
    document.getElementById('udStep1').classList.remove('d-none');
    document.getElementById('udStep2').classList.add('d-none');
    document.getElementById('udStep1Error').classList.add('d-none');
    document.getElementById('udStep2Error').classList.add('d-none');
    document.getElementById('udStep2Result').classList.add('d-none');
    document.getElementById('btnUdPreview').classList.remove('d-none');
    document.getElementById('btnUdSubmit').classList.add('d-none');
}

document.getElementById('btnUpdateDates')?.addEventListener('click', () => {
    udReset();
    updateDatesModal.show();
});

document.getElementById('btnUdBack').addEventListener('click', udReset);

document.getElementById('btnUdPreview').addEventListener('click', async () => {
    const fileEl = document.getElementById('udFile');
    if (!fileEl.files.length) {
        const el = document.getElementById('udStep1Error');
        el.textContent = 'กรุณาเลือกไฟล์ก่อน';
        el.classList.remove('d-none');
        return;
    }
    const btn = document.getElementById('btnUdPreview');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>กำลังอ่าน…';
    document.getElementById('udStep1Error').classList.add('d-none');

    try {
        const fd = new FormData();
        fd.append('action',     'preview_dieno');
        fd.append('file',       fileEl.files[0]);
        fd.append('skip_first', document.getElementById('udSkipFirst').checked ? '1' : '');

        const res  = await fetch('<?= BASE_URL ?>/api/upload_excel.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (!res.ok || json.error) {
            const el = document.getElementById('udStep1Error');
            el.textContent = json.error ?? `HTTP ${res.status}`;
            el.classList.remove('d-none');
            return;
        }

        udTempKey = json.temp_key;

        document.getElementById('udPreviewHead').innerHTML =
            '<tr>' + (json.headers ?? []).map(h =>
                `<th style="white-space:nowrap;font-size:.68rem;padding:.25rem .4rem">${esc(h)}</th>`
            ).join('') + '</tr>';

        document.getElementById('udPreviewBody').innerHTML =
            (json.rows ?? []).map(row =>
                '<tr>' + row.map(cell =>
                    `<td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
                                padding:.25rem .4rem" title="${esc(cell??'')}">${esc(cell??'')}</td>`
                ).join('') + '</tr>'
            ).join('');

        document.getElementById('udPreviewInfo').textContent =
            `แสดง ${json.rows?.length ?? 0} แถวแรก / ทั้งหมด ${json.total_rows} แถว`;

        document.getElementById('udStep1').classList.add('d-none');
        document.getElementById('udStep2').classList.remove('d-none');
        document.getElementById('btnUdPreview').classList.add('d-none');
        document.getElementById('btnUdSubmit').classList.remove('d-none');
    } catch (err) {
        const el = document.getElementById('udStep1Error');
        el.textContent = 'เกิดข้อผิดพลาด: ' + err.message;
        el.classList.remove('d-none');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-eye me-1"></i>ดูตัวอย่าง';
    }
});

document.getElementById('btnUdSubmit').addEventListener('click', async () => {
    if (!udTempKey) return;
    const btn = document.getElementById('btnUdSubmit');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>กำลังอัปเดต…';

    try {
        const fd = new FormData();
        fd.append('action',     'import_dieno');
        fd.append('temp_key',   udTempKey);
        fd.append('skip_first', document.getElementById('udSkipFirst').checked ? '1' : '');

        const res  = await fetch('<?= BASE_URL ?>/api/upload_excel.php', { method: 'POST', body: fd });
        const json = await res.json();
        const el   = document.getElementById('udStep2Result');

        if (!res.ok || json.error) {
            el.className   = 'alert alert-danger py-2 small mt-2';
            el.textContent = json.error ?? 'อัปเดตล้มเหลว';
        } else {
            const hasIssues = (json.errors ?? 0) > 0 || (json.not_found ?? 0) > 0;
            el.className = hasIssues
                ? 'alert alert-warning py-2 small mt-2'
                : 'alert alert-success py-2 small mt-2';

            let html = `<i class="bi bi-check-circle me-1"></i><strong>อัปเดตสำเร็จ ${json.updated} รายการ</strong>`;
            if ((json.not_found ?? 0) > 0) html += ` &nbsp;|&nbsp; ไม่พบ Die No: ${json.not_found} รายการ`;
            if ((json.errors  ?? 0) > 0) html += ` &nbsp;|&nbsp; ข้อผิดพลาด: ${json.errors} รายการ`;
            const errs = json.error_list ?? [];
            if (errs.length) {
                html += '<ul class="mb-0 mt-1">'
                    + errs.slice(0, 10).map(e => `<li>${esc(e)}</li>`).join('')
                    + (errs.length > 10 ? `<li>...และอื่นๆ อีก ${errs.length - 10} รายการ</li>` : '')
                    + '</ul>';
            }
            el.innerHTML = html;
            if ((json.updated ?? 0) > 0) await loadDies();
        }
        el.classList.remove('d-none');
        document.getElementById('btnUdSubmit').classList.add('d-none');
        udTempKey = null;
    } catch (err) {
        const el = document.getElementById('udStep2Error');
        el.textContent = 'Error: ' + err.message;
        el.classList.remove('d-none');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>ยืนยันอัปเดตวันที่';
    }
});

// ── Update API DMK ────────────────────────────────────────────────
document.getElementById('btnSyncDMK')?.addEventListener('click', async () => {
    const btn = document.getElementById('btnSyncDMK');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>กำลังดึงข้อมูล…';

    try {
        const res  = await fetch('<?= BASE_URL ?>/api/external_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'sync'}),
        });
        const json = await res.json();
        if (!res.ok) throw new Error(json.error ?? `HTTP ${res.status}`);
        showToast(`อัปเดต DMK สำเร็จ: ${json.updated ?? 0} รายการ  |  ล้มเหลว: ${json.failed ?? 0} รายการ`);
        await loadDies();
    } catch (err) {
        showToast('Update API DMK ล้มเหลว: ' + err.message, 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-cloud-download me-1"></i>Update API DMK';
    }
});

// ── Event delegation for table buttons ───────────────────────────
document.getElementById('diesTbody').addEventListener('click', e => {
    const editBtn   = e.target.closest('.btn-edit');
    const deleteBtn = e.target.closest('.btn-delete');

    if (editBtn) {
        openEditModal(+editBtn.dataset.id);
    } else if (deleteBtn) {
        openDeleteModal(+deleteBtn.dataset.id, deleteBtn.dataset.label);
    }
});

// ── Delete All (admin only) ───────────────────────────────────────
const _deleteAllEl = document.getElementById('deleteAllModal');
const deleteAllModal = _deleteAllEl ? new bootstrap.Modal(_deleteAllEl) : null;

document.getElementById('btnDeleteAll')?.addEventListener('click', () => {
    document.getElementById('deleteAllPassword').value = '';
    document.getElementById('deleteAllError').classList.add('d-none');
    deleteAllModal?.show();
    // Focus password field after modal opens
    document.getElementById('deleteAllModal')?.addEventListener('shown.bs.modal', () => {
        document.getElementById('deleteAllPassword')?.focus();
    }, { once: true });
});

document.getElementById('btnDeleteAllConfirm')?.addEventListener('click', async () => {
    const password = document.getElementById('deleteAllPassword').value;
    const errEl    = document.getElementById('deleteAllError');
    const btn      = document.getElementById('btnDeleteAllConfirm');

    if (!password) {
        errEl.textContent = 'กรุณากรอกรหัสผ่าน';
        errEl.classList.remove('d-none');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>กำลังลบ…';
    errEl.classList.add('d-none');

    try {
        const res  = await fetch('<?= BASE_URL ?>/api/dies.php?all=1', {
            method:  'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ password }),
        });
        const json = await res.json();
        if (!res.ok || json.error) {
            errEl.textContent = json.error ?? 'เกิดข้อผิดพลาด';
            errEl.classList.remove('d-none');
            return;
        }
        deleteAllModal?.hide();
        showToast(`ลบข้อมูลทั้งหมด ${json.deleted ?? 0} รายการเรียบร้อยแล้ว`, 'success');
        await loadDies();
    } catch (err) {
        errEl.textContent = 'Error: ' + err.message;
        errEl.classList.remove('d-none');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-trash3 me-1"></i>ยืนยันลบทั้งหมด';
    }
});

// ── Toolbar events (admin-only buttons use optional chaining) ────────
document.getElementById('btnAddDie')?.addEventListener('click', openAddModal);
document.getElementById('btnImport')?.addEventListener('click', () => {
    importReset();
    importModal.show();
});

let searchTimer;
document.getElementById('searchBox').addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 250);
});

document.getElementById('weekFilter').addEventListener('change', applyFilters);

// ── Init ──────────────────────────────────────────────────────────
loadDies();

});
</script>

<?php require_once '../includes/footer.php'; ?>
