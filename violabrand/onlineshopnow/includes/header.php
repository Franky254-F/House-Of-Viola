<?php
$cats      = db()->query('SELECT * FROM categories WHERE active = 1 ORDER BY sort_order, name')->fetchAll();
$has_sale  = (bool)db()->query('SELECT 1 FROM products WHERE active = 1 AND flash_sale = 1 LIMIT 1')->fetchColumn();
$store     = setting('store_name');
$title     = isset($page_title) ? $page_title : $store;
$gold      = safe_color(setting('accent_color'), '#d4a853');
$bg        = safe_color(setting('bg_color'), '#0c0c0d');
$cur_cat   = $_GET['c'] ?? '';
$announce  = array_values(array_filter(array_map('trim', explode('|', setting('announcement'))), 'strlen'));
$cust      = customer();
$wa_head   = normalize_phone(setting('whatsapp'));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="<?= e($bg) ?>">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($meta_desc ?? setting('tagline')) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($meta_desc ?? setting('tagline')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
<style>:root{--gold:<?= e($gold) ?>;--bg:<?= e($bg) ?>;--on-gold:<?= e(on_color($gold)) ?>}</style>
</head>
<body>
<?php if (!empty($_SESSION['admin_id'])): ?>
<div class="adminbar"><span>You are signed in as the shop owner</span>
    <a href="<?= e(url('admin/index.php')) ?>">Dashboard</a><a href="<?= e(url('admin/orders.php')) ?>">Orders</a>
    <a href="<?= e(url('admin/products.php')) ?>">Products</a><a href="<?= e(url('admin/hero.php')) ?>">Hero banner</a>
    <a href="<?= e(url('admin/logout.php')) ?>">Sign out</a></div>
<?php endif; ?>
<?php if ($announce): ?>
<div class="announce"><span class="gl"></span>
    <?php foreach ($announce as $i => $a): if ($i) echo '<i class="dot"></i>'; echo '<span>' . e($a) . '</span>'; endforeach; ?>
<span class="gl"></span></div>
<?php endif; ?>

<header class="site-header">
    <div class="container hdr">
        <?= brand_html() ?>
        <form class="hdr-search" action="<?= e(url('category.php')) ?>" method="get" role="search">
            <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Search dresses, sneakers, bags" aria-label="Search the store">
            <button class="hdr-search-btn" aria-label="Search">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            </button>
        </form>
        <div class="hdr-actions">
            <a class="cart-link" href="<?= e(url('cart.php')) ?>" aria-label="Cart, <?= cart_count() ?> items">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 8h14l-1.2 11.2a1 1 0 0 1-1 .8H7.2a1 1 0 0 1-1-.8z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/></svg>
                <span class="cart-txt">Cart</span><b class="cart-n <?= cart_count() ? '' : 'zero' ?>"><?= cart_count() ?></b>
            </a>
            <button type="button" class="menu-btn" id="menuBtn" aria-label="Open menu" aria-expanded="false" aria-controls="menuDrawer">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M3 6.5h18M3 12h18M3 17.5h18"/></svg>
            </button>
        </div>
    </div>
</header>

<div class="drawer-overlay" id="menuOverlay" hidden></div>
<aside class="drawer" id="menuDrawer" aria-label="Menu" aria-hidden="true" tabindex="-1">
    <div class="drawer-head">
        <span class="drawer-title">Menu</span>
        <button type="button" class="drawer-close" id="menuClose" aria-label="Close menu">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M5 5l14 14M19 5L5 19"/></svg>
        </button>
    </div>
    <div class="drawer-body">
        <div class="drawer-acct">
            <?php if ($cust): ?>
                <p class="drawer-hi">Hi, <b><?= e(first_name($cust['name'])) ?></b></p>
                <div class="drawer-btns">
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('account.php')) ?>">My account</a>
                    <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm">Sign out</button></form>
                </div>
            <?php else: ?>
                <p class="drawer-hi">Welcome</p>
                <div class="drawer-btns">
                    <a class="btn btn-gold btn-sm" href="<?= e(url('login.php')) ?>">Sign in</a>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('register.php')) ?>">Create account</a>
                </div>
            <?php endif; ?>
        </div>
        <h3 class="drawer-h">Shop</h3>
        <nav class="drawer-nav" aria-label="Shop by category">
            <a href="<?= e(url('category.php')) ?>">All products</a>
            <?php foreach ($cats as $c): ?><a href="<?= e(url('category.php?c=' . urlencode($c['slug']))) ?>" class="<?= $cur_cat === $c['slug'] ? 'on' : '' ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
            <?php if ($has_sale): ?><a class="is-sale" href="<?= e(url('category.php?flash=1')) ?>"><?= e(setting('flash_title')) ?></a><?php endif; ?>
        </nav>
        <h3 class="drawer-h">Help</h3>
        <nav class="drawer-nav" aria-label="Help">
            <a href="<?= e(url('track.php')) ?>">Track my order</a>
            <a href="<?= e(url('cart.php')) ?>">My cart</a>
            <?php if ($wa_head): ?><a href="<?= e(wa_url($wa_head, 'Hello ' . $store . ', I have a question.')) ?>" target="_blank" rel="noopener">Chat on WhatsApp</a><?php endif; ?>
        </nav>
    </div>
</aside>

<main class="main">
<?php if (!empty($hero_callback)) $hero_callback(); ?>
<div class="container page">
<?php show_flashes(); ?>
