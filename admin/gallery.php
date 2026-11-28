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
        case 'delete':
            $row = q('SELECT * FROM gallery WHERE id = ?', [$id])->fetch();
            if ($row) { delete_image($row['image']); q('DELETE FROM gallery WHERE id = ?', [$id]); }
            if (($_POST['ajax'] ?? '') === '1') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok'=>true,'count'=>(int)q('SELECT COUNT(*) FROM gallery')->fetchColumn()]);
                exit;
            }
            if ($row) flash('Photo removed.');
            break;
        case 'up': case 'down':
            move_row('gallery', $id, $_POST['action']);
            break;
    }
    redirect('gallery.php');
}

$rows = q('SELECT * FROM gallery ORDER BY id DESC')->fetchAll();
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

<p class="muted" style="font-size:13px;margin:0 0 12px;font-weight:500">Total Photos: <span id="gallery-count"><?= count($rows) ?></span></p><div id="gallery-notice" role="status" style="position:fixed;bottom:20px;right:20px;z-index:1000;max-width:calc(100% - 40px)" hidden></div>
<div class="photo-grid">
<?php foreach ($rows as $i => $g): ?>
  <div class="photo-card">
    <img src="<?= img($g['image']) ?>" alt="">
    <div class="item-actions" style="display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:nowrap;margin-top:8px">
      <form method="post" class="inline" data-confirm="Remove this photo from the gallery?" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button class="btn ghost sm danger" type="submit" title="Delete photo" aria-label="Delete photo" style="width:34px;height:34px;padding:0;display:inline-flex;align-items:center;justify-content:center"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 10v7M14 10v7"/></svg></button></form>
    </div>
  </div>
<?php endforeach; ?>
</div>
<div id="gallery-empty" class="panel" <?= $rows?'hidden':'' ?>><p class="muted">No gallery photos yet. Add photos to display them in the homepage carousel.</p></div>
<script>
(()=>{const grid=document.querySelector('.photo-grid'),notice=document.getElementById('gallery-notice');let timer;
function notify(message,error=false){clearTimeout(timer);notice.className='alert '+(error?'err':'ok');notice.textContent=message;notice.hidden=false;if(!error)timer=setTimeout(()=>notice.hidden=true,3000)}
grid.addEventListener('submit',async e=>{const form=e.target;if(!form.matches('form[data-confirm]'))return;e.preventDefault();e.stopPropagation();if(!confirm(form.dataset.confirm))return;const button=form.querySelector('button');if(button.disabled)return;button.disabled=true;try{const data=new FormData(form);data.set('ajax','1');const response=await fetch('gallery.php',{method:'POST',body:data,credentials:'same-origin'});if(!response.ok)throw Error();const result=await response.json();if(!result.ok)throw Error();form.closest('.photo-card').remove();document.getElementById('gallery-count').textContent=result.count;document.getElementById('gallery-empty').hidden=grid.querySelector('.photo-card')!==null;notify('Photo removed.')}catch(err){notify('Could not confirm deletion. Refresh the page to check before retrying.',true);button.disabled=false}});
})();
document.querySelectorAll('.alert:not(.err):not(.warn):not(#gallery-notice)').forEach(el=>{setTimeout(()=>{el.style.transition='opacity 250ms ease';el.style.opacity='0';setTimeout(()=>el.remove(),250)},3000)});
</script>
<?php admin_footer();
