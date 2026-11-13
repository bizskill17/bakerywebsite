<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_login();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $name  = trim((string)($_POST['name'] ?? ''));
        $price = trim((string)($_POST['price'] ?? ''));
        $desc  = trim((string)($_POST['description'] ?? ''));
        $avail = isset($_POST['available']) ? 1 : 0;
        $existing = $id ? q('SELECT * FROM menu_items WHERE id = ?', [$id])->fetch() : null;

        if ($name === '') $errors[] = 'Please enter a name.';
        $image = $existing['image'] ?? '';
        if (!$errors && has_upload('image')) {
            try { $image = save_image($_FILES['image']); }
            catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
        }
        if (!$errors && $image === '') $errors[] = 'Please choose a photo.';

        if (!$errors) {
            if ($existing) {
                if ($image !== $existing['image']) delete_image($existing['image']);
                q('UPDATE menu_items SET name=?, price=?, description=?, available=?, image=? WHERE id=?',
                  [mb_substr($name, 0, 120), mb_substr($price, 0, 60), $desc, $avail, $image, $id]);
                flash("“{$name}” saved.");
            } else {
                q('INSERT INTO menu_items (name, price, description, available, image, sort_order) VALUES (?,?,?,?,?,?)',
                  [mb_substr($name, 0, 120), mb_substr($price, 0, 60), $desc, $avail, $image, next_sort('menu_items')]);
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
$showForm = isset($_GET['new']) || $editId || $errors;
$item = $editId ? q('SELECT * FROM menu_items WHERE id = ?', [$editId])->fetch() : null;
if ($errors) $item = array_merge($item ?: ['image' => ''], array_intersect_key($_POST, array_flip(['name', 'price', 'description'])), ['available' => isset($_POST['available']) ? 1 : 0]);

admin_header($showForm ? ($editId ? 'Edit menu item' : 'Add menu item') : 'Menu Items', 'menu.php');

if ($showForm):
    $item ??= ['name' => '', 'price' => '', 'description' => '', 'image' => '', 'available' => 1];
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
        <small class="muted">JPG/PNG, landscape works best.</small>
      </div>
      <div>
        <label>Name*<input name="name" value="<?= h($item['name']) ?>" required maxlength="120" placeholder="e.g. Walnut Brownies"></label>
        <label>Price <small class="muted">(optional – shown under the name)</small><input name="price" value="<?= h($item['price']) ?>" maxlength="60" placeholder="e.g. ₹450 / box of 6"></label>
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
    $rows = q('SELECT * FROM menu_items ORDER BY sort_order, id')->fetchAll();
?>
  <div class="toolbar"><p class="muted">These appear in “Our Menu” on your website, in this order. On phones the first 3 show until “View Full Menu” is tapped.</p>
    <a class="btn" href="?new=1">+ Add menu item</a></div>
  <div class="item-list">
  <?php foreach ($rows as $i => $m): ?>
    <div class="item-row <?= $m['available'] ? '' : 'off' ?>">
      <img src="<?= img($m['image']) ?>" alt="">
      <div class="item-info">
        <b><?= h($m['name']) ?></b>
        <small class="muted"><?= h($m['price'] ?: 'No price shown') ?><?= $m['available'] ? '' : ' · <span class="status s-cancelled">Sold out</span>' ?></small>
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
