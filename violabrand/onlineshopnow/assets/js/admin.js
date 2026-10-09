(function () {
  var menu = document.getElementById('aMenu'), side = document.getElementById('aSide');
  if (menu && side) menu.addEventListener('click', function () { side.classList.toggle('open'); });

  // Confirm dangerous actions
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); });
  });

  // Size presets on the product form
  document.querySelectorAll('[data-fill]').forEach(function (b) {
    b.addEventListener('click', function () { var t = document.getElementById(b.dataset.target); if (t) { t.value = b.dataset.fill; t.focus(); } });
  });

  // Social media rows: add / remove
  var rows = document.getElementById('socRows'), tpl = document.getElementById('socTpl'), addBtn = document.getElementById('socAdd');
  if (rows && tpl && addBtn) {
    addBtn.addEventListener('click', function () { rows.appendChild(tpl.content.cloneNode(true)); });
    rows.addEventListener('click', function (e) { var b = e.target.closest('.soc-del'); if (b) b.closest('.soc-row').remove(); });
  }

  // "Copy link" buttons
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
      copyText(b.dataset.copy).then(function () { b.textContent = 'Link copied'; }).catch(function () { b.textContent = 'Press Ctrl+C'; });
      setTimeout(function () { b.textContent = label; }, 2200);
    });
  });

  // Live preview of a chosen picture
  document.querySelectorAll('input[type=file][data-preview]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var box = document.getElementById(inp.dataset.preview);
      if (!box || !inp.files || !inp.files[0]) return;
      box.innerHTML = ''; var img = document.createElement('img');
      img.src = URL.createObjectURL(inp.files[0]); box.appendChild(img);
    });
  });

  // New-order alert: checks every 30 seconds while the admin panel is open
  var body = document.body, poll = body.dataset.poll, last = parseInt(body.dataset.latest || '0', 10);
  var toast = document.getElementById('toast'), badge = document.getElementById('newBadge');
  if (!poll) return;
  function beep() {
    try {
      var c = new (window.AudioContext || window.webkitAudioContext)(), o = c.createOscillator(), g = c.createGain();
      o.connect(g); g.connect(c.destination); o.frequency.value = 880; g.gain.value = 0.15;
      o.start(); setTimeout(function () { o.stop(); c.close(); }, 250);
    } catch (e) {}
  }
  function check() {
    fetch(poll, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
      if (!d) return;
      if (badge) { badge.textContent = d.new; badge.hidden = d.new < 1; }
      if (d.latest > last) {
        last = d.latest; beep();
        document.title = '(' + d.new + ') New order! ' + document.title.replace(/^\(\d+\) New order! /, '');
        if (toast) {
          toast.innerHTML = '🛎️ New order received. <a href="' + body.dataset.orders + '">View orders</a>';
          toast.hidden = false;
        }
      }
    }).catch(function () {});
  }
  setInterval(check, 30000);
})();
