(function () {
  var body = document.body, dot = document.getElementById('live');
  var cls = function (l, n) { return n ? 'full' : (l >= 50 ? 'mid' : ''); };
  function poll() {
    fetch('api/status.php', { credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw 0; return r.json(); })
      .then(function (d) {
        d.bins.forEach(function (b) {
          document.querySelectorAll('[data-bin="' + b.id + '"]').forEach(function (el) {
            var f = function (n) { return el.querySelector('[data-f="' + n + '"]'); };
            var c = cls(b.level, b.needs);
            if (f('level')) f('level').textContent = b.level;
            if (f('ring')) { f('ring').style.setProperty('--p', b.level); f('ring').className = 'ring ' + c; }
            if (f('bar')) { f('bar').style.width = b.level + '%'; f('bar').className = c; }
            if (f('fill')) { f('fill').style.height = b.level + '%'; f('fill').className = 'fill ' + c; }
            if (f('status')) f('status').innerHTML = b.status_html;
            if (f('online')) f('online').innerHTML = b.online_html;
            if (f('ago')) f('ago').textContent = b.ago;
            el.classList.toggle('alert', b.needs);
          });
        });
        document.querySelectorAll('.unread-count').forEach(function (u) { u.textContent = d.unread; u.hidden = !d.unread; });
        if (body.dataset.live === '1' && String(d.notif) !== body.dataset.notif) {
          var a = document.activeElement;
          if (!a || ['INPUT', 'TEXTAREA', 'SELECT'].indexOf(a.tagName) < 0) location.reload();
        }
        if (dot) dot.className = 'live on';
      })
      .catch(function () { if (dot) dot.className = 'live'; });
  }
  if (document.getElementById('live')) { poll(); setInterval(poll, 5000); }
})();
