<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_login();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'import_photos') {
        try {
            if (!class_exists('ZipArchive')) throw new RuntimeException('ZIP support is not enabled on hosting.');
            $file=$_FILES['photo_zip']??[];
            if (($file['error']??1)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || $file['size']>10*1024*1024) throw new RuntimeException('Choose a ZIP file up to 10 MB.');
            $map=['atta-dry-fruits-cake'=>'Atta Dry Fruits Cake','cake-without-milkmaid'=>'Cake Without Milkmaid','choco-chip-brownie'=>'Choco Chip Brownie','chocolate-cake'=>'Chocolate Cake','chocolate-chip-brownie-cake'=>'Chocolate Chip Brownie Cake','chocolate-chip-cake'=>'Chocolate Chip Cake','chocolate-muffin'=>'Chocolate Muffin','classic-dry-fruits-cake'=>'Classic Dry Fruits Cake','lotus-biscoff-brownie'=>'Lotus Biscoff Brownie','marble-cake'=>'Marble Cake','mawa-cake'=>'Mawa Cake','nutella-brownie'=>'Nutella Brownie','oats-cake'=>'Oats Cake','orange-almond-cake'=>'Orange Almond Cake','vanilla-muffin'=>'Vanilla Muffin','walnut-brownie'=>'Walnut Brownie','wheat-and-jaggery-cake'=>'Wheat & Jaggery Cake'];
            $z=new ZipArchive(); if ($z->open($file['tmp_name'])!==true) throw new RuntimeException('Cannot open ZIP.');
            $count=0;$logo=false;$seen=[];
            try {
                for ($i=0;$i<$z->numFiles;$i++) {
                    $stat=$z->statIndex($i); $base=basename($stat['name']); $key=pathinfo($base,PATHINFO_FILENAME);
                    if (!isset($map[$key]) && $key!=='original-logo') continue;
                    if (isset($seen[$key]) || strtolower(pathinfo($base,PATHINFO_EXTENSION))!=='jpg' || $stat['size']>5*1024*1024) continue;
                    $bytes=$z->getFromIndex($i,5*1024*1024); if ($bytes===false) continue;
                    $info=@getimagesizefromstring($bytes); if (!$info || $info[2]!==IMAGETYPE_JPEG || $info[0]*$info[1]>30000000) continue;
                    if ($key!=='original-logo') { $row=q('SELECT id FROM menu_items WHERE name=? AND active=1',[$map[$key]])->fetch(); if (!$row) continue; }
                    $dir=ROOT.'/uploads'; if (!is_dir($dir) && !mkdir($dir,0755,true)) throw new RuntimeException('Cannot create uploads folder.');
                    $rel='uploads/catalogue-'.$key.'-'.bin2hex(random_bytes(6)).'.jpg';
                    if (file_put_contents(ROOT.'/'.$rel,$bytes)!==strlen($bytes)) throw new RuntimeException('Cannot write image.');
                    if ($key==='original-logo') {save_setting('brand_logo',$rel);$logo=true;} else {q('UPDATE menu_items SET image=? WHERE id=?',[$rel,$row['id']]);$count++;}
                    $seen[$key]=true;
                }
            } finally {$z->close();}
            flash('Imported '.$count.' product photographs'.($logo?' and original logo':'').'.');
            redirect('menu.php');
        } catch (Throwable $e) { $errors[]=$e->getMessage(); }
    } elseif ($action === 'save') {
        $name  = trim((string)($_POST['name'] ?? ''));
        $price = trim((string)($_POST['price'] ?? ''));
        $category = trim((string)($_POST['category'] ?? ''));
        $unit = trim((string)($_POST['unit'] ?? ''));
        $active = isset($_POST['active']) ? 1 : 0;
        if ($category === '' || mb_strlen($category)>100) $errors[] = 'Enter a category (max 100 characters).';
        if (!in_array($unit,['piece','cake','box','kg'],true)) $errors[] = 'Choose a unit.';
        if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D',$price) || (float)$price <= 0) $errors[] = 'Enter a positive numeric price.';
        $desc  = trim((string)($_POST['description'] ?? ''));
        $avail = isset($_POST['available']) ? 1 : 0;
        $existing = $id ? q('SELECT * FROM menu_items WHERE id = ?', [$id])->fetch() : null;

        if ($name === '') $errors[] = 'Please enter a name.';
        $image = $existing['image'] ?? '';
        if (!$errors && has_upload('image')) {
            try { $image = save_image($_FILES['image']); }
            catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
        }


        if (!$errors) {
            if ($existing) {
                if ($image !== $existing['image']) delete_image($existing['image']);
                q('UPDATE menu_items SET name=?, price=?, amount=?, category=?, unit=?, active=?, description=?, available=?, image=? WHERE id=?',
                  [mb_substr($name, 0, 120), $price, $price, $category, $unit, $active, $desc, $avail, $image, $id]);
                flash("“{$name}” saved.");
            } else {
                q('INSERT INTO menu_items (name, price, amount, category, unit, active, description, available, image, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?)',
                  [mb_substr($name, 0, 120), $price, $price, $category, $unit, $active, $desc, $avail, $image, next_sort('menu_items')]);
                flash("“{$name}” added to your menu.");
            }
            redirect('menu.php');
        }
        if ($image !== '' && $image !== ($existing['image'] ?? '')) delete_image($image);
    } else {
        if ($action === 'delete') {
            $row = q('SELECT * FROM menu_items WHERE id = ?', [$id])->fetch();
            if ($row) { delete_image($row['image']); q('DELETE FROM menu_items WHERE id = ?', [$id]); flash("“{$row['name']}” deleted."); }
        } elseif ($action === 'toggle') {
            q('UPDATE menu_items SET available = 1 - available WHERE id = ?', [$id]);
            flash('Availability updated.');
        } elseif ($action === 'up' || $action === 'down') {
            move_row('menu_items', $id, $action);
        }
        redirect('menu.php');
    }
}

