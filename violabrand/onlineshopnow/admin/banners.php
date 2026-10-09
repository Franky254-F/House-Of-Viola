<?php
require __DIR__ . '/_init.php'; require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0); $act = $_POST['action'] ?? '';
    if ($act === 'save') {
        $title = trim($_POST['title'] ?? ''); $sub = trim($_POST['subtitle'] ?? ''); $link = trim($_POST['link'] ?? '');
        $sort = (int)($_POST['sort_order'] ?? 0); $active = empty($_POST['active']) ? 0 : 1;
        $old = null;
        if ($id) { $s = db()->prepare('SELECT image FROM banners WHERE id = ?'); $s->execute([$id]); $old = $s->fetchColumn() ?: null; }
        if ($link !== '' && !preg_match('#^(https?://|/)#i', $link) ) $link = url($link);   // "category.php?c=fashion" works too
        try {
            $img = save_image($_FILES['image'] ?? null, 'banners');
            if (!$img && !$old) throw new UserError('Choose a banner picture. Wide pictures (about 1000 x 700) look best.');
            if ($id) {
                db()->prepare('UPDATE banners SET title=?, subtitle=?, link=?, sort_order=?, active=?, image=? WHERE id=?')->execute([$title, $sub, $link, $sort, $active, $img ?: $old, $id]);
                if ($img && $old) delete_upload($old);
            } else {
                db()->prepare('INSERT INTO banners (title, subtitle, link, sort_order, active, image) VALUES (?,?,?,?,?,?)')->execute([$title, $sub, $link, $sort, $active, $img]);
            }
            flash('success', 'Banner saved.');
        } catch (UserError $e) { flash('error', $e->getMessage()); }
    } elseif ($act === 'delete') {
        $s = db()->prepare('SELECT image FROM banners WHERE id = ?'); $s->execute([$id]); delete_upload($s->fetchColumn() ?: null);
        db()->prepare('DELETE FROM banners WHERE id = ?')->execute([$id]);
        flash('success', 'Banner deleted.');
    }
    redirect('admin/banners.php');
}
$edit = null;
if (!empty($_GET['edit'])) { $st = db()->prepare('SELECT * FROM banners WHERE id = ?'); $st->execute([(int)$_GET['edit']]); $edit = $st->fetch() ?: null; }
$rows = db()->query('SELECT * FROM banners ORDER BY sort_order, id')->fetchAll();
admin_header('Promo banners', 'banners');
?>
<p class="muted">Promo banners are the framed pictures shown in a row on your home page, below the new arrivals (up to 3 show). Good for a sale, a new collection or a delivery offer. The big top banner is edited under <a href="<?= e(url('admin/hero.php')) ?>" class="lnk">Hero banner</a>.</p>
<div class="cols">
<section class="box">
    <?php foreach ($rows as $b): ?>
    <div class="ban-row <?= $b['active'] ? '' : 'dim' ?>">
        <img src="<?= e(img_url($b['image'])) ?>" alt="">
        <div><b><?= e($b['title'] ?: '(no headline)') ?></b><br><small class="muted"><?= e($b['subtitle']) ?></small><br><small class="muted">Position <?= (int)$b['sort_order'] ?><?= $b['active'] ? '' : ' | hidden' ?></small></div>
        <div class="actions"><a class="btn btn-sm" href="?edit=<?= (int)$b['id'] ?>">Edit</a>
            <form method="post" class="inline" data-confirm="Delete this banner?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="btn btn-sm btn-danger" name="action" value="delete">Delete</button></form></div>
    </div>
    <?php endforeach; if (!$rows): ?><p class="muted" style="text-align:center;padding:22px">No promo banners yet. Add one with the form on the right.</p><?php endif; ?>
</section>
<section class="box form-box">
    <h2><?= $edit ? 'Edit banner' : 'Add a banner' ?></h2>
    <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <div class="field"><label for="image">Picture</label><input id="image" type="file" name="image" accept="image/*" data-preview="prevB">
            <div class="preview" id="prevB"><?php if (!empty($edit['image'])): ?><img src="<?= e(url($edit['image'])) ?>" alt=""><?php endif; ?></div></div>
        <div class="field"><label for="title">Headline</label><input id="title" name="title" value="<?= e($edit['title'] ?? '') ?>" maxlength="120"></div>
        <div class="field"><label for="subtitle">Short text</label><input id="subtitle" name="subtitle" value="<?= e($edit['subtitle'] ?? '') ?>" maxlength="200"></div>
        <div class="field"><label for="link">Link <small>(e.g. category.php?c=fashion, or leave blank)</small></label><input id="link" name="link" value="<?= e($edit['link'] ?? '') ?>" maxlength="255"></div>
        <div class="field"><label for="sort_order">Position <small>(1 shows first)</small></label><input id="sort_order" name="sort_order" type="number" value="<?= (int)($edit['sort_order'] ?? 0) ?>"></div>
        <div class="checks"><label><input type="checkbox" name="active" value="1" <?= ($edit['active'] ?? 1) ? 'checked' : '' ?>> Show on the home page</label></div>
        <div class="form-actions"><button class="btn btn-primary"><?= $edit ? 'Save changes' : 'Add banner' ?></button><?php if ($edit): ?> <a class="btn" href="<?= e(url('admin/banners.php')) ?>">Cancel</a><?php endif; ?></div>
    </form>
</section>
</div>
<?php admin_footer(); ?>
