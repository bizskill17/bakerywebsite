<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
date_default_timezone_set('Asia/Kolkata');

const MAX_UPLOAD_BYTES = 10 * 1024 * 1024; // 10 MB
const IMAGE_MAX_SIDE   = 1600;             // px – larger photos are resized

/* ---------- session ---------- */
if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => $https, 'path' => '/']);
    session_name('blissbites');
    session_start();
}

/* ---------- config / database ---------- */
function is_installed(): bool { return is_file(ROOT . '/config.php'); }

function config(): array {
    static $c = null;
    if ($c === null) {
        if (!is_installed()) {
            http_response_code(503);
            exit('<p style="font-family:sans-serif;padding:40px">This website is not set up yet. Open <a href="/install.php">install.php</a> to set it up.</p>');
        }
        $c = require ROOT . '/config.php';
    }
    return $c;
}

function make_pdo(array $c): PDO {
    if (($c['driver'] ?? 'mysql') === 'sqlite') {
        $pdo = new PDO('sqlite:' . $c['path']);
    } else {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int)($c['port'] ?? 3306), $c['name']);
        $pdo = new PDO($dsn, $c['user'], $c['pass']);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

function db(): PDO {
    static $pdo = null;
    return $pdo ??= make_pdo(config());
}

function q(string $sql, array $params = []): PDOStatement {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

/* ---------- settings ---------- */
function settings(bool $refresh = false): array {
    static $s = null;
    if ($s === null || $refresh) {
        $s = [];
        foreach (q('SELECT k, v FROM settings')->fetchAll() as $r) $s[$r['k']] = $r['v'];
    }
    return $s;
}
function setting(string $k, string $default = ''): string {
    $v = settings()[$k] ?? '';
    return $v !== '' ? $v : $default;
}
function save_setting(string $k, string $v): void {
    q('DELETE FROM settings WHERE k = ?', [$k]);
    q('INSERT INTO settings (k, v) VALUES (?, ?)', [$k, $v]);
}

/* ---------- helpers ---------- */
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function now(): string { return date('Y-m-d H:i:s'); }
function client_ip(): string { return substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 64); }

function wa_digits(string $n): string { return preg_replace('/\D+/', '', $n); }
function whatsapp_link(string $text = ''): string {
    $num = wa_digits(setting('whatsapp_number'));
    $text = $text !== '' ? $text : setting('whatsapp_message', "Hi Bliss Bites! I'd like to place an order.");
    return 'https://wa.me/' . $num . '?text=' . rawurlencode($text);
}
function instagram_link(): string {
    return 'https://instagram.com/' . ltrim(trim(setting('instagram_handle', 'blissbites')), '@');
}

function redirect(string $url): never { header('Location: ' . $url); exit; }

function flash(?string $msg = null, string $type = 'ok') {
    if ($msg !== null) { $_SESSION['flash'][] = [$type, $msg]; return null; }
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- CSRF ---------- */
function csrf_token(): string {
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">'; }
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

/* ---------- ordering ---------- */
function move_row(string $table, int $id, string $dir): void {
    if (!in_array($table, ['menu_items', 'gallery', 'stories'], true)) return;
    $ids = array_map('intval', q("SELECT id FROM $table ORDER BY sort_order, id")->fetchAll(PDO::FETCH_COLUMN));
    $i = array_search($id, $ids, true);
    if ($i === false) return;
    $j = $dir === 'up' ? $i - 1 : $i + 1;
    if ($j < 0 || $j >= count($ids)) return;
    [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
    foreach ($ids as $pos => $rid) q("UPDATE $table SET sort_order = ? WHERE id = ?", [$pos, $rid]);
}
function next_sort(string $table): int {
    return (int)q("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM $table")->fetchColumn();
}

/* ---------- image uploads ---------- */
function has_upload(string $field): bool {
    return isset($_FILES[$field]) && ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

/** Normalises $_FILES[field] (single or multiple) into a list of files. */
function upload_list(string $field): array {
    if (!isset($_FILES[$field])) return [];
    $f = $_FILES[$field];
    if (!is_array($f['name'])) return [$f];
    $out = [];
    foreach ($f['name'] as $i => $_) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        $out[] = ['name' => $f['name'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

/**
 * Validates an uploaded image, re-encodes it as JPEG (this strips anything
 * hidden in the file), resizes it and returns its path, e.g. "uploads/2026-11-ab12….jpg".
 */
function save_image(array $f): string {
    if (($f['error'] ?? 1) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'The photo is too large.' : 'The photo could not be uploaded. Please try again.');
    }
    if ($f['size'] > MAX_UPLOAD_BYTES) throw new RuntimeException('The photo is too large (max 10 MB).');
    if (!is_uploaded_file($f['tmp_name']) && PHP_SAPI !== 'cli') throw new RuntimeException('Invalid upload.');

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($f['tmp_name']),
        'image/png'  => @imagecreatefrompng($f['tmp_name']),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($f['tmp_name']) : false,
        'image/gif'  => @imagecreatefromgif($f['tmp_name']),
        default      => false,
    };
    if (!$src) throw new RuntimeException('Please upload a JPG, PNG or WEBP photo.');

    // Fix sideways phone photos
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($f['tmp_name']);
        $rot = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
        if ($rot) $src = imagerotate($src, $rot, 0);
    }

    $w = imagesx($src); $hgt = imagesy($src);
    $scale = min(1, IMAGE_MAX_SIDE / max($w, $hgt));
    $nw = max(1, (int)round($w * $scale)); $nh = max(1, (int)round($hgt * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $hgt);

    $dir = ROOT . '/uploads';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $rel = 'uploads/' . date('Y-m') . '-' . bin2hex(random_bytes(8)) . '.jpg';
    if (!imagejpeg($dst, ROOT . '/' . $rel, 85)) throw new RuntimeException('Could not save the photo. Check that the uploads folder is writable.');
    imagedestroy($src); imagedestroy($dst);
    return $rel;
}

/** Deletes a previously uploaded image (never touches the built-in images/ folder). */
function delete_image(?string $rel): void {
    if ($rel && str_starts_with($rel, 'uploads/') && !str_contains($rel, '..') && is_file(ROOT . '/' . $rel)) {
        @unlink(ROOT . '/' . $rel);
    }
}
