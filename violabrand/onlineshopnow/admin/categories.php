<?php
require __DIR__ . '/_init.php'; require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0); $act = $_POST['action'] ?? '';
    if ($act === 'save') {
        $name = trim($_POST['name'] ?? '');
        $sort = (int)($_POST['sort_order'] ?? 0); $active = empty($_POST['active']) ? 0 : 1;
        if ($name === '' || mb_strlen($name) > 80) { flash('error', 'Enter a category name.'); }
        elseif ($id) {
            db()->prepare('UPDATE categories SET name=?, sort_order=?, active=? WHERE id=?')->execute([$name, $sort, $active, $id]);
            flash('success', 'Category updated.');
        } else {
            db()->prepare('INSERT INTO categories (name, slug, sort_order, active) VALUES (?,?,?,?)')
                ->execute([$name, unique_slug('categories', $name), $sort, $active]);
            flash('success', 'Category added.');
        }
    } elseif ($act === 'delete') {
        db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
        flash('success', 'Category deleted. Its products are kept and now have no category.');
    }
    redirect('admin/categories.php');
}
$edit = null;
if (!empty($_GET['edit'])) { $st = db()->prepare('SELECT * FROM categories WHERE id = ?'); $st->execute([(int)$_GET['edit']]); $edit = $st->fetch() ?: null; }
$rows = db()->query('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS n FROM categories c ORDER BY sort_order, name')->fetchAll();
admin_header('Categories', 'categories');
?>
<div class="cols">
<section class="box">
    <div class="tbl-wrap"><table class="tbl"><tr><th>Category</th><th>Products</th><th>Order</th><th></th></tr>
    <?php foreach ($rows as $c): ?>
        <tr class="<?= $c['active'] ? '' : 'dim' ?>"><td><?= e($c['name']) ?><?= $c['active'] ? '' : ' <small class="muted">(hidden)</small>' ?></td><td><?= (int)$c['n'] ?></td><td><?= (int)$c['sort_order'] ?></td>
        <td class="actions"><a class="btn btn-sm" href="?edit=<?= (int)$c['id'] ?>">Edit</a>
            <form method="post" class="inline" data-confirm="Delete this category? Products stay in the store without a category."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-danger" name="action" value="delete">Delete</button></form></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="4" class="muted" style="text-align:center;padding:22px">No categories yet. Add your first one.</td></tr><?php endif; ?>
    </table></div>
</section>
<section class="box form-box">
    <h2><?= $edit ? 'Edit category' : 'Add a category' ?></h2>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <div class="field"><label for="name">Name</label><input id="name" name="name" value="<?= e($edit['name'] ?? '') ?>" maxlength="80" required></div>
        <div class="field"><label for="sort_order">Position <small>(1 shows first)</small></label><input id="sort_order" name="sort_order" type="number" value="<?= (int)($edit['sort_order'] ?? 0) ?>"></div>
        <div class="checks"><label><input type="checkbox" name="active" value="1" <?= ($edit['active'] ?? 1) ? 'checked' : '' ?>> Show in the store</label></div>
        <div class="form-actions"><button class="btn btn-primary"><?= $edit ? 'Save changes' : 'Add category' ?></button><?php if ($edit): ?> <a class="btn" href="<?= e(url('admin/categories.php')) ?>">Cancel</a><?php endif; ?></div>
    </form>
</section>
</div>
<?php admin_footer(); ?>
