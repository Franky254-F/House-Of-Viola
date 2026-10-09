<?php
// One-time installer. Open http://localhost/<this-folder>/install.php
require __DIR__ . '/config.php';
$lock = __DIR__ . '/install.lock';
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$schema = [
"CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  email VARCHAR(120) NULL UNIQUE,
  phone VARCHAR(20) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(60) PRIMARY KEY,
  v TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  icon VARCHAR(16) NOT NULL DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NULL,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(190) NOT NULL UNIQUE,
  description TEXT,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  old_price DECIMAL(12,2) NULL,
  stock INT NOT NULL DEFAULT 0,
  sizes VARCHAR(255) NOT NULL DEFAULT '',
  colors VARCHAR(255) NOT NULL DEFAULT '',
  image VARCHAR(255) NULL,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  flash_sale TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (category_id), INDEX (active), INDEX (flash_sale), INDEX (featured),
  CONSTRAINT fk_prod_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS product_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  image VARCHAR(255) NOT NULL,
  CONSTRAINT fk_img_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS banners (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL DEFAULT '',
  subtitle VARCHAR(200) NOT NULL DEFAULT '',
  link VARCHAR(255) NOT NULL DEFAULT '',
  image VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_no VARCHAR(30) NOT NULL UNIQUE,
  access_token VARCHAR(40) NOT NULL,
  customer_id INT NULL,
  customer_name VARCHAR(80) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  location VARCHAR(120) NOT NULL,
  address VARCHAR(255) NOT NULL DEFAULT '',
  notes VARCHAR(500) NOT NULL DEFAULT '',
  subtotal DECIMAL(12,2) NOT NULL,
  delivery_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'New',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (status), INDEX (created_at), INDEX (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NULL,
  name VARCHAR(160) NOT NULL,
  variant VARCHAR(120) NOT NULL DEFAULT '',
  price DECIMAL(12,2) NOT NULL,
  qty INT NOT NULL,
  INDEX (order_id),
  CONSTRAINT fk_item_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

$done = false; $error = ''; $in = ['store_name' => 'Online Shop Now', 'whatsapp' => '', 'admin_user' => 'admin', 'sample' => '1'];

// Already installed? (the lock file can disappear on hosts that reset files, so also look for an admin in the database)
$installed = is_file($lock);
if (!$installed) {
    try {
        $probe = new PDO(db_dsn(), DB_USER, DB_PASS, db_pdo_options());
        $installed = (bool)$probe->query('SELECT 1 FROM admins LIMIT 1')->fetchColumn();
    } catch (Throwable $e) { /* database or tables not there yet: fine, we are about to create them */ }
}
if ($installed && !ALLOW_REINSTALL) {
    $done = 'already';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($in as $k => $_) $in[$k] = trim((string)($_POST[$k] ?? ''));
    $pass = (string)($_POST['admin_pass'] ?? ''); $pass2 = (string)($_POST['admin_pass2'] ?? '');
    if ($in['store_name'] === '')                            $error = 'Enter your store name.';
    elseif (!preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $in['admin_user'])) $error = 'Admin username must be 3-40 letters or numbers.';
    elseif (strlen($pass) < 8)                               $error = 'Choose an admin password of at least 8 characters.';
    elseif ($pass !== $pass2)                                $error = 'The two passwords do not match.';
    else {
        try {
            try {   // On XAMPP this creates the database. Shared hosts and cloud databases already made it for you, so a refusal here is fine.
                $root = new PDO(db_dsn(false), DB_USER, DB_PASS, db_pdo_options());
                $root->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', DB_NAME) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            } catch (Throwable $ignored) {}
            $pdo = new PDO(db_dsn(), DB_USER, DB_PASS, db_pdo_options());
            foreach ($schema as $sql) $pdo->exec($sql);
            // Upgrade a database created by an older version of this shop (adds the new columns if missing).
            $addCol = function (string $table, string $col, string $def) use ($pdo) {
                if (!$pdo->query("SHOW COLUMNS FROM `$table` LIKE " . $pdo->quote($col))->fetch())
                    $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
            };
            $addCol('products', 'sizes', "VARCHAR(255) NOT NULL DEFAULT ''");
            $addCol('products', 'colors', "VARCHAR(255) NOT NULL DEFAULT ''");
            $addCol('order_items', 'variant', "VARCHAR(120) NOT NULL DEFAULT ''");
            $addCol('orders', 'customer_id', 'INT NULL');

            $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)')
                ->execute([$in['admin_user'], password_hash($pass, PASSWORD_DEFAULT)]);

            // WhatsApp number: keep digits, turn a leading 0 into the Kenyan code
            $wa = preg_replace('/\D+/', '', $in['whatsapp']);
            if ($wa !== '' && $wa[0] === '0') $wa = '254' . substr($wa, 1);
            elseif (strlen($wa) === 9) $wa = '254' . $wa;
            $set = $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
            $set->execute(['store_name', $in['store_name']]);
            $set->execute(['whatsapp', $wa]);

            if ($in['sample'] === '1' && !(int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn()) {
                $cat = $pdo->prepare('INSERT INTO categories (name, slug, sort_order) VALUES (?,?,?)');
                foreach ([['Women', 'women'], ['Men', 'men'], ['Kids', 'kids'], ['Shoes', 'shoes'], ['Bags', 'bags'], ['Accessories', 'accessories']] as $n => $c)
                    $cat->execute([$c[0], $c[1], $n + 1]);
                $ids = $pdo->query('SELECT slug, id FROM categories')->fetchAll(PDO::FETCH_KEY_PAIR);
                $prod = $pdo->prepare('INSERT INTO products (category_id, name, slug, description, price, old_price, stock, sizes, colors, featured, flash_sale) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                $tops = 'XS, S, M, L, XL'; $kids = '2-3 yrs, 4-5 yrs, 6-7 yrs, 8-9 yrs';
                $shoesW = '36, 37, 38, 39, 40, 41'; $shoesM = '40, 41, 42, 43, 44, 45'; $shoesAll = '36, 37, 38, 39, 40, 41, 42, 43, 44, 45';
                $note = "\n\nThis is a sample item. Edit or delete it in the admin panel.";
                $samples = [
                    // category, name, slug, description, price, old price, stock, sizes, colours, featured, sale
                    ['women', 'Floral Wrap Dress', 'floral-wrap-dress', "Flowy wrap dress with a tie waist. Soft, breathable fabric." . $note, 2400, 3200, 14, $tops, 'Blue floral, Black', 1, 1],
                    ['women', 'High-Waist Straight Jeans', 'high-waist-straight-jeans', "Comfortable stretch denim with a flattering high waist." . $note, 2200, null, 20, 'S, M, L, XL', 'Light blue, Dark blue', 0, 0],
                    ['women', 'Satin Button Blouse', 'satin-button-blouse', "Smooth satin blouse that works for the office or a night out." . $note, 1500, 1900, 18, $tops, 'Ivory, Black, Dusty pink', 1, 0],
                    ['men', 'Slim Fit Chinos', 'slim-fit-chinos', "Everyday chinos with a clean, slim cut." . $note, 2300, null, 22, '30, 32, 34, 36, 38', 'Khaki, Navy, Olive', 0, 0],
                    ['men', 'Classic Oxford Shirt', 'classic-oxford-shirt', "Crisp cotton shirt, smart and easy to style." . $note, 1800, 2400, 16, 'S, M, L, XL, XXL', 'White, Sky blue', 1, 1],
                    ['men', 'Hooded Sweatshirt', 'hooded-sweatshirt', "Warm fleece-lined hoodie for cool evenings." . $note, 2600, null, 12, 'M, L, XL, XXL', 'Black, Grey, Navy', 0, 0],
                    ['kids', 'Kids Cotton Tracksuit', 'kids-cotton-tracksuit', "Soft two-piece tracksuit for school runs and play." . $note, 1700, 2100, 15, $kids, 'Grey, Blue, Red', 0, 1],
                    ['shoes', 'Leather Court Heels', 'leather-court-heels', "Classic block heels you can wear all day." . $note, 3200, 3900, 9, $shoesW, 'Black, Nude', 1, 0],
                    ['shoes', 'Leather Loafers', 'leather-loafers', "Handsome slip-on loafers with a cushioned sole." . $note, 3800, null, 10, $shoesM, 'Black, Brown', 0, 0],
                    ['shoes', 'Canvas Sneakers', 'canvas-sneakers', "Lightweight everyday sneakers for him and her." . $note, 2500, 3000, 25, $shoesAll, 'White, Black', 1, 1],
                    ['bags', 'Structured Tote Bag', 'structured-tote-bag', "Roomy tote with a zip pocket, fits a laptop and your essentials." . $note, 3500, 4500, 8, '', 'Tan, Black, Cream', 1, 0],
                    ['bags', 'Crossbody Bag', 'crossbody-bag', "Compact bag with an adjustable strap, hands-free and stylish." . $note, 2200, null, 14, '', 'Black, Wine, Green', 0, 0],
                    ['bags', 'Everyday Backpack', 'everyday-backpack', "Water-resistant backpack with padded straps." . $note, 2800, 3400, 11, '', 'Black, Grey', 0, 1],
                    ['accessories', 'Leather Belt', 'leather-belt', "Genuine leather belt with a brushed metal buckle." . $note, 900, null, 30, '28-32, 34-38, 40-44', 'Black, Brown', 0, 0],
                    ['accessories', 'Classic Sunglasses', 'classic-sunglasses', "UV-protected lenses in a timeless frame." . $note, 1200, 1600, 20, '', 'Black, Tortoise', 0, 0],
                ];
                foreach ($samples as $s) $prod->execute([$ids[$s[0]] ?? null, $s[1], $s[2], $s[3], $s[4], $s[5], $s[6], $s[7], $s[8], $s[9], $s[10]]);
            }
            @file_put_contents($lock, 'installed ' . date('c'));
            $done = 'ok';
        } catch (Throwable $e) {
            $error = 'Could not set up the database: ' . $e->getMessage() . ' (Is MySQL started in the XAMPP Control Panel? Check DB_USER and DB_PASS in config.php.)';
        }
    }
}
$base = BASE_URL;
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Set up Online Shop Now</title>
<style>
body{font:16px/1.55 "Segoe UI",system-ui,sans-serif;background:#0c0c0d;color:#f3eee4;margin:0;padding:24px}
.box{max-width:540px;margin:4vh auto;background:#151517;border:1px solid rgba(212,168,83,.45);padding:34px}
h1{margin:0 0 6px;font:500 34px/1.1 "Cormorant Garamond",Georgia,serif;letter-spacing:.02em}p.sub{color:#a8a296;margin:0 0 22px}
label{display:block;font-weight:600;font-size:13px;letter-spacing:.05em;margin:16px 0 6px}
input[type=text],input[type=password]{width:100%;padding:12px 14px;border:1px solid rgba(255,255,255,.18);border-radius:2px;background:#0c0c0d;color:#f3eee4;font:inherit}
input:focus{outline:none;border-color:#d4a853}
button,.btn{display:inline-block;margin-top:24px;background:linear-gradient(135deg,#f0d890,#d4a853 55%,#a47c2c);color:#17120a;border:0;border-radius:2px;padding:14px 28px;font:700 12.5px/1.2 "Segoe UI",sans-serif;letter-spacing:.16em;text-transform:uppercase;cursor:pointer;text-decoration:none}
.err{background:rgba(255,122,138,.1);border:1px solid rgba(255,122,138,.5);color:#ffc0c8;padding:12px 14px;margin-bottom:12px}
.ok{background:rgba(37,184,101,.1);border:1px solid rgba(37,184,101,.5);color:#a6f0c6;padding:12px 14px}small{color:#a8a296;font-weight:400}
code{background:#0c0c0d;padding:1px 6px}
.row{display:flex;gap:10px;align-items:center;margin-top:16px}.row input{width:auto}
</style></style></head><body><div class="box">
<?php if ($done === 'ok'): ?>
    <h1>Your store is ready</h1><p class="sub">The database and your admin login have been created.</p>
    <div class="ok">Next: sign in to the admin panel. Upload your <b>Hero banner</b>, add your own products and pictures, and check your WhatsApp number in <b>Store settings</b>.</div>
    <a class="btn" href="<?= $h($base) ?>/admin/">Open the admin panel</a>
    <a class="btn" style="background:#2a2a2e;color:#f3eee4;margin-left:6px" href="<?= $h($base) ?>/">View the store</a>
<?php elseif ($done === 'already'): ?>
    <h1>Already installed</h1><p class="sub">This store has already been set up, so this page is switched off. You can delete <code>install.php</code> from the server. (To run it again, for example to reset the admin password, set <code>ALLOW_REINSTALL</code> to 1 in <code>config.php</code>, then set it back to 0.)</p>
    <a class="btn" href="<?= $h($base) ?>/admin/">Open the admin panel</a>
<?php else: ?>
    <h1>Set up Online Shop Now</h1><p class="sub">This takes about a minute. XAMPP's Apache and MySQL must be running.</p>
    <?php if ($error): ?><div class="err"><?= $h($error) ?></div><?php endif; ?>
    <form method="post">
        <label for="store_name">Store / brand name</label><input id="store_name" type="text" name="store_name" value="<?= $h($in['store_name']) ?>" required>
        <label for="whatsapp">WhatsApp number for orders <small>(e.g. 0712345678)</small></label><input id="whatsapp" type="text" name="whatsapp" value="<?= $h($in['whatsapp']) ?>">
        <label for="admin_user">Admin username</label><input id="admin_user" type="text" name="admin_user" value="<?= $h($in['admin_user']) ?>" required>
        <label for="admin_pass">Admin password <small>(at least 8 characters)</small></label><input id="admin_pass" type="password" name="admin_pass" required>
        <label for="admin_pass2">Repeat password</label><input id="admin_pass2" type="password" name="admin_pass2" required>
        <div class="row"><input id="sample" type="checkbox" name="sample" value="1" checked><label for="sample" style="margin:0;font-weight:400">Add sample clothes, shoes and bags so I can see how it looks (delete them later)</label></div>
        <button>Install store</button>
    </form>
<?php endif; ?>
</div></body></html>
