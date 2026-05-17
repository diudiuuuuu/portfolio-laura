<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$logo = setting('logo_text', 'LOGO');
$logoImage = setting('logo_image', '');
$menuWorks = setting('menu_works', 'Works');
$menuIntro = setting('menu_intro', 'Introduction');
$bannerRatio = (float) setting('banner_ratio', '4.5');
if ($bannerRatio <= 0.1) {
    $bannerRatio = 4.5;
}
$bannerOverlay = setting('banner_overlay', '');
$bannerBg = setting('banner_bg', '');
$bannerItems = fetchBannerItems();
$activeCategory = (int) ($_GET['category'] ?? 0);
$categories = fetchCategories();
$catActiveBg = normalizeColor(setting('cat_active_bg', '#000000'));
$catBorderColor = normalizeColor(setting('cat_border_color', '#111111'));
$musicFile = setting('music_file', '');
$floatingCornerImage = setting('floating_corner_image', '');

$works = arrangeWorks(fetchWorksWithMedia($activeCategory > 0 ? $activeCategory : null));
$preloadImage = '';
if ($bannerBg !== '' && mediaTypeFromPath($bannerBg) !== 'video') {
    $preloadImage = mediaPreviewPath($bannerBg, 'lg');
} elseif ($bannerOverlay !== '') {
    $preloadImage = mediaPreviewPath($bannerOverlay, 'md');
}

