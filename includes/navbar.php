<?php
/**
 * Bootstrap 5 navbar — shared across all pages.
 * Active state is detected by comparing $currentPage (set by including page)
 * against the current filename. Falls back to URI matching if not set.
 *
 * Set $currentPage in the including page:
 *   $currentPage = 'die-planning';   // die_planning.php
 *   $currentPage = 'weekly';         // weekly_summary.php
 *   $currentPage = 'dmk';            // dmk_plan.php
 *   $currentPage = 'settings';       // settings.php
 */

if (!isset($currentPage)) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (str_contains($uri, 'weekly'))        { $currentPage = 'weekly'; }
    elseif (str_contains($uri, 'die_completed'))  { $currentPage = 'die-completed'; }
    elseif (str_contains($uri, 'dmk'))       { $currentPage = 'dmk'; }
    elseif (str_contains($uri, 'settings'))  { $currentPage = 'settings'; }
    elseif (str_contains($uri, 'planning'))  { $currentPage = 'die-planning'; }
    else                                     { $currentPage = 'dashboard'; }
}

function navActive(string $page, string $current): string {
    return $page === $current ? ' active" aria-current="page' : '';
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container-fluid">
        <!-- Brand -->
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= BASE_URL ?>/">
            <i class="bi bi-grid-3x3-gap-fill text-warning"></i>
            Die Planning
        </a>

        <!-- Mobile toggle -->
        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#mainNavbar"
                aria-controls="mainNavbar" aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Nav links -->
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link<?= navActive('dashboard', $currentPage) ?>"
                       href="<?= BASE_URL ?>/">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= navActive('die-planning', $currentPage) ?>"
                       href="<?= BASE_URL ?>/pages/planning.php">
                        <i class="bi bi-table me-1"></i>Die Planning
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= navActive('weekly', $currentPage) ?>"
                       href="<?= BASE_URL ?>/pages/weekly_summary.php">
                        <i class="bi bi-calendar-week me-1"></i>Weekly Summary
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= navActive('dmk', $currentPage) ?>"
                       href="<?= BASE_URL ?>/pages/dmk_plan.php">
                        <i class="bi bi-clipboard-data me-1"></i>DMK Plan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= navActive('die-completed', $currentPage) ?>"
                       href="<?= BASE_URL ?>/pages/die_completed.php">
                        <i class="bi bi-patch-check me-1"></i>Die Completed
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= navActive('settings', $currentPage) ?>"
                       href="<?= BASE_URL ?>/pages/settings.php">
                        <i class="bi bi-gear me-1"></i>Settings
                    </a>
                </li>
            </ul>

            <!-- Right side: role badge + auth button -->
            <div class="d-flex align-items-center gap-2">
                <span class="navbar-text text-secondary small me-1">v1.0</span>
                <?php if (isAdmin()): ?>
                    <span class="badge bg-success d-flex align-items-center gap-1 px-2 py-1">
                        <i class="bi bi-person-check-fill"></i>
                        <?= htmlspecialchars($_SESSION['admin_user'] ?? 'Admin') ?>
                    </span>
                    <a href="<?= BASE_URL ?>/logout.php"
                       class="btn btn-outline-light btn-sm d-flex align-items-center gap-1">
                        <i class="bi bi-box-arrow-right"></i>Logout
                    </a>
                <?php else: ?>
                    <span class="badge bg-secondary d-flex align-items-center gap-1 px-2 py-1"
                          title="ดูข้อมูลได้อย่างเดียว">
                        <i class="bi bi-eye"></i>Viewer
                    </span>
                    <a href="<?= BASE_URL ?>/login.php"
                       class="btn btn-outline-warning btn-sm d-flex align-items-center gap-1">
                        <i class="bi bi-person"></i>Login Admin
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
