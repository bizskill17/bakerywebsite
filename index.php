<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/instagram.php';

$menu    = q('SELECT * FROM menu_items ORDER BY sort_order, id')->fetchAll();
$gallery = q('SELECT * FROM gallery ORDER BY sort_order, id LIMIT 5')->fetchAll();
$stories = q('SELECT * FROM stories ORDER BY sort_order, id')->fetchAll();

// When Instagram sync is on, show the latest Instagram posts instead of the manual photos
if (ig_enabled()) {
    $igPhotos = q("SELECT image, caption, permalink AS link FROM instagram_media WHERE kind = 'photo' ORDER BY sort_order LIMIT 5")->fetchAll();
    $igReels  = q("SELECT image, caption, permalink AS link FROM instagram_media WHERE kind = 'reel' ORDER BY sort_order LIMIT 10")->fetchAll();
    if ($igPhotos) $gallery = $igPhotos;
    if ($igReels)  $stories = $igReels;
}
$shortCap = fn(?string $c, string $fallback) => ($c = trim(preg_replace('/\s+/', ' ', (string)$c))) !== '' ? mb_strimwidth($c, 0, 110, '…') : $fallback;

$wa = whatsapp_link();
$ig = instagram_link();
$aboutParas = array_filter(array_map('trim', preg_split("/\R\s*\R/", setting('about_text'))));

// "Because at Bliss Bites, every bite brings a smile!" → pink "Bliss", line break after the comma on phones
$quote = h(setting('about_quote'));
$quote = preg_replace('/\bBliss\b/', '<em>Bliss</em>', $quote, 1);
$quote = preg_replace('/,\s*/', ',<br class="mobile-only"> ', $quote, 1);

$enqSent  = isset($_GET['sent']);
$enqError = $_SESSION['enq_error'] ?? '';
$old      = $_SESSION['enq_old'] ?? [];
unset($_SESSION['enq_error'], $_SESSION['enq_old']);
$o = fn(string $k) => h($old[$k] ?? '');

$heroImg = setting('hero_image', 'images/hero.jpg');
$deliveryArea = setting('delivery_area', 'Guwahati');
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Bliss Bites by Nutan Jain | Homemade Brownies &amp; Cakes in <?= h($deliveryArea) ?></title>
  <meta name="description" content="Bliss Bites by Nutan Jain – a home-based bakery in <?= h($deliveryArea) ?>. Homemade brownies, brownie cakes, dry cakes and more, baked with love.">
  <meta name="theme-color" content="#c2185b">
  <link rel="icon" href="images/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="css/style.css?v=3">
  <style>.hero-img{background-image:url("<?= h($heroImg) ?>")}</style>
</head>
<body>

<?php readfile(__DIR__ . '/inc/icons.svg'); ?>

<!-- ============ HEADER ============ -->
<header class="site-header" id="top">
  <div class="container header-inner">
    <button class="icon-btn menu-toggle" aria-label="Open menu" aria-expanded="false">
      <svg><use href="#i-menu"/></svg>
    </button>

    <a href="#top" class="logo" aria-label="Bliss Bites by Nutan Jain – home">
      <span class="logo-art">
        <svg class="spark s1"><use href="#i-sparkle"/></svg>
        <svg class="spark s2"><use href="#i-sparkle"/></svg>
        <svg class="spark s3"><use href="#i-sparkle"/></svg>
        <svg class="muffin"><use href="#i-muffin"/></svg>
      </span>
      <span class="logo-word">Bliss <span class="b2">Bites</span></span>
      <span class="logo-by">by Nutan Jain</span>
    </a>

    <nav class="main-nav" aria-label="Main">
      <a href="#top" class="active">Home</a>
      <a href="#menu">Menu</a>
      <a href="#about">About Us</a>
      <a href="#gallery">Gallery</a>
      <a href="#contact">Contact</a>
    </nav>

    <div class="header-actions">
      <a class="icon-btn" href="<?= h($ig) ?>" target="_blank" rel="noopener" aria-label="Instagram"><svg><use href="#i-instagram"/></svg></a>
      <span class="divider desktop-only"></span>
      <a class="icon-btn" href="<?= h($wa) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><svg><use href="#i-whatsapp"/></svg></a>
      <a class="btn btn-primary btn-order desktop-only" href="#contact"><svg><use href="#i-cart"/></svg>Order Now</a>
    </div>
  </div>

  <div class="drawer" hidden>
    <a href="#top">Home</a>
    <a href="#menu">Menu</a>
    <a href="#about">About Us</a>
    <a href="#gallery">Gallery</a>
    <a href="#contact">Contact</a>
    <a href="<?= h($wa) ?>" class="btn btn-primary" target="_blank" rel="noopener"><svg><use href="#i-whatsapp"/></svg>Order on WhatsApp</a>
  </div>
