<?php
/**
 * Login page — admin sign-in for Die Planning system.
 * Guests (view-only) do not need to log in.
 */
require_once __DIR__ . '/includes/auth.php';

// If already admin, go to dashboard
if (isAdmin()) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

$error = '';
$redirect = $_GET['redirect'] ?? BASE_URL . '/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    if (checkAdminCredentials($user, $pass)) {
        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_user'] = $user;
        header('Location: ' . $redirect);
        exit;
    } else {
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Die Planning</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,.5);
        }
        .login-logo {
            width: 56px; height: 56px;
            background: linear-gradient(135deg, #0d6efd, #0dcaf0);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; color: #fff;
            margin: 0 auto 1rem;
        }
        .form-control:focus { box-shadow: 0 0 0 .2rem rgba(13,110,253,.25); }
        .btn-login {
            background: linear-gradient(135deg, #0d6efd, #0dcaf0);
            border: none; color: #fff; font-weight: 600;
            transition: opacity .2s;
        }
        .btn-login:hover { opacity: .9; color: #fff; }
        .guest-link {
            display: block; text-align: center; margin-top: .75rem;
            color: #6c757d; font-size: .85rem; text-decoration: none;
        }
        .guest-link:hover { color: #0d6efd; }
        .badge-viewer {
            background: rgba(108,117,125,.15);
            color: #6c757d;
            border: 1px solid rgba(108,117,125,.3);
            font-weight: 400;
            font-size: .75rem;
        }
    </style>
</head>
<body>
<div class="login-card card border-0">
    <div class="card-body p-4 p-md-5">

        <!-- Logo + title -->
        <div class="text-center mb-4">
            <div class="login-logo">
                <i class="bi bi-grid-3x3-gap-fill"></i>
            </div>
            <h5 class="fw-bold mb-1">Die Planning</h5>
            <p class="text-muted small mb-0">เข้าสู่ระบบสำหรับผู้ดูแล</p>
        </div>

        <!-- Error alert -->
        <?php if ($error): ?>
        <div class="alert alert-danger py-2 small d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Login form -->
        <form method="POST" autocomplete="off">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

            <div class="mb-3">
                <label for="username" class="form-label fw-semibold small">Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0">
                        <i class="bi bi-person text-secondary"></i>
                    </span>
                    <input type="text" class="form-control border-start-0"
                           id="username" name="username"
                           placeholder="ชื่อผู้ใช้"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           autofocus required>
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label fw-semibold small">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0">
                        <i class="bi bi-lock text-secondary"></i>
                    </span>
                    <input type="password" class="form-control border-start-0"
                           id="password" name="password"
                           placeholder="รหัสผ่าน" required>
                    <button class="btn btn-outline-secondary border-start-0" type="button"
                            id="togglePwd" tabindex="-1" title="แสดง/ซ่อนรหัสผ่าน">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-login w-100 py-2">
                <i class="bi bi-box-arrow-in-right me-2"></i>เข้าสู่ระบบ
            </button>
        </form>

        <!-- Guest hint -->
        <div class="mt-3 text-center">
            <span class="badge badge-viewer rounded-pill px-3 py-2">
                <i class="bi bi-eye me-1"></i>
                ผู้เยี่ยมชมสามารถ <a href="<?= BASE_URL ?>/" class="text-decoration-none text-secondary fw-semibold">ดูข้อมูลได้โดยไม่ต้องล็อกอิน</a>
            </span>
        </div>

    </div>
</div>

<script>
document.getElementById('togglePwd').addEventListener('click', function() {
    const pwd  = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        pwd.type = 'password';
        icon.className = 'bi bi-eye';
    }
});
</script>
</body>
</html>