// Collect first 4 non-video cover URLs for <link rel="preload"> in <head>.
$preloadCovers = [];
foreach ($works as $w) {
    if (count($preloadCovers) >= 4) {
        break;
    }
    $c = $w['cover_path'];
    if ($c === '' && !empty($w['media'])) {
        $c = $w['media'][0]['media_path'];
    }
    if ($c !== '' && mediaTypeFromPath($c) !== 'video') {
        $preloadCovers[] = mediaPreviewPath($c, 'sm');
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc($menuWorks) ?></title>
  <link rel="stylesheet" href="/assets/style.css">
  <?php if ($preloadImage !== ''): ?>
    <link rel="preload" as="image" href="<?= esc($preloadImage) ?>">
  <?php endif; ?>
  <?php foreach ($preloadCovers as $preloadCover): ?>
    <link rel="preload" as="image" href="<?= esc($preloadCover) ?>">
  <?php endforeach; ?>
</head>
<body>
  <div class="site-wrap">
    <section class="banner" style="--banner-ratio: <?= esc((string) $bannerRatio) ?>;">
      <?php if ($bannerBg !== ''): ?>
        <?php if (mediaTypeFromPath($bannerBg) === 'video'): ?>
          <video class="banner-bg-media" data-defer-src="<?= esc($bannerBg) ?>" muted loop playsinline preload="none"></video>
        <?php else: ?>
          <img class="banner-bg-media" src="<?= esc(mediaPreviewPath($bannerBg, 'lg')) ?>" alt="banner background" fetchpriority="high">
        <?php endif; ?>
      <?php endif; ?>

      <header class="top-nav banner-nav">
        <div class="logo">
          <?php if ($logoImage !== ''): ?>
            <img src="<?= esc(mediaPreviewPath($logoImage, 'sm')) ?>" srcset="<?= esc(mediaPreviewSrcset($logoImage)) ?>" sizes="64px" alt="logo" style="height:32px;width:auto;display:block;" fetchpriority="high">
          <?php else: ?>
            <?= esc($logo) ?>
          <?php endif; ?>
        </div>
        <nav class="nav-right">
          <a class="active" href="/index.php"><?= esc($menuWorks) ?></a>
          <a href="/introduction.php"><?= esc($menuIntro) ?></a>
        </nav>
      </header>

      <div class="banner-float-layer" data-banner-float>
        <?php foreach ($bannerItems as $idx => $item): ?>
          <div class="banner-float-item" data-seed="<?= (int) $item['id'] ?>">
            <?php if ($item['media_type'] === 'video'): ?>
              <video data-defer-src="<?= esc((string) $item['media_path']) ?>" muted loop playsinline preload="none"></video>
            <?php else: ?>
              <?php $bannerPriority = $idx < 2; ?>
              <img src="<?= esc(mediaPreviewPath((string) $item['media_path'], 'sm')) ?>" srcset="<?= esc(mediaPreviewSrcset((string) $item['media_path'])) ?>" sizes="220px" alt="banner item" loading="<?= $bannerPriority ? 'eager' : 'lazy' ?>" fetchpriority="<?= $bannerPriority ? 'high' : 'auto' ?>" decoding="async">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($bannerOverlay !== ''): ?>
        <div class="banner-top-image"><img src="<?= esc(mediaPreviewPath($bannerOverlay, 'md')) ?>" srcset="<?= esc(mediaPreviewSrcset($bannerOverlay)) ?>" sizes="100vw" alt="banner top png" loading="lazy"></div>
      <?php endif; ?>
      <div class="banner-categories" style="--cat-active-bg:<?= esc($catActiveBg) ?>;--cat-border:<?= esc($catBorderColor) ?>;">
        <a class="cat-pill <?= $activeCategory === 0 ? 'active' : '' ?>" href="/index.php">All</a>
        <?php foreach ($categories as $cat): ?>
          <a class="cat-pill <?= $activeCategory === (int) $cat['id'] ? 'active' : '' ?>" href="/index.php?category=<?= (int) $cat['id'] ?>"><?= esc((string) $cat['name']) ?></a>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="works-grid">
      <?php foreach ($works as $idx => $w): ?>
        <?php
          $cover = $w['cover_path'];
          if ($cover === '' && !empty($w['media'])) {
              $cover = $w['media'][0]['media_path'];
          }
          $isVideoThumb = mediaTypeFromPath($cover) === 'video';
          $coverDisplay = $cover;
          if ($coverDisplay !== '') {
              $coverDisplay .= (str_contains($coverDisplay, '?') ? '&' : '?') . 'v=' . rawurlencode((string) ($w['updated_at'] ?? ''));
          }
          $coverThumbSize = !empty($w['_is_big']) ? 'md' : 'sm';
          $coverThumb = $coverDisplay !== '' && !$isVideoThumb ? mediaPreviewPath($coverDisplay, $coverThumbSize) : $coverDisplay;
          $modalMedia = array_map(static function (array $item): array {
              $path = normalizePublicPath((string) ($item['media_path'] ?? ''));
              $type = (string) ($item['media_type'] ?? mediaTypeFromPath($path));
              return [
                  'media_path' => $path,
                  'media_type' => $type,
                  'preview_path' => $type === 'video' ? '' : mediaPreviewPath($path, 'lg'),
              ];
          }, array_values(array_filter($w['media'] ?? [], 'is_array')));
          $mediaJson = json_encode($modalMedia, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
          $style = styleString($w);
          $cardBg = normalizeCardBg((string) ($w['card_bg'] ?? 'black'));
          $cardClass = 'work-card work-card-bg-' . $cardBg . (!empty($w['_is_big']) ? ' is-big' : '');
          $cardPriority = $idx < 6;
        ?>
        <article
          class="<?= esc($cardClass) ?>"
          data-work-card
          data-title="<?= esc($w['title']) ?>"
          data-description="<?= esc($w['description']) ?>"
          data-meta="<?= esc($w['meta_text']) ?>"
          data-time="<?= esc($w['created_time']) ?>"
          data-title-weight="<?= (int) ($w['title_font_weight'] ?? 600) ?>"
          data-modal-size="<?= (int) $w['modal_font_size'] ?>"
          data-modal-bg="<?= esc($cardBg) ?>"
          data-media="<?= esc((string) $mediaJson) ?>"
        >
          <div class="work-media-wrap">
            <?php if ($cover !== ''): ?>
              <?php if ($isVideoThumb): ?>
                <video class="work-media-thumb" data-defer-src="<?= esc($coverDisplay) ?>" muted playsinline preload="none"></video>
              <?php else: ?>
                <img class="work-media-thumb" src="<?= esc($coverThumb) ?>" srcset="<?= esc(mediaPreviewSrcset($coverDisplay)) ?>" sizes="<?= !empty($w['_is_big']) ? '(max-width: 960px) 100vw, 50vw' : '(max-width: 960px) 100vw, 25vw' ?>" alt="<?= esc($w['title']) ?>" loading="<?= $cardPriority ? 'eager' : 'lazy' ?>" fetchpriority="<?= $cardPriority ? 'high' : 'auto' ?>" decoding="async">
              <?php endif; ?>
            <?php endif; ?>
            <div class="work-overlay"></div>
          </div>
          <h3 class="work-title" style="<?= esc($style) ?>"><?= esc($w['title']) ?></h3>
        </article>
      <?php endforeach; ?>
    </section>
  </div>

  <?php if ($musicFile !== ''): ?>
    <audio id="site-music" loop preload="metadata" autoplay>
      <source src="<?= esc($musicFile) ?>" type="audio/mpeg">
    </audio>
    <button class="music-tab" type="button" data-music-toggle aria-label="toggle music">▶ MUSIC</button>
  <?php endif; ?>

  <?php if ($floatingCornerImage !== ''): ?>
    <button class="corner-float" type="button" aria-label="floating corner image">
      <img src="<?= esc(mediaPreviewPath($floatingCornerImage, 'sm')) ?>" alt="floating corner">
    </button>
  <?php endif; ?>

  <div class="modal-layer" data-modal-layer>
    <div class="work-modal" data-modal>
      <button class="modal-close" data-close type="button">×</button>
      <div class="modal-head">
        <div class="modal-col"><h3 data-m-title></h3></div>
        <div class="modal-col">
          <p data-m-desc></p>
        </div>
        <div class="modal-col">
          <p data-m-time></p>
          <p style="margin-top:12px;" data-m-meta></p>
        </div>
      </div>
      <div class="modal-gallery" data-m-gallery></div>
    </div>
  </div>

  <script src="/assets/app.js"></script>
</body>
</html>