$editId = (int)($_GET['edit'] ?? $_POST['id'] ?? 0);
$showForm = isset($_GET['new']) || $editId || ($errors && ($_POST['action']??'')!=='import_photos');
$item = $editId ? q('SELECT * FROM menu_items WHERE id = ?', [$editId])->fetch() : null;
if ($errors) $item = array_merge($item ?: ['image' => ''], array_intersect_key($_POST, array_flip(['name', 'price', 'description','category','unit'])), ['available' => isset($_POST['available']) ? 1 : 0, 'active'=>isset($_POST['active'])?1:0, 'amount'=>$_POST['price']??'']);

admin_header($showForm ? ($editId ? 'Edit menu item' : 'Add menu item') : 'Menu Items', 'menu.php');

if ($showForm):
    $item ??= ['name' => '', 'price' => '', 'description' => '', 'image' => '', 'available' => 1, 'active'=>1, 'category'=>'Brownies','unit'=>'piece','amount'=>''];
?>
  <?php foreach ($errors as $e): ?><div class="alert err"><?= h($e) ?></div><?php endforeach; ?>
  <form class="panel form" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)$editId ?>">
    <div class="form-cols">
      <div class="photo-pick">
        <img id="prev" src="<?= $item['image'] ? img($item['image']) : '' ?>" alt="" <?= $item['image'] ? '' : 'hidden' ?>>
        <label class="btn ghost">Choose photo<input type="file" name="image" accept="image/*" data-preview="prev" hidden></label>
        <small class="muted">Upload an authentic product photograph when available.</small>
      </div>
      <div>
        <label>Name*<input name="name" value="<?= h($item['name']) ?>" required maxlength="120" placeholder="e.g. Walnut Brownies"></label>
        <label>Category*<input name="category" list="categories" required maxlength="100" value="<?= h($item['category'] ?? 'Brownies') ?>"><datalist id="categories"><?php foreach(array_unique(array_merge(catalogue_categories(),q('SELECT DISTINCT category FROM menu_items')->fetchAll(PDO::FETCH_COLUMN))) as $c): ?><option value="<?= h($c) ?>"><?php endforeach; ?></datalist></label>
        <label>Selling price (₹)*<input type="number" min="0.01" max="99999999" step="0.01" name="price" value="<?= h($item['amount'] ?? '') ?>" required></label>
        <label>Unit*<select name="unit"><?php foreach(['piece','cake','box','kg'] as $u): ?><option <?= ($item['unit']??'piece')===$u?'selected':'' ?>><?= h($u) ?></option><?php endforeach; ?></select></label>
        <label class="check"><input type="checkbox" name="active" <?= ($item['active']??1)?'checked':'' ?>> Active on website</label>
        <label>Short description <small class="muted">(optional)</small><textarea name="description" rows="3" placeholder="e.g. Fudgy, eggless, topped with roasted walnuts"><?= h($item['description']) ?></textarea></label>
        <label class="check"><input type="checkbox" name="available" <?= $item['available'] ? 'checked' : '' ?>> Available to order (untick to show “Sold out”)</label>
      </div>
    </div>
    <div class="form-actions">
      <button class="btn" type="submit">Save</button>
      <a class="btn ghost" href="menu.php">Cancel</a>
    </div>
  </form>
<?php else:
    $rows = q('SELECT * FROM menu_items ORDER BY active DESC, sort_order, id')->fetchAll();
?>
  <?php foreach ($errors as $e): ?><div class="alert err"><?= h($e) ?></div><?php endforeach; ?>
  <form class="panel form" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="import_photos"><label>Import Bliss Bites product photographs and original logo (ZIP)<input type="file" name="photo_zip" accept=".zip" required></label><button class="btn" type="submit">Import photographs</button></form>
  <div class="toolbar">
    <a class="btn" href="?new=1">+ Add menu item</a></div>
  <div class="item-list">
  <?php foreach ($rows as $i => $m): ?>
    <div class="item-row <?= $m['available'] ? '' : 'off' ?>">
      <?php if ($m['image']): ?><img src="<?= img($m['image']) ?>" alt=""><?php endif; ?>
      <div class="item-info">
        <b><?= h($m['name']) ?></b>
        <small class="muted"><?= h($m['category']) ?> · <?= h(catalogue_price($m)) ?><?= $m['active'] ? '' : ' · Inactive' ?><?= $m['available'] ? '' : ' · <span class="status s-cancelled">Sold out</span>' ?></small>
      </div>
      <div class="item-actions">
        <?= $i > 0 ? action_button('up', (int)$m['id'], '↑') : '<span class="btn ghost sm disabled">↑</span>' ?>
        <?= $i < count($rows) - 1 ? action_button('down', (int)$m['id'], '↓') : '<span class="btn ghost sm disabled">↓</span>' ?>
        <?= action_button('toggle', (int)$m['id'], $m['available'] ? 'Mark sold out' : 'Mark available') ?>
        <a class="btn ghost sm" href="?edit=<?= (int)$m['id'] ?>">Edit</a>
        <?= action_button('delete', (int)$m['id'], 'Delete', 'ghost sm danger', 'Delete “' . $m['name'] . '” from the menu?') ?>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="panel"><p class="muted">No menu items yet.</p></div><?php endif; ?>
  </div>
<?php endif;
admin_footer();
