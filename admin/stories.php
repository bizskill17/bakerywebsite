<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_login();

function clean_link(string $url): string {
    $url = trim($url);
    if ($url === '') return '';
    if (!preg_match('~^https?://~i', $url)) $url = 'https://' . $url;
    return filter_var($url, FILTER_VALIDATE_URL) ? mb_substr($url, 0, 255) : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    switch ($_POST['action'] ?? '') {
        case 'add':
            if (!has_upload('image')) { flash('Please choose a cover photo for the story.', 'err'); break; }
            try {
                q('INSERT INTO stories (image, link, caption, sort_order) VALUES (?, ?, ?, ?)',
                  [save_image($_FILES['image']), clean_link((string)$_POST['link']), mb_substr(trim((string)$_POST['caption']), 0, 160), next_sort('stories')]);
                flash('Story added.');
            } catch (RuntimeException $e) { flash($e->getMessage(), 'err'); }
            break;
        case 'update':
            $row = q('SELECT * FROM stories WHERE id = ?', [$id])->fetch();
            if (!$row) break;
            $image = $row['image'];
            if (has_upload('image')) {
                try { $image = save_image($_FILES['image']); delete_image($row['image']); }
                catch (RuntimeException $e) { flash($e->getMessage(), 'err'); break; }
            }
            q('UPDATE stories SET image = ?, link = ?, caption = ? WHERE id = ?',
              [$image, clean_link((string)$_POST['link']), mb_substr(trim((string)$_POST['caption']), 0, 160), $id]);
            flash('Story saved.');
            break;
        case 'delete':
            $row = q('SELECT * FROM stories WHERE id = ?', [$id])->fetch();
            if ($row) { delete_image($row['image']); q('DELETE FROM stories WHERE id = ?', [$id]); flash('Story removed.'); }
            break;
        case 'up': case 'down':
            move_row('stories', $id, $_POST['action']);
            break;
    }
    redirect('stories.php');
}

$rows = q('SELECT * FROM stories ORDER BY sort_order, id')->fetchAll();
admin_header('Baking Stories', 'stories.php');
?>
<?php if (setting('instagram_sync') === '1' && setting('instagram_token') !== ''): ?>
  <div class="alert warn">Instagram sync is ON, so your website shows your latest Instagram reels here instead. These photos are used only if sync is off or finds none. <a href="instagram.php">Instagram Sync →</a></div>
<?php endif; ?>
<p class="muted intro">Each story is a cover photo that opens your Instagram reel (or YouTube video) when tapped. Copy the reel's link from Instagram (⋯ → Copy link) and paste it here. Leave the link empty to open your Instagram profile.</p>

<form class="panel form" method="post" enctype="multipart/form-data">
  <?= csrf_field() ?><input type="hidden" name="action" value="add">
  <h2>Add a story</h2>
  <div class="form-cols">
    <div class="photo-pick">
      <img id="newprev" alt="" hidden>
      <label class="btn ghost">Choose cover photo<input type="file" name="image" accept="image/*" data-preview="newprev" hidden></label>
      <small class="muted">Portrait (tall) photos look best.</small>
    </div>
    <div>
      <label>Reel / video link<input name="link" placeholder="https://www.instagram.com/reel/…"></label>
      <label>Short description <small class="muted">(optional)</small><input name="caption" maxlength="160" placeholder="e.g. Making our Biscoff brownies"></label>
      <button class="btn" type="submit">Add story</button>
    </div>
  </div>
</form>

<div class="photo-grid tall">
<?php foreach ($rows as $i => $s): ?>
  <div class="story-cell">
  <form class="photo-card" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
    <img id="sp<?= (int)$s['id'] ?>" src="<?= img($s['image']) ?>" alt="">
    <label class="btn ghost sm">Change photo<input type="file" name="image" accept="image/*" data-preview="sp<?= (int)$s['id'] ?>" hidden></label>
    <input name="link" value="<?= h($s['link']) ?>" placeholder="Reel link (optional)">
    <input name="caption" value="<?= h($s['caption']) ?>" placeholder="Description (optional)" maxlength="160">
    <button class="btn sm" type="submit">Save</button>
  </form>
  <div class="item-actions under">
    <?= $i > 0 ? action_button('up', (int)$s['id'], '←') : '' ?>
    <?= $i < count($rows) - 1 ? action_button('down', (int)$s['id'], '→') : '' ?>
    <?= action_button('delete', (int)$s['id'], 'Delete', 'ghost sm danger', 'Remove this story?') ?>
  </div>
  </div>
<?php endforeach; ?>
</div>
<?php if (!$rows): ?><div class="panel"><p class="muted">No stories yet – this section is hidden on the website until you add one.</p></div><?php endif; ?>
<?php admin_footer();
