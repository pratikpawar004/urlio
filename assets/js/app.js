(function () {
  function toast(msg) {
    var t = document.createElement('div'); t.className = 'toast'; t.textContent = msg;
    document.body.appendChild(t); setTimeout(function () { t.remove(); }, 1800);
  }
  function copy(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text).then(function () { toast('Copy ho gaya ✔'); });
    }
    var ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta);
    ta.select(); try { document.execCommand('copy'); toast('Copy ho gaya ✔'); } catch (e) { toast('Copy fail'); }
    ta.remove();
  }
  function qrModal(text) {
    var m = document.createElement('div'); m.className = 'modal';
    m.innerHTML = '<div class="box"><h3>QR code</h3><div class="qrbox" id="qrTarget"></div>' +
      '<div class="actions" style="justify-content:center"><button class="btn" id="qrDl">Download</button>' +
      '<button class="btn btn-ghost" id="qrClose">Close</button></div></div>';
    document.body.appendChild(m);
    if (window.QRCode) new QRCode(document.getElementById('qrTarget'), {text: text, width: 220, height: 220, correctLevel: QRCode.CorrectLevel.M});
    else document.getElementById('qrTarget').textContent = 'QR library load nahi hui (internet check karein).';
    function close() { m.remove(); }
    m.addEventListener('click', function (e) { if (e.target === m) close(); });
    document.getElementById('qrClose').onclick = close;
    document.getElementById('qrDl').onclick = function () {
      var c = m.querySelector('canvas'), img = m.querySelector('img');
      var a = document.createElement('a'); a.download = 'qr.png';
      a.href = c ? c.toDataURL('image/png') : (img ? img.src : ''); if (a.href) a.click();
    };
  }
  document.addEventListener('click', function (e) {
    var c = e.target.closest('[data-copy]'); if (c) { e.preventDefault(); copy(c.getAttribute('data-copy')); return; }
    var q = e.target.closest('[data-qr]'); if (q) { e.preventDefault(); qrModal(q.getAttribute('data-qr')); }
  });

  // AJAX shorten (JS na chale to normal form submit hota hai)
  var form = document.getElementById('shortenForm');
  if (form && window.fetch) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = form.querySelector('button[type=submit]'), old = btn.textContent;
      btn.disabled = true; btn.textContent = '…';
      fetch(form.action, {method: 'POST', body: new FormData(form), headers: {'X-Requested-With': 'fetch'}, credentials: 'same-origin'})
        .then(function (r) { return r.json(); })
        .then(function (d) {
          var box = document.getElementById('result');
          if (d.ok) { box.innerHTML = d.html; form.querySelector('[name=url]').value = ''; }
          else { box.innerHTML = '<div class="alert alert-err" style="margin-top:14px"></div>'; box.firstChild.textContent = d.message; }
        })
        .catch(function () { form.submit(); })
        .finally(function () { btn.disabled = false; btn.textContent = old; });
    });
  }
})();
