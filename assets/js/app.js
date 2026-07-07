/* ============================================================
   Die Planning — Global JS
   ============================================================ */

// Bootstrap tooltip initialisation (used site-wide)
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el);
    });
});
