/* Product add/edit form: slug, media (instant preview + AJAX Cloudinary upload), colours with image assignment,
 * size chips (filled from a size guide) and the variant matrix. Server state arrives in window.PRODUCT_FORM. */
(function () {
  'use strict';
  var CFG = window.PRODUCT_FORM, S = CFG.state;
  var $ = function (id) { return document.getElementById(id); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); };
  var form = $('productForm'), uid = 1;
  var ALLOWED = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

  /* ------------------------------------------------------------------ slug */
  function slugify(s) {
    return String(s || '').normalize('NFKD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
  }
  var nameEl = $('f-name'), slugEl = $('f-slug'), slugManual = !!S.slugManual;
  if (nameEl && slugEl) {
    if (!slugEl.value) slugEl.value = slugify(nameEl.value);
    nameEl.addEventListener('input', function () { if (!slugManual) slugEl.value = slugify(nameEl.value); });
    slugEl.addEventListener('input', function () { slugManual = slugEl.value !== '' && slugEl.value !== slugify(nameEl.value); });
  }

  /* ----------------------------------------------------- subcategory select */
  var cat = $('f-category_id'), sub = $('f-subcategory_id');
  function fillSubs() {
    var sel = sub.dataset.selected || sub.value, list = CFG.subcategories[cat.value] || [];
    sub.innerHTML = '<option value="">Select Subcategory</option>' + list.map(function (s) {
      return '<option value="' + s.id + '"' + (String(s.id) === String(sel) ? ' selected' : '') + '>' + esc(s.name) + '</option>';
    }).join('');
    sub.dataset.selected = '';
  }
  cat.addEventListener('change', fillSubs); fillSubs();

  /* ----------------------------------------------------------------- state */
  var images = S.images.map(function (i) { return { u: uid++, id: i.id || null, url: i.url, preview: i.url, main: !!i.main, status: 'ready', progress: 100, error: '' }; });
  if (images.length && !images.some(function (i) { return i.main; })) images[0].main = true;

  var colors = S.colors.map(function (c) {
    return { u: uid++, id: c.id || '', name: c.name || '', hex: c.hex || '#cccccc',
      imgs: (c.images || []).map(function (k) { return images[k] && images[k].u; }).filter(Boolean) };
  });

  var chips = []; // size chips {label, id, checked, guide}
  function chip(label, checked, id, guide) {
    var found = chips.filter(function (c) { return c.label.toLowerCase() === label.toLowerCase(); })[0];
    if (found) { if (checked) found.checked = true; return found; }
    var c = { label: label, id: id || '', checked: !!checked, guide: !!guide };
    chips.push(c); return c;
  }
  S.sizes.forEach(function (s) { chip(s.size_label, true, s.id); });
  var guides = {}; S.guides.forEach(function (g) { guides[g.id] = g; });
  var guideSel = $('productSizeGuideSelect');
  guideSel.innerHTML = '<option value="">-- No Size Guide Assigned --</option>' + S.guides.map(function (g) {
    return '<option value="' + g.id + '"' + (String(g.id) === String(CFG.guideId) ? ' selected' : '') + '>' + esc(g.title) + '</option>';
  }).join('');
  if (guides[CFG.guideId]) guides[CFG.guideId].sizes.forEach(function (l) { chip(l, false, '', true); });
  ['XS', 'S', 'M', 'L', 'XL'].forEach(function (l) { chip(l, false); });

  var vmap = {}; // "colour|size" -> variant data
  S.variants.forEach(function (v) {
    var c = v.color === '' || v.color == null ? '' : (S.colors[v.color] || {}).name;
    var z = v.size === '' || v.size == null ? '' : (S.sizes[v.size] || {}).size_label;
    vmap[(c || '') + '|' + (z || '')] = v;
  });

  var has = $('toggleVariants'), sale = $('toggleSale');
  var scenario = function () { return document.querySelector('input[name=variant_scenario]:checked').value; };
  var activeColors = function () { return scenario() === 'size_only' ? [] : colors.filter(function (c) { return c.name.trim(); }); };
  var activeSizes = function () { return scenario() === 'color_only' ? [] : chips.filter(function (c) { return c.checked; }); };
  var imgByU = function (u) { return images.filter(function (i) { return i.u === u; })[0]; };
  var imgLabel = function (i) { return i.file ? i.file.name : 'Image #' + (images.indexOf(i) + 1); };

  /* ----------------------------------------------------------------- media */
  function overlay(i) {
    if (i.status === 'uploading') {
      return '<div class="upload-overlay"><div>' + (i.progress >= 100 ? 'Processing…' : 'Uploading ' + i.progress + '%') + '</div><div class="progress"><div class="progress-bar" style="width:' + i.progress + '%"></div></div></div>';
    }
    if (i.status === 'error') {
      return '<div class="upload-overlay text-danger"><div>' + esc(i.error) + '</div><button type="button" class="btn btn-xs btn-outline-danger mt-1" data-act="retry" data-u="' + i.u + '">Retry</button></div>';
    }
    return '';
  }
  function renderMedia() {
    var main = images.filter(function (i) { return i.main; })[0], box = $('mainImageContainer');
    if (main) {
      box.innerHTML = '<div class="d-flex align-items-center justify-content-between p-3 border rounded flex-wrap gap-2" style="background-color: var(--beige-bg-alt); border-color: var(--beige-border-subtle) !important;">' +
        '<div class="d-flex align-items-center gap-3"><div class="position-relative bg-white rounded border p-1" style="width:72px;height:72px;">' +
        '<img src="' + esc(main.preview) + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:4px;">' + overlay(main) +
        '<span class="badge bg-primary text-white position-absolute bottom-0 start-50 translate-middle-x mb-1" style="font-size:8px;padding:1px 4px;">MAIN</span></div>' +
        '<div style="min-width:0"><div class="fw-bold text-dark fs-8">' + esc(imgLabel(main)) + '</div>' +
        (main.url ? '<small class="text-muted d-block text-truncate" style="max-width:340px;"><a href="' + esc(main.url) + '" target="_blank" rel="noopener">' + esc(main.url) + '</a></small>' : '<small class="text-muted d-block">Not uploaded yet</small>') +
        '<span class="badge bg-label-success fs-9"><i class="mdi mdi-check-circle-outline me-1"></i>Primary Storefront Cover</span></div></div>' +
        '<div class="d-flex align-items-center gap-2"><button type="button" class="btn btn-sm btn-outline-beige" data-act="change-main"><i class="mdi mdi-sync me-1"></i> Change</button>' +
        '<button type="button" class="btn btn-sm btn-icon text-danger" data-act="remove" data-u="' + main.u + '" title="Remove main image"><i class="mdi mdi-trash-can-outline fs-5"></i></button></div></div>';
    } else {
      box.innerHTML = '<div class="border rounded p-4 text-center" id="dropzoneMainImg" role="button" style="cursor:pointer;background-color: var(--beige-bg-alt); border-style: dashed !important; border-color: var(--beige-border-medium) !important;">' +
        '<i class="mdi mdi-cloud-upload-outline fs-1 text-primary mb-2"></i><p class="mb-1 fw-medium fs-8 text-dark">Click or Drag &amp; Drop Main Image</p>' +
        '<small class="text-muted">JPG, PNG, WEBP or GIF up to ' + CFG.maxMb + ' MB</small></div>';
    }
    var extra = images.filter(function (i) { return !i.main; });
    $('additionalImagesCountBadge').textContent = extra.length + (extra.length === 1 ? ' image' : ' images');
    $('additionalImagesContainer').innerHTML = extra.map(function (i) {
      return '<div class="gallery-thumb-card" title="' + esc(i.url || imgLabel(i)) + '"><img src="' + esc(i.preview) + '" alt="">' + overlay(i) +
        '<button type="button" class="btn-remove-gallery-img" data-act="remove" data-u="' + i.u + '" title="Remove image">&times;</button>' +
        (i.status === 'ready' ? '<button type="button" class="btn btn-xs btn-primary make-main" data-act="make-main" data-u="' + i.u + '">Make main</button>' : '') + '</div>';
    }).join('') + '<label class="gallery-thumb-card" style="cursor:pointer;border-style: dashed !important; border-color: var(--beige-border-medium) !important; background-color: var(--beige-bg-alt);" title="Add Additional Images">' +
      '<div class="text-center"><i class="mdi mdi-plus fs-3 text-muted"></i><small class="fs-9 text-muted d-block fw-semibold">Add Photo</small></div></label>';
    renderUrls();
  }
  function renderUrls() {
    var ready = images.filter(function (i) { return i.url; }), el = $('imageUrlList');
    if (!el) { el = document.createElement('details'); el.id = 'imageUrlList'; el.className = 'mt-3 fs-9'; $('uploadErrors').after(el); }
    el.hidden = !ready.length;
    el.innerHTML = '<summary class="text-muted" style="cursor:pointer">Image URLs (' + ready.length + ')</summary>' + ready.map(function (i) {
      return '<input class="form-control form-control-sm mt-1" readonly value="' + esc(i.url) + '" onfocus="this.select()">';
    }).join('');
  }
  function updateOverlays() { renderMedia(); }

  function addFiles(fileList, asMain) {
    Array.prototype.forEach.call(fileList, function (file) {
      var item = { u: uid++, id: null, url: '', file: file, preview: URL.createObjectURL(file), main: false, status: 'uploading', progress: 0, error: '' };
      if (asMain) { var old = images.filter(function (i) { return i.main; })[0]; if (old) dropImage(old.u); item.main = true; }
      else if (!images.some(function (i) { return i.main; })) item.main = true;
      images.push(item);
      var problem = ALLOWED.indexOf(file.type) < 0 ? '“' + file.name + '” is not a JPG, PNG, WEBP or GIF image.' : (file.size > CFG.maxMb * 1048576 ? '“' + file.name + '” is larger than ' + CFG.maxMb + ' MB.' : '');
      if (problem) { item.status = 'error'; item.error = problem.length > 60 ? 'Invalid file' : problem; showError(problem); } else { upload(item); }
    });
    renderAll();
  }
  function showError(msg) {
    var d = document.createElement('div'); d.className = 'alert alert-danger alert-dismissible fs-8 py-2 mb-2';
    d.innerHTML = esc(msg) + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'; $('uploadErrors').appendChild(d);
  }
  function upload(item) {
    item.status = 'uploading'; item.progress = 0; item.error = '';
    var fd = new FormData(); fd.append('image', item.file);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', CFG.uploadUrl);
    xhr.setRequestHeader('X-CSRF-TOKEN', CFG.csrf); xhr.setRequestHeader('Accept', 'application/json');
    xhr.upload.onprogress = function (e) { if (e.lengthComputable) { item.progress = Math.round(e.loaded / e.total * 100); renderMedia(); } };
    xhr.onload = function () {
      var j = {}; try { j = JSON.parse(xhr.responseText); } catch (e) { /* non-JSON error page */ }
      if (xhr.status === 200 && j.url) { item.url = j.url; item.status = 'ready'; item.progress = 100; }
      else {
        item.status = 'error';
        item.error = j.message || (xhr.status === 419 ? 'Session expired — reload the page.' : 'Upload failed.');
        showError((item.file ? item.file.name + ': ' : '') + item.error);
      }
      renderAll();
    };
    xhr.onerror = function () { item.status = 'error'; item.error = 'Network error.'; showError((item.file ? item.file.name + ': ' : '') + 'network error while uploading.'); renderAll(); };
    xhr.send(fd);
  }
  function dropImage(u) {
    var i = imgByU(u); if (!i) return;
    images.splice(images.indexOf(i), 1);
    if (i.file && i.preview) URL.revokeObjectURL(i.preview);
    colors.forEach(function (c) { c.imgs = c.imgs.filter(function (x) { return x !== u; }); });
    if (i.main && images.length) images[0].main = true;
  }

  $('mainImageFileInput').addEventListener('change', function (e) { if (e.target.files.length) addFiles([e.target.files[0]], true); e.target.value = ''; });
  $('additionalImagesFileInput').addEventListener('change', function (e) { addFiles(e.target.files, false); e.target.value = ''; });
  $('cardProductMedia').addEventListener('click', function (e) {
    if (e.target.closest('#dropzoneMainImg') || e.target.closest('[data-act=change-main]')) { $('mainImageFileInput').click(); return; }
    if (e.target.closest('label.gallery-thumb-card') && !e.target.closest('[data-act]')) { e.preventDefault(); $('additionalImagesFileInput').click(); return; }
    var b = e.target.closest('[data-act]'); if (!b) return;
    var u = +b.dataset.u;
    if (b.dataset.act === 'remove') { dropImage(u); renderAll(); }
    if (b.dataset.act === 'retry') { var i = imgByU(u); if (i && i.file) { upload(i); renderMedia(); } }
    if (b.dataset.act === 'make-main') { images.forEach(function (i) { i.main = i.u === u; }); renderAll(); }
  });
  $('mainImageContainer').addEventListener('dragover', function (e) { e.preventDefault(); });
  $('mainImageContainer').addEventListener('drop', function (e) { e.preventDefault(); if (e.dataTransfer.files.length) addFiles([e.dataTransfer.files[0]], true); });

  /* ---------------------------------------------------------------- colours */
  function colorCard(c) {
    var thumbs = images.length ? images.map(function (i) {
      var sel = c.imgs.indexOf(i.u) >= 0;
      return '<div class="color-img-selectable ' + (sel ? 'selected' : '') + '" data-act="toggle-img" data-c="' + c.u + '" data-u="' + i.u + '" title="' + (sel ? 'Selected for ' : 'Click to assign to ') + esc(c.name || 'this color') + '">' +
        '<img src="' + esc(i.preview) + '" class="selectable-img" alt="">' + (i.main ? '<span class="main-badge-tag">Main</span>' : '') + (sel ? '<span class="check-badge"><i class="mdi mdi-check"></i></span>' : '') + '</div>';
    }).join('') : '<small class="text-muted fs-9 fst-italic">No images uploaded in Product Media above yet.</small>';
    var n = c.imgs.length;
    return '<div class="color-item-card" data-c="' + c.u + '"><div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2"><div class="d-flex align-items-center gap-2 flex-grow-1 me-2">' +
      '<input type="color" class="form-control form-control-color border-0 p-0 c-picker" value="' + esc(c.hex) + '" style="width:34px;height:34px;cursor:pointer;">' +
      '<span class="c-swatch rounded-circle border flex-shrink-0" style="width:24px;height:24px;background-color:' + esc(c.hex) + ';display:inline-block;"></span>' +
      '<input type="text" class="form-control form-control-sm c-name fw-medium" value="' + esc(c.name) + '" placeholder="Color Name" style="max-width:180px;">' +
      '<input type="text" class="form-control form-control-sm c-hex text-muted" value="' + esc(c.hex) + '" placeholder="#HEX" style="width:90px;"></div>' +
      '<div class="d-flex align-items-center gap-2"><span class="badge ' + (n ? 'bg-label-primary' : 'bg-label-secondary') + ' fs-9">' + (n ? '<i class="mdi mdi-check-circle me-1"></i>' + n + ' assigned' : '<i class="mdi mdi-earth me-1"></i>General (' + images.length + ')') + '</span>' +
      '<button type="button" class="btn btn-sm btn-icon text-danger" data-act="remove-color" data-c="' + c.u + '" title="Delete Color"><i class="mdi mdi-trash-can-outline fs-5"></i></button></div></div>' +
      '<div class="pt-2 border-top" style="border-color: var(--beige-border-subtle) !important;"><div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-1">' +
      '<small class="text-muted fs-9 fw-bold text-uppercase"><i class="mdi mdi-image-multiple-outline me-1"></i> Select images for ' + esc(c.name || 'this color') + ' (click to assign / unassign)</small>' +
      '<div class="d-flex gap-1"><button type="button" class="btn btn-xs btn-outline-beige" data-act="all-imgs" data-c="' + c.u + '">Select All</button><button type="button" class="btn btn-xs btn-outline-secondary" data-act="clear-imgs" data-c="' + c.u + '">Clear</button></div></div>' +
      '<div class="d-flex flex-wrap gap-2 align-items-center mb-1">' + thumbs + '</div>' +
      '<small class="text-muted fs-9 d-block mt-1">' + (n ? '<strong>' + n + '</strong> image' + (n === 1 ? '' : 's') + ' represent this color on the storefront.' : 'No color-specific images: general product images apply.') + '</small></div></div>';
  }
  function renderColors() { $('colorsContainer').innerHTML = colors.map(colorCard).join('') || '<div class="text-muted fs-8">No colors yet. Click “Add Color”.</div>'; }
  var colorOf = function (el) { var id = +el.closest('[data-c]').dataset.c; return colors.filter(function (c) { return c.u === id; })[0]; };
  var cbox = $('colorsContainer');
  cbox.addEventListener('click', function (e) {
    var b = e.target.closest('[data-act]'); if (!b) return;
    var c = colors.filter(function (x) { return x.u === +b.dataset.c; })[0]; if (!c) return;
    var u = +b.dataset.u;
    if (b.dataset.act === 'toggle-img') c.imgs = c.imgs.indexOf(u) >= 0 ? c.imgs.filter(function (x) { return x !== u; }) : c.imgs.concat(u);
    if (b.dataset.act === 'all-imgs') c.imgs = images.map(function (i) { return i.u; });
    if (b.dataset.act === 'clear-imgs') c.imgs = [];
    if (b.dataset.act === 'remove-color') colors.splice(colors.indexOf(c), 1);
    renderColors(); renderMatrix();
  });
  cbox.addEventListener('input', function (e) {
    var c = colorOf(e.target), card = e.target.closest('.color-item-card');
    if (e.target.classList.contains('c-name')) c.name = e.target.value;
    if (e.target.classList.contains('c-picker') || e.target.classList.contains('c-hex')) {
      var h = e.target.value; if (!/^#[0-9a-fA-F]{6}$/.test(h)) return;
      c.hex = h; card.querySelector('.c-swatch').style.backgroundColor = h; card.querySelector('.c-picker').value = h; card.querySelector('.c-hex').value = h;
    }
    renderMatrix();
  });
  cbox.addEventListener('change', function (e) { if (e.target.classList.contains('c-name')) renderColors(); });
  $('btnAddColor').onclick = function () { colors.push({ u: uid++, id: '', name: '', hex: '#c8a27a', imgs: [] }); renderColors(); renderMatrix(); };

  /* ------------------------------------------------------------------ sizes */
  function renderChips() {
    $('sizesContainer').innerHTML = chips.map(function (c, i) {
      return '<label class="btn btn-sm btn-outline-beige d-flex align-items-center gap-1 ' + (c.checked ? 'active' : '') + '"><input type="checkbox" class="form-check-input size-chip-checkbox" data-i="' + i + '"' + (c.checked ? ' checked' : '') + '> ' + esc(c.label) + '</label>';
    }).join('');
  }
  $('sizesContainer').addEventListener('change', function (e) { var i = e.target.dataset.i; if (i == null) return; chips[i].checked = e.target.checked; renderChips(); renderMatrix(); });
  function addCustomSize() {
    var v = $('customSizeInput').value.trim(); if (!v) return;
    chip(v, true); $('customSizeInput').value = ''; renderChips(); renderMatrix();
  }
  $('btnAddCustomSize').onclick = addCustomSize;
  $('customSizeInput').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); addCustomSize(); } });
  var prevGuide = guideSel.value;
  guideSel.addEventListener('change', function () {
    var g = guides[guideSel.value];
    if (g) {
      // The guide's sizes replace the ticked sizes. Saved sizes (they own variants) stay listed but unticked.
      var keep = {}; g.sizes.forEach(function (l) { keep[l.toLowerCase()] = true; });
      var dropped = chips.filter(function (c) { return c.checked && c.id && !keep[c.label.toLowerCase()]; });
      if (dropped.length && !confirm('Switching to this size guide removes ' + dropped.map(function (c) { return c.label; }).join(', ') + ' and their variants when you save. Continue?')) {
        guideSel.value = prevGuide; return;
      }
      chips.forEach(function (c) { c.checked = !!keep[c.label.toLowerCase()]; });
      chips = chips.filter(function (c) { return c.checked || c.id || !c.guide; });
      g.sizes.forEach(function (l) { chip(l, true, '', true); });
    } else {
      chips = chips.filter(function (c) { return !(c.guide && !c.id && !c.checked); });
    }
    prevGuide = guideSel.value;
    renderChips(); renderMatrix();
  });
  $('btnClearSizeGuide').onclick = function () { guideSel.value = ''; guideSel.dispatchEvent(new Event('change')); };

  /* ----------------------------------------------------------------- matrix */
  function readMatrix() {
    Array.prototype.forEach.call(document.querySelectorAll('#variantTable tbody tr[data-key]'), function (tr) {
      var s = tr.querySelector('.vs');
      vmap[tr.dataset.key] = {
        id: tr.dataset.id || '', sku: tr.querySelector('.vsku').value, price_override: tr.querySelector('.vp').value,
        selling_price_override: s ? s.value : (vmap[tr.dataset.key] || {}).selling_price_override,
        stock_qty: tr.querySelector('.vst').value, is_active: tr.querySelector('.va').checked ? 1 : 0
      };
    });
  }
  function renderMatrix() {
    readMatrix();
    var sc = scenario(), ac = activeColors(), as = activeSizes(), rows = [], saleOn = sale.checked;
    var base = parseFloat($('f-price').value) || 0;
    var cs = sc === 'size_only' ? [null] : ac.map(function (c, i) { return i; });
    var ss = sc === 'color_only' ? [null] : as.map(function (s, i) { return i; });
    cs.forEach(function (ci) { ss.forEach(function (si) {
      var c = ci == null ? null : ac[ci], z = si == null ? null : as[si];
      var key = (c ? c.name : '') + '|' + (z ? z.label : '');
      var d = vmap[key] || { id: '', sku: '', price_override: '', selling_price_override: '', stock_qty: 0, is_active: 1 };
      var n = rows.length, ex = d.id ? 'data-id="' + esc(d.id) + '"' : '';
      var colorCell = '';
      if (c) {
        var imgs = c.imgs.map(imgByU).filter(Boolean);
        var micro = imgs.slice(0, 3).map(function (i) { return '<img src="' + esc(i.preview) + '" class="variant-color-thumb me-1" alt="">'; }).join('') + (imgs.length > 3 ? '<span class="badge bg-label-secondary fs-9">+' + (imgs.length - 3) + '</span>' : '');
        colorCell = '<td><div class="d-flex align-items-center gap-2"><span class="rounded-circle border flex-shrink-0" style="width:20px;height:20px;background-color:' + esc(c.hex) + ';display:inline-block;"></span><div><span class="fw-medium text-dark fs-8 d-block">' + esc(c.name) + '</span>' +
          '<div class="d-flex align-items-center gap-1 mt-1">' + micro + '<small class="' + (imgs.length ? 'text-success fw-medium' : 'text-muted fst-italic') + ' fs-9">' + (imgs.length ? imgs.length + ' color photos' : 'General (' + images.length + ')') + '</small></div></div></div></td>';
      }
      rows.push('<tr class="' + (+d.is_active ? '' : 'variant-disabled') + '" data-key="' + esc(key) + '" ' + ex + '>' + colorCell +
        (z ? '<td><span class="badge bg-label-secondary fs-8">' + esc(z.label) + '</span></td>' : '') +
        '<td><input type="hidden" name="variants[' + n + '][id]" value="' + esc(d.id) + '"><input type="hidden" name="variants[' + n + '][color]" value="' + (ci == null ? '' : ci) + '"><input type="hidden" name="variants[' + n + '][size]" value="' + (si == null ? '' : si) + '">' +
        '<input class="form-control form-control-sm vsku" style="min-width:130px" name="variants[' + n + '][sku]" placeholder="auto" value="' + esc(d.sku) + '"></td>' +
        '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm vp" style="width:100px" name="variants[' + n + '][price_override]" placeholder="' + (base ? 'Inherit (A$' + base.toFixed(2) + ')' : 'Inherit base') + '" value="' + esc(d.price_override) + '"></td>' +
        (saleOn ? '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm vs" style="width:100px" name="variants[' + n + '][selling_price_override]" placeholder="Inherit" value="' + esc(d.selling_price_override) + '"></td>' : '') +
        '<td><input type="number" min="0" class="form-control form-control-sm vst" style="width:80px" name="variants[' + n + '][stock_qty]" value="' + esc(d.stock_qty) + '"></td>' +
        '<td><input type="hidden" name="variants[' + n + '][is_active]" value="0"><div class="form-check form-switch mb-0"><input type="checkbox" class="form-check-input va" name="variants[' + n + '][is_active]" value="1"' + (+d.is_active ? ' checked' : '') + '></div></td></tr>');
    }); });
    var head = '<thead><tr>' + (sc === 'size_only' ? '' : '<th>Color &amp; Media</th>') + (sc === 'color_only' ? '' : '<th>Size</th>') + '<th>SKU</th><th>Regular Price (A$)</th>' + (saleOn ? '<th>Sale Price (A$)</th>' : '') + '<th>Stock</th><th>Active</th></tr></thead>';
    var cols = 4 + (sc === 'size_only' ? 0 : 1) + (sc === 'color_only' ? 0 : 1) + (saleOn ? 1 : 0);
    $('variantTable').innerHTML = head + '<tbody>' + (rows.join('') || '<tr><td colspan="' + cols + '" class="text-center py-4 text-muted"><i class="mdi mdi-alert-circle-outline me-1"></i> Add at least one ' + (sc === 'size_only' ? 'size' : (sc === 'color_only' ? 'color' : 'color and tick at least one size')) + '.</td></tr>') + '</tbody>';
    $('variantScenarioBadge').textContent = 'Scenario: ' + { color_size: 'Color + Size', color_only: 'Color-only', size_only: 'Size-only' }[sc];
    totals();
  }
  function totals() {
    var t = 0; Array.prototype.forEach.call(document.querySelectorAll('#variantTable tbody tr[data-key]'), function (tr) {
      if (tr.querySelector('.va').checked) t += +tr.querySelector('.vst').value || 0;
    });
    $('totalVariantStockCount').textContent = t; $('sidebarTotalStock').textContent = t;
  }
  var tbl = $('variantTable');
  tbl.addEventListener('input', function (e) { if (e.target.classList.contains('vst')) totals(); });
  tbl.addEventListener('change', function (e) {
    if (e.target.classList.contains('va')) { e.target.closest('tr').classList.toggle('variant-disabled', !e.target.checked); totals(); }
  });
  $('btnEnableAllVariants').onclick = function () { setAll(true); };
  $('btnDisableAllVariants').onclick = function () { setAll(false); };
  function setAll(on) { Array.prototype.forEach.call(tbl.querySelectorAll('.va'), function (c) { c.checked = on; c.closest('tr').classList.toggle('variant-disabled', !on); }); totals(); }
  $('f-price').addEventListener('input', renderMatrix);

  /* ----------------------------------------------------------------- panels */
  function updatePanels() {
    var v = has.checked, sc = scenario();
    Array.prototype.forEach.call(document.querySelectorAll('.variant-only'), function (el) { el.style.display = v ? '' : 'none'; });
    $('cardColors').style.display = v && sc !== 'size_only' ? '' : 'none';
    $('cardSizes').style.display = v && sc !== 'color_only' ? '' : 'none';
    $('variantTypeSelectorContainer').style.display = v ? '' : 'none';
    $('variantStockSummary').style.display = v ? '' : 'none';
    $('simpleStock').style.display = v ? 'none' : '';
    var st = $('f-stock_qty'); if (st) st.disabled = v;
    Array.prototype.forEach.call(document.querySelectorAll('.sale-price-field'), function (el) { el.style.display = sale.checked ? '' : 'none'; });
    var sp = $('f-selling_price'); if (sp) sp.disabled = !sale.checked;
  }
  /* Discount % <-> sale price (the % input has no name, so only the sale price is submitted). */
  (function () {
    var price = $('f-price'), sp = $('f-selling_price'), pct = $('f-discount_pct');
    if (!price || !sp || !pct) return;
    function fromPrice() {
      var p = parseFloat(price.value), s = parseFloat(sp.value);
      pct.value = p > 0 && !isNaN(s) && s <= p ? (Math.round((1 - s / p) * 10000) / 100) : '';
    }
    pct.addEventListener('input', function () {
      var p = parseFloat(price.value), d = parseFloat(pct.value);
      if (!(p > 0) || isNaN(d)) { if (pct.value === '') sp.value = ''; return; }
      sp.value = (p * (1 - Math.min(Math.max(d, 0), 100) / 100)).toFixed(2);
    });
    sp.addEventListener('input', fromPrice);
    price.addEventListener('input', fromPrice);
    fromPrice();
  })();

  function refresh() { updatePanels(); renderMatrix(); }
  has.addEventListener('change', refresh); sale.addEventListener('change', refresh);
  Array.prototype.forEach.call(document.querySelectorAll('input[name=variant_scenario]'), function (r) { r.addEventListener('change', refresh); });

  function renderAll() { renderMedia(); renderColors(); renderMatrix(); }

  /* ----------------------------------------------------------------- submit */
  form.addEventListener('submit', function (e) {
    if (images.some(function (i) { return i.status === 'uploading'; })) { e.preventDefault(); alert('Images are still uploading. Please wait a moment and save again.'); return; }
    if (images.some(function (i) { return i.status === 'error'; })) { e.preventDefault(); alert('Some images failed to upload. Retry or remove them (red thumbnails), then save again.'); return; }
    readMatrix();
    var box = $('dynamicFields'), html = [];
    var add = function (n, v) { html.push('<input type="hidden" name="' + esc(n) + '" value="' + esc(v) + '">'); };
    var ready = images.filter(function (i) { return i.status === 'ready'; }), idx = function (u) { return ready.indexOf(imgByU(u)); };
    ready.forEach(function (i, k) { if (i.id) add('images[' + k + '][id]', i.id); else add('images[' + k + '][url]', i.url); if (i.main) add('main_key', k); });
    if (has.checked) {
      activeColors().forEach(function (c, n) {
        add('colors[' + n + '][id]', c.id); add('colors[' + n + '][name]', c.name.trim()); add('colors[' + n + '][hex_code]', c.hex);
        c.imgs.map(idx).filter(function (k) { return k >= 0; }).forEach(function (k) { add('colors[' + n + '][images][]', k); });
      });
      activeSizes().forEach(function (s, n) { add('sizes[' + n + '][id]', s.id); add('sizes[' + n + '][size_label]', s.label); });
    }
    // The size-guide select lives in a card that may be hidden; never let hiding it drop the assignment.
    var guideHidden = $('cardSizes').style.display === 'none';
    guideSel.disabled = guideHidden; if (guideHidden && guideSel.value) add('size_guide_id', guideSel.value);
    box.innerHTML = html.join('');
  });

  // Lets the import review panel add an already-uploaded (Cloudinary) image to the gallery.
  window.ProductFormApi = {
    addUploadedImage: function (url) {
      if (images.some(function (i) { return i.url === url; })) return false;
      images.push({ u: uid++, id: null, url: url, preview: url, main: !images.some(function (i) { return i.main; }), status: 'ready', progress: 100, error: '' });
      renderAll(); return true;
    }
  };

  fillSubs(); renderChips(); renderAll(); updatePanels();
})();
