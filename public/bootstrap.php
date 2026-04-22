<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/../';
const DB_PATH = APP_ROOT . 'data/site.sqlite';
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

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $needInit = !file_exists(DB_PATH);
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    if ($needInit) {
        initDatabase($pdo);
    }
    migrateDatabase($pdo);
    return $pdo;
}

function initDatabase(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS works (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT "",
            meta_text TEXT NOT NULL DEFAULT "",
            created_time TEXT NOT NULL DEFAULT "",
            cover_path TEXT NOT NULL DEFAULT "",
            emphasized INTEGER NOT NULL DEFAULT 0,
            title_font_size INTEGER NOT NULL DEFAULT 16,
            title_font_weight INTEGER NOT NULL DEFAULT 600,
            title_font_family TEXT NOT NULL DEFAULT "Arial, sans-serif",
            title_color TEXT NOT NULL DEFAULT "#ffffff",
            title_italic INTEGER NOT NULL DEFAULT 0,
            title_underline INTEGER NOT NULL DEFAULT 0,
            modal_font_size INTEGER NOT NULL DEFAULT 28,
            category_id INTEGER NOT NULL DEFAULT 1,
            card_bg TEXT NOT NULL DEFAULT "black",
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS work_media (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            work_id INTEGER NOT NULL,
            media_path TEXT NOT NULL,
            media_type TEXT NOT NULL,
            position INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            FOREIGN KEY(work_id) REFERENCES works(id) ON DELETE CASCADE
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS settings (
            key_name TEXT PRIMARY KEY,
            value_text TEXT NOT NULL DEFAULT ""
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS banner_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            media_path TEXT NOT NULL,
            media_type TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            section TEXT NOT NULL,
            action TEXT NOT NULL,
            detail TEXT NOT NULL,
            created_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            sort_order INTEGER NOT NULL DEFAULT 0
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS intro_content (
            id INTEGER PRIMARY KEY CHECK(id = 1),
            col1_title TEXT NOT NULL DEFAULT "",
            col2_text TEXT NOT NULL DEFAULT "",
            col3_text TEXT NOT NULL DEFAULT "",
            top_image_path TEXT NOT NULL DEFAULT "",
            font_size INTEGER NOT NULL DEFAULT 24,
            font_weight INTEGER NOT NULL DEFAULT 500,
            font_family TEXT NOT NULL DEFAULT "Arial, sans-serif",
            color TEXT NOT NULL DEFAULT "#ffffff",
            italic INTEGER NOT NULL DEFAULT 0,
            underline INTEGER NOT NULL DEFAULT 0,
            line_height REAL NOT NULL DEFAULT 1.5
        )'
    );

    $adminCount = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    if ($adminCount === 0) {
        $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (:u, :p)');
        $stmt->execute([
            ':u' => 'admin',
            ':p' => password_hash('admin123456', PASSWORD_DEFAULT),
        ]);
    }

    $introCount = (int) $pdo->query('SELECT COUNT(*) FROM intro_content')->fetchColumn();
    if ($introCount === 0) {
        $pdo->exec("INSERT INTO intro_content (id, col1_title, col2_text, col3_text, top_image_path, line_height) VALUES (1, 'Introduction', '在这里编辑简介内容。', 'Date: 2026', '/uploads/intro/sample-intro.png', 1.5)");
    }

    $defaults = [
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
    ];
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO settings (key_name, value_text) VALUES (:k, :v)');
    foreach ($defaults as $k => $v) {
        $stmt->execute([':k' => $k, ':v' => $v]);
    }

    $catCount = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    if ($catCount === 0) {
        $stmtCat = $pdo->prepare('INSERT INTO categories (name, sort_order) VALUES (:name, :sort_order)');
        $stmtCat->execute([':name' => '未分类', ':sort_order' => 100]);
    }

    $worksCount = (int) $pdo->query('SELECT COUNT(*) FROM works')->fetchColumn();
    if ($worksCount === 0) {
        $now = date('c');
        $stmt = $pdo->prepare(
            'INSERT INTO works (
                title, description, meta_text, created_time, cover_path, emphasized, title_font_size, title_font_weight, title_font_family,
                title_color, title_italic, title_underline, card_bg, sort_order, created_at, updated_at
            ) VALUES (
                :title, :description, :meta_text, :created_time, :cover_path, :emphasized, 22, 600, "Arial, sans-serif",
                "#ffffff", 0, 0, "black", 100, :created_at, :updated_at
            )'
        );
        $stmt->execute([
            ':title' => '示例作品',
            ':description' => "这里是作品简介（第二列）。\n你可以在后台自由编辑。",
            ':meta_text' => "这里是补充信息。\n支持多行文本。",
            ':created_time' => 'Date: 2026',
            ':cover_path' => '/uploads/covers/sample-cover.png',
            ':emphasized' => 1,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $workId = (int) $pdo->lastInsertId();
        $stmtMedia = $pdo->prepare(
            'INSERT INTO work_media (work_id, media_path, media_type, position, created_at) VALUES (:work_id, :media_path, :media_type, :position, :created_at)'
        );
        $stmtMedia->execute([
            ':work_id' => $workId,
            ':media_path' => '/uploads/media/sample-detail.png',
            ':media_type' => 'image',
            ':position' => 0,
            ':created_at' => $now,
        ]);
    }
}

function migrateDatabase(PDO $pdo): void
{
    $columns = [];
    foreach ($pdo->query('PRAGMA table_info(works)')->fetchAll() as $col) {
        $columns[] = $col['name'];
    }
    if (!in_array('modal_font_size', $columns, true)) {
        $pdo->exec('ALTER TABLE works ADD COLUMN modal_font_size INTEGER NOT NULL DEFAULT 28');
    }
    if (!in_array('category_id', $columns, true)) {
        $pdo->exec('ALTER TABLE works ADD COLUMN category_id INTEGER NOT NULL DEFAULT 1');
    }
    if (!in_array('card_bg', $columns, true)) {
        $pdo->exec('ALTER TABLE works ADD COLUMN card_bg TEXT NOT NULL DEFAULT "black"');
    }

    $introCols = [];
    foreach ($pdo->query('PRAGMA table_info(intro_content)')->fetchAll() as $col) {
        $introCols[] = $col['name'];
    }
    if (!in_array('line_height', $introCols, true)) {
        $pdo->exec('ALTER TABLE intro_content ADD COLUMN line_height REAL NOT NULL DEFAULT 1.5');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            sort_order INTEGER NOT NULL DEFAULT 0
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS banner_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            media_path TEXT NOT NULL,
            media_type TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            section TEXT NOT NULL,
            action TEXT NOT NULL,
            detail TEXT NOT NULL,
            created_at TEXT NOT NULL
        )'
    );
    $count = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare('INSERT INTO categories (name, sort_order) VALUES (:name, :sort_order)');
        $stmt->execute([':name' => '未分类', ':sort_order' => 100]);
    }
    $firstCat = $pdo->query('SELECT id, name FROM categories ORDER BY id ASC LIMIT 1')->fetch();
    if ($firstCat && $firstCat['name'] === 'all') {
        $u = $pdo->prepare('UPDATE categories SET name = :n WHERE id = :id');
        $u->execute([':n' => '未分类', ':id' => (int) $firstCat['id']]);
    }

    $settingDefaults = [
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
    ];
    $stmtSetting = $pdo->prepare('INSERT OR IGNORE INTO settings (key_name, value_text) VALUES (:k, :v)');
    foreach ($settingDefaults as $k => $v) {
        $stmtSetting->execute([':k' => $k, ':v' => $v]);
    }
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

function setting(string $key, string $fallback = ''): string
{
    $stmt = db()->prepare('SELECT value_text FROM settings WHERE key_name = :k');
    $stmt->execute([':k' => $key]);
    $value = $stmt->fetchColumn();
    return $value !== false ? (string) $value : $fallback;
}

function updateSetting(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO settings (key_name, value_text) VALUES (:k, :v) ON CONFLICT(key_name) DO UPDATE SET value_text = excluded.value_text');
    $stmt->execute([':k' => $key, ':v' => $value]);
}

function isAdmin(): bool
{
    return isset($_SESSION['admin_id']);
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
    if ($num < 100) {
        $num = 100;
    }
    if ($num > 900) {
        $num = 900;
    }
    return (int) (round($num / 100) * 100);
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
    $parts[] = 'font-size:' . (int) ($item['title_font_size'] ?? 16) . 'px';
    $parts[] = 'font-weight:' . (int) ($item['title_font_weight'] ?? 600);
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

function fetchCategories(): array
{
    return db()->query('SELECT * FROM categories ORDER BY sort_order DESC, id DESC')->fetchAll();
}

function fetchBannerItems(): array
{
    return db()->query('SELECT * FROM banner_items ORDER BY sort_order DESC, id DESC')->fetchAll();
}

function defaultCategoryId(): int
{
    $row = db()->query('SELECT id FROM categories ORDER BY sort_order DESC, id DESC LIMIT 1')->fetch();
    return $row ? (int) $row['id'] : 1;
}

function addAuditLog(string $section, string $action, string $detail): void
{
    $stmt = db()->prepare('INSERT INTO audit_logs (section, action, detail, created_at) VALUES (:section, :action, :detail, :created_at)');
    $stmt->execute([
        ':section' => $section,
        ':action' => $action,
        ':detail' => $detail,
        ':created_at' => date('c'),
    ]);
}

function fetchAuditLogs(int $limit = 120): array
{
    $stmt = db()->prepare('SELECT * FROM audit_logs ORDER BY id DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function fetchWorksWithMedia(?int $categoryId = null): array
{
    if ($categoryId !== null && $categoryId > 0) {
        $stmt = db()->prepare('SELECT * FROM works WHERE category_id = :cid ORDER BY sort_order DESC, id DESC');
        $stmt->execute([':cid' => $categoryId]);
        $works = $stmt->fetchAll();
    } else {
        $works = db()->query('SELECT * FROM works ORDER BY sort_order DESC, id DESC')->fetchAll();
    }
    if (!$works) {
        return [];
    }
    $ids = array_column($works, 'id');
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT * FROM work_media WHERE work_id IN ($marks) ORDER BY position ASC, id ASC");
    $stmt->execute($ids);
    $allMedia = $stmt->fetchAll();
    $mediaMap = [];
    foreach ($allMedia as $m) {
        $wid = (int) $m['work_id'];
        if (!isset($mediaMap[$wid])) {
            $mediaMap[$wid] = [];
        }
        $mediaMap[$wid][] = $m;
    }
    foreach ($works as &$w) {
        $w['media'] = $mediaMap[(int) $w['id']] ?? [];
    }
    return $works;
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
db();
