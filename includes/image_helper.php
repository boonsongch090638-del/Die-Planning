<?php
/**
 * Image helper — shared by api/dmk_plan.php, api/images.php, api/export_zip.php
 *
 * Storage layout (primary — new):
 *   /uploads/images/{tech_dwg_no}/{filename}
 *
 * Storage layout (legacy — flat fallback):
 *   /uploads/images/{filename}
 */

const IMG_DIR      = __DIR__ . '/../uploads/images';
define('IMG_URL_BASE', BASE_URL . '/uploads/images');
const IMG_EXTS     = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

// ─────────────────────────────────────────────────────────────────
// Section profile image — external API (sandboxalmdc)
// ─────────────────────────────────────────────────────────────────

const SECTION_PROFILE_API_BASE = 'http://sandboxalmdc.alumetgroup.com:8803/alm_profile/api';
const SECTION_PROFILE_API_KEY  = '9mvgh5_9Egnqw0hReqflmMiHochHMxaCEzPZ-5ToSLA';

/**
 * Fetch a section's profile image URL from the external Section Profile API.
 * Cached per-request so every die sharing a section only hits the API once.
 */
function getSectionProfileImageUrl(string $sectionCode): ?string {
    static $cache = [];

    $sectionCode = trim($sectionCode);
    if ($sectionCode === '') return null;
    if (array_key_exists($sectionCode, $cache)) return $cache[$sectionCode];

    $url  = SECTION_PROFILE_API_BASE . '/sections/' . rawurlencode($sectionCode) . '/profile/';
    $data = fetchSectionApiJson($url);

    return $cache[$sectionCode] = $data['profile_image_url'] ?? null;
}

/**
 * Minimal GET+JSON client for the Section Profile API (X-Api-Key auth).
 * Returns null on any network/HTTP/JSON failure so a missing profile image
 * is treated as "no image" rather than a hard error for the whole page.
 */
function fetchSectionApiJson(string $url): ?array {
    $headers = ['Accept: application/json', 'X-Api-Key: ' . SECTION_PROFILE_API_KEY];

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $httpCode < 200 || $httpCode >= 300) return null;
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    $ctx = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'header'        => implode("\r\n", $headers),
            'timeout'       => 10,
            'ignore_errors' => true,
        ],
        'ssl'  => ['verify_peer' => false],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) return null;

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

// ─────────────────────────────────────────────────────────────────
// Section profile image cache — the external host serves images over
// plain HTTP, which HTTPS pages (production) block as mixed content.
// api/section_image.php fetches them server-side and serves same-origin;
// these helpers back that proxy with a per-section disk cache so repeat
// loads are fast and the page still works if the external host is down.
// ─────────────────────────────────────────────────────────────────

const SECTION_IMG_CACHE_DIR = __DIR__ . '/../uploads/section_cache';
const SECTION_IMG_CACHE_TTL = 3600; // seconds — re-check the API this often

/** Disk-cache path for a section's proxied profile image (extension-less; MIME is re-detected on read). */
function sectionImageCachePath(string $sectionCode): string {
    $key = preg_replace('/[^A-Za-z0-9_-]/', '_', $sectionCode);
    return SECTION_IMG_CACHE_DIR . '/' . $key . '.img';
}

/**
 * Fetch the raw bytes of a remote image URL.
 * Returns null on any network/HTTP failure.
 */
function fetchRemoteImageBytes(string $url): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($raw !== false && $httpCode >= 200 && $httpCode < 300) ? $raw : null;
    }

    $ctx = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true]]);
    $raw = @file_get_contents($url, false, $ctx);
    return $raw !== false ? $raw : null;
}

/** Sniff the MIME type of in-memory image bytes (for setting the proxy's Content-Type). */
function detectImageMime(string $bytes): string {
    if (function_exists('finfo_open')) {
        $fi   = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_buffer($fi, $bytes);
        finfo_close($fi);
        if ($mime) return $mime;
    }
    return 'application/octet-stream';
}

// ─────────────────────────────────────────────────────────────────
// Per-request caches — built once, reused for every die in the loop
// ─────────────────────────────────────────────────────────────────

/**
 * Subfolder index: maps strtolower(dirName) → ['dirName'=>..., 'path'=>...]
 * Built once per PHP process from a single scandir(IMG_DIR) call.
 *
 * @return array<string, array{dirName:string, path:string}>
 */
function _imgSubfolderIndex(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $cache = [];
    if (!is_dir(IMG_DIR)) return $cache;

    $entries = scandir(IMG_DIR, SCANDIR_SORT_ASCENDING) ?: [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $full = IMG_DIR . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($full) && !str_starts_with($entry, 'temp_')) {
            $cache[strtolower($entry)] = ['dirName' => $entry, 'path' => $full];
        }
    }
    return $cache;
}

/**
 * Flat file list: all image filenames directly inside IMG_DIR (no subdirs).
 * Built once per PHP process from a single scandir(IMG_DIR) call.
 *
 * @return string[]
 */
function _imgFlatFileList(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $cache = [];
    if (!is_dir(IMG_DIR)) return $cache;

    $entries = scandir(IMG_DIR, SCANDIR_SORT_ASCENDING) ?: [];
    foreach ($entries as $file) {
        if ($file[0] === '.' || is_dir(IMG_DIR . DIRECTORY_SEPARATOR . $file)) continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, IMG_EXTS, true)) {
            $cache[] = $file;
        }
    }
    return $cache;
}

/**
 * Files inside a specific subfolder — cached per directory path.
 *
 * @return string[]
 */
