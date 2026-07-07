<?php
/**
 * Settings — API Configuration
 *
 * GET  : display form with current active api_settings row + sync log
 * POST : save (INSERT or UPDATE) api_settings, then PRG redirect
 */

$pageTitle   = 'Settings';
$currentPage = 'settings';
require_once '../includes/db.php';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

$pdo = getDB();

// ── Helper ──────────────────────────────────────────────────────────
function e(mixed $v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ── Handle POST (Save Settings) — admin only ────────────────────────
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'save') {
    if (!isAdmin()) {
        $flash = ['type' => 'danger', 'msg' => 'Permission denied. Please login as admin.'];
    } else {
        $apiName  = trim($_POST['api_name']  ?? '');
        $apiUrl   = trim($_POST['api_url']   ?? '');
        $apiKey   = trim($_POST['api_key']   ?? '');
        $dmkField = trim($_POST['dmk_field'] ?? 'status');
        $rawHdrs  = trim($_POST['headers']   ?? '{}');
        $id       = (int)($_POST['id'] ?? 0);

        if ($apiName === '' || $apiUrl === '') {
            $flash = ['type' => 'danger', 'msg' => 'API Name and URL are required.'];
        } else {
            // Validate / normalise headers JSON
            $hdrsArr = [];
            if ($rawHdrs !== '' && $rawHdrs !== '{}') {
                $decoded = json_decode($rawHdrs, true);
                if (!is_array($decoded)) {
                    $flash = ['type' => 'danger', 'msg' => 'Custom Headers must be a valid JSON object, e.g. {"X-Token":"abc"}.'];
                } else {
                    $hdrsArr = $decoded;
                }
            }

            if ($flash === null) {
                $hdrsJson = json_encode($hdrsArr,      JSON_UNESCAPED_UNICODE);
                $fmJson   = json_encode(['dmk_status' => $dmkField], JSON_UNESCAPED_UNICODE);

                if ($id > 0) {
                    $pdo->prepare("
                        UPDATE api_settings
                        SET api_name=:name, api_url=:url, api_key=:key,
                            headers=:hdrs, field_mapping=:fm
                        WHERE id=:id
                    ")->execute([
                        ':name' => $apiName, ':url' => $apiUrl, ':key' => $apiKey,
                        ':hdrs' => $hdrsJson, ':fm'  => $fmJson, ':id'  => $id,
                    ]);
                } else {
                    // Deactivate any existing active rows before inserting
                    $pdo->exec("UPDATE api_settings SET is_active=0 WHERE is_active=1");
                    $pdo->prepare("
                        INSERT INTO api_settings (api_name, api_url, api_key, headers, field_mapping, is_active)
                        VALUES (:name, :url, :key, :hdrs, :fm, 1)
                    ")->execute([
                        ':name' => $apiName, ':url' => $apiUrl, ':key' => $apiKey,
                        ':hdrs' => $hdrsJson, ':fm'  => $fmJson,
                    ]);
                }

                // PRG — prevents re-submit on back/refresh
                header('Location: ' . BASE_URL . '/pages/settings.php?saved=1');
                exit;
            }
        }
    }
}

// ── Load current active settings ────────────────────────────────────
$cfg     = $pdo->query("SELECT * FROM api_settings WHERE is_active=1 ORDER BY id LIMIT 1")->fetch();
$savedOk = isset($_GET['saved']);

// Derive default form values from DB
$form = [
    'id'        => $cfg['id']      ?? '',
    'api_name'  => $cfg['api_name'] ?? '',
    'api_url'   => $cfg['api_url']  ?? '',
    'api_key'   => $cfg['api_key']  ?? '',
    'dmk_field' => 'status',
    'headers'   => $cfg['headers']  ?? '{}',
];

if ($cfg) {
    $fm = json_decode($cfg['field_mapping'] ?? '{}', true);
    $form['dmk_field'] = $fm['dmk_status'] ?? 'status';
}

// On validation error, keep POSTed values
if ($flash !== null) {
    foreach (['api_name','api_url','api_key','dmk_field','headers'] as $k) {
        if (isset($_POST[$k])) $form[$k] = $_POST[$k];
    }
    if (isset($_POST['id'])) $form['id'] = (int)$_POST['id'];
}

