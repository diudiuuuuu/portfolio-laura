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

$works = arrangeWorks(fetchWorksWithMedia($activeCategory > 0 ? $activeCategory : null));
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc($menuWorks) ?></title>
  <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
  <div class="site-wrap">
    <section class="banner" style="--banner-ratio: <?= esc((string) $bannerRatio) ?>;">
      <?php if ($bannerBg !== ''): ?>
        <?php if (mediaTypeFromPath($bannerBg) === 'video'): ?>
          <video class="banner-bg-media" src="<?= esc($bannerBg) ?>" muted autoplay loop playsinline preload="metadata"></video>
        <?php else: ?>
          <img class="banner-bg-media" src="<?= esc($bannerBg) ?>" alt="banner background">
        <?php endif; ?>
      <?php endif; ?>

      <header class="top-nav banner-nav">
        <div class="logo">
          <?php if ($logoImage !== ''): ?>
            <img src="<?= esc($logoImage) ?>" alt="logo" style="height:32px;width:auto;display:block;">
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
        <?php foreach ($bannerItems as $item): ?>
          <div class="banner-float-item" data-seed="<?= (int) $item['id'] ?>">
            <?php if ($item['media_type'] === 'video'): ?>
              <video src="<?= esc((string) $item['media_path']) ?>" muted autoplay loop playsinline preload="metadata"></video>
            <?php else: ?>
              <img src="<?= esc((string) $item['media_path']) ?>" alt="banner item">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($bannerOverlay !== ''): ?>
        <div class="banner-top-image"><img src="<?= esc($bannerOverlay) ?>" alt="banner top png"></div>
      <?php endif; ?>
      <div class="banner-categories" style="--cat-active-bg:<?= esc($catActiveBg) ?>;--cat-border:<?= esc($catBorderColor) ?>;">
        <a class="cat-pill <?= $activeCategory === 0 ? 'active' : '' ?>" href="/index.php">All</a>
        <?php foreach ($categories as $cat): ?>
          <a class="cat-pill <?= $activeCategory === (int) $cat['id'] ? 'active' : '' ?>" href="/index.php?category=<?= (int) $cat['id'] ?>"><?= esc((string) $cat['name']) ?></a>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="works-grid">
      <?php foreach ($works as $w): ?>
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
          $mediaJson = json_encode($w['media'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
          $style = styleString($w);
          $cardBg = normalizeCardBg((string) ($w['card_bg'] ?? 'black'));
          $cardClass = 'work-card work-card-bg-' . $cardBg . (!empty($w['_is_big']) ? ' is-big' : '');
        ?>
        <article
          class="<?= esc($cardClass) ?>"
          data-work-card
          data-title="<?= esc($w['title']) ?>"
          data-description="<?= esc($w['description']) ?>"
          data-meta="<?= esc($w['meta_text']) ?>"
          data-time="<?= esc($w['created_time']) ?>"
          data-modal-size="<?= (int) $w['modal_font_size'] ?>"
          data-modal-bg="<?= esc($cardBg) ?>"
          data-media="<?= esc((string) $mediaJson) ?>"
        >
          <?php if ($cover !== ''): ?>
            <?php if ($isVideoThumb): ?>
              <video class="work-media-thumb" src="<?= esc($coverDisplay) ?>" muted playsinline autoplay loop preload="metadata"></video>
            <?php else: ?>
              <img class="work-media-thumb" src="<?= esc($coverDisplay) ?>" alt="<?= esc($w['title']) ?>" loading="lazy">
            <?php endif; ?>
          <?php endif; ?>
          <div class="work-overlay"></div>
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