function _imgSubfolderFiles(string $dirPath): array {
    static $cache = [];
    if (isset($cache[$dirPath])) return $cache[$dirPath];

    $files   = [];
    $entries = scandir($dirPath, SCANDIR_SORT_ASCENDING) ?: [];
    foreach ($entries as $file) {
        if ($file[0] === '.') continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, IMG_EXTS, true)) {
            $files[] = $file;
        }
    }
    $cache[$dirPath] = $files;
    return $files;
}

// ─────────────────────────────────────────────────────────────────
// Subfolder lookup (primary)
// ─────────────────────────────────────────────────────────────────

/**
 * Return images from /uploads/images/{techDwgNo}/ using the cached index.
 * O(1) folder lookup — no repeated scandir on the root directory.
 *
 * @return array<array{filename:string, path:string, url:string}>
 */
function findImagesInSubfolder(string $techDwgNo): array {
    $techDwgNo = trim($techDwgNo);
    if ($techDwgNo === '') return [];

    $index = _imgSubfolderIndex();
    $entry = $index[strtolower($techDwgNo)] ?? null;
    if ($entry === null) return [];

    $dirName  = $entry['dirName'];
    $matchDir = $entry['path'];

    $files = [];
    foreach (_imgSubfolderFiles($matchDir) as $file) {
        $files[] = [
            'filename' => $file,
            'path'     => $matchDir . DIRECTORY_SEPARATOR . $file,
            'url'      => IMG_URL_BASE . '/' . rawurlencode($dirName) . '/' . rawurlencode($file),
        ];
    }
    return $files;
}

/**
 * Return flat images whose filename starts with $prefix.
 * Uses the cached flat-file list — no repeated scandir.
 *
 * @return array<array{filename:string, path:string, url:string}>
 */
function findImagesFlat(string $prefix): array {
    $prefix = trim($prefix);
    if ($prefix === '') return [];

    $found = [];
    foreach (_imgFlatFileList() as $file) {
        if (stripos($file, $prefix) === 0) {
            $found[] = [
                'filename' => $file,
                'path'     => IMG_DIR . DIRECTORY_SEPARATOR . $file,
                'url'      => IMG_URL_BASE . '/' . rawurlencode($file),
            ];
        }
    }
    return $found;
}

/**
 * Get all images for a die, checking locations in priority order:
 *   1. /uploads/images/{techDwgNo}/    (subfolder — set by ZIP upload)
 *   2. /uploads/images/ prefix=techDwgNo  (flat fallback)
 *   3. /uploads/images/ prefix=section    (flat, broader prefix)
 *
 * @return array<array{filename:string, path:string, url:string}>
 */
function getDieImages(string $techDwgNo, string $section = ''): array {
    $techDwgNo = trim($techDwgNo);
    $section   = trim($section);

    if ($techDwgNo !== '') {
        $imgs = findImagesInSubfolder($techDwgNo);
        if (!empty($imgs)) return sortImagesByPrimary($imgs);

        $imgs = findImagesFlat($techDwgNo);
        if (!empty($imgs)) return sortImagesByPrimary($imgs);
    }

    if ($section !== '') {
        $imgs = findImagesFlat($section);
        if (!empty($imgs)) return sortImagesByPrimary($imgs);
    }

    return [];
}

/**
 * Sort images so the _1 variant comes first, then alphabetically.
 *
 * @param  array<array{filename:string, path:string, url:string}> $imgs
 * @return array<array{filename:string, path:string, url:string}>
 */
function sortImagesByPrimary(array $imgs): array {
    usort($imgs, static function (array $a, array $b): int {
        $a1 = (int) preg_match('/_1\.[a-z]+$/i', $a['filename']);
        $b1 = (int) preg_match('/_1\.[a-z]+$/i', $b['filename']);
        if ($a1 !== $b1) return $b1 - $a1;
        return strcasecmp($a['filename'], $b['filename']);
    });
    return $imgs;
}

// ─────────────────────────────────────────────────────────────────
// Convenient accessors
// ─────────────────────────────────────────────────────────────────

/** All image URLs for a die (subfolder then flat). */
function findImageUrlsForDie(string $techDwgNo, string $section = ''): array {
    return array_column(getDieImages($techDwgNo, $section), 'url');
}

/** All absolute image paths for a die. Used by export_zip.php. */
function findImagePathsForDie(string $techDwgNo, string $section = ''): array {
    return array_column(getDieImages($techDwgNo, $section), 'path');
}

// ─────────────────────────────────────────────────────────────────
// Legacy API (backward-compatible — flat only)
// ─────────────────────────────────────────────────────────────────

function findImageNames(string $prefix): array {
    return array_column(findImagesFlat($prefix), 'filename');
}

function findImageUrls(string $prefix): array {
    return array_column(findImagesFlat($prefix), 'url');
}

function findImagePaths(string $prefix): array {
    return array_column(findImagesFlat($prefix), 'path');
}

// ─────────────────────────────────────────────────────────────────
// Filename helpers
// ─────────────────────────────────────────────────────────────────

/**
 * Extract base die number from a file stem.
 * "A20829-001_1"  → "A20829-001"
 * "A20829-001_12" → "A20829-001"
 * "A20829-001"    → "A20829-001"   (no variant suffix)
 */
function extractBaseDieNo(string $stem): string {
    if (preg_match('/^(.+)_(\d+)$/', $stem, $m)) {
        return $m[1];
    }
    return $stem;
}

/**
 * Sanitize an uploaded filename:
 *  - basename (strip directory traversal)
 *  - spaces → underscores
 *  - only alphanumeric, hyphens, underscores, dots
 *  - no leading dot
 */
function sanitizeUploadFilename(string $name): string {
    $name = basename($name);
    $name = str_replace(' ', '_', $name);
    $name = preg_replace('/[^a-zA-Z0-9\-_\.]/', '', $name);
    return ltrim($name, '.');
}
