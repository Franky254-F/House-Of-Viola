<?php
require __DIR__ . '/includes/functions.php';
$page_title = setting('store_name') . ' | ' . setting('tagline');

$promos   = db()->query('SELECT * FROM banners WHERE active = 1 ORDER BY sort_order, id LIMIT 3')->fetchAll();
$latest   = db()->query('SELECT * FROM products WHERE active = 1 ORDER BY id DESC LIMIT 8')->fetchAll();
$featured = db()->query('SELECT * FROM products WHERE active = 1 AND featured = 1 ORDER BY id DESC LIMIT 4')->fetchAll();
$onsale   = db()->query('SELECT * FROM products WHERE active = 1 AND flash_sale = 1 ORDER BY id DESC LIMIT 8')->fetchAll();
$tiles    = db()->query("SELECT c.name, c.slug,
        (SELECT p.image FROM products p WHERE p.category_id = c.id AND p.active = 1 AND p.image IS NOT NULL AND p.image <> '' ORDER BY p.id DESC LIMIT 1) AS img,
        (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.active = 1) AS n
    FROM categories c WHERE c.active = 1 ORDER BY c.sort_order, c.name")->fetchAll();

// The hero is full-width, so it is drawn by the header just before the page content.
$hero_callback = function () {
    $img = setting('hero_image');
    $ov  = max(0, min(85, (int)setting('hero_overlay', '55'))) / 100;
    $b1  = trim(setting('hero_btn1_text')); $b2 = trim(setting('hero_btn2_text'));
    ?>
    <section class="hero <?= $img ? 'has-img' : '' ?>" style="--ov:<?= e($ov) ?>">
        <?php if ($img): ?><div class="hero-bg" style="background-image:url('<?= e(url($img)) ?>')"></div><?php endif; ?>
        <span class="arc arc-1" aria-hidden="true"></span><span class="arc arc-2" aria-hidden="true"></span>
        <div class="container hero-inner">
            <?php if (trim(setting('hero_eyebrow')) !== ''): ?><p class="eyebrow"><span class="gl"></span><?= e(setting('hero_eyebrow')) ?><span class="gl"></span></p><?php endif; ?>
            <h1><?= hero_title_html(setting('hero_title')) ?></h1>
            <i class="rule" aria-hidden="true"></i>
            <?php if (trim(setting('hero_text')) !== ''): ?><p class="hero-text"><?= e(setting('hero_text')) ?></p><?php endif; ?>
            <div class="hero-cta">
                <?php if ($b1 !== ''): ?><a class="btn btn-gold btn-lg" href="<?= e(hero_link(setting('hero_btn1_link'))) ?>"><?= e($b1) ?> <span aria-hidden="true">&rarr;</span></a><?php endif; ?>
                <?php if ($b2 !== ''): ?><a class="btn btn-ghost btn-lg" href="<?= e(hero_link(setting('hero_btn2_link'))) ?>"><?= e($b2) ?></a><?php endif; ?>
            </div>
        </div>
    </section>
    <?php
};
require __DIR__ . '/includes/header.php';
?>
<?php if ($tiles): ?>
<section class="section">
    <div class="sec-head"><h2>Shop by category</h2></div>
    <div class="tiles">
        <?php foreach ($tiles as $i => $t): ?>
        <a class="tile tile-<?= $i % 3 ?>" href="<?= e(url('category.php?c=' . urlencode($t['slug']))) ?>"
           <?= $t['img'] ? 'style="background-image:url(\'' . e(url($t['img'])) . '\')"' : '' ?>>
            <span class="tile-name"><?= e($t['name']) ?></span>
            <span class="tile-n"><?= (int)$t['n'] ?> item<?= (int)$t['n'] === 1 ? '' : 's' ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="sec-head"><h2>New in</h2><a href="<?= e(url('category.php')) ?>">See everything</a></div>
    <?php if ($latest): ?><div class="grid"><?php foreach ($latest as $p) product_card($p); ?></div>
    <?php else: ?><div class="empty"><p>No items yet. Add your first one in the <a class="lnk" href="<?= e(url('admin/')) ?>">admin panel</a>.</p></div><?php endif; ?>
</section>

<?php if ($promos): ?>
<section class="promos promos-<?= count($promos) ?>">
    <?php foreach ($promos as $b): ?>
    <a class="promo" href="<?= e($b['link'] !== '' ? $b['link'] : url('category.php')) ?>" style="background-image:url('<?= e(img_url($b['image'])) ?>')">
        <span class="promo-txt">
            <?php if ($b['title'] !== ''): ?><b><?= e($b['title']) ?></b><?php endif; ?>
            <?php if ($b['subtitle'] !== ''): ?><span><?= e($b['subtitle']) ?></span><?php endif; ?>
            <em class="promo-go">Shop now</em>
        </span>
    </a>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<ol class="how">
    <li><b>Pick your size</b><span>Choose the piece, then your size and colour.</span></li>
    <li><b>Place the order</b><span>Add your name, phone and where we should deliver.</span></li>
    <li><b>Confirm on WhatsApp</b><span>Send the order to us. We confirm and arrange delivery.</span></li>
</ol>

<?php if ($featured): ?>
<section class="section">
    <div class="sec-head"><h2>Our favourites</h2><a href="<?= e(url('category.php?featured=1')) ?>">See all</a></div>
    <div class="grid"><?php foreach ($featured as $p) product_card($p); ?></div>
</section>
<?php endif; ?>

<?php if ($onsale): ?>
<section class="section sale-section">
    <div class="sec-head">
        <h2><?= e(setting('flash_title')) ?>
            <?php if (setting('flash_end') !== ''): ?><span class="timer" id="countdown" data-end="<?= e(setting('flash_end')) ?>"></span><?php endif; ?></h2>
        <a href="<?= e(url('category.php?flash=1')) ?>">See all</a>
    </div>
    <div class="grid"><?php foreach ($onsale as $p) product_card($p); ?></div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
