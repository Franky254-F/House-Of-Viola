<?php
require __DIR__ . '/_init.php'; require_admin();

$text_keys = ['hero_eyebrow' => 60, 'hero_title' => 120, 'hero_text' => 300,
              'hero_btn1_text' => 30, 'hero_btn1_link' => 255, 'hero_btn2_text' => 30, 'hero_btn2_link' => 255];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $new = [];
    foreach ($text_keys as $k => $max) $new[$k] = mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);
    $new['hero_overlay'] = (string)max(0, min(85, (int)($_POST['hero_overlay'] ?? 55)));
    try {
        $img = save_image($_FILES['hero_image'] ?? null, 'site', 2200);     // wide pictures are kept sharper than product photos
        $current = setting('hero_image');
        if ($img) { delete_upload($current); $new['hero_image'] = $img; }
        elseif (!empty($_POST['remove_hero'])) { delete_upload($current); $new['hero_image'] = ''; }
        $up = db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
        foreach ($new as $k => $v) $up->execute([$k, $v]);
        flash('success', 'Hero banner saved. View your store to see it.');
        redirect('admin/hero.php');
    } catch (UserError $e) { flash('error', $e->getMessage()); }
}
$s = settings();
$ov = max(0, min(85, (int)$s['hero_overlay']));
admin_header('Hero banner', 'hero');
?>
<p class="muted" style="margin-top:0">This is the big banner at the very top of your home page. Upload a wide picture, then write the words that sit on top of it.</p>
<form method="post" enctype="multipart/form-data">
<?= csrf_field() ?>
<div class="cols">
<div>
    <section class="box form-box">
        <h2>Banner picture</h2>
        <div class="dropzone" id="dz">
            <input id="hero_image" type="file" name="hero_image" accept="image/*">
            <label for="hero_image" class="dz-label">
                <b>Click to upload your banner picture</b>
                <span>Wide landscape photo, at least 1600 pixels across (for example 2000 x 900).<br>Keep the person or product on the <u>right</u>, so the words on the left stay easy to read.</span>
            </label>
        </div>
        <?php if ($s['hero_image']): ?><label class="inline-check" style="margin-top:10px"><input type="checkbox" name="remove_hero" value="1"> Remove the current picture (shows the plain black and gold banner)</label><?php endif; ?>
        <div class="field" style="margin-top:16px"><label for="hero_overlay">Darkness over the picture: <b id="ovVal"><?= $ov ?></b>%</label>
            <input id="hero_overlay" type="range" name="hero_overlay" min="0" max="85" value="<?= $ov ?>">
            <small class="muted">Raise it if the words are hard to read on a bright picture.</small></div>
    </section>
    <section class="box form-box">
        <h2>Words on the banner</h2>
        <div class="field"><label for="hero_eyebrow">Small line above the headline</label><input id="hero_eyebrow" name="hero_eyebrow" value="<?= e($s['hero_eyebrow']) ?>" maxlength="60" placeholder="New collection"></div>
        <div class="field"><label for="hero_title">Headline <small>(put *stars* around words you want in italic gold)</small></label>
            <input id="hero_title" name="hero_title" value="<?= e($s['hero_title']) ?>" maxlength="120" placeholder="Dress well. *Order easy.*"></div>
        <div class="field"><label for="hero_text">Short paragraph</label><textarea id="hero_text" name="hero_text" rows="3" maxlength="300"><?= e($s['hero_text']) ?></textarea></div>
        <div class="grid2">
            <div class="field"><label for="hero_btn1_text">Gold button text</label><input id="hero_btn1_text" name="hero_btn1_text" value="<?= e($s['hero_btn1_text']) ?>" maxlength="30"></div>
            <div class="field"><label for="hero_btn1_link">Gold button goes to</label><input id="hero_btn1_link" name="hero_btn1_link" value="<?= e($s['hero_btn1_link']) ?>" placeholder="category.php"></div>
            <div class="field"><label for="hero_btn2_text">Outline button text <small>(blank to hide)</small></label><input id="hero_btn2_text" name="hero_btn2_text" value="<?= e($s['hero_btn2_text']) ?>" maxlength="30"></div>
            <div class="field"><label for="hero_btn2_link">Outline button goes to</label><input id="hero_btn2_link" name="hero_btn2_link" value="<?= e($s['hero_btn2_link']) ?>" placeholder="category.php?flash=1"></div>
        </div>
        <small class="muted">Links can be a page of your store (like <code>category.php?c=shoes</code>) or a full web address.</small>
    </section>
</div>
<div>
    <section class="box form-box" style="position:sticky;top:76px">
        <h2>Live preview</h2>
        <div class="pv" id="pv" style="--ov:<?= $ov / 100 ?>;<?= $s['hero_image'] ? "background-image:url('" . e(url($s['hero_image'])) . "')" : '' ?>">
            <div class="pv-in">
                <p class="pv-eye" id="pvEye"><?= e($s['hero_eyebrow']) ?></p>
                <h3 id="pvTitle"><?= hero_title_html($s['hero_title']) ?></h3>
                <p class="pv-txt" id="pvText"><?= e($s['hero_text']) ?></p>
                <span class="pv-btn" id="pvB1"><?= e($s['hero_btn1_text']) ?></span> <span class="pv-btn pv-ghost" id="pvB2"><?= e($s['hero_btn2_text']) ?></span>
            </div>
        </div>
        <p class="muted" style="margin-bottom:0">A simple preview. Your real home page looks bigger and sharper.</p>
    </section>
</div>
</div>
<div class="form-actions sticky"><button class="btn btn-primary btn-lg">Save hero banner</button>
    <a class="btn btn-lg" target="_blank" href="<?= e(url('index.php')) ?>">View store</a></div>
</form>
<script>
(function () {
  var $ = function (id) { return document.getElementById(id); };
  function title(s) {
    var box = $('pvTitle'); box.textContent = '';
    s.split(/(\*[^*]+\*)/).forEach(function (p) {
      if (/^\*[^*]+\*$/.test(p)) { var em = document.createElement('em'); em.textContent = p.slice(1, -1); box.appendChild(em); }
      else box.appendChild(document.createTextNode(p));
    });
  }
  [['hero_eyebrow', 'pvEye'], ['hero_text', 'pvText'], ['hero_btn1_text', 'pvB1'], ['hero_btn2_text', 'pvB2']].forEach(function (m) {
    $(m[0]).addEventListener('input', function () { $(m[1]).textContent = this.value; });
  });
  $('hero_title').addEventListener('input', function () { title(this.value); });
  $('hero_overlay').addEventListener('input', function () { $('ovVal').textContent = this.value; $('pv').style.setProperty('--ov', this.value / 100); });
  $('hero_image').addEventListener('change', function () {
    if (this.files && this.files[0]) {
      $('pv').style.backgroundImage = "url('" + URL.createObjectURL(this.files[0]) + "')";
      document.querySelector('#dz .dz-label b').textContent = 'Picture chosen: ' + this.files[0].name;
    }
  });
})();
</script>
<?php admin_footer(); ?>
