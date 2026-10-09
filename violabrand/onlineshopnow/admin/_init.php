<?php
require_once __DIR__ . '/../includes/functions.php';

function require_admin(): void {
    if (empty($_SESSION['admin_id'])) redirect('admin/login.php');
}
function admin_user(): string { return $_SESSION['admin_name'] ?? 'admin'; }

/** Whole-number or decimal input from a form field. Returns null when blank or invalid. */
function num_or_null($raw): ?float {
    $raw = trim(str_replace(',', '', (string)$raw));
    return ($raw !== '' && is_numeric($raw) && (float)$raw >= 0) ? (float)$raw : null;
}

function admin_header(string $title, string $active = ''): void {
    $row = db()->query("SELECT COUNT(*) AS n, COALESCE(MAX(id),0) AS last_id FROM orders WHERE status = 'New'")->fetch();
    $newOrders = (int)$row['n'];
    $latest = (int)db()->query('SELECT COALESCE(MAX(id),0) FROM orders')->fetchColumn();
    $nav = [
        'dashboard'  => ['Dashboard', 'admin/index.php', '📊'],
        'orders'     => ['Orders', 'admin/orders.php', '🧾'],
        'products'   => ['Products', 'admin/products.php', '📦'],
        'customers'  => ['Customers', 'admin/customers.php', '👥'],
        'categories' => ['Categories', 'admin/categories.php', '🗂️'],
        'hero'       => ['Hero banner', 'admin/hero.php', '🖼️'],
        'banners'    => ['Promo banners', 'admin/banners.php', '🎞️'],
        'settings'   => ['Store settings', 'admin/settings.php', '⚙️'],
        'account'    => ['My account', 'admin/password.php', '🔑'],
    ];
    ?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | Admin</title>
<link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>">
<?php $__gold = safe_color(setting('accent_color'), '#d4a853'); ?>
<style>:root{--brand:<?= e($__gold) ?>;--on-brand:<?= e(on_color($__gold)) ?>;--ink:<?= e(safe_color(setting('bg_color'), '#0c0c0d')) ?>}</style>
</head>
<body data-latest="<?= $latest ?>" data-poll="<?= e(url('admin/poll.php')) ?>" data-orders="<?= e(url('admin/orders.php')) ?>">
<div class="a-wrap">
    <aside class="a-side" id="aSide">
        <div class="a-brand"><?= e(setting('store_name')) ?><small>Admin panel</small></div>
        <nav>
        <?php foreach ($nav as $key => [$label, $href, $icon]): ?>
            <a href="<?= e(url($href)) ?>" class="<?= $active === $key ? 'on' : '' ?>"><span><?= $icon ?></span><?= e($label) ?>
                <?php if ($key === 'orders'): ?><b class="a-badge" id="newBadge" <?= $newOrders ? '' : 'hidden' ?>><?= $newOrders ?></b><?php endif; ?></a>
        <?php endforeach; ?>
        </nav>
        <div class="a-side-foot">
            <a href="<?= e(url('index.php')) ?>" target="_blank">🌐 View store</a>
            <a href="<?= e(url('admin/logout.php')) ?>">🚪 Sign out (<?= e(admin_user()) ?>)</a>
        </div>
    </aside>
    <div class="a-main">
        <header class="a-top"><button id="aMenu" class="a-menu" aria-label="Menu">&#9776;</button><h1><?= e($title) ?></h1></header>
        <div class="a-content">
<?php show_flashes();
}
function admin_footer(): void { ?>
        </div>
    </div>
</div>
<div class="toast" id="toast" hidden></div>
<script src="<?= e(url('assets/js/admin.js')) ?>"></script>
</body></html>
<?php }
