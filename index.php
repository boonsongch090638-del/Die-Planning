<?php
$pageTitle   = 'Dashboard';
$currentPage = 'dashboard';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

$pdo = getDB();

// ── Summary queries ─────────────────────────────────────────
$total     = (int) $pdo->query("SELECT COUNT(*) FROM dies")->fetchColumn();

// Current ISO week  e.g. "16/2026"
$now       = new DateTime();
$thisWeek  = $now->format('W') . '/' . $now->format('Y');

$thisWeekCount = (int) $pdo->prepare("SELECT COUNT(*) FROM dies WHERE die_finish_plan = ?")->execute([$thisWeek])
              ?: 0;
$stmtW = $pdo->prepare("SELECT COUNT(*) FROM dies WHERE die_finish_plan = ?");
$stmtW->execute([$thisWeek]);
$thisWeekCount = (int) $stmtW->fetchColumn();

$pending = (int) $pdo->query("SELECT COUNT(*) FROM dies WHERE (die_pc_actual_date IS NULL OR die_pc_actual_date = '')")->fetchColumn();
$finish  = (int) $pdo->query("SELECT COUNT(*) FROM dies WHERE die_pc_actual_date IS NOT NULL AND die_pc_actual_date != ''")->fetchColumn();
$waiting = (int) $pdo->query("SELECT COUNT(*) FROM dies WHERE plan_status = 'waiting'")->fetchColumn();
$hold    = (int) $pdo->query("SELECT COUNT(*) FROM dies WHERE plan_status = 'hold'")->fetchColumn();

// ── Recent 5 dies ──────────────────────────────────────────
$recent = $pdo->query("SELECT * FROM dies ORDER BY id DESC LIMIT 5")->fetchAll();
foreach ($recent as &$d) {
    $d['die_no']        = trim($d['section'] ?? '') . '-' . trim($d['index_tab'] ?? '');
    $d['die_pc_status'] = !empty($d['die_pc_actual_date']) ? 'Finish' : 'Pending';
}
unset($d);
?>

<style>
.stat-card { transition: transform .18s, box-shadow .18s; }
.stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(0,0,0,.12) !important; }
.week-badge { font-size: .7rem; padding: .15rem .55rem; }
</style>

