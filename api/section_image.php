<?php
/**
 * Section profile image proxy.
 *
 * GET /api/section_image.php?section=4001
 *
 * The Section Profile API's images live on an HTTP-only host. Browsers
 * block loading them from our HTTPS pages as mixed content, so this
 * endpoint fetches the image server-side and re-serves it same-origin.
 * Results are cached to disk per section code — repeat loads are fast,
 * and a stale cache is served if the external API/image host is down.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/image_helper.php';

$section = trim($_GET['section'] ?? '');
if ($section === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'section is required']);
    exit;
}

if (!is_dir(SECTION_IMG_CACHE_DIR)) {
    @mkdir(SECTION_IMG_CACHE_DIR, 0755, true);
}

$cachePath = sectionImageCachePath($section);
$isFresh   = is_file($cachePath) && (time() - filemtime($cachePath)) < SECTION_IMG_CACHE_TTL;

if (!$isFresh) {
    $remoteUrl = getSectionProfileImageUrl($section);
    $bytes     = $remoteUrl ? fetchRemoteImageBytes($remoteUrl) : null;
    if ($bytes !== null) {
        @file_put_contents($cachePath, $bytes);
    }
}

if (!is_file($cachePath)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'No image available for this section']);
    exit;
}

$bytes = file_get_contents($cachePath);
header('Content-Type: ' . detectImageMime($bytes));
header('Cache-Control: public, max-age=' . SECTION_IMG_CACHE_TTL);
header('Content-Length: ' . strlen($bytes));
echo $bytes;
