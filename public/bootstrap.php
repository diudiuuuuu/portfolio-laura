<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/../';
const SITE_CONTENT_PATH = APP_ROOT . 'data/site-content.json';
const UPLOAD_BASE = __DIR__ . '/uploads';
const ALLOWED_MEDIA_EXT = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm'];
const ALLOWED_IMAGE_EXT = ['jpg', 'jpeg', 'png', 'gif'];
const ALLOWED_AUDIO_EXT = ['mp3', 'mpeg', 'mpga'];

// Keep local built-in server upload limits aligned with project php.ini defaults.
@ini_set('upload_max_filesize', '256M');
@ini_set('post_max_size', '256M');
@ini_set('max_file_uploads', '50');
@ini_set('max_execution_time', '300');
@ini_set('max_input_time', '300');
@ini_set('memory_limit', '512M');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function ensureUploadDirs(): void
{
    $dirs = [
        UPLOAD_BASE,
        UPLOAD_BASE . '/covers',
        UPLOAD_BASE . '/media',
        UPLOAD_BASE . '/banner',
        UPLOAD_BASE . '/intro',
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }
}

function normalizePublicPath(string $path): string
{
    $path = trim($path);
    if ($path === '' || preg_match('#^(https?:)?//#i', $path)) {
        return $path;
    }
    return str_starts_with($path, '/') ? $path : '/' . $path;
}

function loadSiteContent(): array
{
    if (isset($GLOBALS['__site_content_cache']) && is_array($GLOBALS['__site_content_cache'])) {
        return $GLOBALS['__site_content_cache'];
    }

    $defaults = [
        'settings' => [
            'logo_text' => 'LOGO',
            'menu_works' => 'Works',
            'menu_intro' => 'Introduction',
            'banner_ratio' => '4.5',
            'banner_overlay' => '/uploads/banner/default-top.png',
            'banner_bg' => '',
            'logo_image' => '',
            'music_file' => '',
            'cat_active_bg' => '#000000',
            'cat_border_color' => '#111111',
        ],
        'categories' => [
            ['id' => 1, 'name' => '未分类', 'sort_order' => 100],
        ],
        'banner_items' => [],
        'intro_content' => [
            'id' => 1,
            'col1_title' => 'Introduction',
            'col2_text' => '在这里编辑简介内容。',
            'col3_text' => 'Date: 2026',
            'top_image_path' => '/uploads/intro/sample-intro.png',
            'font_size' => 24,
            'font_weight' => 500,
            'font_family' => 'Arial, sans-serif',
            'color' => '#ffffff',
            'italic' => 0,
            'underline' => 0,
            'line_height' => 1.5,
        ],
        'works' => [],
        'admins' => [
            [
                'id' => 1,
                'username' => 'admin',
                'password_hash' => password_hash('admin123456', PASSWORD_DEFAULT),
            ],
        ],
        'audit_logs' => [],
    ];

    $decoded = null;
    if (is_file(SITE_CONTENT_PATH)) {
        $raw = @file_get_contents(SITE_CONTENT_PATH);
        if ($raw !== false && $raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $decoded = $json;
            }
        }
    }

    if (!is_array($decoded)) {
        $GLOBALS['__site_content_cache'] = $defaults;
        return $GLOBALS['__site_content_cache'];
    }

    $content = $defaults;
    $content['settings'] = array_merge(
        $defaults['settings'],
        is_array($decoded['settings'] ?? null) ? $decoded['settings'] : []
    );
    $content['categories'] = is_array($decoded['categories'] ?? null) ? array_values($decoded['categories']) : $defaults['categories'];
    $content['banner_items'] = is_array($decoded['banner_items'] ?? null) ? array_values($decoded['banner_items']) : [];
    $content['intro_content'] = array_merge(
        $defaults['intro_content'],
        is_array($decoded['intro_content'] ?? null) ? $decoded['intro_content'] : []
    );
    $content['works'] = is_array($decoded['works'] ?? null) ? array_values($decoded['works']) : [];
    $content['admins'] = is_array($decoded['admins'] ?? null) && $decoded['admins'] !== [] ? array_values($decoded['admins']) : $defaults['admins'];
    $content['audit_logs'] = is_array($decoded['audit_logs'] ?? null) ? array_values($decoded['audit_logs']) : [];

    $GLOBALS['__site_content_cache'] = $content;
    return $GLOBALS['__site_content_cache'];
}

