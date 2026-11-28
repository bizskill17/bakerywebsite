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
<style>
.enquiry-tools{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:14px}
.enquiry-tools input{flex:1 1 250px;width:auto;min-width:0}
.enquiry-tools select{flex:0 1 220px;width:auto;min-width:130px}
.enquiry-tools .btn{margin-left:auto;white-space:nowrap}
.enquiry-table-wrap{overflow-x:auto;background:#fff;border:1px solid #edcbd7;border-radius:12px}
.enquiry-table{border-collapse:collapse;width:100%;min-width:950px;text-align:left;table-layout:auto}
.enquiry-table th,.enquiry-table td{padding:12px 10px;border-bottom:1px solid #f1dce5;vertical-align:top;font-size:13px;line-height:1.5;overflow-wrap:anywhere}
.enquiry-table th{background:#fcebf1;color:#a90e4c;font-weight:600;white-space:nowrap}
.enquiry-table tr:last-child td{border-bottom:0}
.enquiry-table .customer{min-width:125px}.enquiry-table .phone{white-space:nowrap}.enquiry-table .items{min-width:135px;max-width:220px}.enquiry-table .summary{min-width:180px;max-width:300px}.enquiry-table .qty{text-align:center}.enquiry-table .received{display:block;color:#927480;font-size:11px;margin-top:4px}
.enquiry-table .message-preview{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;white-space:pre-wrap}
.enquiry-table .order-total-text{font-weight:600;color:#a90e4c;margin-bottom:3px}
.enquiry-table .enquiry-actions{display:flex;gap:6px;align-items:center;white-space:nowrap}.enquiry-actions form{margin:0;display:inline-flex}
.enquiry-action{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1px solid #edcbd7;border-radius:8px;color:#b30950;background:white;cursor:pointer;text-decoration:none;padding:0}
.enquiry-action svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}.enquiry-action.wa-link{color:#16854c}
.enquiry-action:focus-visible{outline:2px solid #b30950}
.enquiry-detail{border:1px solid #edcbd7;border-radius:16px;padding:24px;width:min(520px,calc(100% - 32px));max-height:85vh;overflow:auto;color:inherit;background:#fff}
.enquiry-detail::backdrop{background:rgba(50,15,30,.35)}.enquiry-detail h2{color:#a90e4c;margin:0 0 14px}.enquiry-detail pre{white-space:pre-wrap;overflow-wrap:anywhere;font:inherit;background:#fcf3f6;padding:12px;border-radius:8px;margin:10px 0 18px}.enquiry-detail .detail-buttons{display:flex;justify-content:flex-end;gap:8px}.enquiry-detail select{margin-top:5px}
.enquiry-table [hidden],#enquiry-empty[hidden]{display:none!important}.enquiry-count{font-size:12px;color:#927480;margin-top:10px}
</style>
<div class="enquiry-tools">
  <input type="search" id="enquiry-search" placeholder="Search enquiries (name, phone, item, message...)" aria-label="Search enquiries">
  <select id="enquiry-status" aria-label="Filter by status">
    <option value="all" <?= $filter==='all'?'selected':'' ?>>All Statuses</option>
    <option value="open" <?= $filter==='open'?'selected':'' ?>>Open</option>
    <?php foreach(STATUSES as $k=>$label): ?><option value="<?= h($k) ?>" <?= $filter===$k?'selected':'' ?>><?= h($label) ?></option><?php endforeach; ?>
  </select>
  <a class="btn ghost sm" href="?export=csv">↓ Download Excel/CSV</a>
</div>
<div class="toolbar">
  <div class="tabs">
    <a href="?f=open" class="<?= $filter === 'open' ? 'on' : '' ?>">Open (<?= $open ?>)</a>
    <?php foreach (STATUSES as $k => $label): ?>
      <a href="?f=<?= $k ?>" class="<?= $filter === $k ? 'on' : '' ?>"><?= $label ?> (<?= $counts[$k] ?? 0 ?>)</a>
    <?php endforeach; ?>
    <a href="?f=all" class="<?= $filter === 'all' ? 'on' : '' ?>">All (<?= array_sum($counts) ?>)</a>
  </div>
</div>
<div class="enquiry-table-wrap">
<table class="enquiry-table">
<thead><tr><th>Name</th><th>Phone</th><th>Items</th><th>Qty</th><th>Needed by</th><th>Status</th><th>Message / Order Summary</th><th>Action</th></tr></thead>
<tbody>
<?php foreach ($rows as $e):
    $digits=wa_digits($e['phone']);
    if(strlen($digits)===10) $digits='91'.$digits;
    $reply="Hi {$e['name']}, thank you for your enquiry at Bliss Bites" . ($e['item'] ? " for {$e['item']}" : '') . '! ';
    $items=[]; $notes=[]; $orderTotal='';
    $lines=preg_split('/\R/', (string)$e['message']);
    $summaryMode=true;
    foreach($lines as $line) {
        if($summaryMode && preg_match('/^(.+?)\s+×\s+(\d+)\s+@\s+₹.+\s+=\s+₹[\d,.]+$/u', trim($line),$parts)) { $items[]=$parts[1].' × '.$parts[2]; continue; }
        if($summaryMode && $items && preg_match('/^(?:Estimated total|Total):\s*(₹[\d,.]+)$/iu', trim($line),$parts)) { $orderTotal=$parts[1];$summaryMode=false;continue; }
        $summaryMode=false; $notes[]=$line;
    }
    $note=trim(implode("\n",$notes));
    $eid=(int)$e['id'];
?>
<tr data-enquiry-row data-search="<?= h($e['name'].' '.$e['phone'].' '.$e['item'].' '.$e['quantity'].' '.$e['message'].' '.(STATUSES[$e['status']]??$e['status'])) ?>">
  <td class="customer"><b><?= h($e['name']) ?></b><span class="received"><?= h(date('d/m/Y, g:i a',strtotime($e['created_at']))) ?></span></td>
  <td class="phone"><a href="tel:<?= h($e['phone']) ?>"><?= h($e['phone']) ?></a></td>
  <td class="items"><?php if($items): foreach($items as $item): ?><div><?= h($item) ?></div><?php endforeach; else: ?><?= h($e['item']?:'—') ?><?php endif; ?></td>
  <td class="qty"><?= h($e['quantity']?:'—') ?></td>
  <td><?= $e['needed_by']?h(date('d/m/Y',strtotime($e['needed_by']))):'—' ?></td>
  <td><span class="status s-<?= h($e['status']) ?>"><?= h(STATUSES[$e['status']]??$e['status']) ?></span></td>
  <td class="summary"><?php if($orderTotal): ?><div class="order-total-text">Total: <?= h($orderTotal) ?></div><?php endif; ?><div class="message-preview"><?= h($note?:($orderTotal?'':'—')) ?></div></td>
  <td><div class="enquiry-actions">
    <?php if($digits): ?><a class="enquiry-action wa-link" href="https://wa.me/<?= h($digits) ?>?text=<?= h(rawurlencode($reply)) ?>" target="_blank" rel="noopener" title="Reply on WhatsApp" aria-label="Reply on WhatsApp"><svg viewBox="0 0 24 24"><path d="M20 11.5a8 8 0 0 1-11.8 7L4 20l1.5-4.1A8 8 0 1 1 20 11.5Z"/><path d="M9 8c-2 2 3 7 5 6l1-1-2-1-1 1-3-3 1-1-1-2Z"/></svg></a><?php endif; ?>
    <button class="enquiry-action" type="button" data-open-detail="<?= $eid ?>" title="View details / change status" aria-label="View details and change status"><svg viewBox="0 0 24 24"><path d="m15 5 4 4M4 20l4-1 12-12-4-4L4 15z"/></svg></button>
    <form method="post" onsubmit="return confirm('Delete this enquiry permanently?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $eid ?>"><button class="enquiry-action" type="submit" title="Delete" aria-label="Delete enquiry"><svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 10v7M14 10v7"/></svg></button></form>
  </div></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<p class="enquiry-count" id="enquiry-count" aria-live="polite"></p>
<p class="muted" id="enquiry-empty" hidden>No matching enquiries.</p>
<?php foreach($rows as $e): ?>
<dialog class="enquiry-detail" id="enquiry-detail-<?= (int)$e['id'] ?>">
  <h2><?= h($e['name']) ?></h2>
  <p><?= h($e['phone']) ?> · Received <?= h(date('d/m/Y, g:i a',strtotime($e['created_at']))) ?></p>
  <p>Items: <?= h($e['item']?:'—') ?> · Quantity: <?= h($e['quantity']?:'—') ?></p>
  <p>Needed by: <?= $e['needed_by']?h(date('d/m/Y',strtotime($e['needed_by']))):'—' ?></p>
  <pre><?= h($e['message']?:'No message') ?></pre>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
    <label>Status<select name="status"><?php foreach(STATUSES as $k=>$label): ?><option value="<?= h($k) ?>" <?= $e['status']===$k?'selected':'' ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
    <div class="detail-buttons"><button type="button" class="btn ghost" data-close-detail>Cancel</button><button class="btn" type="submit">Save</button></div>
  </form>
</dialog>
<?php endforeach; ?>
<script>
(()=>{const search=document.getElementById('enquiry-search'),status=document.getElementById('enquiry-status'),rows=[...document.querySelectorAll('[data-enquiry-row]')],count=document.getElementById('enquiry-count'),empty=document.getElementById('enquiry-empty');function filter(){const term=search.value.trim().toLowerCase();let n=0;rows.forEach(r=>{r.hidden=!r.dataset.search.toLowerCase().includes(term);if(!r.hidden)n++});count.textContent=n+' enquir'+(n===1?'y':'ies');empty.hidden=n!==0}search.addEventListener('input',filter);status.addEventListener('change',()=>location.href='?f='+encodeURIComponent(status.value));document.querySelectorAll('[data-open-detail]').forEach(b=>b.addEventListener('click',()=>document.getElementById('enquiry-detail-'+b.dataset.openDetail).showModal()));document.querySelectorAll('[data-close-detail]').forEach(b=>b.addEventListener('click',()=>b.closest('dialog').close()));filter()})();
</script>
<?php admin_footer();