// ── Last sync log row ────────────────────────────────────────────────
$lastSync = $pdo->query("SELECT * FROM sync_log ORDER BY id DESC LIMIT 1")->fetch();

// ── Sync log (last 10) ───────────────────────────────────────────────
$syncLogs = $pdo->query("SELECT * FROM sync_log ORDER BY id DESC LIMIT 10")->fetchAll();
?>

<style>
.section-card { border-left: 4px solid #0d6efd; }
.log-pill { font-size: .72rem; padding: .15rem .5rem; }
#testResponseWrap pre { max-height: 300px; overflow: auto; font-size: .78rem; }
</style>

<main class="container py-3" style="max-width:860px">

    <!-- ── Toast ─────────────────────────────────────────────────── -->
    <div id="toastWrap" class="position-fixed top-0 end-0 p-3" style="z-index:9999">
        <div id="appToast" class="toast align-items-center border-0" role="alert" aria-live="assertive">
            <div class="d-flex">
                <div class="toast-body fw-semibold" id="toastMsg"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto"
                        data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <h4 class="mb-3 fw-bold">
        <i class="bi bi-gear-fill me-2 text-primary"></i>Settings
    </h4>

    <?php if ($savedOk): ?>
    <div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
        <i class="bi bi-check-circle-fill me-1"></i>Settings saved successfully.
        <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show py-2 small" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-1"></i><?= e($flash['msg']) ?>
        <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════════════
         CARD — API Settings
    ═══════════════════════════════════════════════════════════════ -->
    <div class="card shadow-sm mb-4 section-card">
        <div class="card-header bg-white py-2 d-flex align-items-center gap-2">
            <i class="bi bi-plug-fill text-primary"></i>
            <span class="fw-semibold">External API Configuration</span>
            <?php if ($cfg): ?>
            <span class="badge bg-success ms-auto log-pill">Active</span>
            <?php else: ?>
            <span class="badge bg-secondary ms-auto log-pill">Not configured</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="post" id="settingsForm" novalidate>
                <input type="hidden" name="_action" value="save">
                <input type="hidden" name="id" value="<?= e($form['id']) ?>">

                <!-- Row 1: Name + URL -->
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label form-label-sm fw-semibold mb-1">
                            API Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control form-control-sm" name="api_name"
                               value="<?= e($form['api_name']) ?>"
                               placeholder="e.g. DMK Production API" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label form-label-sm fw-semibold mb-1">
                            API URL <span class="text-danger">*</span>
                        </label>
                        <input type="url" class="form-control form-control-sm" name="api_url"
                               id="inp_api_url"
                               value="<?= e($form['api_url']) ?>"
                               placeholder="https://api.example.com/die-status" required>
                        <div class="form-text">
                            Will be called as: <code>{url}?die_no={no}&amp;key={api_key}</code>
                        </div>
                    </div>
                </div>

                <!-- Row 2: Key + DMK Field -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label form-label-sm fw-semibold mb-1">API Key</label>
                        <input type="text" class="form-control form-control-sm" name="api_key"
                               id="inp_api_key"
                               value="<?= e($form['api_key']) ?>"
                               placeholder="Leave blank if not required"
                               autocomplete="off">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm fw-semibold mb-1">
                            DMK Status Field
                            <i class="bi bi-info-circle text-secondary ms-1"
                               data-bs-toggle="tooltip"
                               title="The JSON key in the API response that contains the DMK status value, e.g. &quot;status&quot; or &quot;dmk_status&quot;"></i>
                        </label>
                        <input type="text" class="form-control form-control-sm" name="dmk_field"
                               id="inp_dmk_field"
                               value="<?= e($form['dmk_field']) ?>"
                               placeholder="status">
                        <div class="form-text">
                            Key name inside the JSON response, e.g. <code>status</code>
                        </div>
                    </div>
                </div>

                <!-- Row 3: Custom Headers -->
                <div class="row g-3 mb-3">
                    <div class="col-12">
                        <label class="form-label form-label-sm fw-semibold mb-1">
                            Custom Headers
                            <span class="text-muted fw-normal small">(JSON object, optional)</span>
                        </label>
                        <textarea class="form-control form-control-sm font-monospace" name="headers"
                                  id="inp_headers" rows="3"
                                  placeholder='{"Authorization": "Bearer token123"}'><?= e($form['headers']) ?></textarea>
                        <div class="form-text">
                            Extra HTTP headers to send with each request. Must be valid JSON.
                        </div>
                    </div>
                </div>

                <!-- Action buttons -->
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <?php if (isAdmin()): ?>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-save me-1"></i>Save Settings
                    </button>
                    <?php else: ?>
                    <span class="badge bg-warning text-dark px-3 py-2">
                        <i class="bi bi-lock me-1"></i>Login เป็น Admin เพื่อแก้ไข
                    </span>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnTestConn">
                        <i class="bi bi-lightning-charge me-1"></i>Test Connection
                    </button>
                    <div class="ms-auto text-muted small">
                        <?php if ($lastSync): ?>
                        Last sync: <strong><?= e($lastSync['synced_at']) ?></strong>
                        &nbsp;·&nbsp;
                        <span class="text-success">Updated: <?= (int)$lastSync['success'] ?></span>
                        &nbsp;
                        <span class="text-danger">Failed: <?= (int)$lastSync['failed'] ?></span>
                        <?php else: ?>
                        No sync has been run yet.
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <!-- Test Connection result panel -->
            <div id="testResponseWrap" class="d-none mt-3">
                <hr class="my-2">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="fw-semibold small">Test Result</span>
                    <span id="testBadge" class="badge"></span>
                    <code id="testUrl" class="small text-muted ms-1"></code>
                </div>
                <pre id="testResponseBody" class="bg-light border rounded p-2 mb-0"></pre>
            </div>

        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         CARD — Image Storage
    ═══════════════════════════════════════════════════════════════ -->
    <div class="card shadow-sm mb-4 section-card" style="border-left-color:#fd7e14">
        <div class="card-header bg-white py-2 d-flex align-items-center gap-2">
            <i class="bi bi-images text-warning"></i>
            <span class="fw-semibold">Image Storage</span>
            <button class="btn btn-outline-secondary btn-sm ms-auto" id="btnRescan" title="Refresh stats and re-check DB matches">
                <i class="bi bi-arrow-clockwise me-1"></i>Re-scan
            </button>
        </div>
        <div class="card-body">
            <div id="imgStatsArea">
                <div class="text-center text-muted py-3 small">
                    <span class="spinner-border spinner-border-sm me-1"></span>Loading…
                </div>
            </div>
            <hr class="my-3">
            <div class="d-flex gap-2 justify-content-end">
                <button class="btn btn-outline-danger btn-sm" id="btnClearImages">
                    <i class="bi bi-trash me-1"></i>Clear All Images
                </button>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         CARD — Sync Log
    ═══════════════════════════════════════════════════════════════ -->
    <div class="card shadow-sm section-card" style="border-left-color:#198754">
        <div class="card-header bg-white py-2 d-flex align-items-center gap-2">
            <i class="bi bi-clock-history text-success"></i>
            <span class="fw-semibold">Sync Log</span>
            <span class="text-muted small ms-auto">Last 10 runs</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($syncLogs)): ?>
            <p class="text-center text-muted py-4 mb-0 small">
                <i class="bi bi-inbox fs-4 d-block mb-1"></i>No sync runs yet.
            </p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered mb-0" style="font-size:.8rem">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center" style="width:45px">#</th>
                            <th>Timestamp</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Updated</th>
                            <th class="text-center">Failed</th>
                            <th class="text-center" style="width:90px">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($syncLogs as $row):
                        $details = json_decode($row['details'] ?? '[]', true) ?: [];
                        $rowId   = (int)$row['id'];
                    ?>
                    <tr>
                        <td class="text-center text-muted"><?= $rowId ?></td>
                        <td><?= e($row['synced_at']) ?></td>
                        <td class="text-center"><?= (int)$row['total'] ?></td>
                        <td class="text-center">
                            <span class="badge bg-success log-pill"><?= (int)$row['success'] ?></span>
                        </td>
                        <td class="text-center">
                            <?php $f = (int)$row['failed']; ?>
                            <span class="badge <?= $f > 0 ? 'bg-danger' : 'bg-secondary' ?> log-pill">
                                <?= $f ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <?php if (!empty($details)): ?>
                            <button class="btn btn-outline-secondary btn-sm log-pill py-0 px-2 btn-log-details"
                                    data-log-id="<?= $rowId ?>"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#logDetail<?= $rowId ?>">
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (!empty($details)): ?>
                    <tr class="collapse" id="logDetail<?= $rowId ?>">
                        <td colspan="6" class="p-0">
                            <div class="p-2 bg-light border-top" style="font-size:.75rem">
                                <?php
                                $shown = array_slice($details, 0, 20);
                                foreach ($shown as $d):
                                    $ok = $d['success'] ?? false;
                                ?>
                                <span class="badge <?= $ok ? 'bg-success' : 'bg-danger' ?> me-1 mb-1 log-pill">
                                    <?= e($d['no'] ?? '?') ?>
                                    <?php if (!$ok): ?>
                                    <i class="bi bi-exclamation-triangle ms-1"
                                       title="<?= e($d['error'] ?? '') ?>"
                                       data-bs-toggle="tooltip"></i>
                                    <?php else: ?>
                                    → <?= e($d['new_status'] ?? '') ?>
                                    <?php endif; ?>
                                </span>
                                <?php endforeach; ?>
                                <?php if (count($details) > 20): ?>
                                <span class="text-muted">… and <?= count($details) - 20 ?> more</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</main>

