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
    }
    redirect('stories.php');
}

$rows = q('SELECT * FROM stories ORDER BY id DESC')->fetchAll();
admin_header('Baking Stories', 'stories.php');
?>
<?php if (setting('instagram_sync') === '1' && setting('instagram_token') !== ''): ?>
  <div class="alert warn">Instagram sync is ON, so your website shows your latest Instagram reels here instead. These photos are used only if sync is off or finds none. <a href="instagram.php">Instagram Sync →</a></div>
<?php endif; ?>

<style>
.story-toolbar{display:flex;justify-content:flex-end;margin-bottom:16px}
.story-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px}
.story-tile{background:#fff;border:1px solid #edcbd7;border-radius:14px;padding:10px;min-width:0}
.story-cover{position:relative;display:block;aspect-ratio:3/4;overflow:hidden;border-radius:9px;background:#fbe4ed}
.story-cover img{width:100%;height:100%;object-fit:cover;display:block}
.story-play{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:40px;height:40px;border-radius:50%;background:#ffffffdd;color:#c2185b;display:grid;place-items:center;font-size:18px}
.story-title{font-weight:600;font-size:14px;overflow-wrap:anywhere;margin:10px 0}
.story-actions{display:flex;justify-content:flex-end;align-items:center;gap:7px;border-top:1px solid #f2dce5;padding-top:9px}
.story-actions form{margin:0}.story-action{width:34px;height:34px;padding:0;border:1px solid #edcbd7;border-radius:8px;background:#fff;color:#b30950;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;text-decoration:none}
.story-action svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.story-dialog{width:min(460px,calc(100% - 28px));max-height:90vh;overflow:auto;border:1px solid #edcbd7;border-radius:16px;padding:24px;background:white;color:inherit;box-sizing:border-box}
.story-dialog::backdrop{background:#35132266}.story-dialog h2{color:#a90e4c;margin:0 0 18px}
.story-dialog .cover-preview{display:block;width:100%;max-height:200px;object-fit:contain;background:#fcf3f6;border-radius:10px;margin-bottom:10px}
.story-dialog .cover-preview[hidden]{display:none}.story-dialog input[type=file]{font-size:13px}
.story-dialog-buttons{display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap}
.story-count{margin-top:12px;font-size:12px;color:#927480}
@media(min-width:1000px){.story-grid{grid-template-columns:repeat(auto-fill,minmax(200px,240px))}}
@media(max-width:480px){.story-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.story-tile{padding:7px}.story-dialog{padding:18px}}
</style>
<div class="story-toolbar"><button type="button" class="btn" data-story-open="story-add">+ Add Story</button></div>
<p class="story-count" style="margin:0 0 10px;text-align:left"><?= count($rows) ?> Baking <?= count($rows)===1?'Story':'Stories' ?></p>
<div class="story-grid">
<?php foreach($rows as $s): $sid=(int)$s['id']; $link=clean_link((string)$s['link']); ?>
  <article class="story-tile">
    <?php if($link): ?><a class="story-cover" href="<?= h($link) ?>" target="_blank" rel="noopener noreferrer" aria-label="Open story"><?php else: ?><div class="story-cover"><?php endif; ?>
      <img src="<?= img($s['image']) ?>" alt="<?= h($s['caption'] ?: 'Baking story') ?>" loading="lazy">
      <?php if($link): ?><span class="story-play" aria-hidden="true">▶</span><?php endif; ?>
    <?php if($link): ?></a><?php else: ?></div><?php endif; ?>
    <p class="story-title"><?= h($s['caption'] ?: 'Baking Story') ?></p>
    <div class="story-actions">
      <?php if($link): ?><a class="story-action" href="<?= h($link) ?>" target="_blank" rel="noopener noreferrer" title="Open reel" aria-label="Open reel"><svg viewBox="0 0 24 24"><path d="M13 5h6v6M19 5l-9 9M19 13v6H5V5h6"/></svg></a><?php endif; ?>
      <button type="button" class="story-action" data-story-open="story-edit-<?= $sid ?>" title="Edit story" aria-label="Edit story"><svg viewBox="0 0 24 24"><path d="m15 5 4 4M4 20l4-1 12-12-4-4L4 15z"/></svg></button>
      <form method="post" data-confirm="Remove this story?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $sid ?>"><button class="story-action" type="submit" title="Delete story" aria-label="Delete story"><svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 10v7M14 10v7"/></svg></button></form>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?php if(!$rows): ?><div class="panel"><p class="muted">No stories yet. Add a story to get started.</p></div><?php endif; ?>

<?php foreach(array_merge([null],$rows) as $story): $editing=$story!==null; $sid=$editing?(int)$story['id']:0; $dialogId=$editing?'story-edit-'.$sid:'story-add'; ?>
<dialog class="story-dialog" id="<?= h($dialogId) ?>" aria-labelledby="<?= h($dialogId) ?>-title">
  <h2 id="<?= h($dialogId) ?>-title"><?= $editing?'Edit Story':'Add Story' ?></h2>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="action" value="<?= $editing?'update':'add' ?>">
    <?php if($editing): ?><input type="hidden" name="id" value="<?= $sid ?>"><?php endif; ?>
    <img class="cover-preview" alt="Cover preview" <?= $editing?'src="'.img($story['image']).'"':'hidden' ?>>
    <label><?= $editing?'Replace Cover Photo':'Cover Photo' ?><input type="file" name="image" accept="image/*" <?= $editing?'':'required' ?>></label>
    <label>Reel / Video Link<input name="link" maxlength="255" value="<?= h($story['link']??'') ?>" placeholder="https://www.instagram.com/reel/…"></label>
    <label>Story Title<input name="caption" maxlength="160" value="<?= h($story['caption']??'') ?>" placeholder="Enter story title"></label>
    <div class="story-dialog-buttons"><button class="btn ghost" type="button" data-story-close>Cancel</button><button class="btn" type="submit">Save</button></div>
  </form>
</dialog>
<?php endforeach; ?>
<script>
(()=>{document.querySelectorAll('[data-story-open]').forEach(b=>b.addEventListener('click',()=>document.getElementById(b.dataset.storyOpen).showModal()));document.querySelectorAll('[data-story-close]').forEach(b=>b.addEventListener('click',()=>b.closest('dialog').close()));document.querySelectorAll('.story-dialog').forEach(d=>{const input=d.querySelector('input[type=file]'),preview=d.querySelector('.cover-preview');let url=null;input.addEventListener('change',()=>{if(!input.files.length)return;if(url)URL.revokeObjectURL(url);url=URL.createObjectURL(input.files[0]);preview.src=url;preview.hidden=false});});document.querySelectorAll('.alert:not(.err):not(.warn)').forEach(el=>setTimeout(()=>{el.style.transition='opacity 250ms';el.style.opacity='0';setTimeout(()=>el.remove(),250)},3000));})();
</script>
<?php admin_footer();
