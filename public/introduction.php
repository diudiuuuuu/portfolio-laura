<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$logo = setting('logo_text', 'LOGO');
$logoImage = setting('logo_image', '');
$menuWorks = setting('menu_works', 'Works');
$menuIntro = setting('menu_intro', 'Introduction');

$intro = fetchIntroContent();
$introStyle = introStyleString($intro);
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc($menuIntro) ?></title>
  <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
  <div class="site-wrap">
    <header class="top-nav">
      <div class="logo">
        <?php if ($logoImage !== ''): ?>
          <img src="<?= esc($logoImage) ?>" alt="logo" style="height:32px;width:auto;display:block;">
        <?php else: ?>
          <?= esc($logo) ?>
        <?php endif; ?>
      </div>
      <nav class="nav-right">
        <a href="/index.php"><?= esc($menuWorks) ?></a>
        <a class="active" href="/introduction.php"><?= esc($menuIntro) ?></a>
      </nav>
    </header>

    <?php if (($intro['top_image_path'] ?? '') !== ''): ?>
      <div class="intro-top-global">
        <img src="<?= esc($intro['top_image_path']) ?>" alt="intro top">
      </div>
    <?php endif; ?>

    <section class="intro-layout">
      <div class="intro-col" style="<?= esc($introStyle) ?>"><?= nl2br(esc((string) ($intro['col1_title'] ?? ''))) ?></div>
      <div class="intro-col" style="<?= esc($introStyle) ?>"><?= nl2br(esc((string) ($intro['col2_text'] ?? ''))) ?></div>
      <div class="intro-col" style="<?= esc($introStyle) ?>"><?= nl2br(esc((string) ($intro['col3_text'] ?? ''))) ?></div>
    </section>
  </div>
</body>
</html>
