<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('./#contact');

$f = [];
foreach (['name', 'phone', 'item', 'quantity', 'needed_by', 'message', 'website'] as $k) {
    $f[$k] = trim((string)($_POST[$k] ?? ''));
}

$f['products'] = is_array($_POST['products'] ?? null) ? $_POST['products'] : [];
// Spam bots fill the hidden "website" field – pretend success and save nothing.
if ($f['website'] !== '') redirect('./?sent=1#contact');

$fail = function (string $msg) use ($f): never {
    $_SESSION['enq_error'] = $msg;
    unset($f['website']);
    $_SESSION['enq_old'] = $f;
    redirect('./#contact');
};

$lines=[]; $total=0; $count=0;
foreach($f['products'] as $id=>$qty) {
    if (!is_scalar($qty) || !ctype_digit((string)$qty) || !ctype_digit((string)$id)) $fail('Invalid quantity.');
    $qty=(int)$qty; if (!$qty) continue;
    if ($qty>999) $fail('Quantity must be 999 or less.');
    $m=q('SELECT * FROM menu_items WHERE id=? AND active=1 AND available=1 AND amount IS NOT NULL',[(int)$id])->fetch();
    if (!$m) $fail('A selected product is no longer available.');
    $subtotal=(float)$m['amount']*$qty; $total+=$subtotal; $count+=$qty;
    $lines[]=$m['name'].' × '.$qty.' @ ₹'.$m['amount'].' / '.$m['unit'].' = ₹'.number_format($subtotal,2);
}
if (!$lines) $fail('Please select at least one product.');
$f['item']=count($lines)===1 ? explode(' × ',$lines[0])[0] : count($lines).' products';
$f['quantity']=(string)$count;
$orderSummary=implode("\n",$lines)."\nEstimated total: ₹".number_format($total,2);
$digits = preg_replace('/\D+/', '', $f['phone']);
if (mb_strlen($f['name']) < 2)                         $fail('Please enter your name.');
if (strlen($digits) < 10 || strlen($digits) > 13)      $fail('Please enter a valid phone number (10 digits).');
if ($f['needed_by'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['needed_by'])) $f['needed_by'] = '';

// At most 5 enquiries per hour from the same connection
$recent = (int)q('SELECT COUNT(*) FROM enquiries WHERE ip = ? AND created_at >= ?',
                 [client_ip(), date('Y-m-d H:i:s', time() - 3600)])->fetchColumn();
if ($recent >= 5) $fail('You have sent several enquiries already. Please message us on WhatsApp instead.');

q('INSERT INTO enquiries (name, phone, item, quantity, needed_by, message, status, ip, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
    mb_substr($f['name'], 0, 120), mb_substr($f['phone'], 0, 30), mb_substr($f['item'], 0, 120),
    mb_substr($f['quantity'], 0, 60), $f['needed_by'], $orderSummary . "\n\n" . mb_substr($f['message'], 0, 1000), 'new', client_ip(), now(),
]);

redirect('./?sent=1#contact');
