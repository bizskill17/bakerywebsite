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
        case 'save':
            if (q('SELECT id FROM gallery WHERE id = ?', [$id])->fetch()) flash('Photo saved.');
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
admin_header('Gallery', 'gallery.php');
?>
<form id="gallery-upload" method="post" enctype="multipart/form-data" style="display:flex;justify-content:flex-end;align-items:center;gap:10px;margin-bottom:16px">
  <?= csrf_field() ?><input type="hidden" name="action" value="upload">
  <input type="file" id="gallery-files" name="photos[]" accept="image/*" multiple hidden>
  <span id="gallery-upload-status" class="muted" role="status"></span>
  <button class="btn" type="button" id="gallery-add" style="display:inline-flex;align-items:center;gap:7px"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V3m-5 5 5-5 5 5M4 16v5h16v-5"/></svg>Add Photos</button>
  <noscript><p>Enable JavaScript to upload photographs.</p></noscript>
</form>
<script>
(()=>{const form=document.getElementById('gallery-upload'),files=document.getElementById('gallery-files'),button=document.getElementById('gallery-add'),status=document.getElementById('gallery-upload-status');button.addEventListener('click',()=>files.click());files.addEventListener('change',()=>{if(!files.files.length)return;button.disabled=true;button.textContent='Uploading…';status.textContent='Uploading '+files.files.length+' photo(s)…';form.requestSubmit()})})();
</script>

<div class="photo-grid">
<?php foreach ($rows as $i => $g): ?>
  <div class="photo-card">
    <img src="<?= img($g['image']) ?>" alt="">
    <div class="item-actions" style="display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:nowrap;margin-top:8px">
      <?= action_button('save', (int)$g['id'], 'Save') ?>
      <?= action_button('delete', (int)$g['id'], 'Delete', 'ghost sm danger', 'Remove this photo from the gallery?') ?>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php if (!$rows): ?><div class="panel"><p class="muted">No gallery photos yet. Add photos to display them in the homepage carousel.</p></div><?php endif; ?>
<?php admin_footer();
