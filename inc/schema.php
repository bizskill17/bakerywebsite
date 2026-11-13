<?php
declare(strict_types=1);

function create_schema(PDO $pdo, string $driver): void {
    $my  = $driver === 'mysql';
    $id  = $my ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $end = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';

    $sql = [
        "CREATE TABLE IF NOT EXISTS admins (id $id, username VARCHAR(60) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL)$end",
        "CREATE TABLE IF NOT EXISTS settings (k VARCHAR(64) NOT NULL PRIMARY KEY, v TEXT)$end",
        "CREATE TABLE IF NOT EXISTS menu_items (id $id, name VARCHAR(120) NOT NULL, description TEXT, price VARCHAR(60) DEFAULT '',
            image VARCHAR(255) DEFAULT '', available INT NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0)$end",
        "CREATE TABLE IF NOT EXISTS gallery (id $id, image VARCHAR(255) NOT NULL, caption VARCHAR(160) DEFAULT '', sort_order INT NOT NULL DEFAULT 0)$end",
        "CREATE TABLE IF NOT EXISTS stories (id $id, image VARCHAR(255) NOT NULL, link VARCHAR(255) DEFAULT '', caption VARCHAR(160) DEFAULT '', sort_order INT NOT NULL DEFAULT 0)$end",
        "CREATE TABLE IF NOT EXISTS enquiries (id $id, name VARCHAR(120) NOT NULL, phone VARCHAR(30) NOT NULL, item VARCHAR(120) DEFAULT '',
            quantity VARCHAR(60) DEFAULT '', needed_by VARCHAR(20) DEFAULT '', message TEXT, status VARCHAR(20) NOT NULL DEFAULT 'new',
            ip VARCHAR(64) DEFAULT '', created_at VARCHAR(19) NOT NULL)$end",
        "CREATE TABLE IF NOT EXISTS instagram_media (id $id, ig_id VARCHAR(64) NOT NULL UNIQUE, kind VARCHAR(10) NOT NULL,
            caption TEXT, permalink VARCHAR(255) DEFAULT '', image VARCHAR(255) NOT NULL, taken_at VARCHAR(30) DEFAULT '',
            sort_order INT NOT NULL DEFAULT 0)$end",
        "CREATE TABLE IF NOT EXISTS login_attempts (id $id, ip VARCHAR(64) NOT NULL, attempted_at INT NOT NULL)$end",
    ];
    foreach ($sql as $s) $pdo->exec($s);
}

function seed_content(PDO $pdo): void {
    if ((int)$pdo->query('SELECT COUNT(*) FROM settings')->fetchColumn() > 0) return;

    $settings = [
        'whatsapp_number'  => '',
        'instagram_handle' => 'blissbites',
        'whatsapp_message' => "Hi Bliss Bites! I'd like to place an order.",
        'hero_title'       => 'Homemade Brownies, Cakes & More',
        'hero_tagline'     => 'Homemade Brownies, Cakes and more, baked with Love',
        'hero_image'       => 'images/hero.jpg',
        'about_subtitle'   => 'Made with Love, Baked with Happiness',
        'about_text'       => "Welcome to Bliss Bites by Nutan Jain, a home-based bakery in Guwahati, where a passion for baking turns simple ingredients into delightful treats.\n\n"
                            . "From our rich, fudgy brownies and indulgent brownie cakes to our freshly baked dry cakes, every creation is prepared with love, care and attention to detail.\n\n"
                            . "Whether you're celebrating a special occasion, surprising someone you love or simply treating yourself, we're here to make your moments a little sweeter.",
        'about_quote'      => 'Because at Bliss Bites, every bite brings a smile!',
        'about_image'      => 'images/about-nutan.jpg',
        'about_image_mobile' => 'images/about-brownie.jpg',
        'delivery_area'    => 'Guwahati',
    ];
    $st = $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?)');
    foreach ($settings as $k => $v) $st->execute([$k, $v]);

    $st = $pdo->prepare('INSERT INTO menu_items (name, description, price, image, available, sort_order) VALUES (?, ?, ?, ?, 1, ?)');
    foreach ([['Brownies', 'images/menu-brownies.jpg'], ['Brownie Cakes', 'images/menu-brownie-cakes.jpg'],
              ['Dry Cakes', 'images/menu-dry-cakes.jpg'], ['Marble & More', 'images/menu-marble.jpg']] as $i => [$n, $img]) {
        $st->execute([$n, '', '', $img, $i]);
    }
    $st = $pdo->prepare('INSERT INTO gallery (image, caption, sort_order) VALUES (?, ?, ?)');
    foreach (['Stacked chocolate brownies', 'Chocolate chip cake', 'Biscoff brownies', 'Marble cake slices', 'Brownies with cookie crumble'] as $i => $c) {
        $st->execute(['images/gallery-' . ($i + 1) . '.jpg', $c, $i]);
    }
    $st = $pdo->prepare('INSERT INTO stories (image, link, caption, sort_order) VALUES (?, ?, ?, ?)');
    foreach (['Pouring chocolate batter', 'Fresh brownie tray', 'Sprinkling chocolate chips', 'Chocolate drizzle brownies', 'Bliss Bites gift box'] as $i => $c) {
        $st->execute(['images/story-' . ($i + 1) . '.jpg', '', $c, $i]);
    }
}
