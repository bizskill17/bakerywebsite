<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_login();

$textFields = [
    'Contact & links' => [
        'whatsapp_number'  => ['WhatsApp number', 'With country code, e.g. 91 98765 43210', 'input'],
        'whatsapp_message' => ['Pre-filled WhatsApp message', 'The text customers see when they tap “Order on WhatsApp”', 'input'],
        'instagram_handle' => ['Instagram username', 'Without @, e.g. blissbites', 'input'],
        'delivery_area'    => ['Delivery city', 'Shown as “… Delivery” on the website', 'input'],
    ],
    'Top banner' => [
        'hero_tagline' => ['Tagline (computer)', 'Under the big logo, e.g. Homemade Brownies, Cakes and more, baked with Love', 'input'],
        'hero_title'   => ['Headline (phone)', 'Big heading on phones, e.g. Homemade Brownies, Cakes & More', 'input'],
    ],
    'About section' => [
        'about_subtitle' => ['Highlighted line', 'e.g. Made with Love, Baked with Happiness', 'input'],
        'about_text'     => ['About text', 'Leave an empty line between paragraphs', 'textarea'],
        'about_quote'    => ['Closing line', 'e.g. Because at Bliss Bites, every bite brings a smile!', 'input'],
    ],
];
$imageFields = [
    'hero_image'         => ['Top banner photo', 'Wide (landscape) photo'],
    'about_image'        => ['About photo (computer)', 'Photo of you baking works well'],
    'about_image_mobile' => ['About photo (phone)', 'Landscape photo of your bakes'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($textFields as $group) {
        foreach ($group as $key => $_) {
            $v = trim(str_replace("\r\n", "\n", (string)($_POST[$key] ?? '')));
            if ($key === 'instagram_handle') $v = ltrim(preg_replace('~^https?://(www\.)?instagram\.com/~i', '', rtrim($v, '/')), '@');
            save_setting($key, mb_substr($v, 0, 5000));
        }
    }
    $ok = true;
    foreach ($imageFields as $key => $_) {
        if (!has_upload($key)) continue;
        try {
            $new = save_image($_FILES[$key]);
            delete_image(setting($key));
            save_setting($key, $new);
        } catch (RuntimeException $e) { flash($imageFields[$key][0] . ': ' . $e->getMessage(), 'err'); $ok = false; }
    }
    if ($ok) flash('Website content saved. Open the website to see your changes.');
    redirect('settings.php');
}

$s = settings(true);
admin_header('Website Content', 'settings.php');
?>
<form class="form" method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <?php foreach ($textFields as $groupName => $group): ?>
  <section class="panel">
    <h2><?= h($groupName) ?></h2>
    <?php foreach ($group as $key => [$label, $hint, $type]): ?>
      <label><?= h($label) ?> <small class="muted"><?= h($hint) ?></small>
        <?php if ($type === 'textarea'): ?>
          <textarea name="<?= $key ?>" rows="9"><?= h($s[$key] ?? '') ?></textarea>
        <?php else: ?>
          <input name="<?= $key ?>" value="<?= h($s[$key] ?? '') ?>">
        <?php endif; ?>
      </label>
    <?php endforeach; ?>
  </section>
  <?php endforeach; ?>

  <section class="panel">
    <h2>Photos</h2>
    <div class="photo-grid">
    <?php foreach ($imageFields as $key => [$label, $hint]): ?>
      <div class="photo-card">
        <b><?= h($label) ?></b>
        <img id="p-<?= $key ?>" src="<?= img($s[$key] ?? '') ?>" alt="">
        <label class="btn ghost sm">Replace photo<input type="file" name="<?= $key ?>" accept="image/*" data-preview="p-<?= $key ?>" hidden></label>
        <small class="muted"><?= h($hint) ?></small>
      </div>
    <?php endforeach; ?>
    </div>
  </section>

  <div class="form-actions sticky"><button class="btn" type="submit">Save all changes</button></div>
</form>
<?php admin_footer();
