<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
requireAdmin();

function findItemIndexById(array $items, int $id): ?int
{
    foreach ($items as $idx => $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((int) ($item['id'] ?? 0) === $id) {
            return $idx;
        }
    }
    return null;
}

function nextMediaIdFromWorks(array $works): int
{
    $max = 0;
    foreach ($works as $work) {
        if (!is_array($work)) {
            continue;
        }
        $mediaList = $work['media'] ?? [];
        if (!is_array($mediaList)) {
            continue;
        }
        foreach ($mediaList as $media) {
            if (!is_array($media)) {
                continue;
            }
            $id = (int) ($media['id'] ?? 0);
            if ($id > $max) {
                $max = $id;
            }
        }
    }
    return $max + 1;
}

if (isPostTooLarge()) {
    $limit = (string) ini_get('post_max_size');
    flash('上传失败：提交内容超过服务器限制（当前 post_max_size=' . $limit . '）。请压缩文件或提高上传限制。', 'global', 'error');
    redirectAdmin('global');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $flashTarget = postFlashTarget('global');

    if ($action === 'create_category') {
        $name = trim((string) ($_POST['category_name'] ?? ''));
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        if ($name !== '') {
            $content = loadSiteContent();
            $categories = is_array($content['categories'] ?? null) ? $content['categories'] : [];
            $duplicated = false;
            foreach ($categories as $cat) {
                if ((string) ($cat['name'] ?? '') === $name) {
                    $duplicated = true;
                    break;
                }
            }
            if ($duplicated) {
                flash('分类名重复或无效', $flashTarget, 'error');
            } else {
                $categories[] = [
                    'id' => nextItemId($categories),
                    'name' => $name,
                    'sort_order' => $sortOrder,
                ];
                $content['categories'] = array_values($categories);
                saveSiteContent($content);
                flash('分类已创建', $flashTarget);
                addAuditLog('分类', '创建', '分类：' . $name);
            }
        }
        redirectAdmin($flashTarget);
    }

    if ($action === 'update_category') {
        $id = (int) ($_POST['category_id'] ?? 0);
        $name = trim((string) ($_POST['category_name'] ?? ''));
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        if ($id > 0 && $name !== '') {
            $content = loadSiteContent();
            $categories = is_array($content['categories'] ?? null) ? $content['categories'] : [];
            $index = findItemIndexById($categories, $id);
            if ($index === null) {
                flash('分类更新失败', $flashTarget, 'error');
            } else {
                $duplicated = false;
                foreach ($categories as $cat) {
                    if ((int) ($cat['id'] ?? 0) !== $id && (string) ($cat['name'] ?? '') === $name) {
                        $duplicated = true;
                        break;
                    }
                }
                if ($duplicated) {
                    flash('分类名重复或无效', $flashTarget, 'error');
                } else {
                    $categories[$index]['name'] = $name;
                    $categories[$index]['sort_order'] = $sortOrder;
                    $content['categories'] = array_values($categories);
                    saveSiteContent($content);
                    flash('分类已更新', $flashTarget);
                    addAuditLog('分类', '更新', '分类ID ' . $id . ' -> ' . $name);
                }
            }
        }
        redirectAdmin($flashTarget);
    }

    if ($action === 'delete_category') {
        $id = (int) ($_POST['category_id'] ?? 0);
        if ($id > 0) {
            $content = loadSiteContent();
            $categories = is_array($content['categories'] ?? null) ? $content['categories'] : [];
            if (count($categories) <= 1) {
                flash('至少保留一个分类', $flashTarget, 'error');
                redirectAdmin($flashTarget);
            }
            $works = is_array($content['works'] ?? null) ? $content['works'] : [];
            $used = false;
            foreach ($works as $work) {
                if ((int) ($work['category_id'] ?? 0) === $id) {
                    $used = true;
                    break;
                }
            }
            if ($used) {
                flash('该分类下有作品，无法删除', $flashTarget, 'error');
            } else {
                $categories = array_values(array_filter($categories, static fn($cat): bool => is_array($cat) && (int) ($cat['id'] ?? 0) !== $id));
                $content['categories'] = $categories;
                saveSiteContent($content);
                flash('分类已删除', $flashTarget);
                addAuditLog('分类', '删除', '分类ID ' . $id);
            }
        }
        redirectAdmin($flashTarget);
    }

    if ($action === 'create_work') {
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            flash('作品名不能为空', $flashTarget, 'error');
            redirectAdmin($flashTarget);
        }

        $description = trim((string) ($_POST['description'] ?? ''));
        $metaText = trim((string) ($_POST['meta_text'] ?? ''));
        $createdTime = trim((string) ($_POST['created_time'] ?? ''));
        $emphasized = isset($_POST['emphasized']) ? 1 : 0;
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        $categoryId = (int) ($_POST['category_id'] ?? defaultCategoryId());
        if ($categoryId <= 0) {
            $categoryId = defaultCategoryId();
        }
        $fontSize = max(10, min(80, (int) ($_POST['title_font_size'] ?? 16)));
        $modalFontSize = max(10, min(80, (int) ($_POST['modal_font_size'] ?? 28)));
        $fontWeight = normalizeFontWeight((string) ($_POST['title_font_weight'] ?? '600'));
        $fontFamily = trim((string) ($_POST['title_font_family'] ?? 'Arial, sans-serif'));
        $titleColor = normalizeColor((string) ($_POST['title_color'] ?? '#ffffff'));
        $titleItalic = isset($_POST['title_italic']) ? 1 : 0;
        $titleUnderline = isset($_POST['title_underline']) ? 1 : 0;
        $cardBg = normalizeCardBg((string) ($_POST['card_bg'] ?? 'black'));

        $coverPath = '';
        $coverPreprocessed = (int) ($_POST['cover_preprocessed'] ?? 0) === 1;
        if (isset($_FILES['cover_file'])) {
            $saved = saveUpload($_FILES['cover_file'], 'covers', ALLOWED_MEDIA_EXT);
            if ($saved !== null) {
                $coverPath = finalizeUploadedPath($coverPreprocessed ? $saved : applyCoverTransform($saved, $_POST));
            }
        }

        $content = loadSiteContent();
        $works = is_array($content['works'] ?? null) ? $content['works'] : [];
        $workId = nextItemId($works);
        $now = date('c');
        $work = [
            'id' => $workId,
            'title' => $title,
            'description' => $description,
            'meta_text' => $metaText,
            'created_time' => $createdTime,
            'cover_path' => $coverPath,
            'emphasized' => $emphasized,
            'title_font_size' => $fontSize,
            'title_font_weight' => $fontWeight,
            'title_font_family' => $fontFamily === '' ? 'Arial, sans-serif' : $fontFamily,
            'title_color' => $titleColor,
            'title_italic' => $titleItalic,
            'title_underline' => $titleUnderline,
            'modal_font_size' => $modalFontSize,
            'category_id' => $categoryId,
            'card_bg' => $cardBg,
            'sort_order' => $sortOrder,
            'created_at' => $now,
            'updated_at' => $now,
            'media' => [],
        ];

        if (isset($_FILES['media_files']) && is_array($_FILES['media_files']['name'] ?? null)) {
            $count = count($_FILES['media_files']['name']);
            $pos = 0;
            $nextMediaId = nextMediaIdFromWorks($works);
            for ($i = 0; $i < $count; $i++) {
                $file = [
                    'name' => $_FILES['media_files']['name'][$i] ?? '',
                    'type' => $_FILES['media_files']['type'][$i] ?? '',
                    'tmp_name' => $_FILES['media_files']['tmp_name'][$i] ?? '',
                    'error' => $_FILES['media_files']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $_FILES['media_files']['size'][$i] ?? 0,
                ];
                $saved = saveUpload($file, 'media', ALLOWED_MEDIA_EXT);
                if ($saved === null) {
                    continue;
                }
                $work['media'][] = [
                    'id' => $nextMediaId++,
                    'work_id' => $workId,
                    'media_path' => finalizeUploadedPath($saved),
                    'media_type' => mediaTypeFromPath($saved),
                    'position' => $pos++,
                    'created_at' => $now,
                ];
            }
        }
        $works[] = $work;
        $content['works'] = array_values($works);
        saveSiteContent($content);

        flash('作品已创建并同步到首页。cover_path=' . ($coverPath !== '' ? $coverPath : '(empty)'), $flashTarget);
        addAuditLog('作品', '创建', '作品：' . $title);
        redirectAdmin($flashTarget);
    }

    if ($action === 'update_work') {
        $workId = (int) ($_POST['work_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($workId <= 0 || $title === '') {
            flash('更新失败：作品ID或标题为空', $flashTarget, 'error');
            redirectAdmin($flashTarget);
        }
        $description = trim((string) ($_POST['description'] ?? ''));
        $metaText = trim((string) ($_POST['meta_text'] ?? ''));
        $createdTime = trim((string) ($_POST['created_time'] ?? ''));
        $emphasized = isset($_POST['emphasized']) ? 1 : 0;
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        $categoryId = (int) ($_POST['category_id'] ?? defaultCategoryId());
        if ($categoryId <= 0) {
            $categoryId = defaultCategoryId();
        }
        $fontSize = max(10, min(80, (int) ($_POST['title_font_size'] ?? 16)));
        $modalFontSize = max(10, min(80, (int) ($_POST['modal_font_size'] ?? 28)));
        $fontWeight = normalizeFontWeight((string) ($_POST['title_font_weight'] ?? '600'));
        $fontFamily = trim((string) ($_POST['title_font_family'] ?? 'Arial, sans-serif'));
        $titleColor = normalizeColor((string) ($_POST['title_color'] ?? '#ffffff'));
        $titleItalic = isset($_POST['title_italic']) ? 1 : 0;
        $titleUnderline = isset($_POST['title_underline']) ? 1 : 0;
        $cardBg = normalizeCardBg((string) ($_POST['card_bg'] ?? 'black'));

        $content = loadSiteContent();
        $works = is_array($content['works'] ?? null) ? $content['works'] : [];
        $workIndex = findItemIndexById($works, $workId);
        if ($workIndex === null) {
            flash('更新失败：作品不存在', $flashTarget, 'error');
            redirectAdmin($flashTarget);
        }
        $row = $works[$workIndex];

        $coverPath = $row['cover_path'];
        $hasCropIntent = (int) ($_POST['cover_crop_apply'] ?? 0) === 1 || isset($_POST['cover_apply_submit']);
        $coverPreprocessed = (int) ($_POST['cover_preprocessed'] ?? 0) === 1;
        $hasUploadedCover = isset($_FILES['cover_file']) && (int) ($_FILES['cover_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($hasUploadedCover) {
            $saved = saveUpload($_FILES['cover_file'], 'covers', ALLOWED_MEDIA_EXT);
            if ($saved !== null) {
                $coverPath = finalizeUploadedPath($coverPreprocessed ? $saved : applyCoverTransform($saved, $_POST));
            } else {
                $err = (int) ($_FILES['cover_file']['error'] ?? UPLOAD_ERR_NO_FILE);
                flash('封面上传失败，错误码：' . $err, $flashTarget, 'error');
                redirectAdmin($flashTarget);
            }
        } elseif ($hasCropIntent) {
            $cropSource = (string) $coverPath;
            if ($cropSource === '' || mediaTypeFromPath($cropSource) === 'video') {
                $cropSource = '';
                $mediaList = is_array($row['media'] ?? null) ? $row['media'] : [];
                usort($mediaList, static function (array $a, array $b): int {
                    $posA = (int) ($a['position'] ?? 0);
                    $posB = (int) ($b['position'] ?? 0);
                    if ($posA !== $posB) {
                        return $posA <=> $posB;
                    }
                    return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
                });
                foreach ($mediaList as $media) {
                    if ((string) ($media['media_type'] ?? '') === 'image') {
                        $cropSource = (string) ($media['media_path'] ?? '');
                        break;
                    }
                }
            }
            if ($cropSource === '') {
                flash('当前作品没有可裁剪的图片资源。请先上传图片封面或至少一张图片详情媒体。', $flashTarget, 'error');
                redirectAdmin($flashTarget);
            }
            // Always clone to a new cover path so crop result is persisted and cache-safe.
            $cloned = cloneUploadPath($cropSource, 'covers');
            if ($cloned !== null) {
                $coverPath = finalizeUploadedPath(applyCoverTransform($cloned, $_POST));
            } else {
                $coverPath = finalizeUploadedPath(applyCoverTransform($cropSource, $_POST));
            }
        }

        $works[$workIndex]['title'] = $title;
        $works[$workIndex]['description'] = $description;
        $works[$workIndex]['meta_text'] = $metaText;
        $works[$workIndex]['created_time'] = $createdTime;
        $works[$workIndex]['cover_path'] = $coverPath;
        $works[$workIndex]['emphasized'] = $emphasized;
        $works[$workIndex]['title_font_size'] = $fontSize;
        $works[$workIndex]['title_font_weight'] = $fontWeight;
        $works[$workIndex]['title_font_family'] = $fontFamily === '' ? 'Arial, sans-serif' : $fontFamily;
        $works[$workIndex]['title_color'] = $titleColor;
        $works[$workIndex]['title_italic'] = $titleItalic;
        $works[$workIndex]['title_underline'] = $titleUnderline;
        $works[$workIndex]['modal_font_size'] = $modalFontSize;
        $works[$workIndex]['category_id'] = $categoryId;
        $works[$workIndex]['card_bg'] = $cardBg;
        $works[$workIndex]['sort_order'] = $sortOrder;
        $works[$workIndex]['updated_at'] = date('c');

        if (isset($_FILES['media_files']) && is_array($_FILES['media_files']['name'] ?? null)) {
            $count = count($_FILES['media_files']['name']);
            $mediaList = is_array($works[$workIndex]['media'] ?? null) ? $works[$workIndex]['media'] : [];
            $maxPos = -1;
            foreach ($mediaList as $media) {
                $maxPos = max($maxPos, (int) ($media['position'] ?? -1));
            }
            $start = $maxPos + 1;
            $nextMediaId = nextMediaIdFromWorks($works);
            for ($i = 0; $i < $count; $i++) {
                $file = [
                    'name' => $_FILES['media_files']['name'][$i] ?? '',
                    'type' => $_FILES['media_files']['type'][$i] ?? '',
                    'tmp_name' => $_FILES['media_files']['tmp_name'][$i] ?? '',
                    'error' => $_FILES['media_files']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $_FILES['media_files']['size'][$i] ?? 0,
                ];
                $saved = saveUpload($file, 'media', ALLOWED_MEDIA_EXT);
                if ($saved === null) {
                    continue;
                }
                $mediaList[] = [
                    'id' => $nextMediaId++,
                    'work_id' => $workId,
                    'media_path' => finalizeUploadedPath($saved),
                    'media_type' => mediaTypeFromPath($saved),
                    'position' => $start++,
                    'created_at' => date('c'),
                ];
            }
            $works[$workIndex]['media'] = array_values($mediaList);
        }
        $content['works'] = array_values($works);
        saveSiteContent($content);

        flash('作品已更新。cover_path=' . ($coverPath !== '' ? $coverPath : '(empty)'), $flashTarget);
        addAuditLog('作品', '更新', '作品ID ' . $workId . '：' . $title);
        redirectAdmin($flashTarget);
    }

    if ($action === 'delete_work') {
        $workId = (int) ($_POST['work_id'] ?? 0);
        if ($workId > 0) {
            $content = loadSiteContent();
            $works = is_array($content['works'] ?? null) ? $content['works'] : [];
            $works = array_values(array_filter($works, static fn($work): bool => is_array($work) && (int) ($work['id'] ?? 0) !== $workId));
            $content['works'] = $works;
            saveSiteContent($content);
            flash('作品已删除', $flashTarget);
            addAuditLog('作品', '删除', '作品ID ' . $workId);
        }
        redirectAdmin($flashTarget);
    }

    if ($action === 'delete_media') {
        $mediaId = (int) ($_POST['media_id'] ?? 0);
        if ($mediaId > 0) {
            $content = loadSiteContent();
            $works = is_array($content['works'] ?? null) ? $content['works'] : [];
            foreach ($works as &$work) {
                if (!is_array($work)) {
                    continue;
                }
                $mediaList = is_array($work['media'] ?? null) ? $work['media'] : [];
                $mediaList = array_values(array_filter($mediaList, static fn($media): bool => is_array($media) && (int) ($media['id'] ?? 0) !== $mediaId));
                $work['media'] = $mediaList;
            }
            unset($work);
            $content['works'] = array_values($works);
            saveSiteContent($content);
            flash('详情媒体已删除', $flashTarget);
            addAuditLog('作品媒体', '删除', '媒体ID ' . $mediaId);
        }
        redirectAdmin($flashTarget);
    }

    if ($action === 'delete_banner_item') {
        $id = (int) ($_POST['banner_item_id'] ?? 0);
        if ($id > 0) {
            $content = loadSiteContent();
            $items = is_array($content['banner_items'] ?? null) ? $content['banner_items'] : [];
            $items = array_values(array_filter($items, static fn($item): bool => is_array($item) && (int) ($item['id'] ?? 0) !== $id));
            $content['banner_items'] = $items;
            saveSiteContent($content);
            flash('Banner媒体已删除', $flashTarget);
            addAuditLog('Banner媒体', '删除', 'Banner媒体ID ' . $id);
        }
        redirectAdmin($flashTarget);
    }

    if ($action === 'add_banner_items') {
        if (isset($_FILES['banner_items']) && is_array($_FILES['banner_items']['name'] ?? null)) {
            $content = loadSiteContent();
            $items = is_array($content['banner_items'] ?? null) ? $content['banner_items'] : [];
            $count = count($_FILES['banner_items']['name']);
            $existingCount = count($items);
            $pos = $existingCount + 1;
            $nextId = nextItemId($items);
            for ($i = 0; $i < $count; $i++) {
                $file = [
                    'name' => $_FILES['banner_items']['name'][$i] ?? '',
                    'type' => $_FILES['banner_items']['type'][$i] ?? '',
                    'tmp_name' => $_FILES['banner_items']['tmp_name'][$i] ?? '',
                    'error' => $_FILES['banner_items']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $_FILES['banner_items']['size'][$i] ?? 0,
                ];
                $saved = saveUpload($file, 'banner', ALLOWED_MEDIA_EXT);
                if ($saved === null) {
                    continue;
                }
                $items[] = [
                    'id' => $nextId++,
                    'media_path' => finalizeUploadedPath($saved),
                    'media_type' => mediaTypeFromPath($saved),
                    'sort_order' => $pos++,
                    'created_at' => date('c'),
                ];
            }
            $content['banner_items'] = array_values($items);
            saveSiteContent($content);
            flash('漂浮媒体已新增', $flashTarget);
            addAuditLog('Banner媒体', '新增', '新增了漂浮媒体');
        }
        redirectAdmin($flashTarget);
    }

    if ($action === 'upload_music') {
        $musicCurrent = setting('music_file', '');
        if (isset($_FILES['music_file'])) {
            $saved = saveUpload($_FILES['music_file'], 'banner', ALLOWED_AUDIO_EXT);
            if ($saved !== null) {
                $musicCurrent = finalizeUploadedPath($saved);
                updateSetting('music_file', $musicCurrent);
                flash('音乐上传成功', $flashTarget);
                addAuditLog('顶部设置', '音乐上传', '音乐文件：' . $saved);
            } else {
                $err = (int) ($_FILES['music_file']['error'] ?? UPLOAD_ERR_NO_FILE);
                flash('音乐上传失败，错误码：' . $err . '（请使用mp3并检查文件大小）', $flashTarget, 'error');
            }
        }
        redirectAdmin($flashTarget);
    }

    if ($action === 'update_banner_item') {
        $id = (int) ($_POST['banner_item_id'] ?? 0);
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        if ($id > 0) {
            $content = loadSiteContent();
            $items = is_array($content['banner_items'] ?? null) ? $content['banner_items'] : [];
            $index = findItemIndexById($items, $id);
            if ($index !== null) {
                $row = $items[$index];
                $mediaPath = $row['media_path'];
                $mediaType = $row['media_type'];
                $replaceAttempted = isset($_FILES['replace_file']) && (int) ($_FILES['replace_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
                if (isset($_FILES['replace_file'])) {
                    $saved = saveUpload($_FILES['replace_file'], 'banner', ALLOWED_MEDIA_EXT);
                    if ($saved !== null) {
                        $mediaPath = finalizeUploadedPath($saved);
                        $mediaType = mediaTypeFromPath($saved);
                    } elseif ($replaceAttempted) {
                        $err = (int) ($_FILES['replace_file']['error'] ?? UPLOAD_ERR_NO_FILE);
                        flash('漂浮媒体替换失败，错误码：' . $err, $flashTarget, 'error');
                        redirectAdmin($flashTarget);
                    }
                }
                $items[$index]['media_path'] = $mediaPath;
                $items[$index]['media_type'] = $mediaType;
                $items[$index]['sort_order'] = $sortOrder;
                $content['banner_items'] = array_values($items);
                saveSiteContent($content);
                flash('Banner媒体已更新', $flashTarget);
                addAuditLog('Banner媒体', '更新', 'Banner媒体ID ' . $id . ' 已更新');
            }
        }
        redirectAdmin($flashTarget);
    }

    if ($action === 'update_intro') {
        $col1 = trim((string) ($_POST['col1_title'] ?? ''));
        $col2 = trim((string) ($_POST['col2_text'] ?? ''));
        $col3 = trim((string) ($_POST['col3_text'] ?? ''));
        $fontSize = max(10, min(80, (int) ($_POST['font_size'] ?? 24)));
        $fontWeight = normalizeFontWeight((string) ($_POST['font_weight'] ?? '500'));
        $fontFamily = trim((string) ($_POST['font_family'] ?? 'Arial, sans-serif'));
        $color = normalizeColor((string) ($_POST['color'] ?? '#ffffff'));
        $italic = isset($_POST['italic']) ? 1 : 0;
        $underline = isset($_POST['underline']) ? 1 : 0;
        $lineHeight = (float) ($_POST['line_height'] ?? 1.5);
        if ($lineHeight <= 0) {
            $lineHeight = 1.5;
        }

        $content = loadSiteContent();
        $current = is_array($content['intro_content'] ?? null) ? $content['intro_content'] : [];
        $imagePath = $current['top_image_path'] ?? '';
        if (isset($_FILES['intro_top_image'])) {
            $saved = saveUpload($_FILES['intro_top_image'], 'intro', ALLOWED_IMAGE_EXT);
            if ($saved !== null) {
                $imagePath = finalizeUploadedPath($saved);
            }
        }

        $content['intro_content'] = [
            'id' => 1,
            'col1_title' => $col1,
            'col2_text' => $col2,
            'col3_text' => $col3,
            'top_image_path' => $imagePath,
            'font_size' => $fontSize,
            'font_weight' => $fontWeight,
            'font_family' => $fontFamily === '' ? 'Arial, sans-serif' : $fontFamily,
            'color' => $color,
            'italic' => $italic,
            'underline' => $underline,
            'line_height' => $lineHeight,
        ];
        saveSiteContent($content);
        flash('Introduction 已更新', $flashTarget);
        addAuditLog('Introduction', '更新', '三列内容与样式已保存');
        redirectAdmin($flashTarget);
    }

    if ($action === 'update_header_banner') {
        updateSetting('menu_works', trim((string) ($_POST['menu_works'] ?? 'Works')));
        updateSetting('menu_intro', trim((string) ($_POST['menu_intro'] ?? 'Introduction')));
        updateSetting('banner_ratio', (string) max(1.0, min(12.0, (float) ($_POST['banner_ratio'] ?? 4.5))));

        $current = setting('banner_overlay', '');
        if (isset($_FILES['banner_overlay'])) {
            $saved = saveUpload($_FILES['banner_overlay'], 'banner', ['png']);
            if ($saved !== null) {
                $current = finalizeUploadedPath($saved);
            }
        }
        updateSetting('banner_overlay', $current);
        $bgCurrent = setting('banner_bg', '');
        if (isset($_FILES['banner_bg'])) {
            $saved = saveUpload($_FILES['banner_bg'], 'banner', ALLOWED_MEDIA_EXT);
            if ($saved !== null) {
                $bgCurrent = finalizeUploadedPath($saved);
            }
        }
        updateSetting('banner_bg', $bgCurrent);

        $logoCurrent = setting('logo_image', '');
        if (isset($_FILES['logo_image'])) {
            $saved = saveUpload($_FILES['logo_image'], 'banner', ALLOWED_IMAGE_EXT);
            if ($saved !== null) {
                $logoCurrent = finalizeUploadedPath($saved);
            }
        }
        updateSetting('logo_image', $logoCurrent);
        updateSetting('cat_active_bg', normalizeColor((string) ($_POST['cat_active_bg'] ?? '#000000')));
        updateSetting('cat_border_color', normalizeColor((string) ($_POST['cat_border_color'] ?? '#111111')));
        flash('菜单与Banner设置已更新', $flashTarget);
        addAuditLog('顶部设置', '更新', '菜单、Logo、Banner与分类样式已保存');
        redirectAdmin($flashTarget);
    }

    if ($action === 'change_admin_password') {
        $username = trim((string) ($_POST['new_username'] ?? ''));
        $password = (string) ($_POST['new_password'] ?? '');
        if ($username !== '' && $password !== '') {
            $ok = updateAdminCredentialsJson((int) ($_SESSION['admin_id'] ?? 0), $username, password_hash($password, PASSWORD_DEFAULT));
            if ($ok) {
                $_SESSION['admin_username'] = $username;
                flash('管理员账号密码已更新', $flashTarget);
                addAuditLog('管理员', '更新', '管理员凭证已修改');
            } else {
                flash('管理员用户名不可重复或当前会话无效', $flashTarget, 'error');
            }
        } else {
            flash('管理员用户名和密码都不能为空', $flashTarget, 'error');
        }
        redirectAdmin($flashTarget);
    }
}

$works = fetchWorksWithMedia();
$categories = fetchCategories();
$intro = fetchIntroContent();
$bannerItems = fetchBannerItems();
$logs = fetchAuditLogs(80);
$flashData = flash();

function versionedMediaPath(string $path, string $versionSeed = ''): string
{
    if ($path === '') {
        return '';
    }
    $glue = str_contains($path, '?') ? '&' : '?';
    $seed = $versionSeed !== '' ? $versionSeed : date('U');
    return $path . $glue . 'v=' . rawurlencode($seed);
}

function renderMediaPreview(string $path, string $title, string $versionSeed = ''): string
{
    if ($path === '') {
        return '';
    }
    $src = versionedMediaPath($path, $versionSeed);
    if (mediaTypeFromPath($path) === 'video') {
        return '<video src="' . esc($src) . '" muted playsinline loop autoplay></video>';
    }
    return '<img src="' . esc($src) . '" alt="' . esc($title) . '">';
}

function renderFlashAt(string $target, ?array $flashData): string
{
    return '';
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>后台管理</title>
  <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
  <div class="admin-wrap">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:14px;">
      <h1>作品集管理后台</h1>
      <div style="font-size:12px;color:#bbb;">
        当前管理员：<?= esc((string) ($_SESSION['admin_username'] ?? 'admin')) ?> |
        <a href="/logout.php" style="text-decoration:underline;">退出</a>
      </div>
    </div>

    <?php if ($flashData && (($flashData['message'] ?? '') !== '')): ?>
      <div class="admin-feedback-layer open" data-admin-feedback>
        <div class="admin-feedback-modal <?= (($flashData['type'] ?? 'success') === 'error') ? 'is-error' : '' ?>">
          <div class="admin-feedback-title"><?= (($flashData['type'] ?? 'success') === 'error') ? '操作失败' : '操作成功' ?></div>
          <div class="admin-feedback-text"><?= esc((string) $flashData['message']) ?></div>
          <button type="button" data-admin-feedback-close>知道了</button>
        </div>
      </div>
    <?php endif; ?>

    <?= renderFlashAt('global', $flashData) ?>

    <div class="admin-grid">
      <section class="admin-card" id="category-section" data-flash-target="category-section">
        <h2>分类管理（首页分类自动同步）</h2>
        <?= renderFlashAt('category-section', $flashData) ?>
        <form method="post" class="admin-form-grid" style="margin-bottom:10px;">
          <input type="hidden" name="action" value="create_category">
          <input type="hidden" name="flash_target" value="category-section">
          <div>
            <label>新分类名</label>
            <input type="text" name="category_name" required>
          </div>
          <div>
            <label>排序值</label>
            <input type="number" name="sort_order" value="0">
          </div>
          <div style="display:flex;align-items:flex-end;">
            <button type="submit">创建分类</button>
          </div>
        </form>
        <div class="work-list">
          <?php foreach ($categories as $cat): ?>
            <article class="work-list-item">
              <form method="post" class="admin-form-grid">
                <input type="hidden" name="action" value="update_category">
                <input type="hidden" name="flash_target" value="category-section">
                <input type="hidden" name="category_id" value="<?= (int) $cat['id'] ?>">
                <div>
                  <label>分类名</label>
                  <input type="text" name="category_name" value="<?= esc((string) $cat['name']) ?>" required>
                </div>
                <div>
                  <label>排序值</label>
                  <input type="number" name="sort_order" value="<?= (int) $cat['sort_order'] ?>">
                </div>
                <div style="display:flex;gap:8px;align-items:flex-end;">
                  <button type="submit">保存分类</button>
                </div>
              </form>
              <form method="post" onsubmit="return confirm('确认删除分类？');" style="margin-top:8px;">
                <input type="hidden" name="action" value="delete_category">
                <input type="hidden" name="flash_target" value="category-section">
                <input type="hidden" name="category_id" value="<?= (int) $cat['id'] ?>">
                <button type="submit" class="btn-danger">删除</button>
              </form>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="admin-card" id="work-create-section" data-flash-target="work-create-section">
        <h2>1. 作品上传区（创建新作品）</h2>
        <?= renderFlashAt('work-create-section', $flashData) ?>
        <form method="post" enctype="multipart/form-data" class="admin-form-grid">
          <input type="hidden" name="action" value="create_work">
          <input type="hidden" name="flash_target" value="work-create-section">
          <div>
            <label>作品名（必填）</label>
            <input type="text" name="title" required>
          </div>
          <div>
            <label>排序值（大者优先）</label>
            <input type="number" name="sort_order" value="0">
          </div>
          <div class="full">
            <label>简介（第二列）</label>
            <textarea name="description"></textarea>
          </div>
          <div class="full">
            <label>创作时间（第三列，段落输入）</label>
            <textarea name="created_time" placeholder="例如：Date: 2025&#10;April - June"></textarea>
          </div>
          <div class="full">
            <label>补充信息（第三列）</label>
            <textarea name="meta_text" placeholder="例如：Materials: ..."></textarea>
          </div>
          <div>
            <label>首页标题字号(px)</label>
            <input type="number" name="title_font_size" value="16" min="10" max="80">
          </div>
          <div>
            <label>弹窗三列字号(px)</label>
            <input type="number" name="modal_font_size" value="28" min="10" max="80">
          </div>
          <div>
            <label>首页标题粗细(0-900)</label>
            <input type="number" name="title_font_weight" value="600" min="0" max="900" step="1">
          </div>
          <div>
            <label>首页标题字体</label>
            <input type="text" name="title_font_family" value="Arial, sans-serif">
          </div>
          <div>
            <label>首页标题颜色</label>
            <input type="color" name="title_color" value="#ffffff">
          </div>
          <div style="display:flex;gap:10px;align-items:center;">
            <label style="margin:0;"><input type="checkbox" name="title_italic"> 斜体</label>
            <label style="margin:0;"><input type="checkbox" name="title_underline"> 下划线</label>
            <label style="margin:0;"><input type="checkbox" name="emphasized"> 强调（优先进入大正方形）</label>
          </div>
          <div>
            <label>首页卡片底色</label>
            <select name="card_bg">
              <option value="black">纯黑底</option>
              <option value="white">纯白底</option>
            </select>
          </div>
          <div class="full">
            <label>作品封面图（支持上传后缩放裁剪 + 可选纯色边框）</label>
            <input type="file" name="cover_file" accept=".mp4,.gif,.png,.jpg,.jpeg,.webm">
            <button type="button" class="btn-ghost" data-cover-open style="margin-top:8px;">打开封面裁剪弹窗</button>
            <div class="cover-tools" data-cover-tools data-current-cover="">
              <div class="cover-tools-mask" data-cover-close></div>
              <div class="cover-tools-dialog">
                <div class="cover-tools-head">
                  <strong>封面裁剪设置</strong>
                  <div style="display:flex;gap:8px;">
                    <button type="submit" name="cover_apply_submit" value="1" class="btn-ghost" data-cover-apply>确认并同步保存</button>
                    <button type="button" class="btn-ghost" data-cover-close>关闭</button>
                  </div>
                </div>
                <div class="cover-tools-body">
                  <div class="cover-preview-stage" data-cover-stage>
                    <img alt="cover preview" data-cover-preview>
                    <div class="cover-crop-frame" data-cover-frame></div>
                  </div>
                  <div class="cover-controls">
                    <div style="font-size:12px;color:#a9a9a9;">可直接在左侧图片区域按住拖拽定位，再微调用滑杆。</div>
                    <label>缩放（仅图片）<input type="range" min="1" max="3" step="0.01" value="1" data-cover-zoom></label>
                    <label>X位移（仅图片）<input type="range" min="-100" max="100" step="1" value="0" data-cover-x></label>
                    <label>Y位移（仅图片）<input type="range" min="-100" max="100" step="1" value="0" data-cover-y></label>
                    <label>边框粗细(px)<input type="range" min="0" max="60" step="1" value="0" data-cover-border-width></label>
                    <label>边框颜色<input type="color" value="#000000" data-cover-border-color></label>
                  </div>
                </div>
              </div>
              <input type="hidden" name="cover_zoom" value="1" data-cover-zoom-hidden>
              <input type="hidden" name="cover_offset_x" value="0" data-cover-x-hidden>
              <input type="hidden" name="cover_offset_y" value="0" data-cover-y-hidden>
              <input type="hidden" name="cover_border_width" value="0" data-cover-border-width-hidden>
              <input type="hidden" name="cover_border_color" value="#000000" data-cover-border-color-hidden>
              <input type="hidden" name="cover_crop_x" value="0.5" data-cover-crop-x-hidden>
              <input type="hidden" name="cover_crop_y" value="0.5" data-cover-crop-y-hidden>
              <input type="hidden" name="cover_crop_size" value="1" data-cover-crop-size-hidden>
              <input type="hidden" name="cover_crop_x_px" value="0" data-cover-crop-x-px-hidden>
              <input type="hidden" name="cover_crop_y_px" value="0" data-cover-crop-y-px-hidden>
              <input type="hidden" name="cover_crop_size_px" value="0" data-cover-crop-size-px-hidden>
              <input type="hidden" name="cover_crop_apply" value="0" data-cover-apply-hidden>
              <input type="hidden" name="cover_preprocessed" value="0" data-cover-preprocessed-hidden>
            </div>
          </div>
          <div>
            <label>分类（封面图下方）</label>
            <select name="category_id">
              <?php foreach ($categories as $cat): ?>
                <option value="<?= (int) $cat['id'] ?>"><?= esc((string) $cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="full">
            <label>作品详情媒体（多选，按选择顺序展示，统一宽度）</label>
            <input type="file" name="media_files[]" multiple accept=".mp4,.gif,.png,.jpg,.jpeg,.webm">
          </div>
          <div class="full">
            <button type="submit">创建并同步到首页</button>
          </div>
        </form>
      </section>

      <section class="admin-card" id="intro-section" data-flash-target="intro-section">
        <h2>2. Introduction 编辑区（三列）</h2>
        <?= renderFlashAt('intro-section', $flashData) ?>
        <form method="post" enctype="multipart/form-data" class="admin-form-grid">
          <input type="hidden" name="action" value="update_intro">
          <input type="hidden" name="flash_target" value="intro-section">
          <div class="full">
            <label>第一列内容（顶部可显示图片）</label>
            <textarea name="col1_title"><?= esc((string) ($intro['col1_title'] ?? '')) ?></textarea>
          </div>
          <div class="full">
            <label>第二列内容</label>
            <textarea name="col2_text"><?= esc((string) ($intro['col2_text'] ?? '')) ?></textarea>
          </div>
          <div class="full">
            <label>第三列内容</label>
            <textarea name="col3_text"><?= esc((string) ($intro['col3_text'] ?? '')) ?></textarea>
          </div>
          <div class="full">
            <label>第一列顶部图片</label>
            <input type="file" name="intro_top_image" accept=".png,.jpg,.jpeg,.gif">
            <?php if (($intro['top_image_path'] ?? '') !== ''): ?>
              <div class="thumb-inline" style="margin-top:8px;">
                <img src="<?= esc((string) $intro['top_image_path']) ?>" alt="intro thumb">
              </div>
            <?php endif; ?>
          </div>
          <div>
            <label>字号(px)</label>
            <input type="number" name="font_size" value="<?= esc((string) ($intro['font_size'] ?? 24)) ?>" min="10" max="80">
          </div>
          <div>
            <label>行间距</label>
            <input type="number" name="line_height" value="<?= esc((string) ($intro['line_height'] ?? 1.5)) ?>" step="any">
          </div>
          <div>
            <label>粗细(100-900)</label>
            <input type="number" name="font_weight" value="<?= esc((string) ($intro['font_weight'] ?? 500)) ?>" min="100" max="900" step="100">
          </div>
          <div>
            <label>字体</label>
            <input type="text" name="font_family" value="<?= esc((string) ($intro['font_family'] ?? 'Arial, sans-serif')) ?>">
          </div>
          <div>
            <label>颜色</label>
            <input type="color" name="color" value="<?= esc((string) ($intro['color'] ?? '#ffffff')) ?>">
          </div>
          <div style="display:flex;gap:10px;align-items:center;">
            <label style="margin:0;"><input type="checkbox" name="italic" <?= ((int) ($intro['italic'] ?? 0)) === 1 ? 'checked' : '' ?>> 斜体</label>
            <label style="margin:0;"><input type="checkbox" name="underline" <?= ((int) ($intro['underline'] ?? 0)) === 1 ? 'checked' : '' ?>> 下划线</label>
          </div>
          <div class="full">
            <button type="submit">保存 Introduction</button>
          </div>
        </form>
      </section>

      <section class="admin-card" id="banner-section" data-flash-target="banner-section">
        <h2>3. 顶部菜单栏 + 4. Banner 编辑区</h2>
        <?= renderFlashAt('banner-section', $flashData) ?>
        <form method="post" enctype="multipart/form-data" class="admin-form-grid">
          <input type="hidden" name="action" value="update_header_banner">
          <input type="hidden" name="flash_target" value="banner-section">
          <div>
            <label>左上Logo图片</label>
            <input type="file" name="logo_image" accept=".png,.jpg,.jpeg,.gif">
            <?php if (setting('logo_image', '') !== ''): ?>
              <div class="thumb-inline" style="margin-top:8px;">
                <img src="<?= esc(setting('logo_image', '')) ?>" alt="logo thumb">
              </div>
            <?php endif; ?>
          </div>
          <div>
            <label>菜单文案1（Works）</label>
            <input type="text" name="menu_works" value="<?= esc(setting('menu_works', 'Works')) ?>">
          </div>
          <div>
            <label>菜单文案2（Introduction）</label>
            <input type="text" name="menu_intro" value="<?= esc(setting('menu_intro', 'Introduction')) ?>">
          </div>
          <div>
            <label>Banner 容器宽高比（例如 4.5）</label>
            <input type="number" name="banner_ratio" min="1" max="12" step="0.1" value="<?= esc(setting('banner_ratio', '4.5')) ?>">
          </div>
          <div class="full">
            <label>Banner 背景层（mp4/gif/png/jpg）</label>
            <input type="file" name="banner_bg" accept=".mp4,.gif,.png,.jpg,.jpeg,.webm">
            <?php if (setting('banner_bg', '') !== ''): ?>
              <div class="thumb-inline" style="margin-top:8px;">
                <?php if (mediaTypeFromPath(setting('banner_bg', '')) === 'video'): ?>
                  <video src="<?= esc(setting('banner_bg', '')) ?>" muted autoplay loop playsinline></video>
                <?php else: ?>
                  <img src="<?= esc(setting('banner_bg', '')) ?>" alt="banner bg thumb">
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="full">
            <label>Banner 上方 PNG 图层</label>
            <input type="file" name="banner_overlay" accept=".png">
            <?php if (setting('banner_overlay', '') !== ''): ?>
              <div class="thumb-inline" style="margin-top:8px;">
                <img src="<?= esc(setting('banner_overlay', '')) ?>" alt="banner overlay thumb">
              </div>
            <?php endif; ?>
          </div>
          <div>
            <label>分类高亮色</label>
            <input type="color" name="cat_active_bg" value="<?= esc(setting('cat_active_bg', '#000000')) ?>">
          </div>
          <div>
            <label>分类描边色</label>
            <input type="color" name="cat_border_color" value="<?= esc(setting('cat_border_color', '#111111')) ?>">
          </div>
          <div class="full">
            <button type="submit">保存菜单与 Banner</button>
          </div>
        </form>

        <form method="post" enctype="multipart/form-data" class="admin-form-grid" style="margin-top:12px;">
          <input type="hidden" name="action" value="upload_music">
          <input type="hidden" name="flash_target" value="banner-section">
          <div class="full">
            <label>背景音乐（mp3）独立上传</label>
            <input type="file" name="music_file" accept=".mp3,audio/mpeg">
            <button type="submit" style="margin-top:8px;">保存音乐</button>
            <div style="margin-top:6px;font-size:12px;color:#a9c7ff;">当前音乐路径：<?= esc(setting('music_file', '(未设置)')) ?></div>
            <?php if (setting('music_file', '') !== ''): ?>
              <audio controls style="margin-top:8px;width:320px;">
                <source src="<?= esc(setting('music_file', '')) ?>" type="audio/mpeg">
              </audio>
            <?php endif; ?>
          </div>
        </form>

        <form method="post" enctype="multipart/form-data" class="admin-form-grid" style="margin-top:12px;">
          <input type="hidden" name="action" value="add_banner_items">
          <input type="hidden" name="flash_target" value="banner-section">
          <div class="full">
            <label>顶部随机漂浮媒体新增（数量不限，mp4/gif/png/jpg）</label>
            <input type="file" name="banner_items[]" multiple accept=".mp4,.gif,.png,.jpg,.jpeg,.webm">
            <button type="submit" style="margin-top:8px;">新增漂浮媒体</button>
          </div>
        </form>

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;">
          <?php foreach ($bannerItems as $bi): ?>
            <div style="border:1px solid #2d2d2d;padding:6px;">
              <div class="thumb-inline">
                <?php if ($bi['media_type'] === 'video'): ?>
                  <video src="<?= esc((string) $bi['media_path']) ?>" muted autoplay loop playsinline></video>
                <?php else: ?>
                  <img src="<?= esc((string) $bi['media_path']) ?>" alt="banner item">
                <?php endif; ?>
              </div>
              <form method="post" enctype="multipart/form-data" style="margin-top:6px;display:grid;gap:6px;">
                <input type="hidden" name="action" value="update_banner_item">
                <input type="hidden" name="flash_target" value="banner-section">
                <input type="hidden" name="banner_item_id" value="<?= (int) $bi['id'] ?>">
                <input type="number" name="sort_order" value="<?= (int) $bi['sort_order'] ?>" style="width:92px;">
                <input type="file" name="replace_file" accept=".mp4,.gif,.png,.jpg,.jpeg,.webm" style="width:92px;">
                <button type="submit" style="width:92px;padding:4px 0;">保存</button>
              </form>
              <form method="post" style="margin-top:4px;">
                <input type="hidden" name="action" value="delete_banner_item">
                <input type="hidden" name="flash_target" value="banner-section">
                <input type="hidden" name="banner_item_id" value="<?= (int) $bi['id'] ?>">
                <button type="submit" class="btn-danger" style="width:92px;padding:4px 0;">删除</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="admin-card" id="admin-account-section" data-flash-target="admin-account-section">
        <h2>管理员账号设置</h2>
        <?= renderFlashAt('admin-account-section', $flashData) ?>
        <form method="post" class="admin-form-grid">
          <input type="hidden" name="action" value="change_admin_password">
          <input type="hidden" name="flash_target" value="admin-account-section">
          <div>
            <label>新用户名</label>
            <input type="text" name="new_username" required value="<?= esc((string) ($_SESSION['admin_username'] ?? 'admin')) ?>">
          </div>
          <div>
            <label>新密码</label>
            <input type="password" name="new_password" required>
          </div>
          <div style="display:flex;align-items:flex-end;">
            <button type="submit">更新管理员凭证</button>
          </div>
        </form>
      </section>

      <section class="admin-card" id="works-section" data-flash-target="works-section">
        <h2>已创建作品（编辑 / 删除）</h2>
        <?= renderFlashAt('works-section', $flashData) ?>
        <div class="work-list">
          <?php foreach ($works as $w): ?>
            <?php $workTarget = 'work-' . (int) $w['id']; ?>
            <article class="work-list-item" id="<?= esc($workTarget) ?>" data-flash-target="<?= esc($workTarget) ?>">
              <?= renderFlashAt($workTarget, $flashData) ?>
              <div class="work-list-cover">
                <?= renderMediaPreview((string) ($w['cover_path'] !== '' ? $w['cover_path'] : ($w['media'][0]['media_path'] ?? '')), (string) $w['title'], (string) ($w['updated_at'] ?? '')) ?>
              </div>
              <form method="post" enctype="multipart/form-data" class="admin-form-grid">
                <input type="hidden" name="action" value="update_work">
                <input type="hidden" name="flash_target" value="<?= esc($workTarget) ?>">
                <input type="hidden" name="work_id" value="<?= (int) $w['id'] ?>">
                <div class="full">
                  <label>作品名</label>
                  <input type="text" name="title" value="<?= esc((string) $w['title']) ?>" required>
                </div>
                <div>
                  <label>排序值</label>
                  <input type="number" name="sort_order" value="<?= (int) $w['sort_order'] ?>">
                </div>
                <div style="display:flex;align-items:flex-end;">
                  <label style="margin:0;"><input type="checkbox" name="emphasized" <?= ((int) $w['emphasized']) === 1 ? 'checked' : '' ?>> 强调</label>
                </div>
                <div class="full">
                  <label>简介</label>
                  <textarea name="description"><?= esc((string) $w['description']) ?></textarea>
                </div>
                <div class="full">
                  <label>创作时间（第三列，段落输入）</label>
                  <textarea name="created_time"><?= esc((string) ($w['created_time'] ?? '')) ?></textarea>
                </div>
                <div class="full">
                  <label>补充信息（第三列）</label>
                  <textarea name="meta_text"><?= esc((string) ($w['meta_text'] ?? '')) ?></textarea>
                </div>
                <div>
                  <label>字号</label>
                  <input type="number" name="title_font_size" min="10" max="80" value="<?= (int) $w['title_font_size'] ?>">
                </div>
                <div>
                  <label>弹窗三列字号</label>
                  <input type="number" name="modal_font_size" min="10" max="80" value="<?= (int) ($w['modal_font_size'] ?? 28) ?>">
                </div>
                <div>
                  <label>粗细</label>
                  <input type="number" name="title_font_weight" min="0" max="900" step="1" value="<?= (int) $w['title_font_weight'] ?>">
                </div>
                <div>
                  <label>字体</label>
                  <input type="text" name="title_font_family" value="<?= esc((string) $w['title_font_family']) ?>">
                </div>
                <div>
                  <label>颜色</label>
                  <input type="color" name="title_color" value="<?= esc((string) $w['title_color']) ?>">
                </div>
                <div style="display:flex;gap:10px;align-items:center;">
                  <label style="margin:0;"><input type="checkbox" name="title_italic" <?= ((int) $w['title_italic']) === 1 ? 'checked' : '' ?>> 斜体</label>
                  <label style="margin:0;"><input type="checkbox" name="title_underline" <?= ((int) $w['title_underline']) === 1 ? 'checked' : '' ?>> 下划线</label>
                </div>
                <div>
                  <label>首页卡片底色</label>
                  <select name="card_bg">
                    <option value="black" <?= (($w['card_bg'] ?? 'black') === 'black') ? 'selected' : '' ?>>纯黑底</option>
                    <option value="white" <?= (($w['card_bg'] ?? 'black') === 'white') ? 'selected' : '' ?>>纯白底</option>
                  </select>
                </div>
                <div class="full">
                  <label>更换封面（支持缩放裁剪 + 可选纯色边框）</label>
                  <input type="file" name="cover_file" accept=".mp4,.gif,.png,.jpg,.jpeg,.webm">
                  <button type="button" class="btn-ghost" data-cover-open style="margin-top:8px;">打开封面裁剪弹窗</button>
                  <div class="cover-tools" data-cover-tools data-current-cover="<?= esc(mediaTypeFromPath((string) ($w['cover_path'] ?? '')) === 'image' ? versionedMediaPath((string) ($w['cover_path'] ?? ''), (string) ($w['updated_at'] ?? '')) : '') ?>">
                    <div class="cover-tools-mask" data-cover-close></div>
                    <div class="cover-tools-dialog">
                      <div class="cover-tools-head">
                        <strong>封面裁剪设置</strong>
                        <div style="display:flex;gap:8px;">
                          <button type="submit" name="cover_apply_submit" value="1" class="btn-ghost" data-cover-apply>确认并同步保存</button>
                          <button type="button" class="btn-ghost" data-cover-close>关闭</button>
                        </div>
                      </div>
                      <div class="cover-tools-body">
                        <div class="cover-preview-stage" data-cover-stage>
                          <img alt="cover preview" data-cover-preview>
                          <div class="cover-crop-frame" data-cover-frame></div>
                        </div>
                        <div class="cover-controls">
                          <div style="font-size:12px;color:#a9a9a9;">可直接在左侧图片区域按住拖拽定位，再微调用滑杆。</div>
                          <label>缩放（仅图片）<input type="range" min="1" max="3" step="0.01" value="1" data-cover-zoom></label>
                          <label>X位移（仅图片）<input type="range" min="-100" max="100" step="1" value="0" data-cover-x></label>
                          <label>Y位移（仅图片）<input type="range" min="-100" max="100" step="1" value="0" data-cover-y></label>
                          <label>边框粗细(px)<input type="range" min="0" max="60" step="1" value="0" data-cover-border-width></label>
                          <label>边框颜色<input type="color" value="#000000" data-cover-border-color></label>
                        </div>
                      </div>
                    </div>
                    <input type="hidden" name="cover_zoom" value="1" data-cover-zoom-hidden>
                    <input type="hidden" name="cover_offset_x" value="0" data-cover-x-hidden>
                    <input type="hidden" name="cover_offset_y" value="0" data-cover-y-hidden>
                    <input type="hidden" name="cover_border_width" value="0" data-cover-border-width-hidden>
                    <input type="hidden" name="cover_border_color" value="#000000" data-cover-border-color-hidden>
                    <input type="hidden" name="cover_crop_x" value="0.5" data-cover-crop-x-hidden>
                    <input type="hidden" name="cover_crop_y" value="0.5" data-cover-crop-y-hidden>
                    <input type="hidden" name="cover_crop_size" value="1" data-cover-crop-size-hidden>
                    <input type="hidden" name="cover_crop_x_px" value="0" data-cover-crop-x-px-hidden>
                    <input type="hidden" name="cover_crop_y_px" value="0" data-cover-crop-y-px-hidden>
                    <input type="hidden" name="cover_crop_size_px" value="0" data-cover-crop-size-px-hidden>
                    <input type="hidden" name="cover_crop_apply" value="0" data-cover-apply-hidden>
                    <input type="hidden" name="cover_preprocessed" value="0" data-cover-preprocessed-hidden>
                  </div>
                </div>
                <div>
                  <label>分类（封面图下方）</label>
                  <select name="category_id">
                    <?php foreach ($categories as $cat): ?>
                      <option value="<?= (int) $cat['id'] ?>" <?= (int) $w['category_id'] === (int) $cat['id'] ? 'selected' : '' ?>><?= esc((string) $cat['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="full">
                  <label>追加详情媒体（多选）</label>
                  <input type="file" name="media_files[]" multiple accept=".mp4,.gif,.png,.jpg,.jpeg,.webm">
                </div>
                <div class="full" style="display:flex;gap:8px;flex-wrap:wrap;">
                  <button type="submit">保存作品</button>
                </div>
              </form>
              <form method="post" onsubmit="return confirm('确认删除该作品？');" style="margin-top:8px;">
                <input type="hidden" name="action" value="delete_work">
                <input type="hidden" name="flash_target" value="<?= esc($workTarget) ?>">
                <input type="hidden" name="work_id" value="<?= (int) $w['id'] ?>">
                <button type="submit" class="btn-danger">删除作品</button>
              </form>

              <?php if (!empty($w['media'])): ?>
                <div style="margin-top:8px;display:grid;gap:6px;">
                  <?php foreach ($w['media'] as $m): ?>
                    <div style="display:flex;gap:8px;align-items:center;justify-content:space-between;border-top:1px solid #2a2a2a;padding-top:6px;">
                      <div style="display:flex;gap:8px;align-items:center;">
                        <div class="thumb-inline" style="width:56px;height:56px;">
                          <?php if ($m['media_type'] === 'video'): ?>
                            <video src="<?= esc((string) $m['media_path']) ?>" muted autoplay loop playsinline></video>
                          <?php else: ?>
                            <img src="<?= esc((string) $m['media_path']) ?>" alt="media thumb">
                          <?php endif; ?>
                        </div>
                        <span style="font-size:12px;color:#aaa;word-break:break-all;"><?= esc((string) $m['media_path']) ?></span>
                      </div>
                      <form method="post" onsubmit="return confirm('删除此条媒体？');">
                        <input type="hidden" name="action" value="delete_media">
                        <input type="hidden" name="flash_target" value="<?= esc($workTarget) ?>">
                        <input type="hidden" name="media_id" value="<?= (int) $m['id'] ?>">
                        <button type="submit" class="btn-danger">删媒体</button>
                      </form>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="admin-card">
        <h2>修改记录</h2>
        <div style="display:grid;gap:8px;">
          <?php foreach ($logs as $log): ?>
            <div style="border-top:1px solid #2f2f2f;padding-top:8px;font-size:12px;color:#d0d0d0;">
              <strong>[<?= esc((string) $log['section']) ?>]</strong>
              <?= esc((string) $log['action']) ?> -
              <?= esc((string) $log['detail']) ?>
              <span style="color:#8e8e8e;">(<?= esc((string) $log['created_at']) ?>)</span>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    </div>
  </div>
  <script src="/assets/app.js?v=<?= urlencode((string) @filemtime(__DIR__ . '/assets/app.js')) ?>"></script>
</body>
</html>
