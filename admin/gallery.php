<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    switch ($_POST['action'] ?? '') {
        case 'upload':
            $added = 0;
            foreach (upload_list('photos') as $f) {
                try {
                    q('INSERT INTO gallery (image, caption, sort_order) VALUES (?, ?, ?)', [save_image($f), '', next_sort('gallery')]);
                    $added++;
                } catch (RuntimeException $e) {
                    flash($f['name'] . ': ' . $e->getMessage(), 'err');
                }
            }
            if ($added) flash("$added photo(s) added to the gallery.");
            elseif (!upload_list('photos')) flash('Please choose at least one photo.', 'err');
            break;
        case 'caption':
            q('UPDATE gallery SET caption = ? WHERE id = ?', [mb_substr(trim((string)$_POST['caption']), 0, 160), $id]);
            flash('Description saved.');
            break;
        case 'delete':
            $row = q('SELECT * FROM gallery WHERE id = ?', [$id])->fetch();
            if ($row) { delete_image($row['image']); q('DELETE FROM gallery WHERE id = ?', [$id]); flash('Photo removed.'); }
            break;
        case 'up': case 'down':
            move_row('gallery', $id, $_POST['action']);
            break;
    }
    redirect('gallery.php');
}

$rows = q('SELECT * FROM gallery ORDER BY sort_order, id')->fetchAll();
admin_header('Instagram Gallery', 'gallery.php');
?>
<form class="panel upload-box" method="post" enctype="multipart/form-data">
  <?= csrf_field() ?><input type="hidden" name="action" value="upload">
  <div>
    <h2>Add photos</h2>
    <p class="muted">You can pick several photos at once. Computers show the first 5 photos; phones show the first 3. Square photos look best.</p>
  </div>
  <label class="btn ghost">Choose photos<input type="file" name="photos[]" accept="image/*" multiple hidden onchange="this.closest('form').querySelector('.chosen').textContent = this.files.length + ' photo(s) chosen'"></label>
  <span class="chosen muted"></span>
  <button class="btn" type="submit">Upload</button>
</form>

<div class="photo-grid">
<?php foreach ($rows as $i => $g): ?>
  <div class="photo-card <?= $i >= 5 ? 'hidden-on-site' : '' ?>">
    <img src="<?= img($g['image']) ?>" alt="">
    <?php if ($i >= 5): ?><span class="tag">Not shown (only first 5 appear)</span><?php endif; ?>
    <form method="post" class="cap">
      <?= csrf_field() ?><input type="hidden" name="action" value="caption"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
      <input name="caption" value="<?= h($g['caption']) ?>" placeholder="Short description (optional)" maxlength="160">
      <button class="btn ghost sm" type="submit">Save</button>
    </form>
    <div class="item-actions">
      <?= $i > 0 ? action_button('up', (int)$g['id'], '←') : '' ?>
      <?= $i < count($rows) - 1 ? action_button('down', (int)$g['id'], '→') : '' ?>
      <?= action_button('delete', (int)$g['id'], 'Delete', 'ghost sm danger', 'Remove this photo from the gallery?') ?>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php if (!$rows): ?><div class="panel"><p class="muted">No photos yet – the gallery section is hidden on the website until you add some.</p></div><?php endif; ?>
<?php admin_footer();
