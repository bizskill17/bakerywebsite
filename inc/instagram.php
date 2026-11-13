<?php
declare(strict_types=1);
/*
 * Instagram sync
 * --------------
 * Pulls the latest posts from your Instagram professional (Business/Creator) account:
 *   photos & carousels -> "Instagram Gallery"
 *   reels & videos     -> "Watch Our Baking Stories" (cover image, opens the reel)
 * Images are downloaded to uploads/ because Instagram's image links expire.
 * Runs at most every IG_SYNC_EVERY seconds (triggered by visitors and/or a cron job),
 * or immediately with "Sync now" in the admin panel.
 */

const IG_SYNC_EVERY  = 300;  // 5 minutes
const IG_KEEP        = 30;   // how many recent posts to keep
const IG_REFRESH_AGE = 7 * 86400; // renew the access token weekly (tokens last 60 days)
const IG_MAX_IMAGE   = 15 * 1024 * 1024;

function ig_api_base(): string {
    return rtrim(getenv('IG_API_BASE') ?: 'https://graph.instagram.com', '/');
}

/** Current access token. A token supplied via GitHub secret (config.php) is adopted when it changes. */
function ig_token(): string {
    $cfgTok = trim((string)(config()['instagram_token'] ?? ''));
    if ($cfgTok !== '' && setting('instagram_config_token_hash') !== sha1($cfgTok)) {
        save_setting('instagram_token', $cfgTok);
        save_setting('instagram_token_refreshed_at', (string)time());
        save_setting('instagram_config_token_hash', sha1($cfgTok));
        if (setting('instagram_sync') === '') save_setting('instagram_sync', '1');
        settings(true);
    }
    return setting('instagram_token');
}

function ig_enabled(): bool {
    $token = ig_token(); // read first: may switch sync on when a token arrives via GitHub secret
    return $token !== '' && setting('instagram_sync') === '1';
}

function ig_http_get(string $url, int $maxBytes = 2_000_000): array {
    $ch = curl_init($url);
    $buf = '';
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 4,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_USERAGENT      => 'BlissBitesWebsite/1.0',
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS | CURLPROTO_HTTP,
        CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$buf, $maxBytes) {
            $buf .= $chunk;
            return strlen($buf) > $maxBytes ? 0 : strlen($chunk); // abort oversized downloads
        },
    ]);
    $ok = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($ok === false) throw new RuntimeException('Could not reach Instagram (' . ($err ?: 'network error') . ').');
    return [$code, $buf];
}

function ig_json(string $path, array $params = []): array {
    $params['access_token'] = ig_token();
    [$code, $body] = ig_http_get(ig_api_base() . $path . '?' . http_build_query($params));
    $j = json_decode($body, true);
    if (!is_array($j)) throw new RuntimeException("Instagram sent an unexpected reply (HTTP $code).");
    if (isset($j['error'])) {
        $msg = (string)($j['error']['message'] ?? 'unknown error');
        if (($j['error']['code'] ?? 0) == 190) $msg = 'Your Instagram access token is invalid or expired. Paste a new one on the Instagram Sync page. (' . $msg . ')';
        throw new RuntimeException('Instagram: ' . $msg);
    }
    return $j;
}

/** Renews the long-lived token about once a week so it never reaches its 60-day expiry. */
function ig_maybe_refresh_token(): void {
    $at = (int)setting('instagram_token_refreshed_at', '0');
    if ($at && time() - $at < IG_REFRESH_AGE) return;
    try {
        $j = ig_json('/refresh_access_token', ['grant_type' => 'ig_refresh_token']);
        if (!empty($j['access_token'])) {
            save_setting('instagram_token', (string)$j['access_token']);
            if (!empty($j['expires_in'])) save_setting('instagram_token_expires_at', (string)(time() + (int)$j['expires_in']));
        }
        save_setting('instagram_token_refreshed_at', (string)time());
    } catch (RuntimeException) {
        // brand-new tokens can't be refreshed in their first 24 hours – try again tomorrow
        save_setting('instagram_token_refreshed_at', (string)(time() - IG_REFRESH_AGE + 86400));
    }
    settings(true);
}

function ig_download_image(string $url): string {
    [$code, $bytes] = ig_http_get($url, IG_MAX_IMAGE);
    if ($code !== 200 || $bytes === '') throw new RuntimeException("Image download failed (HTTP $code).");
    $tmp = tempnam(sys_get_temp_dir(), 'ig');
    file_put_contents($tmp, $bytes);
    try { return save_image_file($tmp, 'ig-'); }
    finally { @unlink($tmp); }
}