<!-- ═══════════════════════════════════════════════════════════════
     JavaScript
════════════════════════════════════════════════════════════════ -->
<script>
(function () {
'use strict';

// ── Toast ────────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const el  = document.getElementById('appToast');
    const txt = document.getElementById('toastMsg');
    el.className = `toast align-items-center border-0 text-white bg-${type}`;
    txt.textContent = msg;
    bootstrap.Toast.getOrCreateInstance(el, { delay: 4000 }).show();
}

// ── Test Connection ──────────────────────────────────────────────
document.getElementById('btnTestConn').addEventListener('click', async () => {
    const btn = document.getElementById('btnTestConn');
    const apiUrl  = document.querySelector('[name="api_url"]').value.trim();
    const apiKey  = document.querySelector('[name="api_key"]').value.trim();
    const headers = document.getElementById('inp_headers').value.trim();
    const dmkFld  = document.getElementById('inp_dmk_field').value.trim();

    if (!apiUrl) {
        showToast('Please enter an API URL first.', 'warning');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Testing…';

    const wrap = document.getElementById('testResponseWrap');
    const pre  = document.getElementById('testResponseBody');
    const badge = document.getElementById('testBadge');
    const urlEl = document.getElementById('testUrl');

    wrap.classList.remove('d-none');
    pre.textContent  = 'Calling API…';
    badge.className  = 'badge bg-secondary';
    badge.textContent = 'pending';
    urlEl.textContent = '';

    try {
        const res  = await fetch('<?= BASE_URL ?>/api/external_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'test',
                api_url: apiUrl,
                api_key: apiKey,
                headers: headers || '{}',
                sample_die_no: 'TEST001',
            }),
        });
        const json = await res.json();

        urlEl.textContent = json.url_called ?? '';

        if (json.success) {
            badge.className  = 'badge bg-success';
            badge.textContent = `HTTP 200 OK`;

            // Highlight the DMK field if found
            const resp = json.response ?? {};
            if (dmkFld && resp[dmkFld] !== undefined) {
                badge.textContent += ` · "${dmkFld}": ${JSON.stringify(resp[dmkFld])}`;
            }
            pre.textContent = JSON.stringify(resp, null, 2);
        } else {
            badge.className  = 'badge bg-danger';
            badge.textContent = 'Failed';
            pre.textContent = json.error ?? 'Unknown error';
        }
    } catch (err) {
        badge.className  = 'badge bg-danger';
        badge.textContent = 'Error';
        pre.textContent = err.message;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-lightning-charge me-1"></i>Test Connection';
    }
});

