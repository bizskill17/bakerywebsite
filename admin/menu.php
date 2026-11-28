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
$showForm = isset($_GET['new']) || $editId || $errors;
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
    $rows = q('SELECT * FROM menu_items ORDER BY sort_order, id')->fetchAll();
    $groups=[];
    foreach ($rows as $m) $groups[$m['category']][]=$m;
    $ordered=[];
    foreach(catalogue_categories() as $c) if(isset($groups[$c]) && $c!=='Other') $ordered[$c]=$groups[$c];
    foreach($groups as $c=>$items) if(!isset($ordered[$c]) && $c!=='Other') $ordered[$c]=$items;
    if(isset($groups['Other'])) $ordered['Other']=$groups['Other'];
?>
<style>
.menu-tools{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0 0 14px}
.menu-tools .menu-search{flex:1;min-width:160px}
.menu-tools input,.menu-tools select{font:inherit;padding:9px 12px;border:1px solid #eccbd7;border-radius:9px;background:#fff;max-width:100%}
.menu-table-wrap{overflow-x:auto;border:1px solid #eccbd7;border-radius:12px;background:#fff}
.menu-table{border-collapse:collapse;width:100%;min-width:700px;text-align:left}
.menu-table th,.menu-table td{padding:7px 12px;border-bottom:1px solid #f4e2e8;vertical-align:middle;font-size:13px}
.menu-table thead th{background:#fff;color:#a90e4c;font-weight:600;white-space:nowrap}
.menu-table .category-heading th{background:#fbe4ed;color:#a90e4c;font-size:14px;padding:9px 12px}
.menu-table .product-thumb{width:48px;height:48px;object-fit:cover;display:block;border-radius:7px}
.menu-table .price-col{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
.menu-table .unit-col{text-transform:capitalize}
.menu-table .menu-actions{display:flex;align-items:center;gap:5px;white-space:nowrap}
.menu-table .menu-actions form{display:inline-flex;margin:0}
.menu-table .menu-action{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border:1px solid #edcbd7;border-radius:8px;color:#b30950;background:#fff;cursor:pointer;padding:0;text-decoration:none}
.menu-table .menu-action svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.menu-table .menu-action:focus-visible{outline:2px solid #b30950}
.menu-table .menu-action[aria-pressed=true]{background:#fbe4ed}
.menu-table .product-state{display:block;font-size:11px;color:#927280;margin-top:2px}
.menu-table [hidden],#menu-empty[hidden]{display:none!important}
.menu-total{font-size:12px;color:#927280;margin-top:10px}
@media(max-width:650px){.menu-tools .menu-search{flex-basis:100%}.menu-table th,.menu-table td{padding:6px 9px}}
.menu-tools .menu-add-icon{margin-left:auto;display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;padding:0;flex:none;font-size:26px;line-height:1;border-radius:50%}
</style>
  <?php foreach ($errors as $e): ?><div class="alert err"><?= h($e) ?></div><?php endforeach; ?>
  <div class="menu-tools">
    <input class="menu-search" id="menu-search" type="search" placeholder="Search menu items..." aria-label="Search menu items">
    <select id="menu-category" aria-label="Filter by category"><option value="">All Categories</option><?php foreach($ordered as $c=>$items): ?><option value="<?= h($c) ?>"><?= h($c) ?></option><?php endforeach; ?></select>
    <a class="btn menu-add-icon" href="?new=1" title="Add menu item" aria-label="Add menu item">+</a>
  </div>
  <div class="menu-table-wrap">
  <table class="menu-table">
    <thead><tr><th scope="col">Name</th><th scope="col">Photo</th><th scope="col">Category</th><th scope="col" class="price-col">Price</th><th scope="col">Unit</th><th scope="col">Action</th></tr></thead>
    <?php foreach($ordered as $c=>$items): ?>
    <tbody data-menu-group="<?= h($c) ?>">
    <tr class="category-heading"><th scope="rowgroup" colspan="6"><?= h($c) ?> <span class="group-count">(<?= count($items) ?>)</span></th></tr>
    <?php foreach($items as $m): ?>
    <tr data-menu-item data-name="<?= h($m['name']) ?>">
      <td><b><?= h($m['name']) ?></b><?php if(!$m['active'] || !$m['available']): ?><small class="product-state"><?= !$m['active']?'Inactive':'Sold out' ?></small><?php endif; ?></td>
      <td><?php if($m['image']): ?><img class="product-thumb" src="<?= img($m['image']) ?>" alt="<?= h($m['name']) ?>" loading="lazy"><?php else: ?><span class="muted">—</span><?php endif; ?></td>
      <td><?= h($m['category']) ?></td>
      <td class="price-col"><?= $m['amount']!==null?'₹'.h(rtrim(rtrim(number_format((float)$m['amount'],2,'.',''),'0'),'.')):h($m['price']?:'—') ?></td>
      <td class="unit-col"><?= h($m['unit']) ?></td>
      <td><div class="menu-actions">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="menu-action" type="submit" title="<?= $m['available']?'Mark sold out':'Mark available' ?>" aria-label="<?= $m['available']?'Mark sold out':'Mark available' ?>" aria-pressed="<?= $m['available']?'false':'true' ?>"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><?php if($m['available']): ?><path d="m8 12 3 3 5-6"/><?php else: ?><path d="m8 8 8 8"/><?php endif; ?></svg></button></form>
        <a class="menu-action" href="?edit=<?= (int)$m['id'] ?>" title="Edit" aria-label="Edit <?= h($m['name']) ?>"><svg viewBox="0 0 24 24"><path d="m15 5 4 4M4 20l4-1 12-12-4-4L4 15z"/></svg></a>
        <form method="post" onsubmit="return confirm('Delete this menu item?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="action" value="delete"><button class="menu-action" type="submit" title="Delete" aria-label="Delete <?= h($m['name']) ?>"><svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 10v7M14 10v7"/></svg></button></form>
      </div></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <?php endforeach; ?>
  </table>
  </div>
  <p class="menu-total" id="menu-total" aria-live="polite"></p>
  <p class="muted" id="menu-empty" hidden>No matching menu items.</p>
  <script>
  (()=>{const search=document.getElementById('menu-search'),category=document.getElementById('menu-category'),groups=[...document.querySelectorAll('[data-menu-group]')],total=document.getElementById('menu-total'),empty=document.getElementById('menu-empty');
  function filter(){const query=search.value.trim().toLowerCase();let count=0;groups.forEach(g=>{let visible=0;const match=!category.value||category.value===g.dataset.menuGroup;g.querySelectorAll('[data-menu-item]').forEach(r=>{r.hidden=!(match&&r.dataset.name.toLowerCase().includes(query));if(!r.hidden)visible++});g.hidden=visible===0;g.querySelector('.group-count').textContent='('+visible+')';count+=visible});total.textContent=count+' menu item'+(count===1?'':'s');empty.hidden=count!==0}
  search.addEventListener('input',filter);category.addEventListener('change',filter);filter();
  })();
  </script>
<?php endif;
admin_footer();
