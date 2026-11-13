<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_login();

$counts = [
    'new'     => new_enquiry_count(),
    'menu'    => (int)q('SELECT COUNT(*) FROM menu_items')->fetchColumn(),
    'gallery' => (int)q('SELECT COUNT(*) FROM gallery')->fetchColumn(),
    'stories' => (int)q('SELECT COUNT(*) FROM stories')->fetchColumn(),
];
$latest = q('SELECT * FROM enquiries ORDER BY id DESC LIMIT 5')->fetchAll();
$missingWa = wa_digits(setting('whatsapp_number')) === '';

admin_header('Dashboard', 'index.php');
?>
<?php if ($missingWa): ?>
  <div class="alert warn">Your WhatsApp number is not set yet, so the “Order on WhatsApp” buttons won't work.
    <a href="settings.php">Add it in Website Content →</a></div>
<?php endif; ?>

<div class="stats">
  <a class="stat <?= $counts['new'] ? 'hot' : '' ?>" href="enquiries.php"><b><?= $counts['new'] ?></b><span>New enquiries</span></a>
  <a class="stat" href="menu.php"><b><?= $counts['menu'] ?></b><span>Menu items</span></a>
  <a class="stat" href="gallery.php"><b><?= $counts['gallery'] ?></b><span>Gallery photos</span></a>
  <a class="stat" href="stories.php"><b><?= $counts['stories'] ?></b><span>Baking stories</span></a>
</div>

<section class="panel">
  <div class="panel-head"><h2>Latest enquiries</h2><a class="btn ghost sm" href="enquiries.php">See all</a></div>
  <?php if (!$latest): ?>
    <p class="muted">No enquiries yet. When customers fill in the order form on your website, they'll show up here.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Received</th><th>Name</th><th>Item</th><th>Needed by</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($latest as $e): ?>
        <tr>
          <td><?= h(date('d M, g:i a', strtotime($e['created_at']))) ?></td>
          <td><?= h($e['name']) ?><br><small class="muted"><?= h($e['phone']) ?></small></td>
          <td><?= h($e['item']) ?><?= $e['quantity'] !== '' ? ' · ' . h($e['quantity']) : '' ?></td>
          <td><?= $e['needed_by'] ? h(date('d M Y', strtotime($e['needed_by']))) : '—' ?></td>
          <td><span class="status s-<?= h($e['status']) ?>"><?= h(ucfirst($e['status'])) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<section class="panel">
  <h2>Quick actions</h2>
  <div class="quick">
    <a class="btn" href="menu.php?new=1">+ Add menu item</a>
    <a class="btn" href="gallery.php">+ Add gallery photos</a>
    <a class="btn" href="stories.php">+ Add baking story</a>
    <a class="btn ghost" href="settings.php">Edit website text &amp; links</a>
  </div>
</section>
<?php admin_footer();