function saveSiteContent(array $content): void
{
    $json = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Failed to encode site content.');
    }
    file_put_contents(SITE_CONTENT_PATH, $json);
    $GLOBALS['__site_content_cache'] = $content;
}

function nextItemId(array $items): int
{
    $max = 0;
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $id = (int) ($item['id'] ?? 0);
        if ($id > $max) {
            $max = $id;
        }
    }
    return $max + 1;
}

function findAdminByUsername(string $username): ?array
{
    $username = trim($username);
    if ($username === '') {
        return null;
    }
    $admins = loadSiteContent()['admins'] ?? [];
    if (!is_array($admins)) {
        return null;
    }
    foreach ($admins as $admin) {
        if (!is_array($admin)) {
            continue;
        }
        if ((string) ($admin['username'] ?? '') === $username) {
            return $admin;
        }
    }
    return null;
}

function findAdminById(int $adminId): ?array
{
    if ($adminId <= 0) {
        return null;
    }
    $admins = loadSiteContent()['admins'] ?? [];
    if (!is_array($admins)) {
        return null;
    }
    foreach ($admins as $admin) {
        if ((int) ($admin['id'] ?? 0) === $adminId) {
            return $admin;
        }
    }
    return null;
}

function updateAdminCredentialsJson(int $adminId, string $username, string $passwordHash): bool
{
    if ($adminId <= 0 || $username === '' || $passwordHash === '') {
        return false;
    }
    $content = loadSiteContent();
    $admins = is_array($content['admins'] ?? null) ? $content['admins'] : [];
    $targetIndex = null;
    foreach ($admins as $idx => $admin) {
        if (!is_array($admin)) {
            continue;
        }
        $id = (int) ($admin['id'] ?? 0);
        $name = (string) ($admin['username'] ?? '');
        if ($id !== $adminId && $name === $username) {
            return false;
        }
        if ($id === $adminId) {
            $targetIndex = $idx;
        }
    }
    if ($targetIndex === null) {
        return false;
    }
    $admins[$targetIndex]['username'] = $username;
    $admins[$targetIndex]['password_hash'] = $passwordHash;
    $content['admins'] = array_values($admins);
    saveSiteContent($content);
    return true;
}

function setting(string $key, string $fallback = ''): string
{
    $settings = loadSiteContent()['settings'] ?? [];
    if (is_array($settings) && array_key_exists($key, $settings)) {
        $value = (string) $settings[$key];
    } else {
        $value = $fallback;
    }

    if (in_array($key, ['logo_image', 'music_file', 'banner_overlay', 'banner_bg'], true)) {
        return normalizePublicPath($value);
    }
    return $value;
}

function updateSetting(string $key, string $value): void
{
    $content = loadSiteContent();
    $settings = is_array($content['settings'] ?? null) ? $content['settings'] : [];
    $settings[$key] = $value;
    $content['settings'] = $settings;
    saveSiteContent($content);
}

function isAdmin(): bool
{
    $adminId = (int) ($_SESSION['admin_id'] ?? 0);
    return $adminId > 0 && findAdminById($adminId) !== null;
}

function requireAdmin(): void
{
    if (!isAdmin()) {
        header('Location: /login.php');
        exit;
    }
}

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function flash(?string $message = null, string $target = 'global', string $type = 'success'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = [
            'message' => $message,
            'target' => $target,
            'type' => $type === 'error' ? 'error' : 'success',
        ];
        return null;
    }
    $msg = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($msg) ? $msg : null;
}

function redirectAdmin(string $target = 'global'): void
{
    header('Location: /admin.php');
    exit;
}

function postFlashTarget(string $fallback = 'global'): string
{
    $target = trim((string) ($_POST['flash_target'] ?? ''));
    return $target !== '' ? $target : $fallback;
}

function iniSizeToBytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }
    $unit = strtolower(substr($value, -1));
    $number = (float) $value;
    switch ($unit) {
        case 'g':
            return (int) round($number * 1024 * 1024 * 1024);
        case 'm':
            return (int) round($number * 1024 * 1024);
        case 'k':
            return (int) round($number * 1024);
        default:
            return (int) round($number);
    }
}

function isPostTooLarge(): bool
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return false;
    }
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength <= 0) {
        return false;
    }
    if (!empty($_POST) || !empty($_FILES)) {
        return false;
    }
    $postMax = iniSizeToBytes((string) ini_get('post_max_size'));
    return $postMax > 0 && $contentLength > $postMax;
}

function normalizeFontWeight(string $value): int
{
    $num = (int) $value;
    if ($num < 0) {
        $num = 0;
    }
    if ($num > 900) {
        $num = 900;
    }
    return $num;
}

function normalizeColor(string $value): string
{
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
        return $value;
    }
    return '#ffffff';
}

function saveUpload(array $file, string $targetDir, array $allowedExt): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $name = $file['name'] ?? '';
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return null;
    }
    $safe = date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    $dirPath = UPLOAD_BASE . '/' . $targetDir;
    if (!is_dir($dirPath)) {
        mkdir($dirPath, 0775, true);
    }
    $dest = $dirPath . '/' . $safe;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }
    return '/uploads/' . $targetDir . '/' . $safe;
}

function normalizeCardBg(string $value): string
{
    return strtolower($value) === 'white' ? 'white' : 'black';
}

function applyCoverTransform(string $savedPath, array $params): string
{
    $ext = strtolower(pathinfo($savedPath, PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_IMAGE_EXT, true)) {
        return $savedPath;
    }

    $absolute = __DIR__ . $savedPath;
    if (!is_file($absolute)) {
        return $savedPath;
    }
    $size = @getimagesize($absolute);
    if (!$size || !isset($size[0], $size[1])) {
        return $savedPath;
    }
    [$srcW, $srcH] = [(int) $size[0], (int) $size[1]];
    if ($srcW < 2 || $srcH < 2) {
        return $savedPath;
    }

    $cropXPx = (float) ($params['cover_crop_x_px'] ?? -1);
    $cropYPx = (float) ($params['cover_crop_y_px'] ?? -1);
    $cropSizePx = (float) ($params['cover_crop_size_px'] ?? -1);
    $cropXNorm = (float) ($params['cover_crop_x'] ?? -1);
    $cropYNorm = (float) ($params['cover_crop_y'] ?? -1);
    $cropSizeNorm = (float) ($params['cover_crop_size'] ?? -1);
    $borderWidth = (int) ($params['cover_border_width'] ?? 0);
    $borderWidth = max(0, min(120, $borderWidth));
    $borderColor = normalizeColor((string) ($params['cover_border_color'] ?? '#000000'));

    if ($cropXPx >= 0 && $cropYPx >= 0 && $cropSizePx > 0) {
        $srcX = (int) round($cropXPx);
        $srcY = (int) round($cropYPx);
        $cropSize = (int) round($cropSizePx);
        $cropSize = max(1, min(min($srcW, $srcH), $cropSize));
        $srcX = max(0, min($srcW - $cropSize, $srcX));
        $srcY = max(0, min($srcH - $cropSize, $srcY));
    } elseif ($cropXNorm >= 0 && $cropYNorm >= 0 && $cropSizeNorm > 0) {
        $srcX = (int) round($cropXNorm * $srcW);
        $srcY = (int) round($cropYNorm * $srcH);
        $cropSize = (int) round($cropSizeNorm * min($srcW, $srcH));
        $cropSize = max(1, min(min($srcW, $srcH), $cropSize));
        $srcX = max(0, min($srcW - $cropSize, $srcX));
        $srcY = max(0, min($srcH - $cropSize, $srcY));
    } else {
        $zoom = (float) ($params['cover_zoom'] ?? 1);
        if ($zoom < 1) {
            $zoom = 1;
        }
        if ($zoom > 3) {
            $zoom = 3;
        }
        $offsetX = (float) ($params['cover_offset_x'] ?? 0);
        $offsetY = (float) ($params['cover_offset_y'] ?? 0);
        $offsetX = max(-100, min(100, $offsetX));
        $offsetY = max(-100, min(100, $offsetY));
        $baseCrop = min($srcW, $srcH);
        $cropSize = (int) max(1, round($baseCrop / $zoom));
        $remainX = max(0, $srcW - $cropSize);
        $remainY = max(0, $srcH - $cropSize);
        $srcX = (int) round(($remainX / 2) + ($offsetX / 100) * ($remainX / 2));
        $srcY = (int) round(($remainY / 2) + ($offsetY / 100) * ($remainY / 2));
        $srcX = max(0, min($remainX, $srcX));
        $srcY = max(0, min($remainY, $srcY));
    }

    $destSize = 1200;
    $inner = max(1, $destSize - 2 * $borderWidth);
    $gdAvailable = function_exists('imagecreatetruecolor') && function_exists('imagecopyresampled');
    if ($gdAvailable) {
        $srcImage = null;
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $srcImage = @imagecreatefromjpeg($absolute);
        } elseif ($ext === 'png') {
            $srcImage = @imagecreatefrompng($absolute);
        } elseif ($ext === 'gif') {
            $srcImage = @imagecreatefromgif($absolute);
        }
        if ($srcImage) {
            $dest = imagecreatetruecolor($destSize, $destSize);
            if ($dest) {
                $rgb = sscanf($borderColor, "#%02x%02x%02x");
                $fill = imagecolorallocate($dest, (int) ($rgb[0] ?? 0), (int) ($rgb[1] ?? 0), (int) ($rgb[2] ?? 0));
                imagefill($dest, 0, 0, $fill);
                imagecopyresampled($dest, $srcImage, $borderWidth, $borderWidth, $srcX, $srcY, $inner, $inner, $cropSize, $cropSize);
                if ($ext === 'jpg' || $ext === 'jpeg') {
                    @imagejpeg($dest, $absolute, 92);
                } elseif ($ext === 'png') {
                    @imagepng($dest, $absolute, 5);
                } elseif ($ext === 'gif') {
                    @imagegif($dest, $absolute);
                }
                imagedestroy($dest);
            }
            imagedestroy($srcImage);
            return $savedPath;
        }
    }

    // Fallback for environments without GD/Imagick: use macOS built-in `sips`.
    $ok = runSipsCropResizePad($absolute, $srcX, $srcY, $cropSize, $inner, $destSize, $borderColor, $borderWidth > 0);
    if (!$ok) {
        return $savedPath;
    }
    return $savedPath;
}

