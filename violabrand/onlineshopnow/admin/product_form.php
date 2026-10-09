<?php
require __DIR__ . '/_init.php'; require_admin();

$id = (int)($_GET['id'] ?? 0);
$p = null; $gallery = [];
if ($id) {
    $st = db()->prepare('SELECT * FROM products WHERE id = ?'); $st->execute([$id]); $p = $st->fetch();
    if (!$p) { flash('error', 'Product not found.'); redirect('admin/products.php'); }
    $g = db()->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY id'); $g->execute([$id]); $gallery = $g->fetchAll();
}
$cats = db()->query('SELECT id, name FROM categories ORDER BY sort_order, name')->fetchAll();
$f = $p ?: ['name' => '', 'category_id' => '', 'price' => '', 'old_price' => '', 'stock' => 10, 'sizes' => '', 'colors' => '', 'description' => '', 'featured' => 0, 'flash_sale' => 0, 'active' => 1, 'image' => null];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $f['name'] = trim($_POST['name'] ?? '');
    $f['category_id'] = (int)($_POST['category_id'] ?? 0) ?: null;
    $f['description'] = trim($_POST['description'] ?? '');
    $f['sizes']  = implode(', ', product_options($_POST['sizes'] ?? ''));
    $f['colors'] = implode(', ', product_options($_POST['colors'] ?? ''));
    $price = num_or_null($_POST['price'] ?? ''); $old = num_or_null($_POST['old_price'] ?? '');
    $stockRaw = trim($_POST['stock'] ?? '');
    $f['price'] = $_POST['price'] ?? ''; $f['old_price'] = $_POST['old_price'] ?? ''; $f['stock'] = $stockRaw;
    foreach (['featured', 'flash_sale', 'active'] as $k) $f[$k] = empty($_POST[$k]) ? 0 : 1;
    if ($f['name'] === '' || mb_strlen($f['name']) > 160) $errors[] = 'Enter a product name (up to 160 characters).';
    if ($price === null) $errors[] = 'Enter a valid price, for example 1500.';
    if (trim((string)($_POST['old_price'] ?? '')) !== '' && $old === null) $errors[] = 'The old price must be a number (or leave it blank).';
    if (!ctype_digit($stockRaw)) $errors[] = 'Stock must be a whole number, 0 or more.';

    if (!$errors) {
        $saved = [];
        try {
            $newImg = save_image($_FILES['image'] ?? null, 'products'); if ($newImg) $saved[] = $newImg;
            $newGallery = [];
            foreach (files_list('gallery') as $file) { $path = save_image($file, 'products'); if ($path) { $saved[] = $path; $newGallery[] = $path; } }
            $oldPrice = ($old !== null && $old > $price) ? $old : null;

            if ($id) {
                $image = $newImg ?: $p['image'];
                db()->prepare('UPDATE products SET category_id=?, name=?, description=?, price=?, old_price=?, stock=?, sizes=?, colors=?, featured=?, flash_sale=?, active=?, image=? WHERE id=?')
                    ->execute([$f['category_id'], $f['name'], $f['description'], $price, $oldPrice, (int)$stockRaw, $f['sizes'], $f['colors'], $f['featured'], $f['flash_sale'], $f['active'], $image, $id]);
                if ($newImg && $p['image']) delete_upload($p['image']);
                foreach ((array)($_POST['del_gallery'] ?? []) as $gid) {
                    $s = db()->prepare('SELECT image FROM product_images WHERE id = ? AND product_id = ?'); $s->execute([(int)$gid, $id]);
                    if ($path = $s->fetchColumn()) { delete_upload($path); db()->prepare('DELETE FROM product_images WHERE id = ?')->execute([(int)$gid]); }
                }
            } else {
                $slug = unique_slug('products', $f['name']);
                db()->prepare('INSERT INTO products (category_id, name, slug, description, price, old_price, stock, sizes, colors, featured, flash_sale, active, image) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$f['category_id'], $f['name'], $slug, $f['description'], $price, $oldPrice, (int)$stockRaw, $f['sizes'], $f['colors'], $f['featured'], $f['flash_sale'], $f['active'], $newImg]);
                $id = (int)db()->lastInsertId();
            }
            foreach ($newGallery as $path) db()->prepare('INSERT INTO product_images (product_id, image) VALUES (?, ?)')->execute([$id, $path]);
            flash('success', 'Product saved.');
            redirect('admin/products.php');
        } catch (UserError $e) {
            foreach ($saved as $path) delete_upload($path);
            $errors[] = $e->getMessage();
        }
    }
}
admin_header($p ? 'Edit product' : 'Add product', 'products');
?>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="box form-box">
    <?= csrf_field() ?>
    <div class="grid2">
        <div class="field"><label for="name">Product name</label><input id="name" name="name" value="<?= e($f['name']) ?>" maxlength="160" required></div>
        <div class="field"><label for="category_id">Category</label>
            <select id="category_id" name="category_id"><option value="">No category</option>
                <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$f['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="price">Selling price (<?= e(setting('currency')) ?>)</label><input id="price" name="price" inputmode="decimal" value="<?= e($f['price']) ?>" required></div>
        <div class="field"><label for="old_price">Old price <small>(optional, shows a discount)</small></label><input id="old_price" name="old_price" inputmode="decimal" value="<?= e($f['old_price']) ?>"></div>
        <div class="field"><label for="stock">Items in stock <small>(0 = sold out)</small></label><input id="stock" name="stock" inputmode="numeric" value="<?= e($f['stock']) ?>" required></div>
    </div>
    <div class="grid2">
        <div class="field"><label for="sizes">Sizes <small>(separate with commas, leave blank if none)</small></label>
            <input id="sizes" name="sizes" value="<?= e($f['sizes']) ?>" placeholder="S, M, L, XL">
            <div class="presets"><button type="button" class="btn btn-sm" data-fill="XS, S, M, L, XL" data-target="sizes">Clothes</button>
                <button type="button" class="btn btn-sm" data-fill="36, 37, 38, 39, 40, 41" data-target="sizes">Shoes 36-41</button>
                <button type="button" class="btn btn-sm" data-fill="40, 41, 42, 43, 44, 45" data-target="sizes">Shoes 40-45</button>
                <button type="button" class="btn btn-sm" data-fill="2-3 yrs, 4-5 yrs, 6-7 yrs, 8-9 yrs" data-target="sizes">Kids</button>
                <button type="button" class="btn btn-sm" data-fill="" data-target="sizes">Clear</button></div></div>
        <div class="field"><label for="colors">Colours <small>(separate with commas, leave blank if none)</small></label>
            <input id="colors" name="colors" value="<?= e($f['colors']) ?>" placeholder="Black, White, Navy">
            <small class="muted">Customers pick one size and one colour. All sizes of this item share the stock number above.</small></div>
    </div>
    <div class="field"><label for="description">Description</label><textarea id="description" name="description" rows="6"><?= e($f['description']) ?></textarea></div>

    <div class="grid2">
        <div class="field"><label for="image">Main picture</label>
            <input id="image" type="file" name="image" accept="image/*" data-preview="prevMain">
            <div class="preview" id="prevMain"><?php if ($f['image']): ?><img src="<?= e(url($f['image'])) ?>" alt=""><?php endif; ?></div>
            <small class="muted">Upright (portrait) pictures look best. Big phone photos are shrunk automatically.</small></div>
        <div class="field"><label for="gallery">More pictures <small>(optional, choose several)</small></label>
            <input id="gallery" type="file" name="gallery[]" accept="image/*" multiple>
            <?php if ($gallery): ?><div class="gal"><?php foreach ($gallery as $gi): ?>
                <label class="gal-item"><img src="<?= e(url($gi['image'])) ?>" alt=""><span><input type="checkbox" name="del_gallery[]" value="<?= (int)$gi['id'] ?>"> remove</span></label>
            <?php endforeach; ?></div><?php endif; ?></div>
    </div>
    <div class="checks">
        <label><input type="checkbox" name="active" value="1" <?= $f['active'] ? 'checked' : '' ?>> Show in the store</label>
        <label><input type="checkbox" name="featured" value="1" <?= $f['featured'] ? 'checked' : '' ?>> Favourite (shown on the home page)</label>
        <label><input type="checkbox" name="flash_sale" value="1" <?= $f['flash_sale'] ? 'checked' : '' ?>> On sale (shown in the sale section)</label>
    </div>
    <div class="form-actions"><button class="btn btn-primary btn-lg">Save product</button> <a class="btn btn-lg" href="<?= e(url('admin/products.php')) ?>">Cancel</a></div>
</form>
<?php admin_footer(); ?>
