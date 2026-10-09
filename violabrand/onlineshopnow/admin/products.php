<?php
require __DIR__ . '/_init.php'; require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $act = $_POST['action'] ?? '';
    if ($act === 'toggle') {
        db()->prepare('UPDATE products SET active = 1 - active WHERE id = ?')->execute([$id]);
        flash('success', 'Product visibility changed.');
    } elseif ($act === 'delete') {
        $st = db()->prepare('SELECT image FROM products WHERE id = ?'); $st->execute([$id]);
        $img = $st->fetchColumn();
        $g = db()->prepare('SELECT image FROM product_images WHERE product_id = ?'); $g->execute([$id]);
        foreach ($g->fetchAll(PDO::FETCH_COLUMN) as $path) delete_upload($path);
        delete_upload($img ?: null);
        db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
        flash('success', 'Product deleted. Past orders keep their details.');
    }
    redirect('admin/products.php' . (!empty($_POST['back']) ? '?' . ltrim((string)$_POST['back'], '?') : ''));
}

$q = trim($_GET['q'] ?? ''); $cat = (int)($_GET['cat'] ?? 0); $page = max(1, (int)($_GET['page'] ?? 1)); $per = 25;
$where = ['1=1']; $args = [];
if ($q !== '') { $where[] = 'p.name LIKE ?'; $args[] = '%' . $q . '%'; }
if ($cat)      { $where[] = 'p.category_id = ?'; $args[] = $cat; }
$w = implode(' AND ', $where);
$st = db()->prepare("SELECT COUNT(*) FROM products p WHERE $w"); $st->execute($args);
$total = (int)$st->fetchColumn(); $pages = max(1, (int)ceil($total / $per)); $page = min($page, $pages);
$st = db()->prepare("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE $w ORDER BY p.id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
$st->execute($args); $rows = $st->fetchAll();
$cats = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$back = http_build_query(array_filter(['q' => $q, 'cat' => $cat, 'page' => $page > 1 ? $page : '']));

admin_header('Products', 'products');
?>
<div class="toolbar">
    <form method="get" class="inline">
        <input name="q" value="<?= e($q) ?>" placeholder="Search products" aria-label="Search">
        <select name="cat" aria-label="Category"><option value="0">All categories</option>
            <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
        <button class="btn">Filter</button>
    </form>
    <a class="btn btn-primary" href="<?= e(url('admin/product_form.php')) ?>">+ Add product</a>
</div>
<div class="box"><div class="tbl-wrap"><table class="tbl">
    <tr><th></th><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Flags</th><th></th></tr>
    <?php foreach ($rows as $p): ?>
    <tr class="<?= $p['active'] ? '' : 'dim' ?>">
        <td><img class="thumb" src="<?= e(img_url($p['image'])) ?>" alt=""></td>
        <td><a href="<?= e(url('admin/product_form.php?id=' . $p['id'])) ?>"><b><?= e($p['name']) ?></b></a><?= $p['active'] ? '' : ' <small class="muted">(hidden)</small>' ?></td>
        <td><?= e($p['cat_name'] ?? '-') ?></td>
        <td><?= e(money($p['price'])) ?><?php if ($p['old_price']): ?><br><small class="muted"><s><?= e(money($p['old_price'])) ?></s></small><?php endif; ?></td>
        <td class="<?= $p['stock'] <= 0 ? 'bad' : ($p['stock'] <= 5 ? 'warn' : '') ?>"><?= (int)$p['stock'] ?></td>
        <td><?= $p['featured'] ? '<span class="chip">Favourite</span> ' : '' ?><?= $p['flash_sale'] ? '<span class="chip chip-hot">Sale</span>' : '' ?></td>
        <td class="actions">
            <a class="btn btn-sm" href="<?= e(url('admin/product_form.php?id=' . $p['id'])) ?>">Edit</a>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
                <button class="btn btn-sm" name="action" value="toggle"><?= $p['active'] ? 'Hide' : 'Show' ?></button></form>
            <form method="post" class="inline" data-confirm="Delete this product and its pictures? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
                <button class="btn btn-sm btn-danger" name="action" value="delete">Delete</button></form>
        </td>
    </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="muted" style="text-align:center;padding:26px">No products found. Click <b>+ Add product</b> to create one.</td></tr><?php endif; ?>
</table></div></div>
<?= pager($page, $pages, ['q' => $q, 'cat' => $cat]) ?>
<?php admin_footer(); ?>
