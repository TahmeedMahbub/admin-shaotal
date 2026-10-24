/* Auto-fill the slug from the name/title (lowercase, hyphens) until the user edits it by hand. */
(function () {
  function slugify(s) {
    return String(s || '').normalize('NFKD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
  }
  var slugEl = document.getElementById('f-slug');
  var srcEl = document.getElementById('f-name') || document.getElementById('f-title');
  if (!slugEl || !srcEl || slugEl.closest('#productForm')) return;
  var manual = slugEl.value !== '' && slugEl.value !== slugify(srcEl.value);
  if (!slugEl.value) slugEl.value = slugify(srcEl.value);
  srcEl.addEventListener('input', function () { if (!manual) slugEl.value = slugify(srcEl.value); });
  slugEl.addEventListener('input', function () { manual = slugEl.value !== '' && slugEl.value !== slugify(srcEl.value); });
  slugEl.addEventListener('blur', function () { if (slugEl.value) slugEl.value = slugify(slugEl.value); });
})();