// ── Init Bootstrap tooltips ──────────────────────────────────────
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el, { html: true });
});

// ── Image Storage ─────────────────────────────────────────────────
function e(v) {
    return (v ?? '').toString()
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function loadImageStats() {
    const area = document.getElementById('imgStatsArea');
    area.innerHTML = '<div class="text-center text-muted py-3 small"><span class="spinner-border spinner-border-sm me-1"></span>Loading…</div>';
    try {
        const res  = await fetch('<?= BASE_URL ?>/api/image_management.php');
        const data = await res.json();
        renderImageStats(data);
    } catch (err) {
        area.innerHTML = `<p class="text-danger small mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Failed: ${e(err.message)}</p>`;
    }
}

function renderImageStats(data) {
    const area = document.getElementById('imgStatsArea');

    const folderRows = (data.folders ?? []).map(f => `
        <tr>
            <td class="small font-monospace">${e(f.name)}</td>
            <td class="text-center small">${f.count}</td>
        </tr>`).join('');

    area.innerHTML = `
        <div class="row g-2 mb-3">
            <div class="col-6 col-sm-3">
                <div class="border rounded p-2 text-center">
                    <div class="fs-5 fw-bold text-primary">${data.total_files ?? 0}</div>
                    <div class="small text-muted">Total Images</div>
                </div>
            </div>
            <div class="col-6 col-sm-3">
                <div class="border rounded p-2 text-center">
                    <div class="fs-5 fw-bold text-info">${data.total_mb ?? '0.00'} MB</div>
                    <div class="small text-muted">Storage Used</div>
                </div>
            </div>
            <div class="col-6 col-sm-3">
                <div class="border rounded p-2 text-center">
                    <div class="fs-5 fw-bold text-success">${data.folder_count ?? 0}</div>
                    <div class="small text-muted">Die Folders</div>
                </div>
            </div>
            <div class="col-6 col-sm-3">
                <div class="border rounded p-2 text-center">
                    <div class="fs-5 fw-bold text-warning">${data.db_matched ?? 0}</div>
                    <div class="small text-muted">DB Matched</div>
                </div>
            </div>
        </div>
        ${folderRows ? `
        <div style="max-height:220px;overflow-y:auto">
            <table class="table table-sm table-bordered mb-0" style="font-size:.78rem">
                <thead class="table-dark">
                    <tr>
                        <th>Folder (Tech DWG No)</th>
                        <th class="text-center" style="width:80px">Images</th>
                    </tr>
                </thead>
                <tbody>${folderRows}</tbody>
            </table>
        </div>` : '<p class="text-muted small text-center mb-0"><i class="bi bi-inbox me-1"></i>No image folders yet. Upload images via the DMK Plan page.</p>'}
        ${data.flat_count ? `<p class="text-muted small mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>${data.flat_count} flat image(s) not in a die subfolder</p>` : ''}`;
}

document.getElementById('btnRescan').addEventListener('click', async () => {
    const btn = document.getElementById('btnRescan');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Scanning…';
    await loadImageStats();
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Re-scan';
});

document.getElementById('btnClearImages').addEventListener('click', async () => {
    if (!confirm('Delete ALL uploaded images? This cannot be undone.')) return;
    const btn = document.getElementById('btnClearImages');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Clearing…';
    try {
        const res  = await fetch('<?= BASE_URL ?>/api/image_management.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'clear'}),
        });
        const data = await res.json();
        if (data.success) {
            showToast('All images deleted successfully.', 'success');
            renderImageStats(data);
        } else {
            showToast(data.error ?? 'Failed to clear images.', 'danger');
        }
    } catch (err) {
        showToast('Request failed: ' + err.message, 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-trash me-1"></i>Clear All Images';
    }
});

loadImageStats();

})();
</script>

<?php require_once '../includes/footer.php'; ?>