function runSipsCropResizePad(string $absolutePath, int $srcX, int $srcY, int $cropSize, int $inner, int $destSize, string $borderColor, bool $needPad): bool
{
    $path = escapeshellarg($absolutePath);
    $cropCmd = 'sips -c ' . (int) $cropSize . ' ' . (int) $cropSize . ' --cropOffset ' . (int) $srcY . ' ' . (int) $srcX . ' ' . $path . ' >/dev/null 2>&1';
    exec($cropCmd, $out1, $code1);
    if ($code1 !== 0) {
        return false;
    }
    $resizeCmd = 'sips -z ' . (int) $inner . ' ' . (int) $inner . ' ' . $path . ' >/dev/null 2>&1';
    exec($resizeCmd, $out2, $code2);
    if ($code2 !== 0) {
        return false;
    }
    if ($needPad) {
        $hex = strtoupper(ltrim($borderColor, '#'));
        if (!preg_match('/^[0-9A-F]{6}$/', $hex)) {
            $hex = '000000';
        }
        $padCmd = 'sips -p ' . (int) $destSize . ' ' . (int) $destSize . ' --padColor ' . $hex . ' ' . $path . ' >/dev/null 2>&1';
        exec($padCmd, $out3, $code3);
        if ($code3 !== 0) {
            return false;
        }
    }
    return true;
}

