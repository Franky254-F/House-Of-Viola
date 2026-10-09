<?php
require __DIR__ . '/includes/functions.php';

$slug  = trim($_GET['c'] ?? '');
$q     = trim($_GET['q'] ?? '');
$sort  = $_GET['sort'] ?? 'new';
$page  = max(1, (int)($_GET['page'] ?? 1));
$per   = 20;

$where = ['p.active = 1']; $args = []; $heading = 'All products';
if ($slug !== '') {
    $st = db()->prepare('SELECT * FROM categories WHERE slug = ? AND active = 1'); $st->execute([$slug]);
    $cat = $st->fetch();
    if (!$cat) { http_response_code(404); $heading = 'Category not found'; $where[] = '0 = 1'; }
    else { $where[] = 'p.category_id = ?'; $args[] = $cat['id']; $heading = $cat['name']; }
}
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
    $args[] = $like; $args[] = $like; $heading = 'Results for "' . $q . '"';
}
if (!empty($_GET['flash']))    { $where[] = 'p.flash_sale = 1'; $heading = setting('flash_title'); }
if (!empty($_GET['featured'])) { $where[] = 'p.featured = 1';   $heading = 'Our favourites'; }

$orders = ['new' => 'p.id DESC', 'low' => 'p.price ASC', 'high' => 'p.price DESC'];
$orderBy = $orders[$sort] ?? $orders['new'];
$wsql = implode(' AND ', $where);

$st = db()->prepare("SELECT COUNT(*) FROM products p WHERE $wsql"); $st->execute($args);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total / $per)); $page = min($page, $pages);
$st = db()->prepare("SELECT p.* FROM products p WHERE $wsql ORDER BY (p.stock > 0) DESC, $orderBy LIMIT $per OFFSET " . (($page - 1) * $per));
$st->execute($args);
$products = $st->fetchAll();

$page_title = $heading . ' | ' . setting('store_name');
require __DIR__ . '/includes/header.php';
$keep = array_filter(['c' => $slug, 'q' => $q, 'sort' => $sort, 'flash' => $_GET['flash'] ?? '', 'featured' => $_GET['featured'] ?? ''], 'strlen');
?>
<div class="page-title">
    <h1 class="page-h"><?= e($heading) ?> <small><?= $total ?> item<?= $total === 1 ? '' : 's' ?></small></h1>
    <form class="sort" method="get">
        <?php foreach (['c' => $slug, 'q' => $q] as $k => $v) if ($v !== '') echo '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">'; ?>
        <select name="sort" onchange="this.form.submit()" aria-label="Sort products">
            <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>Newest</option>
            <option value="low" <?= $sort === 'low' ? 'selected' : '' ?>>Price: low to high</option>
            <option value="high" <?= $sort === 'high' ? 'selected' : '' ?>>Price: high to low</option>
        </select>
    </form>
</div>
<div class="chips">
    <a href="<?= e(url('category.php')) ?>" class="<?= ($slug === '' && $q === '' && empty($_GET['flash']) && empty($_GET['featured'])) ? 'on' : '' ?>">All</a>
    <?php foreach ($cats as $cc): ?><a href="<?= e(url('category.php?c=' . urlencode($cc['slug']))) ?>" class="<?= $slug === $cc['slug'] ? 'on' : '' ?>"><?= e($cc['name']) ?></a><?php endforeach; ?>
</div>
<?php if ($products): ?>
    <div class="grid"><?php foreach ($products as $p) product_card($p); ?></div>
    <?= pager($page, $pages, $keep) ?>
<?php else: ?>
    <div class="empty"><p>Nothing found. Try a different word or <a class="lnk" href="<?= e(url('category.php')) ?>">browse everything</a>.</p></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