/**
 * Runs a sync if the last one was at least $minAge seconds ago.
 * Returns ['added'=>n, 'removed'=>n, 'photos'=>n, 'reels'=>n] or ['skipped'=>reason] or ['error'=>msg].
 */
function ig_sync(int $minAge = IG_SYNC_EVERY): array {
    if (ig_token() === '') return ['error' => 'No Instagram access token saved yet.'];
    if (time() - (int)setting('instagram_last_sync', '0') < $minAge) return ['skipped' => 'synced recently'];

    @mkdir(ROOT . '/data', 0755, true);
    $fh = @fopen(ROOT . '/data/ig-sync.lock', 'c');
    if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) return ['skipped' => 'a sync is already running'];

    save_setting('instagram_last_sync', (string)time());
    try {
        ig_maybe_refresh_token();
        $j = ig_json('/me/media', [
            'fields' => 'id,caption,media_type,media_product_type,media_url,thumbnail_url,permalink,timestamp',
            'limit'  => IG_KEEP,
        ]);
        $items = array_slice(is_array($j['data'] ?? null) ? $j['data'] : [], 0, IG_KEEP);

        $existing = [];
        foreach (q('SELECT * FROM instagram_media')->fetchAll() as $r) $existing[$r['ig_id']] = $r;

        $seen = []; $added = 0; $failed = 0;
        foreach ($items as $pos => $it) {
            $id = (string)($it['id'] ?? '');
            if ($id === '') continue;
            $type    = (string)($it['media_type'] ?? '');
            $isVideo = $type === 'VIDEO';
            $kind    = $isVideo ? 'reel' : 'photo';
            $caption = mb_substr(trim((string)($it['caption'] ?? '')), 0, 2000);
            $link    = (string)($it['permalink'] ?? '');
            if ($link !== '' && !preg_match('~^https://(www\.)?instagram\.com/~i', $link)) $link = '';

            if (isset($existing[$id])) {
                $seen[$id] = true;
                q('UPDATE instagram_media SET kind = ?, caption = ?, permalink = ?, sort_order = ? WHERE ig_id = ?', [$kind, $caption, $link, $pos, $id]);
                continue;
            }
            $src = $isVideo ? ($it['thumbnail_url'] ?? '') : ($it['media_url'] ?? $it['thumbnail_url'] ?? '');
            if (!$src) continue;
            try {
                $img = ig_download_image((string)$src);
            } catch (RuntimeException) {
                // e.g. a carousel that starts with a video – try its cover instead
                if (!$isVideo && !empty($it['thumbnail_url'])) {
                    try { $img = ig_download_image((string)$it['thumbnail_url']); } catch (RuntimeException) { $failed++; continue; }
                } else { $failed++; continue; }
            }
            q('INSERT INTO instagram_media (ig_id, kind, caption, permalink, image, taken_at, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)',
              [$id, $kind, $caption, $link, $img, (string)($it['timestamp'] ?? ''), $pos]);
            $seen[$id] = true;
            $added++;
        }

        // drop posts that were deleted on Instagram or fell out of the latest IG_KEEP
        $removed = 0;
        foreach ($existing as $id => $r) {
            if (isset($seen[$id])) continue;
            delete_image($r['image']);
            q('DELETE FROM instagram_media WHERE ig_id = ?', [$id]);
            $removed++;
        }

        save_setting('instagram_last_ok', (string)time());
        save_setting('instagram_last_error', $failed ? "$failed post(s) could not be downloaded and will be retried." : '');
        return ['added' => $added, 'removed' => $removed] + ig_counts();
    } catch (Throwable $e) {
        save_setting('instagram_last_error', $e->getMessage());
        return ['error' => $e->getMessage()];
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
        settings(true);
    }
}

function ig_counts(): array {
    $c = ['photos' => 0, 'reels' => 0];
    foreach (q('SELECT kind, COUNT(*) n FROM instagram_media GROUP BY kind')->fetchAll() as $r) {
        $c[$r['kind'] === 'reel' ? 'reels' : 'photos'] = (int)$r['n'];
    }
    return $c;
}

/** Called at the very end of a page: sends the page to the visitor first, then syncs if due. */
function ig_sync_after_response(): void {
    if (!ig_enabled() || time() - (int)setting('instagram_last_sync', '0') < IG_SYNC_EVERY) return;
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    ignore_user_abort(true);
    @set_time_limit(120);
    if (function_exists('litespeed_finish_request')) litespeed_finish_request();
    elseif (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    else flush();
    try { ig_sync(); } catch (Throwable) {}
}