function cloneUploadPath(string $sourcePath, string $targetDir): ?string
{
    $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    if ($ext === '') {
        return null;
    }
    $srcAbs = __DIR__ . $sourcePath;
    if (!is_file($srcAbs)) {
        return null;
    }
    $dirPath = UPLOAD_BASE . '/' . $targetDir;
    if (!is_dir($dirPath)) {
        mkdir($dirPath, 0775, true);
    }
    $safe = date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    $dstAbs = $dirPath . '/' . $safe;
    if (!@copy($srcAbs, $dstAbs)) {
        return null;
    }
    return '/uploads/' . $targetDir . '/' . $safe;
}

function mediaTypeFromPath(string $path): string
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($ext, ['mp4', 'webm'], true) ? 'video' : 'image';
}

function styleString(array $item): string
{
    $parts = [];
    $weight = (int) ($item['title_font_weight'] ?? 600);
    if ($weight <= 0) {
        $weight = 1;
    }
    $parts[] = 'font-size:' . (int) ($item['title_font_size'] ?? 16) . 'px';
    $parts[] = 'font-weight:' . $weight;
    $parts[] = 'font-family:' . ($item['title_font_family'] ?? 'Arial, sans-serif');
    $parts[] = 'color:' . normalizeColor((string) ($item['title_color'] ?? '#ffffff'));
    if ((int) ($item['title_italic'] ?? 0) === 1) {
        $parts[] = 'font-style:italic';
    }
    if ((int) ($item['title_underline'] ?? 0) === 1) {
        $parts[] = 'text-decoration:underline';
    }
    return implode(';', $parts);
}

function introStyleString(array $intro): string
{
    $parts = [];
    $parts[] = 'font-size:' . (int) ($intro['font_size'] ?? 24) . 'px';
    $parts[] = 'font-weight:' . (int) ($intro['font_weight'] ?? 500);
    $parts[] = 'font-family:' . ($intro['font_family'] ?? 'Arial, sans-serif');
    $parts[] = 'color:' . normalizeColor((string) ($intro['color'] ?? '#ffffff'));
    if ((int) ($intro['italic'] ?? 0) === 1) {
        $parts[] = 'font-style:italic';
    }
    if ((int) ($intro['underline'] ?? 0) === 1) {
        $parts[] = 'text-decoration:underline';
    }
    $parts[] = 'line-height:' . (float) ($intro['line_height'] ?? 1.5);
    $parts[] = 'text-align:left';
    return implode(';', $parts);
}

function fetchIntroContent(): array
{
    $intro = loadSiteContent()['intro_content'] ?? [];
    if (!is_array($intro)) {
        return [];
    }
    if (isset($intro['top_image_path'])) {
        $intro['top_image_path'] = normalizePublicPath((string) $intro['top_image_path']);
    }
    return $intro;
}

function fetchCategories(): array
{
    $categories = loadSiteContent()['categories'] ?? [];
    if (!is_array($categories)) {
        return [];
    }
    usort($categories, static function (array $a, array $b): int {
        $sortA = (int) ($a['sort_order'] ?? 0);
        $sortB = (int) ($b['sort_order'] ?? 0);
        if ($sortA !== $sortB) {
            return $sortB <=> $sortA;
        }
        return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
    });
    return array_values($categories);
}

function fetchBannerItems(): array
{
    $items = loadSiteContent()['banner_items'] ?? [];
    if (!is_array($items)) {
        return [];
    }
    usort($items, static function (array $a, array $b): int {
        $sortA = (int) ($a['sort_order'] ?? 0);
        $sortB = (int) ($b['sort_order'] ?? 0);
        if ($sortA !== $sortB) {
            return $sortB <=> $sortA;
        }
        return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
    });
    foreach ($items as &$item) {
        $item['media_path'] = normalizePublicPath((string) ($item['media_path'] ?? ''));
        $item['media_type'] = (string) ($item['media_type'] ?? mediaTypeFromPath($item['media_path']));
    }
    unset($item);
    return array_values($items);
}

function defaultCategoryId(): int
{
    $categories = fetchCategories();
    return isset($categories[0]['id']) ? (int) $categories[0]['id'] : 1;
}

