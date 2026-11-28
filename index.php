<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/instagram.php';

$menu    = q("SELECT * FROM menu_items WHERE active=1 AND category<>? ORDER BY sort_order, id", ['Other'])->fetchAll();
$groups=[]; foreach ($menu as $m) $groups[$m['category']][]=$m;
$brandLogo=setting('brand_logo');
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

$heroPhotos = [];
foreach ($gallery as $g) {
    $src = trim((string)($g['image'] ?? ''));
    if ($src !== '') $heroPhotos[$src] = (string)($g['caption'] ?? '');
}
if (count($heroPhotos) < 4) {
    foreach ($menu as $m) {
        $src = trim((string)($m['image'] ?? ''));
        if ($src !== '' && !str_starts_with($src,'images/menu-')) $heroPhotos[$src] = (string)$m['name'];
    }
}
$heroPhotos = array_slice($heroPhotos, 0, 16, true);
if (!$heroPhotos && ($heroImg = setting('authentic_hero'))) $heroPhotos[$heroImg] = 'Bliss Bites';
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
  <link rel="stylesheet" href="css/style.css?v=4">
  <style>
/* Homepage photo showcase */
.hero.hero-showcase{display:block;min-height:0;padding:24px 0 62px;background:radial-gradient(ellipse at 50% 65%,#fff5f7 0,#fbdce6 64%,#f8d0de 100%);overflow:hidden}
.hero.hero-showcase .hero-inner{width:100%;position:relative;z-index:2}
.hero.hero-showcase .hero-copy{width:100%;padding:12px 0 15px;align-items:center;text-align:center}
.hero-showcase .showcase-tag{display:block;font-family:var(--serif);font-size:clamp(1.35rem,2.5vw,2rem);line-height:1.35;font-weight:500;color:var(--ink);margin:12px 0 24px}
.hero-showcase .heart-rule{margin:8px 0 2px}
.hero-showcase .heart-rule::before,.hero-showcase .heart-rule::after{width:clamp(45px,8vw,100px)}
.showcase{position:relative;width:100%;z-index:2}
.showcase-window{overflow:hidden;position:relative;padding:16px 0 22px;touch-action:pan-y;mask-image:linear-gradient(90deg,transparent,#000 6%,#000 94%,transparent)}
.showcase-track{display:flex;align-items:center;gap:clamp(10px,1.6vw,23px);width:max-content;will-change:transform}
.showcase-card{flex:none;width:clamp(170px,24vw,345px);height:clamp(176px,24vw,340px);border:3px solid rgba(255,255,255,.85);border-radius:28px;overflow:hidden;background:#f8d0de;box-shadow:0 12px 22px rgba(145,31,80,.12);transform:rotate(-3deg)}
.showcase-card:nth-child(even){transform:rotate(3deg)}
.showcase-card img{display:block;width:100%;height:100%;object-fit:cover}
.showcase-arrow{position:absolute;top:46%;transform:translateY(-50%);z-index:3;width:48px;height:48px;border:0;border-radius:50%;background:rgba(255,255,255,.93);color:#c2185b;box-shadow:0 3px 14px #b72d6420;cursor:pointer;display:grid;place-items:center}
.showcase-arrow svg{width:24px;height:24px}
.showcase-prev{left:clamp(8px,2vw,30px)}.showcase-next{right:clamp(8px,2vw,30px)}
.showcase-controls{display:flex;justify-content:center;align-items:center;gap:10px;margin:0 0 12px}
.showcase-dot{width:10px;height:10px;background:#eaa3ba;border:0;border-radius:50%;padding:0;cursor:pointer}
.showcase-dot[aria-current=true]{background:#c2185b}
@media(max-width:820px){.hero.hero-showcase{padding:10px 0 40px}.showcase-window{padding:12px 0 15px}.showcase-card{width:clamp(150px,45vw,260px);height:clamp(165px,47vw,270px);border-radius:20px}.showcase-arrow{width:36px;height:36px}.showcase-arrow svg{width:20px;height:20px}.hero.hero-showcase .hero-copy{padding:5px 0 15px}.hero-showcase .showcase-tag{font-size:1.25rem;margin:10px 0 16px}}
.brand-original{mix-blend-mode:multiply;background:transparent;width:auto;max-width:150px;max-height:90px;object-fit:contain}.hero .brand-original{max-width:290px;max-height:230px}
.catalogue-tabs{display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin:20px 0}.catalogue-tabs button{font:inherit;border:1px solid #ebc6d3;border-radius:30px;background:white;color:var(--berry);padding:8px 15px;cursor:pointer}.catalogue-tabs button[aria-pressed=true]{background:var(--pink);color:white}
.catalogue-group{margin:24px 0}.catalogue-group h3{color:var(--berry);margin-bottom:12px}.catalogue-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.catalogue-item{display:flex;align-items:center;gap:14px;padding:14px;background:white;border:1px solid #f1d2dd;border-radius:14px}.catalogue-item img{width:90px;height:90px;object-fit:cover;border-radius:10px}.catalogue-item .detail{flex:1;min-width:0}.catalogue-item h4{margin:0 0 4px}.catalogue-item p{font-size:.85rem}.catalogue-price{color:var(--berry);font-weight:600}.catalogue-item button{font-size:.8rem;white-space:nowrap}.order-products{display:grid;gap:9px}.order-products label{display:grid;grid-template-columns:1fr 90px;align-items:center;gap:10px}.order-products input{width:90px}.order-group{padding:10px 0;border-bottom:1px solid #f1d2dd}.order-group h4{margin-bottom:8px;color:var(--berry)}.order-total{font-size:1.15rem;font-weight:600;color:var(--berry);text-align:right}
@media(max-width:650px){.catalogue-list{grid-template-columns:1fr}.catalogue-item img{width:72px;height:72px}.brand-original{max-width:100px;max-height:65px}}

.catalogue-item .product-photo-trigger{border:0;padding:0;background:transparent;cursor:zoom-in;flex:none;display:block;line-height:0;border-radius:10px}
.catalogue-item .product-photo-trigger:focus-visible{outline:3px solid #c2185b;outline-offset:3px}
.product-dialog{box-sizing:border-box;width:min(520px,calc(100% - 32px));max-height:90vh;max-height:90dvh;overflow:auto;border:1px solid #edcbd7;border-radius:22px;padding:20px;background:#fff5f8;color:var(--ink);box-shadow:0 16px 70px #38132455;margin:auto}
.product-dialog::backdrop{background:rgba(30,12,22,.62)}
.product-dialog-header,.product-dialog-footer{display:flex;align-items:center;justify-content:space-between;gap:12px}
.product-dialog-header{margin-bottom:14px}.product-dialog-header h2{color:#a90e4c;font-size:1.2rem;margin:0}
.product-dialog-close{width:36px;height:36px;flex:none;border:0;border-radius:50%;background:#f9e0ea;color:#a90e4c;font-size:25px;cursor:pointer}
.product-dialog-photo{width:100%;height:auto;max-height:56vh;object-fit:contain;display:block;border-radius:12px;background:transparent}
.product-dialog-footer{margin-top:16px;flex-wrap:wrap}.product-dialog-price{color:#a90e4c;font-weight:600;font-size:1.15rem}
.product-dialog [hidden]{display:none!important}
@media(max-width:480px){.product-dialog{padding:14px}.product-dialog-photo{max-height:50vh}.product-dialog-footer .btn{font-size:.9rem}}
</style>
</head>
<body id="top">

<?php readfile(__DIR__ . '/inc/icons.svg'); ?>

<!-- ============ HEADER ============ -->
<header class="site-header">
  <div class="container header-inner">
    <button class="icon-btn menu-toggle" aria-label="Open menu" aria-expanded="false">
      <svg><use href="#i-menu"/></svg>
    </button>

    <a href="#top" class="logo" aria-label="Bliss Bites by Nutan Jain – home">
      <?php if ($brandLogo): ?><img class="brand-original" src="<?= h($brandLogo) ?>" alt="Bliss Bites by Nutan Jain"><?php else: ?><span class="logo-by">Bliss Bites by Nutan Jain</span><?php endif; ?>
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
  <section class="hero hero-showcase" aria-label="Welcome to Bliss Bites">
    <?php if ($heroPhotos): ?>
    <div class="showcase" id="home-showcase" aria-label="Freshly baked treats">
      <div class="showcase-window"><div class="showcase-track">
        <?php foreach ($heroPhotos as $src=>$caption): ?>
          <div class="showcase-card"><img src="<?= h($src) ?>" alt="<?= h($shortCap($caption,'Bliss Bites treat')) ?>" decoding="async"></div>
        <?php endforeach; ?>
      </div></div>
      <?php if (count($heroPhotos)>1): ?>
      <button type="button" class="showcase-arrow showcase-prev" aria-label="Previous photo"><svg><use href="#i-chev-l"/></svg></button>
      <button type="button" class="showcase-arrow showcase-next" aria-label="Next photo"><svg><use href="#i-chev-r"/></svg></button>
      <div class="showcase-controls">
        <?php foreach (array_keys($heroPhotos) as $i=>$src): ?><button type="button" class="showcase-dot" aria-label="Show photo <?= $i+1 ?>" aria-current="<?= $i===0?'true':'false' ?>"></button><?php endforeach; ?>

      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="container hero-inner"><div class="hero-copy">
      <span class="heart-rule" aria-hidden="true"><svg><use href="#i-heart"/></svg></span>
      <h1 class="showcase-tag"><?= h(setting('hero_tagline')) ?> <svg class="ic-heart" aria-hidden="true"><use href="#i-heart"/></svg></h1>
      <a href="#menu" class="btn btn-primary">Explore Our Menu <svg><use href="#i-arrow"/></svg></a>
    </div></div>
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
      <div class="catalogue-tabs" aria-label="Product categories"><button type="button" data-filter="all" aria-pressed="true">All</button><?php foreach($groups as $category=>$items): ?><button type="button" data-filter="<?= h($category) ?>" aria-pressed="false"><?= h($category) ?></button><?php endforeach; ?></div>
      <?php foreach($groups as $category=>$items): ?>
      <div class="catalogue-group" data-category="<?= h($category) ?>"><h3><?= h($category) ?></h3><div class="catalogue-list">
      <?php foreach($items as $m): ?><article class="catalogue-item">
      <?php if ($m['image'] && !str_starts_with($m['image'],'images/menu-')): ?><button type="button" class="product-photo-trigger" data-photo="<?= h($m['image']) ?>" data-name="<?= h($m['name']) ?>" data-price-label="<?= h(catalogue_price($m)) ?>" data-product-id="<?= (int)$m['id'] ?>" data-can-order="<?= $m['available'] && $m['amount']!==null?'1':'0' ?>" aria-label="View photo of <?= h($m['name']) ?>"><img src="<?= h($m['image']) ?>" alt="<?= h($m['name']) ?>" loading="lazy"></button><?php endif; ?>
      <div class="detail"><h4><?= h($m['name']) ?></h4><?php if($m['description']): ?><p><?= h($m['description']) ?></p><?php endif; ?><span class="catalogue-price"><?= h(catalogue_price($m)) ?></span></div>
      <?php if($m['available'] && $m['amount']!==null): ?><button type="button" class="btn btn-outline" data-order="<?= (int)$m['id'] ?>">+ Add</button><?php else: ?><small><?= $m['available']?'Enquire':'Sold out' ?></small><?php endif; ?>
      </article><?php endforeach; ?></div></div><?php endforeach; ?>
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
            <div class="full order-products"><h3>What Would You Like?</h3>
            <label for="product-picker" style="display:block">Select product</label>
            <div style="display:flex;gap:8px;align-items:center"><select id="product-picker"><option value="">Choose a product</option>
            <?php foreach($groups as $category=>$items): ?><optgroup label="<?= h($category) ?>"><?php foreach($items as $m): if(!$m['available'] || $m['amount']===null) continue; ?><option value="<?= (int)$m['id'] ?>" data-price="<?= h($m['amount']) ?>"><?= h($m['name']) ?> — <?= h(catalogue_price($m)) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select><button type="button" class="btn btn-primary" id="add-product">Add</button></div>
            <div id="selected-products"></div>
            <div class="order-total">Estimated total: ₹<span id="order-total">0</span></div>
            <script type="application/json" id="previous-products"><?= json_encode($old['products']??[],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script></div>
            <label>Your name*<input name="name" required maxlength="120" value="<?= $o('name') ?>" autocomplete="name"></label>
            <label>Phone / WhatsApp*<input name="phone" required maxlength="20" inputmode="tel" value="<?= $o('phone') ?>" autocomplete="tel"></label>
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
<dialog class="product-dialog" id="product-photo-dialog" aria-labelledby="product-dialog-name">
  <div class="product-dialog-header"><h2 id="product-dialog-name"></h2><button type="button" class="product-dialog-close" aria-label="Close product photo" autofocus>×</button></div>
  <img class="product-dialog-photo" id="product-dialog-image" alt="">
  <div class="product-dialog-footer"><span class="product-dialog-price" id="product-dialog-price"></span><button type="button" class="btn btn-primary" id="product-dialog-add">+ Add to Order</button></div>
</dialog>

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

<script src="js/main.js?v=3"></script>
<script>
(()=>{const picker=document.getElementById('product-picker'),box=document.getElementById('selected-products'),total=document.getElementById('order-total');const selected=new Map();const products=new Map(picker?[...picker.options].filter(o=>o.value).map(o=>[o.value,{label:o.textContent,price:Number(o.dataset.price)}]):[]);function calc(){if(total)total.textContent=[...selected].reduce((s,[id,q])=>s+products.get(id).price*q,0).toLocaleString('en-IN',{maximumFractionDigits:2})}
function render(){if(!box)return;box.replaceChildren();for(const [id,q] of selected){const row=document.createElement('div');row.className='order-group';row.style.cssText='display:grid;grid-template-columns:minmax(0,1fr) 72px 36px;gap:8px;align-items:center';const label=document.createElement('span');label.textContent=products.get(id).label;const input=document.createElement('input');input.type='number';input.min='1';input.max='999';input.step='1';input.required=true;input.name='products['+id+']';input.value=q;input.setAttribute('aria-label','Quantity for '+products.get(id).label);input.addEventListener('input',()=>{selected.set(id,Math.min(999,Math.max(1,Number(input.value)||1)));calc()});const remove=document.createElement('button');remove.type='button';remove.className='btn btn-outline';remove.style.padding='5px';remove.textContent='×';remove.setAttribute('aria-label','Remove '+products.get(id).label);remove.onclick=()=>{selected.delete(id);render()};row.append(label,input,remove);box.append(row)}calc()}
function add(id){if(!products.has(id))return;selected.set(id,Math.min(999,(selected.get(id)||0)+1));render()}
if(picker){try{for(const [id,q] of Object.entries(JSON.parse(document.getElementById('previous-products').textContent))){if(products.has(id)&&Number.isInteger(Number(q))&&Number(q)>0)selected.set(id,Math.min(999,Number(q)))}}catch(e){}render();document.getElementById('add-product').onclick=()=>{add(picker.value);picker.value=''};}
document.querySelectorAll('[data-order]').forEach(b=>b.addEventListener('click',()=>{if(!picker){location.hash='contact';return}add(b.dataset.order);b.textContent='Added ✓';setTimeout(()=>b.textContent='+ Add',800)}));document.querySelectorAll('[data-filter]').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('[data-filter]').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));document.querySelectorAll('[data-category]').forEach(g=>g.hidden=b.dataset.filter!=='all'&&g.dataset.category!==b.dataset.filter)}));})();
</script><script>
(()=>{const root=document.getElementById('home-showcase');if(!root)return;
const track=root.querySelector('.showcase-track'),win=root.querySelector('.showcase-window'),original=[...track.children],n=original.length;
if(n<2){track.style.margin='auto';return}
const dots=[...root.querySelectorAll('.showcase-dot')],motion=matchMedia('(prefers-reduced-motion: reduce)');
let index=0,step=0,base=0,paused=motion.matches,hover=false,focused=false,startX=null,last=0,frame=0,tween=null;
const clone=(card)=>{const c=card.cloneNode(true);c.setAttribute('aria-hidden','true');c.querySelector('img').alt='';return c};
for(let repeat=0;repeat<3;repeat++)for(const c of original)track.append(clone(c));
for(let repeat=0;repeat<2;repeat++)for(let j=n-1;j>=0;j--)track.prepend(clone(original[j]));
const wrap=x=>((x%n)+n)%n;
function position(){index=wrap(index);track.style.transform='translate3d('+(base-index*step)+'px,0,0)';const active=wrap(Math.round(index));dots.forEach((d,i)=>d.setAttribute('aria-current',String(i===active)))}
function layout(){const c=original[0];step=c.offsetWidth+parseFloat(getComputedStyle(track).columnGap||0);base=(win.clientWidth-c.offsetWidth)/2-2*n*step;position()}
function move(to){let delta=to-index;if(motion.matches){index=to;position();return}tween={from:index,delta,start:performance.now()};}
function tick(now){const dt=last?Math.min(now-last,50):0;last=now;if(tween){const p=Math.min(1,(now-tween.start)/420);index=tween.from+tween.delta*(1-Math.pow(1-p,3));position();if(p===1)tween=null;}else if(!paused&&!hover&&!focused&&!document.hidden){index+=dt/1500;position()}frame=requestAnimationFrame(tick)}
root.querySelector('.showcase-prev').onclick=()=>move(Math.ceil(index)-1);root.querySelector('.showcase-next').onclick=()=>move(Math.floor(index)+1);dots.forEach((d,i)=>d.onclick=()=>{let delta=wrap(i-index);if(delta>n/2)delta-=n;move(index+delta)});
root.addEventListener('mouseenter',()=>{hover=true});root.addEventListener('mouseleave',()=>{hover=false});root.addEventListener('focusin',()=>{focused=true});root.addEventListener('focusout',e=>{if(!root.contains(e.relatedTarget))focused=false});
win.addEventListener('touchstart',e=>{startX=e.touches[0].clientX},{passive:true});win.addEventListener('touchend',e=>{if(startX===null)return;const delta=e.changedTouches[0].clientX-startX;startX=null;if(Math.abs(delta)>35)move(index+(delta<0?1:-1))},{passive:true});
motion.addEventListener?.('change',e=>{paused=e.matches;tween=null});window.addEventListener('resize',layout);layout();frame=requestAnimationFrame(tick);

})();
</script>
<script>
(()=>{const dialog=document.getElementById('product-photo-dialog'),photo=document.getElementById('product-dialog-image'),name=document.getElementById('product-dialog-name'),price=document.getElementById('product-dialog-price'),add=document.getElementById('product-dialog-add');let trigger=null,previousOverflow='';
document.querySelectorAll('.product-photo-trigger').forEach(b=>b.addEventListener('click',()=>{trigger=b;photo.src=b.dataset.photo;photo.alt=b.dataset.name;name.textContent=b.dataset.name;price.textContent=b.dataset.priceLabel;add.hidden=b.dataset.canOrder!=='1';add.textContent='+ Add to Order';previousOverflow=document.body.style.overflow;dialog.showModal();document.body.style.overflow='hidden';}));
dialog.querySelector('.product-dialog-close').onclick=()=>dialog.close();
dialog.addEventListener('click',e=>{if(e.target!==dialog)return;const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)dialog.close()});
dialog.addEventListener('close',()=>{document.body.style.overflow=previousOverflow;photo.removeAttribute('src');if(trigger)trigger.focus({preventScroll:true})});
add.onclick=()=>{if(!trigger)return;const button=trigger.closest('.catalogue-item').querySelector('[data-order]');if(button){button.click();add.textContent='Added ✓';}dialog.close()};
})();
</script>
</body>
</html>
<?php ig_sync_after_response();
