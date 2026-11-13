<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_login();

const STATUSES = ['new' => 'New', 'contacted' => 'Contacted', 'confirmed' => 'Confirmed', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];

/* ---- CSV download ---- */
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bliss-bites-enquiries-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // so Excel shows ₹ and Hindi correctly
    fputcsv($out, ['Received', 'Name', 'Phone', 'Item', 'Quantity', 'Needed by', 'Message', 'Status']);
    foreach (q('SELECT * FROM enquiries ORDER BY id DESC')->fetchAll() as $e) {
        // prefix cells that start with formula characters so spreadsheets don't run them
        $row = array_map(fn($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'" . $v : $v,
            [$e['created_at'], $e['name'], $e['phone'], $e['item'], $e['quantity'], $e['needed_by'], $e['message'], STATUSES[$e['status']] ?? $e['status']]);
        fputcsv($out, $row);
    }
    exit;
}

/* ---- actions ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    switch ($_POST['action'] ?? '') {
        case 'status':
            $st = (string)($_POST['status'] ?? '');
            if (isset(STATUSES[$st])) { q('UPDATE enquiries SET status = ? WHERE id = ?', [$st, $id]); flash('Status updated.'); }
            break;
        case 'delete':
            q('DELETE FROM enquiries WHERE id = ?', [$id]);
            flash('Enquiry deleted.');
            break;
    }
    redirect('enquiries.php' . (isset($_GET['f']) ? '?f=' . urlencode((string)$_GET['f']) : ''));
}

$filter = (string)($_GET['f'] ?? 'open');
$counts = [];
foreach (q('SELECT status, COUNT(*) c FROM enquiries GROUP BY status')->fetchAll() as $r) $counts[$r['status']] = (int)$r['c'];
$open = ($counts['new'] ?? 0) + ($counts['contacted'] ?? 0) + ($counts['confirmed'] ?? 0);

if ($filter === 'all')          $rows = q('SELECT * FROM enquiries ORDER BY id DESC')->fetchAll();
elseif (isset(STATUSES[$filter])) $rows = q('SELECT * FROM enquiries WHERE status = ? ORDER BY id DESC', [$filter])->fetchAll();
else { $filter = 'open';        $rows = q("SELECT * FROM enquiries WHERE status IN ('new','contacted','confirmed') ORDER BY id DESC")->fetchAll(); }

admin_header('Enquiries', 'enquiries.php');
?>
<div class="toolbar">
  <div class="tabs">
    <a href="?f=open" class="<?= $filter === 'open' ? 'on' : '' ?>">Open (<?= $open ?>)</a>
    <?php foreach (STATUSES as $k => $label): ?>
      <a href="?f=<?= $k ?>" class="<?= $filter === $k ? 'on' : '' ?>"><?= $label ?> (<?= $counts[$k] ?? 0 ?>)</a>
    <?php endforeach; ?>
    <a href="?f=all" class="<?= $filter === 'all' ? 'on' : '' ?>">All</a>
  </div>
  <a class="btn ghost sm" href="?export=csv">⬇ Download Excel/CSV</a>
</div>

<?php if (!$rows): ?>
  <div class="panel"><p class="muted">Nothing here.</p></div>
<?php endif; ?>

<div class="enq-list">
<?php foreach ($rows as $e):
    $digits = wa_digits($e['phone']);
    if (strlen($digits) === 10) $digits = '91' . $digits;
    $reply = "Hi {$e['name']}, thank you for your enquiry at Bliss Bites" . ($e['item'] ? " for {$e['item']}" : '') . '! ';
?>
  <article class="enq <?= $e['status'] === 'new' ? 'is-new' : '' ?>">
    <div class="enq-main">
      <div class="enq-top">
        <h3><?= h($e['name']) ?></h3>
        <span class="status s-<?= h($e['status']) ?>"><?= h(STATUSES[$e['status']] ?? $e['status']) ?></span>
        <small class="muted"><?= h(date('d M Y, g:i a', strtotime($e['created_at']))) ?></small>
      </div>
      <dl>
        <div><dt>Phone</dt><dd><a href="tel:<?= h($e['phone']) ?>"><?= h($e['phone']) ?></a></dd></div>
        <div><dt>Item</dt><dd><?= h($e['item'] ?: '—') ?></dd></div>
        <div><dt>Quantity</dt><dd><?= h($e['quantity'] ?: '—') ?></dd></div>
        <div><dt>Needed by</dt><dd><?= $e['needed_by'] ? h(date('D, d M Y', strtotime($e['needed_by']))) : '—' ?></dd></div>
      </dl>
      <?php if (trim((string)$e['message']) !== ''): ?><p class="enq-msg"><?= nl2br(h($e['message'])) ?></p><?php endif; ?>
    </div>
    <div class="enq-actions">
      <a class="btn sm wa" href="https://wa.me/<?= h($digits) ?>?text=<?= h(rawurlencode($reply)) ?>" target="_blank" rel="noopener">Reply on WhatsApp</a>
      <form method="post" class="inline">
        <?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
        <select name="status" onchange="this.form.submit()" aria-label="Change status">
          <?php foreach (STATUSES as $k => $label): ?><option value="<?= $k ?>"<?= $e['status'] === $k ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
        </select>
      </form>
      <?= action_button('delete', (int)$e['id'], 'Delete', 'ghost sm danger', 'Delete this enquiry permanently?') ?>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?php admin_footer();
