<?php
/**
 * Authentication helper — session-based admin/guest role system.
 *
 * Admin credentials are stored here (single-user internal tool).
 * Include this file BEFORE any HTML output (session_start must precede output).
 *
 * Usage in pages  : just include via header.php — isAdmin() is then available.
 * Usage in APIs   : require_once auth.php, then call requireAdminApi() for write ops.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Resolve deployment base path dynamically (handles /boonsong/PlanningDie/ on server)
if (!defined('BASE_URL')) {
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $pos = strpos($scriptName, '/PlanningDie');
    $basePath = ($pos !== false) ? substr($scriptName, 0, $pos + strlen('/PlanningDie')) : '/PlanningDie';
    define('BASE_URL', $basePath);
}

// ── Credentials ──────────────────────────────────────────────────────
define('ADMIN_USER', 'Boonsong.ch');
define('ADMIN_PASS', 'Alumet5902146');

// ── Helpers ──────────────────────────────────────────────────────────

/** Returns true when the current session belongs to the admin. */
function isAdmin(): bool {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

/**
 * Call at the top of any API endpoint that performs a write operation.
 * Terminates with HTTP 403 JSON error if the caller is not the admin.
 */
function requireAdminApi(): void {
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied. Please login as admin.']);
        exit;
    }
}

/**
 * Verify username + password against the stored admin credentials.
 * Uses hash_equals to mitigate timing attacks.
 */
function checkAdminCredentials(string $user, string $pass): bool {
    return hash_equals(ADMIN_USER, $user) && hash_equals(ADMIN_PASS, $pass);
}