</header>

<main>
  <!-- ============ HERO ============ -->
  <section class="hero">
    <div class="hero-img" role="img" aria-label="Freshly baked treats from Bliss Bites"></div>
    <div class="container hero-inner">
      <div class="hero-copy">
        <div class="hero-logo desktop-only" aria-hidden="true">
          <span class="logo-art big">
            <svg class="spark s1"><use href="#i-sparkle"/></svg>
            <svg class="spark s2"><use href="#i-sparkle"/></svg>
            <svg class="muffin"><use href="#i-muffin"/></svg>
          </span>
          <span class="logo-word big">Bliss Bites</span>
          <span class="logo-by big">by Nutan Jain</span>
          <span class="heart-rule"><svg><use href="#i-heart"/></svg></span>
        </div>
        <p class="hero-tag desktop-only"><?= h(setting('hero_tagline')) ?> <svg class="ic-heart"><use href="#i-heart"/></svg></p>

        <h1 class="hero-title mobile-only"><?= h(setting('hero_title')) ?></h1>
        <h1 class="visually-hidden desktop-only">Bliss Bites by Nutan Jain – <?= h(setting('hero_title')) ?></h1>
        <p class="hero-sub mobile-only">Baked with Love <svg class="ic-heart"><use href="#i-heart"/></svg></p>

        <a href="#menu" class="btn btn-primary">Explore Our Menu <svg><use href="#i-arrow"/></svg></a>
      </div>
    </div>
  </section>

  <section class="features features-top mobile-only" aria-label="Why Bliss Bites">
    <div class="feature"><svg><use href="#i-heart"/></svg><span>Homemade<br>Goodness</span></div>
    <div class="feature"><svg><use href="#i-leaf"/></svg><span>Quality<br>Ingredients</span></div>
    <div class="feature"><svg><use href="#i-cupcake"/></svg><span>Baked<br>with Care</span></div>
  </section>

  <!-- ============ MENU ============ -->
  <section class="section menu" id="menu">
    <div class="container">
      <h2 class="section-title"><svg class="desktop-only"><use href="#i-cake"/></svg>Our Menu</h2>
      <div class="menu-grid">
        <?php foreach ($menu as $i => $m): ?>
        <article class="menu-card<?= $i >= 3 ? ' extra' : '' ?><?= $m['available'] ? '' : ' sold-out' ?>">
          <div class="menu-img">
            <img src="<?= h($m['image'] ?: 'images/favicon.svg') ?>" alt="<?= h($m['name']) ?>" loading="lazy">
            <?php if (!$m['available']): ?><span class="badge">Sold out</span><?php endif; ?>
          </div>
          <h3><?= h($m['name']) ?></h3>
          <?php if ($m['price'] !== '' || trim((string)$m['description']) !== ''): ?>
          <div class="menu-meta">
            <?php if ($m['price'] !== ''): ?><span class="price"><?= h($m['price']) ?></span><?php endif; ?>
            <?php if (trim((string)$m['description']) !== ''): ?><p><?= nl2br(h($m['description'])) ?></p><?php endif; ?>
          </div>
          <?php endif; ?>
        </article>
        <?php endforeach; ?>
      </div>
      <?php if (count($menu) > 3): ?>
      <div class="center mobile-only">
        <button class="btn btn-outline js-full-menu">View Full Menu <svg><use href="#i-arrow"/></svg></button>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ============ ABOUT ============ -->
  <section class="section about" id="about">
    <div class="container about-inner">
      <div class="about-photo desktop-only">
        <svg class="doodle"><use href="#i-heart"/></svg>
        <div class="blob"><img src="<?= h(setting('about_image', 'images/about-nutan.jpg')) ?>" alt="Nutan Jain baking at home" loading="lazy"></div>
      </div>
      <div class="about-copy">
        <h2 class="about-title">About Bliss Bites <svg><use href="#i-heart"/></svg></h2>
        <p class="brush"><?= preg_replace('/,\s*/', ',<br class="mobile-only"> ', h(setting('about_subtitle')), 1) ?> <svg class="desktop-only"><use href="#i-heart"/></svg></p>
        <?php foreach ($aboutParas as $p): ?><p><?= nl2br(h($p)) ?></p><?php endforeach; ?>
        <img class="about-brownie mobile-only" src="<?= h(setting('about_image_mobile', 'images/about-brownie.jpg')) ?>" alt="Fudgy chocolate brownies" loading="lazy">
        <?php if (setting('about_quote') !== ''): ?>
        <p class="quote"><span class="burst l mobile-only"></span><span class="q-text"><?= $quote ?> <svg><use href="#i-heart"/></svg></span><span class="burst r mobile-only"></span></p>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- ============ INSTAGRAM GALLERY ============ -->
  <?php if ($gallery): ?>
  <section class="section gallery" id="gallery">
    <div class="container">
      <h2 class="section-title"><svg><use href="#i-instagram"/></svg>Instagram Gallery</h2>
      <p class="section-sub">A glimpse of our freshly baked treats</p>
      <div class="gallery-wrap">
        <span class="burst l desktop-only"></span>
        <div class="gallery-grid">
          <?php foreach ($gallery as $g): ?>
          <a href="<?= h(($g['link'] ?? '') ?: $ig) ?>" target="_blank" rel="noopener"><img src="<?= h($g['image']) ?>" alt="<?= h($shortCap($g['caption'], 'Bliss Bites bake')) ?>" loading="lazy"></a>
          <?php endforeach; ?>
        </div>
        <span class="burst r desktop-only"></span>
      </div>
      <div class="center desktop-only">
        <a class="btn btn-primary btn-sm" href="<?= h($ig) ?>" target="_blank" rel="noopener"><svg><use href="#i-instagram"/></svg>Follow Us on Instagram <svg><use href="#i-arrow"/></svg></a>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- ============ BAKING STORIES ============ -->
  <?php if ($stories): ?>
  <section class="section stories" id="stories">
    <div class="container">
      <h2 class="section-title"><svg><use href="#i-clapper"/></svg>Watch Our Baking Stories <svg class="t-heart"><use href="#i-heart"/></svg></h2>
      <p class="section-sub"><span class="desktop-only">Short videos, big happiness!</span><span class="mobile-only">Short videos, behind the scenes and new creations</span></p>
      <div class="carousel">
        <button class="car-btn prev" aria-label="Previous"><svg><use href="#i-chev-l"/></svg></button>
        <div class="car-track">
          <?php foreach ($stories as $s): ?>
          <a class="story<?= isset($igReels) && $igReels ? ' is-ig' : '' ?>" href="<?= h($s['link'] ?: $ig) ?>" target="_blank" rel="noopener"><img src="<?= h($s['image']) ?>" alt="<?= h($shortCap($s['caption'], 'Baking story') . ' – video') ?>" loading="lazy"></a>
          <?php endforeach; ?>
        </div>
        <button class="car-btn next" aria-label="Next"><svg><use href="#i-chev-r"/></svg></button>
      </div>
      <div class="center mobile-only">
        <a class="btn btn-primary btn-sm" href="<?= h($ig) ?>" target="_blank" rel="noopener"><svg><use href="#i-instagram"/></svg>Watch More on Instagram <svg><use href="#i-arrow"/></svg></a>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <section class="features features-bottom desktop-only" aria-label="Why Bliss Bites">
    <div class="container features-row">
      <div class="feature"><svg><use href="#i-heart"/></svg><span>Homemade<br>Goodness</span></div>
      <div class="feature"><svg><use href="#i-diamond"/></svg><span>Premium<br>Ingredients</span></div>
      <div class="feature"><svg><use href="#i-cupcake"/></svg><span>Baked<br>with Care</span></div>
      <div class="feature"><svg><use href="#i-pin"/></svg><span><?= h($deliveryArea) ?><br>Delivery</span></div>
    </div>
  </section>

  <!-- ============ ORDER ENQUIRY ============ -->
  <section class="section contact" id="contact">
    <div class="container">
      <h2 class="section-title"><svg><use href="#i-cart"/></svg>Place an Order</h2>
      <p class="section-sub">Tell us what you'd like and we'll get back to you shortly</p>

      <div class="enquiry-card">
        <?php if ($enqSent): ?>
          <div class="enq-done">
            <svg><use href="#i-check"/></svg>
            <h3>Thank you! Your enquiry has been sent.</h3>
            <p>We'll call or WhatsApp you soon to confirm your order.</p>
            <a class="btn btn-outline" href="./#contact">Send another enquiry</a>
          </div>
        <?php else: ?>
          <?php if ($enqError): ?><p class="enq-error"><?= h($enqError) ?></p><?php endif; ?>
          <form class="enquiry-form" method="post" action="enquiry.php">
            <label>Your name*<input name="name" required maxlength="120" value="<?= $o('name') ?>" autocomplete="name"></label>
            <label>Phone / WhatsApp*<input name="phone" required maxlength="20" inputmode="tel" value="<?= $o('phone') ?>" autocomplete="tel"></label>
            <label>What would you like?
              <select name="item">
                <?php foreach ($menu as $m): if (!$m['available']) continue; ?>
                <option<?= ($old['item'] ?? '') === $m['name'] ? ' selected' : '' ?>><?= h($m['name']) ?></option>
                <?php endforeach; ?>
                <option<?= ($old['item'] ?? '') === 'Something else' ? ' selected' : '' ?>>Something else</option>
              </select>
            </label>
            <label>Quantity<input name="quantity" maxlength="60" placeholder="e.g. 1 box of 6" value="<?= $o('quantity') ?>"></label>
            <label>Needed by<input type="date" name="needed_by" min="<?= date('Y-m-d') ?>" value="<?= $o('needed_by') ?>"></label>
            <label class="full">Message<textarea name="message" rows="3" maxlength="1000" placeholder="Occasion, flavour, delivery address, anything else…"><?= $o('message') ?></textarea></label>
            <label class="hp" aria-hidden="true">Leave this empty<input name="website" tabindex="-1" autocomplete="off"></label>
            <div class="full center"><button class="btn btn-primary" type="submit"><svg><use href="#i-send"/></svg>Send Enquiry</button></div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<!-- ============ FOOTER ============ -->
<footer class="site-footer">
  <div class="container footer-inner">
    <p class="footer-or">Prefer to chat?</p>
    <a class="btn btn-primary btn-lg" href="<?= h($wa) ?>" target="_blank" rel="noopener"><svg class="wa"><use href="#i-whatsapp"/></svg>Order Now on WhatsApp <svg><use href="#i-arrow"/></svg></a>
    <div class="footer-social">
      <a class="icon-btn" href="<?= h($ig) ?>" target="_blank" rel="noopener" aria-label="Instagram"><svg><use href="#i-instagram"/></svg></a>
      <a class="icon-btn" href="<?= h($wa) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><svg><use href="#i-whatsapp"/></svg></a>
    </div>
    <p class="footer-note">Bliss Bites by Nutan Jain · Home bakery in <?= h($deliveryArea) ?> · © <?= date('Y') ?></p>
  </div>
</footer>

<script src="js/main.js?v=2"></script>
</body>
</html>
<?php ig_sync_after_response();