function addAuditLog(string $section, string $action, string $detail): void
{
    $content = loadSiteContent();
    $logs = is_array($content['audit_logs'] ?? null) ? $content['audit_logs'] : [];
    $logs[] = [
        'id' => nextItemId($logs),
        'section' => $section,
        'action' => $action,
        'detail' => $detail,
        'created_at' => date('c'),
    ];
    $content['audit_logs'] = $logs;
    saveSiteContent($content);
}

function fetchAuditLogs(int $limit = 120): array
{
    $logs = loadSiteContent()['audit_logs'] ?? [];
    if (!is_array($logs)) {
        return [];
    }
    usort($logs, static function (array $a, array $b): int {
        return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
    });
    return array_slice(array_values($logs), 0, max(0, $limit));
}

function fetchWorksWithMedia(?int $categoryId = null): array
{
    $works = loadSiteContent()['works'] ?? [];
    if (!is_array($works) || $works === []) {
        return [];
    }

    $normalized = [];
    foreach ($works as $work) {
        if (!is_array($work)) {
            continue;
        }
        $work['id'] = (int) ($work['id'] ?? 0);
        $work['category_id'] = (int) ($work['category_id'] ?? 0);
        if ($categoryId !== null && $categoryId > 0 && $work['category_id'] !== $categoryId) {
            continue;
        }
        $work['cover_path'] = normalizePublicPath((string) ($work['cover_path'] ?? ''));
        $mediaList = $work['media'] ?? [];
        if (!is_array($mediaList)) {
            $mediaList = [];
        }
        foreach ($mediaList as &$media) {
            if (!is_array($media)) {
                continue;
            }
            $media['media_path'] = normalizePublicPath((string) ($media['media_path'] ?? ''));
            $media['media_type'] = (string) ($media['media_type'] ?? mediaTypeFromPath((string) $media['media_path']));
        }
        unset($media);
        $work['media'] = array_values(array_filter($mediaList, 'is_array'));
        $normalized[] = $work;
    }
    usort($normalized, static function (array $a, array $b): int {
        $sortA = (int) ($a['sort_order'] ?? 0);
        $sortB = (int) ($b['sort_order'] ?? 0);
        if ($sortA !== $sortB) {
            return $sortB <=> $sortA;
        }
        return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
    });
    return $normalized;
}

function arrangeWorks(array $works): array
{
    $pool = [];
    foreach ($works as $w) {
        $w['_used'] = false;
        $w['_is_big'] = false;
        $pool[] = $w;
    }
    $out = [];

    for ($i = 0; $i < 4; $i++) {
        $idx = nextUnusedIndex($pool, false, true);
        if ($idx === null) {
            $idx = nextUnusedIndex($pool);
        }
        if ($idx === null) {
            break;
        }
        $pool[$idx]['_used'] = true;
        $out[] = $pool[$idx];
    }

    while (hasUnused($pool)) {
        $bigIdx = nextUnusedIndex($pool, true);
        if ($bigIdx === null) {
            // No emphasized work left: append all remaining as normal cards.
            while (true) {
                $idx = nextUnusedIndex($pool);
                if ($idx === null) {
                    break;
                }
                $pool[$idx]['_used'] = true;
                $out[] = $pool[$idx];
            }
            break;
        }
        $pool[$bigIdx]['_used'] = true;
        $pool[$bigIdx]['_is_big'] = true;
        $out[] = $pool[$bigIdx];

        for ($i = 0; $i < 4; $i++) {
            $idx = nextUnusedIndex($pool);
            if ($idx === null) {
                break;
            }
            $pool[$idx]['_used'] = true;
            $out[] = $pool[$idx];
        }
    }
    return $out;
}

function nextUnusedIndex(array $pool, bool $mustEmphasized = false, bool $excludeEmphasized = false): ?int
{
    foreach ($pool as $idx => $item) {
        if ($item['_used']) {
            continue;
        }
        if ($mustEmphasized && (int) $item['emphasized'] !== 1) {
            continue;
        }
        if ($excludeEmphasized && (int) $item['emphasized'] === 1) {
            continue;
        }
        return $idx;
    }
    return null;
}

function hasUnused(array $pool): bool
{
    foreach ($pool as $item) {
        if (!$item['_used']) {
            return true;
        }
    }
    return false;
}

ensureUploadDirs();