<main class="container py-4">

    <!-- ── Page header ──────────────────────────────────────── -->
    <div class="d-flex align-items-center mb-4 gap-3">
        <div>
            <h4 class="fw-bold mb-0">
                <i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard
            </h4>
            <p class="text-muted small mb-0">
                <?= date('l, d F Y') ?>
                &nbsp;·&nbsp;
                <span class="badge bg-primary week-badge">Week <?= htmlspecialchars($thisWeek) ?></span>
            </p>
        </div>
        <div class="ms-auto d-flex gap-2">
            <a href="<?= BASE_URL ?>/pages/planning.php" class="btn btn-primary btn-sm">
                <i class="bi bi-table me-1"></i>Die Planning
            </a>
            <a href="<?= BASE_URL ?>/pages/dmk_plan.php?week=<?= urlencode($thisWeek) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-clipboard-data me-1"></i>DMK Plan
            </a>
        </div>
    </div>

    <!-- ── Stat cards ───────────────────────────────────────── -->
    <div class="row g-3 mb-4">

        <!-- Total Dies -->
        <div class="col-6 col-md-3">
            <div class="card stat-card shadow-sm h-100" style="border-left:4px solid #1a5276">
                <div class="card-body d-flex align-items-center gap-3">
                    <i class="bi bi-layers stat-icon text-primary"></i>
                    <div>
                        <div class="stat-number text-primary"><?= $total ?></div>
                        <div class="stat-label text-muted">Total Dies</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- This Week -->
        <div class="col-6 col-md-3">
            <a href="<?= BASE_URL ?>/pages/planning.php?week=<?= urlencode($thisWeek) ?>"
               class="text-decoration-none">
                <div class="card stat-card shadow-sm h-100" style="border-left:4px solid #2980b9">
                    <div class="card-body d-flex align-items-center gap-3">
                        <i class="bi bi-calendar-week stat-icon text-info"></i>
                        <div>
                            <div class="stat-number text-info"><?= $thisWeekCount ?></div>
                            <div class="stat-label text-muted">This Week</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Finish -->
        <div class="col-6 col-md-3">
            <div class="card stat-card shadow-sm h-100" style="border-left:4px solid #198754">
                <div class="card-body d-flex align-items-center gap-3">
                    <i class="bi bi-check-circle stat-icon text-success"></i>
                    <div>
                        <div class="stat-number text-success"><?= $finish ?></div>
                        <div class="stat-label text-muted">Finish (ส่งแล้ว)</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending -->
        <div class="col-6 col-md-3">
            <div class="card stat-card shadow-sm h-100" style="border-left:4px solid #fd7e14">
                <div class="card-body d-flex align-items-center gap-3">
                    <i class="bi bi-hourglass-split stat-icon text-warning"></i>
                    <div>
                        <div class="stat-number text-warning"><?= $pending ?></div>
                        <div class="stat-label text-muted">Pending (รอส่ง)</div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ── Secondary stats row ──────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 bg-warning bg-opacity-10">
                <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center">
                    <span class="small fw-semibold text-warning-emphasis">
                        <i class="bi bi-pause-circle me-1"></i>Waiting
                    </span>
                    <span class="fw-bold fs-5 text-warning"><?= $waiting ?></span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 bg-danger bg-opacity-10">
                <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center">
                    <span class="small fw-semibold text-danger">
                        <i class="bi bi-x-octagon me-1"></i>Hold
                    </span>
                    <span class="fw-bold fs-5 text-danger"><?= $hold ?></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <!-- Progress bar: Finish vs Pending -->
            <?php $pct = $total > 0 ? round($finish / $total * 100) : 0; ?>
            <div class="card shadow-sm border-0">
                <div class="card-body py-2 px-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Progress ส่งมอบ</span>
                        <span class="fw-semibold"><?= $pct ?>%</span>
                    </div>
                    <div class="progress" style="height:10px">
                        <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Recent dies ──────────────────────────────────────── -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex align-items-center py-2">
            <i class="bi bi-clock-history me-2 text-secondary"></i>
            <span class="fw-semibold">รายการล่าสุด</span>
            <a href="<?= BASE_URL ?>/pages/planning.php" class="btn btn-sm btn-outline-primary ms-auto">
                ดูทั้งหมด <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
                <thead class="table-dark">
                    <tr>
                        <th>No</th>
                        <th>Customer</th>
                        <th>Section</th>
                        <th>Die No</th>
                        <th>Machine</th>
                        <th>Finish Plan</th>
                        <th>สาเหตุ</th>
                        <th>สถานะ PC</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($recent)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-3">
                        <i class="bi bi-inbox me-1"></i>ยังไม่มีข้อมูล
                    </td>
                </tr>
                <?php else: foreach ($recent as $d): ?>
                <tr>
                    <td><?= htmlspecialchars($d['no'] ?? '') ?></td>
                    <td><?= htmlspecialchars($d['customer'] ?? '') ?></td>
                    <td><?= htmlspecialchars($d['section'] ?? '') ?></td>
                    <td class="text-primary fw-semibold"><?= htmlspecialchars($d['die_no']) ?></td>
                    <td><?= htmlspecialchars($d['machine'] ?? '') ?></td>
                    <td><?= htmlspecialchars($d['die_finish_plan'] ?? '') ?></td>
                    <td><?= htmlspecialchars($d['reason'] ?? '') ?></td>
                    <td>
                        <?php if ($d['die_pc_status'] === 'Finish'): ?>
                        <span class="badge bg-success" style="font-size:.7rem">Finish</span>
                        <?php else: ?>
                        <span class="badge bg-warning text-dark" style="font-size:.7rem">Pending</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
