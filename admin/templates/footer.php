    </div>
  </div>
</div>
<script>
/* Vizualni (WYSIWYG) editor — inicijalizira sve .wz editore na stranici (stranice, blog…). */
(function () {
  document.querySelectorAll('.wz').forEach(function (wrap) {
    var visual = wrap.querySelector('.wz-visual');
    var html   = wrap.querySelector('.wz-html');
    var tabs   = wrap.querySelectorAll('.wz-tab');
    if (!visual || !html) return;
    var mode = 'visual';

    function toHtml()   { html.value = visual.innerHTML; }
    function toVisual() { visual.innerHTML = html.value; }

    function setMode(m) {
      if (m === mode) return;
      if (m === 'html') toHtml(); else toVisual();
      mode = m;
      wrap.setAttribute('data-mode', m);
      tabs.forEach(function (t) { t.classList.toggle('is-active', t.dataset.mode === m); });
      (m === 'html' ? html : visual).focus();
    }
    tabs.forEach(function (t) { t.addEventListener('click', function () { setMode(t.dataset.mode); }); });

    // dok se tipka u vizualnom prikazu, drži <textarea> usklađen
    visual.addEventListener('input', toHtml);

    // alatna traka (execCommand — bez vanjskih biblioteka)
    wrap.querySelectorAll('.wz-b').forEach(function (b) {
      b.addEventListener('mousedown', function (e) { e.preventDefault(); }); // ne gubi odabir teksta
      b.addEventListener('click', function () {
        visual.focus();
        if (b.dataset.cmd) document.execCommand(b.dataset.cmd, false, null);
        else if (b.dataset.block) document.execCommand('formatBlock', false, b.dataset.block);
        else if (b.dataset.link) { var u = prompt('Unesite URL (npr. https://...)', 'https://'); if (u) document.execCommand('createLink', false, u); }
        toHtml();
      });
    });

    // pri spremanju osiguraj zadnju vrijednost iz vizualnog prikaza
    var form = wrap.closest('form');
    if (form) form.addEventListener('submit', function () { if (mode === 'visual') toHtml(); });
  });
})();
</script>
<script>
/* Lazy maintenance beacon iz ADMINA — drži fiskalne retry-e, sync i osvježavanje
   veze živima i BEZ podešenog crona, čak i kad nema posjeta izlogu (mnogi shared
   hostinzi nemaju cron). Vlasnik ionako redovito otvara admin. Throttle 5 min;
   server sam ograničava (GET_LOCK + vremenske granice) pa je poziv jeftin. */
(function () {
  try {
    var k = 'dj_admin_lazy', now = Date.now();
    if (now - (+localStorage.getItem(k) || 0) > 300000) {
      localStorage.setItem(k, String(now));
      var u = <?= json_encode(url('api/cron.php') . '?lazy=1', JSON_UNESCAPED_SLASHES) ?>;
      if (navigator.sendBeacon) navigator.sendBeacon(u);
      else fetch(u, { method: 'POST', keepalive: true }).catch(function () {});
    }
  } catch (e) {}
})();
</script>
<script>
/* Mobilni izbornik — otvori/zatvori bočnu ladicu */
(function () {
  var h = document.querySelector('[data-adm-menu]');
  var s = document.querySelector('.adm-side');
  var b = document.querySelector('[data-adm-backdrop]');
  if (!h || !s) return;
  function close() { s.classList.remove('open'); if (b) b.classList.remove('show'); }
  h.addEventListener('click', function () { s.classList.toggle('open'); if (b) b.classList.toggle('show'); });
  if (b) b.addEventListener('click', close);
  s.querySelectorAll('a.nav').forEach(function (a) { a.addEventListener('click', close); });
})();
</script>
</body>
</html>
