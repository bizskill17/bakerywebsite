<?php
/*
 * Instagram sync – run every 5 minutes by a Hostinger Cron Job:
 *   hPanel → Advanced → Cron Jobs → Custom → command shown on the admin "Instagram Sync" page.
 * Can also be opened in a browser with the secret key: /cron/instagram-sync.php?key=…
 */
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/instagram.php';

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: noindex');
    $key = setting('cron_key');
    if ($key === '' || !hash_equals($key, (string)($_GET['key'] ?? ''))) { http_response_code(403); exit("Forbidden\n"); }
}
@set_time_limit(120);

if (!ig_enabled()) { echo "Instagram sync is switched off.\n"; exit; }
$r = ig_sync(60); // cron runs every 5 min; allow small timing drift
echo date('Y-m-d H:i:s') . ' ' . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
