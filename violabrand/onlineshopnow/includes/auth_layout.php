<?php
/**
 * Split-screen page used by customer sign in, sign up and the admin sign in.
 * Left: the store's own look (hero picture if you uploaded one). Right: the form.
 */
function auth_start(string $title, string $heading, string $sub, array $o = []): void {
    $tab    = $o['tab'] ?? '';
    $next   = $o['next'] ?? '';
    $perks  = $o['perks'] ?? [];
    $img    = setting('hero_image');
    $ov     = max(0, min(85, (int)setting('hero_overlay', '55'))) / 100;
    $q      = $next !== '' ? '?next=' . urlencode($next) : '';
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title) ?> | <?= e(setting('store_name')) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
<?= theme_css() ?>
</head>
<body class="auth-body">
<div class="auth">
    <aside class="auth-art <?= $img ? 'has-img' : '' ?>" style="--ov:<?= e($ov) ?>;<?= $img ? "background-image:url('" . e(url($img)) . "')" : '' ?>">
        <span class="arc arc-1" aria-hidden="true"></span><span class="arc arc-2" aria-hidden="true"></span>
        <?= brand_html('brand-lg') ?>
        <div class="auth-copy">
            <p class="eyebrow"><span class="gl"></span><?= e($o['eyebrow'] ?? 'Welcome') ?><span class="gl"></span></p>
            <h2><?= hero_title_html($o['art_title'] ?? setting('hero_title')) ?></h2>
            <i class="rule" aria-hidden="true"></i>
            <?php if ($perks): ?><ul class="perks"><?php foreach ($perks as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul><?php endif; ?>
        </div>
        <p class="auth-foot">&copy; <?= date('Y') ?> <?= e(setting('store_name')) ?></p>
    </aside>

    <main class="auth-main">
        <?php if (!empty($o['back'])): ?><a class="auth-back" href="<?= e(url($o['back'][0])) ?>">&larr; <?= e($o['back'][1]) ?></a><?php endif; ?>
        <div class="auth-card">
            <?php if ($tab): ?>
            <div class="auth-tabs">
                <a class="<?= $tab === 'login' ? 'on' : '' ?>" href="<?= e(url('login.php' . $q)) ?>">Sign in</a>
                <a class="<?= $tab === 'register' ? 'on' : '' ?>" href="<?= e(url('register.php' . $q)) ?>">Create account</a>
            </div>
            <?php endif; ?>
            <h1><?= e($heading) ?></h1>
            <p class="auth-sub"><?= e($sub) ?></p>
            <?php show_flashes(); ?>
<?php }

function auth_end(): void { ?>
        </div>
    </main>
</div>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
<?php }

/** A floating-label input. Password fields get a Show/Hide button. */
function auth_field(string $id, string $label, string $type = 'text', string $value = '', array $attr = []): void {
    $extra = '';
    foreach ($attr as $k => $v) $extra .= ' ' . e($k) . '="' . e($v) . '"';
    echo '<div class="fl"><input id="' . e($id) . '" name="' . e($id) . '" type="' . e($type) . '" value="' . e($value) . '" placeholder=" "' . $extra . '>'
       . '<label for="' . e($id) . '">' . e($label) . '</label>';
    if ($type === 'password') echo '<button type="button" class="pw-toggle" aria-pressed="false">Show</button>';
    echo '</div>';
}
