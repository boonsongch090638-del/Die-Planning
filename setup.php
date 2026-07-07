<?php
/**
 * One-time setup/health-check page.
 * Upload this file to the server, open it in the browser once, then DELETE it.
 */
define('SETUP_TOKEN', 'PlanningDie2026');

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$authed = ($token === SETUP_TOKEN);

$results = [];

function check(string $label, bool $ok, string $detail = ''): array {
    return ['label' => $label, 'ok' => $ok, 'detail' => $detail];
}

if ($authed && isset($_POST['run'])) {

    // 1. PHP version
    $results[] = check('PHP >= 8.0', PHP_MAJOR_VERSION >= 8, 'PHP ' . PHP_VERSION);

    // 2. Required extensions
    foreach (['pdo', 'pdo_sqlite', 'mbstring', 'zip'] as $ext) {
        $results[] = check("ext-$ext", extension_loaded($ext));
    }

    // 3. vendor directory
    $vendorOk = is_dir(__DIR__ . '/vendor');
    if (!$vendorOk) {
        // Try to run composer install using bundled composer.phar
        $composerPhar = __DIR__ . '/composer.phar';
        if (file_exists($composerPhar)) {
            $output = [];
            $code   = 0;
            exec('php ' . escapeshellarg($composerPhar) . ' install --no-dev --optimize-autoloader 2>&1', $output, $code);
            $vendorOk = is_dir(__DIR__ . '/vendor');
            $results[] = check('composer install', $vendorOk, implode(' | ', array_slice($output, -3)));
        } else {
            $results[] = check('vendor/ directory', false, 'vendor/ missing and composer.phar not found — upload vendor/ manually');
        }
    } else {
        $results[] = check('vendor/ directory', true, 'already exists');
    }

    // 4. db directory writable
    $dbDir = __DIR__ . '/db';
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0775, true);
    }
    $results[] = check('db/ writable', is_writable($dbDir), realpath($dbDir));

    // 5. uploads directory
    $uploadsDir = __DIR__ . '/uploads';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0775, true);
    }
    $results[] = check('uploads/ writable', is_writable($uploadsDir), realpath($uploadsDir));

    // 6. SQLite database
    $dbPath = __DIR__ . '/db/die_planning.db';
    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $results[] = check('SQLite database', true, $dbPath);
    } catch (Exception $e) {
        $results[] = check('SQLite database', false, $e->getMessage());
    }

    // 7. Try loading autoloader (only if vendor exists)
    if (is_dir(__DIR__ . '/vendor')) {
        try {
            require_once __DIR__ . '/vendor/autoload.php';
            $results[] = check('PhpSpreadsheet autoload', class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet'), '');
        } catch (Exception $e) {
            $results[] = check('PhpSpreadsheet autoload', false, $e->getMessage());
        }
    }
}

$allOk = !empty($results) && array_reduce($results, fn($c, $r) => $c && $r['ok'], true);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>PlanningDie Setup</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:640px">
  <h4 class="mb-1 fw-bold">PlanningDie — Server Setup</h4>
  <p class="text-muted small mb-4">ลบไฟล์นี้ออกทันทีหลังติดตั้งเสร็จ</p>

  <?php if (!$authed): ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <form method="GET">
        <label class="form-label fw-semibold">Setup Token</label>
        <div class="input-group">
          <input type="text" class="form-control" name="token" placeholder="PlanningDie2026" required>
          <button class="btn btn-primary">ยืนยัน</button>
        </div>
        <div class="form-text">token: <code>PlanningDie2026</code></div>
      </form>
    </div>
  </div>

  <?php elseif (empty($results)): ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <p class="mb-3">กดปุ่มด้านล่างเพื่อตรวจสอบและติดตั้ง dependencies</p>
      <form method="POST">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <input type="hidden" name="run" value="1">
        <button class="btn btn-success w-100">ติดตั้ง / ตรวจสอบ</button>
      </form>
    </div>
  </div>

  <?php else: ?>
  <div class="card shadow-sm mb-3">
    <div class="card-body p-0">
      <table class="table table-sm mb-0">
        <thead class="table-dark"><tr><th>รายการ</th><th style="width:80px">สถานะ</th><th>รายละเอียด</th></tr></thead>
        <tbody>
        <?php foreach ($results as $r): ?>
          <tr class="<?= $r['ok'] ? '' : 'table-danger' ?>">
            <td><?= htmlspecialchars($r['label']) ?></td>
            <td><?= $r['ok'] ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger">FAIL</span>' ?></td>
            <td class="text-muted small"><?= htmlspecialchars($r['detail']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($allOk): ?>
    <div class="alert alert-success fw-semibold">
      ติดตั้งสำเร็จ! <a href="/PlanningDie/" class="alert-link">ไปที่ระบบ</a>
      — <strong>กรุณาลบไฟล์ setup.php ออกจาก server ทันที</strong>
    </div>
  <?php else: ?>
    <div class="alert alert-warning">
      มีรายการที่ fail อยู่ — ดูรายละเอียดด้านบนและแก้ไขก่อนใช้งาน
    </div>
    <form method="POST">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <input type="hidden" name="run" value="1">
      <button class="btn btn-outline-primary btn-sm">ตรวจสอบอีกครั้ง</button>
    </form>
  <?php endif; ?>
  <?php endif; ?>
</div>
</body>
</html>
