/* Import review page: highlights fields still needing manual entry, and drives the source-image panel
 * (use original / AI render / quick clean -> preview -> approve -> Cloudinary -> product gallery). */
(function () {
  'use strict';
  var CFG = window.IMPORT_REVIEW;
  if (!CFG) return;
  var $ = function (id) { return document.getElementById(id); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); };

  /* ---------------------------------------------------------- manual fields */
  function fieldFor(name) {
    var el = document.querySelector('[name="' + name + '"]');
    return el && /^(INPUT|SELECT|TEXTAREA)$/.test(el.tagName) && el.type !== 'hidden' ? el : null;
  }
  CFG.manual.forEach(function (name) {
    var el = fieldFor(name);
    if (!el) return;
    var mark = function () { el.classList.toggle('border-warning', el.value === ''); };
    mark();
    el.addEventListener('input', mark);
    el.addEventListener('change', mark);
  });
  var desc = $('descEditor');
  if (desc && !CFG.manual.length) desc.classList.add('border');

  /* ------------------------------------------------------------ image tools */
  var jobs = $('imgJobs');
  function picked() { return Array.prototype.map.call(document.querySelectorAll('.src-pick:checked'), function (c) { return c.value; }); }
  $('imgSelectAll').addEventListener('click', function () {
    var boxes = document.querySelectorAll('.src-pick'), all = Array.prototype.every.call(boxes, function (b) { return b.checked; });
    Array.prototype.forEach.call(boxes, function (b) { b.checked = !all; });
  });

  function post(url, body) {
    return fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CFG.csrf },
      body: JSON.stringify(body)
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) {
        if (!r.ok) throw new Error(j.message || (r.status === 419 ? 'Session expired. Reload the page.' : 'Request failed (' + r.status + ').'));
        return j;
      });
    });
  }
  function card(label) {
    var d = document.createElement('div');
    d.className = 'border rounded p-2 fs-8 bg-white';
    d.innerHTML = '<div class="d-flex justify-content-between align-items-center gap-2"><span class="job-label">' + esc(label) + '</span><span class="job-state text-muted">Working…</span></div><div class="job-body"></div>';
    jobs.prepend(d);
    return { el: d, state: function (html, cls) { var s = d.querySelector('.job-state'); s.className = 'job-state ' + (cls || 'text-muted'); s.innerHTML = html; }, body: d.querySelector('.job-body') };
  }
  function addToGallery(url) {
    return window.ProductFormApi && window.ProductFormApi.addUploadedImage(url);
  }

  function run(mode, url, index) {
    var names = { original: 'Original', gemini: 'AI render', local: 'Quick clean' };
    var job = card(names[mode] + ' of source image #' + (index + 1));
    if (mode === 'original') {
      return post(CFG.original, { url: url }).then(function (j) {
        job.state(addToGallery(j.url) ? 'Added to the product images' : 'Already in the product images', 'text-success');
      }).catch(function (e) { job.state(esc(e.message), 'text-danger'); });
    }
    return post(CFG.generate, { url: url, mode: mode }).then(function (j) {
      job.state('Review the preview, then approve', 'text-muted');
      job.body.innerHTML = '<div class="d-flex flex-wrap gap-3 mt-2 align-items-start">' +
        '<figure class="mb-0"><img src="' + esc(url) + '" referrerpolicy="no-referrer" style="width:180px;border-radius:6px;border:1px solid #ddd"><figcaption class="text-muted fs-9">Original</figcaption></figure>' +
        '<figure class="mb-0"><img src="' + esc(j.preview_url) + '" style="width:180px;border-radius:6px;border:1px solid #ddd"><figcaption class="text-muted fs-9">' + esc(names[mode]) + ' (not saved yet)</figcaption></figure>' +
        '<div class="d-flex flex-column gap-1"><button type="button" class="btn btn-sm btn-beige" data-job="approve">Approve &amp; upload</button>' +
        '<button type="button" class="btn btn-sm btn-outline-secondary" data-job="discard">Discard</button>' +
        '<div class="text-muted fs-9" style="max-width:220px">Check logos, colours and edges against the original before approving.' + (mode === 'local' ? ' Quick clean only swaps a plain backdrop.' : '') + '</div></div></div>';
      job.body.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-job]'); if (!b) return;
        if (b.dataset.job === 'discard') { job.el.remove(); return; }
        b.disabled = true; job.state('Uploading…');
        post(CFG.approve, { preview_id: j.preview_id }).then(function (r) {
          job.body.innerHTML = ''; job.state(addToGallery(r.url) ? 'Approved and added to the product images' : 'Already in the product images', 'text-success');
        }).catch(function (e) { b.disabled = false; job.state(esc(e.message), 'text-danger'); });
      });
    }).catch(function (e) { job.state(esc(e.message), 'text-danger'); });
  }

  Array.prototype.forEach.call(document.querySelectorAll('[data-img-mode]'), function (btn) {
    btn.addEventListener('click', function () {
      var urls = picked();
      if (!urls.length) { alert('Select at least one source image first.'); return; }
      var all = Array.prototype.map.call(document.querySelectorAll('.src-pick'), function (c) { return c.value; });
      btn.disabled = true;
      urls.reduce(function (p, u) { return p.then(function () { return run(btn.dataset.imgMode, u, all.indexOf(u)); }); }, Promise.resolve())
        .then(function () { btn.disabled = false; });
    });
  });
})();
