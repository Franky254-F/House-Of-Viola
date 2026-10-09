(function () {
  // Slide-out menu
  var mBtn = document.getElementById('menuBtn'), drawer = document.getElementById('menuDrawer'),
      overlay = document.getElementById('menuOverlay'), mClose = document.getElementById('menuClose');
  function openMenu() {
    overlay.hidden = false; requestAnimationFrame(function () { overlay.classList.add('show'); });
    drawer.classList.add('open'); drawer.setAttribute('aria-hidden', 'false');
    mBtn.setAttribute('aria-expanded', 'true'); document.body.classList.add('menu-open');
    setTimeout(function () { mClose.focus(); }, 60);
  }
  function closeMenu() {
    overlay.classList.remove('show'); drawer.classList.remove('open'); drawer.setAttribute('aria-hidden', 'true');
    mBtn.setAttribute('aria-expanded', 'false'); document.body.classList.remove('menu-open');
    setTimeout(function () { overlay.hidden = true; }, 260); mBtn.focus();
  }
  if (mBtn && drawer && overlay && mClose) {
    mBtn.addEventListener('click', function () { drawer.classList.contains('open') ? closeMenu() : openMenu(); });
    mClose.addEventListener('click', closeMenu);
    overlay.addEventListener('click', closeMenu);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && drawer.classList.contains('open')) closeMenu(); });
  }

  // Password Show/Hide and strength meter (sign in / sign up pages)
  document.querySelectorAll('.pw-toggle').forEach(function (b) {
    b.addEventListener('click', function () {
      var i = b.parentNode.querySelector('input'), show = i.type === 'password';
      i.type = show ? 'text' : 'password'; b.textContent = show ? 'Hide' : 'Show'; b.setAttribute('aria-pressed', show ? 'true' : 'false');
    });
  });
  var pw = document.getElementById('password'), meter = document.getElementById('pwMeter');
  if (pw && meter) pw.addEventListener('input', function () {
    var v = pw.value, s = 0;
    if (v.length >= 8) s++; if (v.length >= 12) s++;
    if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++;
    if (/\d/.test(v) && /[^A-Za-z0-9]/.test(v)) s++;
    var n = v ? Math.max(1, s) : 0; meter.dataset.s = n;
    meter.querySelector('small').textContent = ['', 'Weak', 'Okay', 'Good', 'Strong'][n];
  });

  // Cookie notice (remembered in this browser)
  var cBar = document.getElementById('cookieBar'), cOk = document.getElementById('cookieOk');
  if (cBar) {
    var seen = false; try { seen = localStorage.getItem('cookieOk') === '1'; } catch (e) {}
    if (!seen) { cBar.hidden = false; document.body.classList.add('has-cookie'); }
    if (cOk) cOk.addEventListener('click', function () {
      cBar.hidden = true; document.body.classList.remove('has-cookie');
      try { localStorage.setItem('cookieOk', '1'); } catch (e) {}
    });
  }

  // Home page slider (kept for compatibility)
  var slider = document.querySelector('.slider');
  if (slider) {
    var slides = slider.querySelectorAll('.slide'), dots = slider.querySelectorAll('.dots button'), i = 0, timer;
    var show = function (n) {
      i = (n + slides.length) % slides.length;
      slides.forEach(function (s, k) { s.classList.toggle('on', k === i); });
      dots.forEach(function (d, k) { d.classList.toggle('on', k === i); });
    };
    var play = function () {
      var still = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (slides.length > 1 && !still) timer = setInterval(function () { show(i + 1); }, 5000);
    };
    dots.forEach(function (d, k) { d.addEventListener('click', function () { clearInterval(timer); show(k); play(); }); });
    show(0); play();
  }

  // Product gallery thumbnails
  var main = document.getElementById('mainImg');
  document.querySelectorAll('.thumbs button').forEach(function (t) {
    t.addEventListener('click', function () {
      main.src = t.dataset.src;
      document.querySelectorAll('.thumbs button').forEach(function (x) { x.classList.remove('on'); });
      t.classList.add('on');
    });
  });

  // Quantity +/- buttons
  document.querySelectorAll('.qty').forEach(function (box) {
    var input = box.querySelector('input'), max = parseInt(input.max || '99', 10);
    box.querySelectorAll('button').forEach(function (b) {
      b.addEventListener('click', function () {
        var v = (parseInt(input.value, 10) || 1) + (b.dataset.d === '+' ? 1 : -1);
        input.value = Math.max(parseInt(input.min || '1', 10), Math.min(max, v));
      });
    });
  });

  // Flash sale countdown
  var cd = document.getElementById('countdown');
  if (cd && cd.dataset.end) {
    var end = new Date(cd.dataset.end.replace(' ', 'T')).getTime();
    var tick = function () {
      var s = Math.floor((end - Date.now()) / 1000);
      if (isNaN(end) || s <= 0) { cd.textContent = 'Ended'; return; }
      var h = Math.floor(s / 3600), m = Math.floor(s % 3600 / 60), sec = s % 60;
      cd.textContent = (h > 99 ? Math.floor(h / 24) + 'd ' + (h % 24) : h) + 'h ' + String(m).padStart(2, '0') + 'm ' + String(sec).padStart(2, '0') + 's';
      setTimeout(tick, 1000);
    };
    tick();
  }

  // "Copy link" buttons (works on http:// addresses too, where the modern clipboard API is blocked)
  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
    return new Promise(function (resolve, reject) {
      var t = document.createElement('textarea');
      t.value = text; t.style.position = 'fixed'; t.style.opacity = '0'; document.body.appendChild(t); t.select();
      try { document.execCommand('copy') ? resolve() : reject(); } catch (e) { reject(e); } document.body.removeChild(t);
    });
  }
  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var label = b.textContent;
      copyText(b.dataset.copy).then(function () { b.textContent = 'Link copied'; })
        .catch(function () { b.textContent = 'Press Ctrl+C'; });
      setTimeout(function () { b.textContent = label; }, 2200);
    });
  });

  // Open WhatsApp automatically after an order is placed
  var go = document.getElementById('waAuto');
  if (go) setTimeout(function () { location.href = go.href; }, 2500);

  // Stop double-clicks from sending a form twice
  document.addEventListener('submit', function (e) {
    if (e.defaultPrevented) return;
    var f = e.target;
    if (f.dataset.busy) { e.preventDefault(); return; }
    f.dataset.busy = '1';
    setTimeout(function () { delete f.dataset.busy; }, 4000);
  });
})();
