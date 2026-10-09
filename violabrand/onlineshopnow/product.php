<?php
require __DIR__ . '/includes/functions.php';

$st = db()->prepare('SELECT p.*, c.name AS cat_name, c.slug AS cat_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.slug = ? AND p.active = 1');
$st->execute([$_GET['p'] ?? '']);
$p = $st->fetch();
if (!$p) {
    http_response_code(404);
    $page_title = 'Item not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty"><h2>We could not find that item</h2><p>It may have been removed. <a href="' . e(url('index.php')) . '" class="lnk">Back to the shop</a></p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}
$gal = db()->prepare('SELECT image FROM product_images WHERE product_id = ? ORDER BY id'); $gal->execute([$p['id']]);
$images = array_values(array_filter(array_merge([$p['image']], array_column($gal->fetchAll(), 'image'))));
$off = discount_pct($p);
$stock = (int)$p['stock'];
$sizes = product_options($p['sizes']); $colors = product_options($p['colors']);
$wa = normalize_phone(setting('whatsapp'));
$myLink = share_url('product.php?p=' . urlencode($p['slug']));

$rel = db()->prepare('SELECT * FROM products WHERE active = 1 AND category_id <=> ? AND id <> ? ORDER BY id DESC LIMIT 4');
$rel->execute([$p['category_id'], $p['id']]);
$related = $rel->fetchAll();

$page_title = $p['name'] . ' | ' . setting('store_name');
$meta_desc = mb_substr(preg_replace('/\s+/', ' ', $p['description']), 0, 150);
require __DIR__ . '/includes/header.php';
?>
<p class="crumbs"><a href="<?= e(url('index.php')) ?>">Home</a>
    <?php if ($p['cat_slug']): ?> / <a href="<?= e(url('category.php?c=' . urlencode($p['cat_slug']))) ?>"><?= e($p['cat_name']) ?></a><?php endif; ?></p>
<div class="pd">
    <div class="gallery">
        <?php if (count($images) > 1): ?><div class="thumbs"><?php foreach ($images as $i => $im): ?>
            <button type="button" class="<?= $i === 0 ? 'on' : '' ?>" data-src="<?= e(url($im)) ?>" aria-label="Picture <?= $i + 1 ?>"><img src="<?= e(url($im)) ?>" alt=""></button>
        <?php endforeach; ?></div><?php endif; ?>
        <div class="big"><img id="mainImg" src="<?= e(img_url($images ? $images[0] : null)) ?>" alt="<?= e($p['name']) ?>"></div>
    </div>
    <div class="pd-info">
        <h1><?= e($p['name']) ?></h1>
        <div class="big-price"><?= e(money($p['price'])) ?>
            <?php if ($off): ?><s><?= e(money($p['old_price'])) ?></s><span class="pill">-<?= $off ?>%</span><?php endif; ?></div>
        <p class="stockline">
            <?php if ($stock <= 0): ?><span class="stock-out">Sold out</span>
            <?php elseif ($stock <= 5): ?><span class="stock-low">Only <?= $stock ?> left</span>
            <?php else: ?><span class="stock-ok">In stock</span><?php endif; ?>
        </p>

        <?php if ($stock > 0): ?>
        <form method="post" action="<?= e(url('cart.php')) ?>">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <?php if ($sizes): ?>
            <fieldset class="opts"><legend>Size</legend>
                <?php foreach ($sizes as $s): ?><label class="opt"><input type="radio" name="size" value="<?= e($s) ?>" required><span><?= e($s) ?></span></label><?php endforeach; ?>
            </fieldset>
            <?php endif; ?>
            <?php if ($colors): ?>
            <fieldset class="opts"><legend>Colour</legend>
                <?php foreach ($colors as $c): ?><label class="opt"><input type="radio" name="color" value="<?= e($c) ?>" required><span><?= e($c) ?></span></label><?php endforeach; ?>
            </fieldset>
            <?php endif; ?>
            <div class="buy-row">
                <div class="qty"><button type="button" data-d="-" aria-label="Less">&minus;</button>
                    <input type="number" name="qty" value="1" min="1" max="<?= min($stock, 99) ?>" aria-label="Quantity">
                    <button type="button" data-d="+" aria-label="More">+</button></div>
                <button class="btn btn-dark btn-lg" name="action" value="add">Add to cart</button>
                <button class="btn btn-wa btn-lg" name="action" value="buy">Order on WhatsApp</button>
            </div>
        </form>
        <?php endif; ?>

        <?php if ($stock > 0 && !customer()): ?><p class="note-box" style="margin:0 0 14px">You will be asked to sign in or create a free account when you add this to your cart.</p><?php endif; ?>
        <div class="share-row">
            <button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e($myLink) ?>">Copy link</button>
            <a class="btn btn-ghost btn-sm" target="_blank" rel="noopener" href="<?= e('https://wa.me/?text=' . rawurlencode($p['name'] . ' - ' . money($p['price']) . ' ' . $myLink)) ?>">Share on WhatsApp</a>
            <?php if ($wa): ?><a class="btn btn-ghost btn-sm" target="_blank" rel="noopener" href="<?= e(wa_url($wa, 'Hello, I have a question about: ' . $p['name'] . ' ' . $myLink)) ?>">Ask us a question</a><?php endif; ?>
        </div>
        <p class="note-box"><?= e(setting('delivery_note')) ?></p>
        <?php if (trim($p['description']) !== ''): ?><div class="desc"><?= e($p['description']) ?></div><?php endif; ?>
    </div>
</div>
<?php if ($related): ?>
<section class="section">
    <div class="sec-head"><h2>You may also like</h2></div>
    <div class="grid"><?php foreach ($related as $r) product_card($r); ?></div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
