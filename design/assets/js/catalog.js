/**
 * Aus Ecom Catalog Management JavaScript
 * Full Product Variant Management & Centralized Media Workflow:
 * - Separate Main Image and Additional Images upload fields
 * - Small thumbnail previews with remove actions for additional images
 * - Displays Main Image & all Additional Images in each color variant for selection
 * - Multi-image selection per color (an image can be assigned to multiple colors)
 * - Unassigned images act as general product images applicable to all variants
 * - Reuses assigned color images across all sizes of that color in the matrix
 * - Manages SKU, stock, and price overrides (regular & sale)
 * - Dynamic price inheritance & combination disabling/enabling
 * - Synchronized product-level vs variant-level stock
 */

document.addEventListener('DOMContentLoaded', function () {

  // ====================================================
  // 1. Initial State Definition
  // ====================================================

  const isEditPage = window.location.pathname.includes('product-edit');

  // Centralized Product Media Library
  let productImages = [
    {
      id: 'img-main-1',
      url: 'assets/img/elements/1.jpg',
      name: 'oak-table-main.jpg',
      size: '420 KB',
      isMain: true
    },
    {
      id: 'img-add-1',
      url: 'assets/img/elements/2.jpg',
      name: 'oak-perspective.jpg',
      size: '310 KB',
      isMain: false
    },
    {
      id: 'img-add-2',
      url: 'assets/img/elements/3.jpg',
      name: 'oak-olive-angle.jpg',
      size: '280 KB',
      isMain: false
    },
    {
      id: 'img-add-3',
      url: 'assets/img/elements/4.jpg',
      name: 'oak-walnut-grain.jpg',
      size: '350 KB',
      isMain: false
    }
  ];

  // Color variants with selected image IDs (an image can be assigned to multiple colors)
  let colors = [
    {
      id: 'color-olive',
      name: 'Olive Green',
      hex: '#6F765F',
      // Olive Green has main image + olive angle assigned
      selectedImageIds: ['img-main-1', 'img-add-2']
    },
    {
      id: 'color-espresso',
      name: 'Espresso Walnut',
      hex: '#3B2B23',
      // Espresso Walnut has main image (shared) + walnut grain assigned
      selectedImageIds: ['img-main-1', 'img-add-3']
    }
  ];

  // Active sizes list
  let activeSizes = ['6-Seater', '8-Seater'];

  // Stored variant state (keyed by colorName_size, or colorName, or size)
  let variantsStore = {};

  // Pre-seed default variants
  variantsStore['Olive Green_6-Seater'] = {
    sku: 'FUR-OAK-OLV-6S',
    priceOverride: '',
    salePriceOverride: '',
    stock: 20,
    active: true
  };
  variantsStore['Olive Green_8-Seater'] = {
    sku: 'FUR-OAK-OLV-8S',
    priceOverride: '450.00',
    salePriceOverride: '390.00',
    stock: 12,
    active: true
  };
  variantsStore['Espresso Walnut_6-Seater'] = {
    sku: 'FUR-OAK-ESP-6S',
    priceOverride: '',
    salePriceOverride: '',
    stock: 10,
    active: true
  };
  variantsStore['Espresso Walnut_8-Seater'] = {
    sku: 'FUR-OAK-ESP-8S',
    priceOverride: '450.00',
    salePriceOverride: '',
    stock: 0,
    active: false // pre-seed one disabled variant
  };

  // Sample presets for quick testing
  const samplePresets = [
    { url: 'assets/img/elements/5.jpg', name: 'wood-swatch-detail.jpg', size: '240 KB' },
    { url: 'assets/img/elements/6.jpg', name: 'dining-room-lifestyle.jpg', size: '510 KB' },
    { url: 'assets/img/elements/7.jpg', name: 'joinery-macro.jpg', size: '190 KB' },
    { url: 'assets/img/elements/8.jpg', name: 'leg-taper-detail.jpg', size: '215 KB' },
    { url: 'assets/img/products/1.png', name: 'studio-white-render.png', size: '120 KB' }
  ];

  // ====================================================
  // 2. DOM Elements
  // ====================================================

  // Media Elements
  const mainImageContainer = document.getElementById('mainImageContainer');
  const mainImageFileInput = document.getElementById('mainImageFileInput');
  const additionalImagesContainer = document.getElementById('additionalImagesContainer');
  const additionalImagesFileInput = document.getElementById('additionalImagesFileInput');
  const additionalImagesCountBadge = document.getElementById('additionalImagesCountBadge');
  const btnQuickSampleMedia = document.getElementById('btnQuickSampleMedia');

  // Variant Elements
  const toggleVariants = document.getElementById('toggleVariants');
  const variantModeRadios = document.querySelectorAll('input[name="variantMode"]');
  const variantTypeSelectorContainer = document.getElementById('variantTypeSelectorContainer');

  const cardColors = document.getElementById('cardColors');
  const cardSizes = document.getElementById('cardSizes');
  const cardVariants = document.getElementById('cardVariants');

  const colorsContainer = document.getElementById('colorsContainer');
  const btnAddColor = document.getElementById('btnAddColor');

  const sizesContainer = document.getElementById('sizesContainer');
  const customSizeInput = document.getElementById('customSizeInput');
  const btnAddCustomSize = document.getElementById('btnAddCustomSize');

  const variantsTableBody = document.getElementById('variantsTableBody');
  const btnGenerateVariants = document.getElementById('btnGenerateVariants');
  const btnEnableAllVariants = document.getElementById('btnEnableAllVariants');
  const btnDisableAllVariants = document.getElementById('btnDisableAllVariants');

  const productSkuInput = document.getElementById('productSku');
  const regularPriceInput = document.getElementById('regularPrice');
  const baseSalePriceInput = document.getElementById('baseSalePrice');
  const baseStockField = document.getElementById('baseStockField');
  const baseStockInput = document.getElementById('baseStockInput');
  const variantStockSummary = document.getElementById('variantStockSummary');
  const totalVariantStockBadge = document.getElementById('totalVariantStockBadge');
  const totalVariantStockCount = document.getElementById('totalVariantStockCount');
  const sidebarTotalStock = document.getElementById('sidebarTotalStock');
  const activeVariantsCount = document.getElementById('activeVariantsCount');

  // Feature Switches
  const toggleStock = document.getElementById('toggleStock');
  const cardStock = document.getElementById('cardStock');
  const togglePreorder = document.getElementById('togglePreorder');
  const cardPreorder = document.getElementById('cardPreorder');
  const toggleSizeGuide = document.getElementById('toggleSizeGuide');
  const cardSizeGuide = document.getElementById('cardSizeGuide');
  const toggleSale = document.getElementById('toggleSale');
  const salePriceFields = document.querySelectorAll('.sale-price-field');

  // ====================================================
  // 3. Centralized Product Media Handlers
  // ====================================================

  /**
   * Render Main Image Upload Field & Preview
   */
  function renderMainImage() {
    if (!mainImageContainer) return;

    const mainImg = productImages.find(img => img.isMain);

    if (mainImg) {
      mainImageContainer.innerHTML = `
        <div class="d-flex align-items-center justify-content-between p-3 border rounded" style="background-color: var(--beige-bg-alt); border-color: var(--beige-border-subtle) !important;">
          <div class="d-flex align-items-center gap-3">
            <div class="position-relative bg-white rounded border p-1" style="width: 72px; height: 72px; display: flex; align-items: center; justify-content: center;">
              <img src="${mainImg.url}" alt="${mainImg.name}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 4px;">
              <span class="badge bg-primary text-white position-absolute bottom-0 start-50 translate-middle-x mb-1" style="font-size: 8px; padding: 1px 4px;">MAIN</span>
            </div>
            <div>
              <div class="fw-bold text-dark fs-8">${mainImg.name}</div>
              <small class="text-muted d-block mb-1">${mainImg.size || 'Cover Photo'}</small>
              <span class="badge bg-label-success fs-9"><i class="mdi mdi-check-circle-outline me-1"></i>Primary Storefront Cover</span>
            </div>
          </div>
          <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-beige" id="btnChangeMainImg"><i class="mdi mdi-sync me-1"></i> Change</button>
            <button type="button" class="btn btn-sm btn-icon text-danger" id="btnRemoveMainImg" title="Remove main image"><i class="mdi mdi-trash-can-outline fs-5"></i></button>
          </div>
        </div>
      `;

      const btnChange = document.getElementById('btnChangeMainImg');
      if (btnChange && mainImageFileInput) {
        btnChange.addEventListener('click', () => mainImageFileInput.click());
      }

      const btnRemove = document.getElementById('btnRemoveMainImg');
      if (btnRemove) {
        btnRemove.addEventListener('click', () => {
          removeProductImage(mainImg.id);
        });
      }

    } else {
      mainImageContainer.innerHTML = `
        <div class="border rounded p-4 text-center cursor-pointer" id="dropzoneMainImg" style="background-color: var(--beige-bg-alt); border-style: dashed !important; border-color: var(--beige-border-medium) !important;">
          <i class="mdi mdi-cloud-upload-outline fs-1 text-primary mb-2"></i>
          <p class="mb-1 fw-medium fs-8 text-dark">Click or Drag & Drop Main Image</p>
          <small class="text-muted">High resolution JPG, PNG, WEBP up to 5MB</small>
        </div>
      `;

      const dropzone = document.getElementById('dropzoneMainImg');
      if (dropzone && mainImageFileInput) {
        dropzone.addEventListener('click', () => mainImageFileInput.click());
      }
    }
  }

  /**
   * Render Additional Images Upload Area & Previews
   */
  function renderAdditionalImages() {
    if (!additionalImagesContainer) return;

    const additionals = productImages.filter(img => !img.isMain);
    if (additionalImagesCountBadge) {
      additionalImagesCountBadge.textContent = `${additionals.length} ${additionals.length === 1 ? 'image' : 'images'}`;
    }

    let html = '';
    additionals.forEach(img => {
      html += `
        <div class="gallery-thumb-card" title="${img.name} (${img.size || ''})">
          <img src="${img.url}" alt="${img.name}">
          <button type="button" class="btn-remove-gallery-img" data-img-id="${img.id}" title="Remove image">&times;</button>
        </div>
      `;
    });

    // Plus upload box
    html += `
      <label class="gallery-thumb-card cursor-pointer" style="border-style: dashed !important; border-color: var(--beige-border-medium) !important; background-color: var(--beige-bg-alt);" title="Add Additional Images">
        <div class="text-center">
          <i class="mdi mdi-plus fs-3 text-muted"></i>
          <small class="fs-9 text-muted d-block fw-semibold">Add Photo</small>
        </div>
        <input type="file" id="additionalImagesFileInputInternal" multiple accept="image/*" class="d-none">
      </label>
    `;

    additionalImagesContainer.innerHTML = html;

    // Attach remove events
    additionalImagesContainer.querySelectorAll('.btn-remove-gallery-img').forEach(btn => {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        const imgId = this.dataset.imgId;
        removeProductImage(imgId);
      });
    });

    // Attach upload event
    const fileInput = document.getElementById('additionalImagesFileInputInternal');
    if (fileInput) {
      fileInput.addEventListener('change', handleAdditionalFilesUpload);
    }
  }

  function handleMainFileUpload(e) {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function (event) {
      const oldMain = productImages.find(img => img.isMain);
      if (oldMain) {
        removeProductImage(oldMain.id, false);
      }

      const newId = 'img-main-' + Date.now();
      productImages.unshift({
        id: newId,
        url: event.target.result,
        name: file.name,
        size: formatBytes(file.size),
        isMain: true
      });

      renderMainImage();
      renderColorsList();
      renderVariantsMatrix();
    };
    reader.readAsDataURL(file);
    e.target.value = '';
  }

  function handleAdditionalFilesUpload(e) {
    const files = Array.from(e.target.files);
    if (!files.length) return;

    let processed = 0;
    files.forEach((file, idx) => {
      const reader = new FileReader();
      reader.onload = function (event) {
        productImages.push({
          id: 'img-add-' + Date.now() + '-' + idx,
          url: event.target.result,
          name: file.name,
          size: formatBytes(file.size),
          isMain: false
        });
        processed++;
        if (processed === files.length) {
          renderAdditionalImages();
          renderColorsList();
          renderVariantsMatrix();
        }
      };
      reader.readAsDataURL(file);
    });
    e.target.value = '';
  }

  function removeProductImage(imgId, triggerRender = true) {
    // Remove from productImages in place
    const idx = productImages.findIndex(img => img.id === imgId);
    if (idx !== -1) {
      productImages.splice(idx, 1);
    }

    // Unassign from any colors that had it selected
    colors.forEach(col => {
      if (col.selectedImageIds) {
        col.selectedImageIds = col.selectedImageIds.filter(id => id !== imgId);
      }
    });

    if (triggerRender) {
      renderMainImage();
      renderAdditionalImages();
      renderColorsList();
      renderVariantsMatrix();
    }
  }

  function formatBytes(bytes) {
    if (!bytes) return '250 KB';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
  }

  // File input listeners
  if (mainImageFileInput) {
    mainImageFileInput.addEventListener('change', handleMainFileUpload);
  }
  if (additionalImagesFileInput) {
    additionalImagesFileInput.addEventListener('change', handleAdditionalFilesUpload);
  }

  // Quick preset sample media
  if (btnQuickSampleMedia) {
    btnQuickSampleMedia.addEventListener('click', function () {
      const randomSample = samplePresets[Math.floor(Math.random() * samplePresets.length)];
      productImages.push({
        id: 'img-sample-' + Date.now(),
        url: randomSample.url,
        name: randomSample.name,
        size: randomSample.size,
        isMain: false
      });
      renderAdditionalImages();
      renderColorsList();
      renderVariantsMatrix();
    });
  }

  // ====================================================
  // 4. Variant Mode Helpers
  // ====================================================

  // Variant scenario state tracking
  let activeVariantMode = 'color-size';
  const initialRadio = document.querySelector('input[name="variantMode"]:checked');
  if (initialRadio) {
    activeVariantMode = initialRadio.value;
  }
  let variantsEnabled = toggleVariants ? toggleVariants.checked : true;

  function getCurrentVariantMode() {
    if (!toggleVariants || !toggleVariants.checked) {
      return 'none';
    }

    const selectedRadio = document.querySelector('input[name="variantMode"]:checked');
    if (selectedRadio) {
      return selectedRadio.value;
    }

    return activeVariantMode || 'color-size';
  }

  function syncVariantModeUI() {
    const mode = getCurrentVariantMode();

    if (variantTypeSelectorContainer) {
      variantTypeSelectorContainer.style.display = (mode === 'none') ? 'none' : 'block';
    }

    if (cardColors) {
      cardColors.style.display = (mode === 'color-size' || mode === 'color-only') ? 'block' : 'none';
    }

    if (cardSizes) {
      cardSizes.style.display = (mode === 'color-size' || mode === 'size-only') ? 'block' : 'none';
    }

    if (cardVariants) {
      cardVariants.style.display = (mode === 'none') ? 'none' : 'block';
    }

    if (baseStockField) {
      baseStockField.style.display = (mode === 'none') ? 'block' : 'none';
    }
    if (variantStockSummary) {
      variantStockSummary.style.display = (mode === 'none') ? 'none' : 'block';
    }

    const pricingHint = document.getElementById('pricingInheritHint');
    if (pricingHint) {
      pricingHint.style.display = (mode === 'none') ? 'none' : 'block';
    }

    const badge = document.getElementById('variantScenarioBadge');
    if (badge) {
      if (mode === 'color-size') badge.textContent = 'Scenario: Color + Size';
      else if (mode === 'color-only') badge.textContent = 'Scenario: Color-only';
      else if (mode === 'size-only') badge.textContent = 'Scenario: Size-only';
      else badge.textContent = 'Scenario: None';
    }

    if (mode !== 'none') {
      renderVariantsMatrix();
    } else {
      updateStockTotals();
    }
  }

  // ====================================================
  // 5. Colors & Multi-Image Variant Selection
  // ====================================================

  /**
   * Render Color Cards with selectable Product Images
   */
  function renderColorsList() {
    if (!colorsContainer) return;

    colorsContainer.innerHTML = '';

    colors.forEach(color => {
      // Ensure selectedImageIds array exists
      if (!color.selectedImageIds) {
        color.selectedImageIds = [];
      }

      // Filter valid selected image IDs that still exist in productImages
      color.selectedImageIds = color.selectedImageIds.filter(id => productImages.some(img => img.id === id));

      const card = document.createElement('div');
      card.className = 'color-item-card';
      card.dataset.colorId = color.id;

      const selectedCount = color.selectedImageIds.length;
      const usesGeneral = selectedCount === 0;

      // Build selectable thumbnail list for this color
      let imageThumbnailsHtml = '';
      if (productImages.length > 0) {
        productImages.forEach(img => {
          const isSelected = color.selectedImageIds.includes(img.id);
          imageThumbnailsHtml += `
            <div class="color-img-selectable ${isSelected ? 'selected' : ''}" data-color-id="${color.id}" data-img-id="${img.id}" title="${img.name} (${isSelected ? 'Selected for ' + color.name : 'Click to assign'})">
              <img src="${img.url}" class="selectable-img" alt="${img.name}">
              ${img.isMain ? '<span class="main-badge-tag">Main</span>' : ''}
              ${isSelected ? '<span class="check-badge"><i class="mdi mdi-check"></i></span>' : ''}
            </div>
          `;
        });
      } else {
        imageThumbnailsHtml = `<small class="text-muted fs-9 fst-italic">No images uploaded in Product Media above yet.</small>`;
      }

      card.innerHTML = `
        <!-- Color Info Bar -->
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="d-flex align-items-center gap-2 flex-grow-1 me-2">
            <input type="color" class="form-control form-control-color border-0 p-0 color-picker-input" value="${color.hex}" title="Choose color" style="width: 34px; height: 34px; cursor: pointer;">
            <span class="color-swatch-circle rounded-circle border flex-shrink-0" style="width: 24px; height: 24px; background-color: ${color.hex}; display: inline-block;"></span>
            <input type="text" class="form-control form-control-sm color-name-input fw-medium" value="${color.name}" placeholder="Color Name" style="max-width: 180px;">
            <input type="text" class="form-control form-control-sm color-hex-input text-muted" value="${color.hex}" placeholder="#HEX" style="width: 90px;">
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="badge ${usesGeneral ? 'bg-label-secondary' : 'bg-label-primary'} fs-9 color-assigned-count">
              ${usesGeneral ? `<i class="mdi mdi-earth me-1"></i>General (${productImages.length})` : `<i class="mdi mdi-check-circle me-1"></i>${selectedCount} assigned`}
            </span>
            <button type="button" class="btn btn-sm btn-icon text-danger btn-remove-color" data-color-id="${color.id}" title="Delete Color"><i class="mdi mdi-trash-can-outline fs-5"></i></button>
          </div>
        </div>

        <!-- Color Image Assignment Section -->
        <div class="pt-2 border-top" style="border-color: var(--beige-border-subtle) !important;">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <small class="text-muted fs-9 fw-bold text-uppercase">
              <i class="mdi mdi-image-multiple-outline me-1"></i> Select Images for ${color.name} (Click thumbnails to assign/unassign):
            </small>
            <div class="d-flex align-items-center gap-1">
              <button type="button" class="btn btn-xs btn-outline-beige btn-select-all-color-imgs" data-color-id="${color.id}" style="font-size: 0.75rem; padding: 0.15rem 0.45rem;">Select All</button>
              <button type="button" class="btn btn-xs btn-outline-secondary btn-clear-color-imgs" data-color-id="${color.id}" style="font-size: 0.75rem; padding: 0.15rem 0.45rem;">Clear</button>
            </div>
          </div>

          <!-- Selectable Image Thumbnails Row -->
          <div class="d-flex flex-wrap gap-2 align-items-center mb-1 color-selectable-images-row">
            ${imageThumbnailsHtml}
          </div>

          <!-- Explanatory note -->
          ${usesGeneral
            ? `<small class="text-muted fs-9 d-block mt-1"><i class="mdi mdi-information-outline me-1"></i> No color-specific images selected. General product images (${productImages.length}) apply to this color across all sizes.</small>`
            : `<small class="text-success fs-9 d-block mt-1"><i class="mdi mdi-check-circle-outline me-1"></i> <strong>${selectedCount} ${selectedCount === 1 ? 'image' : 'images'}</strong> assigned to represent <strong>${color.name}</strong> on the storefront (reused for all sizes).</small>`
          }
        </div>
      `;

      colorsContainer.appendChild(card);
      attachColorCardEvents(card, color);
    });
  }

  function attachColorCardEvents(card, color) {
    const picker = card.querySelector('.color-picker-input');
    const swatch = card.querySelector('.color-swatch-circle');
    const hexInput = card.querySelector('.color-hex-input');
    const nameInput = card.querySelector('.color-name-input');
    const btnRemove = card.querySelector('.btn-remove-color');
    const btnSelectAll = card.querySelector('.btn-select-all-color-imgs');
    const btnClear = card.querySelector('.btn-clear-color-imgs');

    if (picker) {
      picker.addEventListener('input', function () {
        color.hex = this.value;
        if (swatch) swatch.style.backgroundColor = this.value;
        if (hexInput) hexInput.value = this.value.toUpperCase();
        renderVariantsMatrix();
      });
    }

    if (hexInput) {
      hexInput.addEventListener('input', function () {
        color.hex = this.value;
        if (swatch) swatch.style.backgroundColor = this.value;
        if (picker) picker.value = this.value;
        renderVariantsMatrix();
      });
    }

    if (nameInput) {
      nameInput.addEventListener('input', function () {
        color.name = this.value;
        renderColorsList();
        renderVariantsMatrix();
      });
    }

    if (btnRemove) {
      btnRemove.addEventListener('click', function () {
        if (colors.length <= 1) {
          return;
        }
        colors = colors.filter(c => c.id !== color.id);
        renderColorsList();
        renderVariantsMatrix();
      });
    }

    // Toggle thumbnail selection for this color
    card.querySelectorAll('.color-img-selectable').forEach(thumb => {
      thumb.addEventListener('click', function () {
        const imgId = this.dataset.imgId;
        if (color.selectedImageIds.includes(imgId)) {
          color.selectedImageIds = color.selectedImageIds.filter(id => id !== imgId);
        } else {
          color.selectedImageIds.push(imgId);
        }
        renderColorsList();
        renderVariantsMatrix();
      });
    });

    // Select all images for this color
    if (btnSelectAll) {
      btnSelectAll.addEventListener('click', function () {
        color.selectedImageIds = productImages.map(img => img.id);
        renderColorsList();
        renderVariantsMatrix();
      });
    }

    // Clear all images for this color (reverts to general images)
    if (btnClear) {
      btnClear.addEventListener('click', function () {
        color.selectedImageIds = [];
        renderColorsList();
        renderVariantsMatrix();
      });
    }
  }

  if (btnAddColor) {
    btnAddColor.addEventListener('click', function () {
      const newId = 'color-' + Date.now();
      const defaultHex = '#8B5A2B';
      colors.push({
        id: newId,
        name: 'New Color',
        hex: defaultHex,
        // By default, starts with main image assigned or empty (general)
        selectedImageIds: productImages.length > 0 ? [productImages[0].id] : []
      });
      renderColorsList();
      renderVariantsMatrix();
    });
  }

  // ====================================================
  // 6. Sizes Management
  // ====================================================

  function attachSizeEvents() {
    if (!sizesContainer) return;
    const chips = sizesContainer.querySelectorAll('.size-chip-checkbox');
    chips.forEach(chip => {
      chip.removeEventListener('change', onSizeChipChange);
      chip.addEventListener('change', onSizeChipChange);
    });
  }

  function onSizeChipChange() {
    const selected = [];
    sizesContainer.querySelectorAll('.size-chip-checkbox:checked').forEach(c => {
      selected.push(c.value);
    });
    activeSizes = selected;
    renderVariantsMatrix();
  }

  if (btnAddCustomSize && customSizeInput) {
    btnAddCustomSize.addEventListener('click', addCustomSize);
    customSizeInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        addCustomSize();
      }
    });
  }

  function addCustomSize() {
    const val = customSizeInput.value.trim();
    if (!val) return;

    const existing = Array.from(sizesContainer.querySelectorAll('.size-chip-checkbox')).map(c => c.value.toLowerCase());
    if (existing.includes(val.toLowerCase())) {
      alert('This size already exists.');
      customSizeInput.value = '';
      return;
    }

    const label = document.createElement('label');
    label.className = 'btn btn-sm btn-outline-beige d-flex align-items-center gap-1';
    label.innerHTML = `
      <input type="checkbox" class="form-check-input size-chip-checkbox" value="${val}" checked> ${val}
    `;
    sizesContainer.appendChild(label);
    customSizeInput.value = '';

    attachSizeEvents();
    onSizeChipChange();
  }

  // ====================================================
  // 7. Variants Matrix Generation & Rendering
  // ====================================================

  function sanitizeCode(str) {
    return str.replace(/[^a-zA-Z0-9]/g, '').substr(0, 3).toUpperCase();
  }

  function renderVariantsMatrix() {
    if (!variantsTableBody) return;

    const mode = getCurrentVariantMode();
    if (mode === 'none') {
      variantsTableBody.innerHTML = '';
      updateStockTotals();
      return;
    }

    const baseSku = productSkuInput?.value.trim() || 'PRD-01';
    const baseRegPrice = parseFloat(regularPriceInput?.value) || 0;
    const baseSalePrice = parseFloat(baseSalePriceInput?.value) || 0;
    const hasSale = toggleSale ? toggleSale.checked : false;

    // Header adjustments
    const thColor = document.getElementById('thColor');
    const thSize = document.getElementById('thSize');
    const thSalePrice = document.getElementById('thSalePrice');

    if (thColor) thColor.style.display = (mode === 'size-only') ? 'none' : '';
    if (thSize) thSize.style.display = (mode === 'color-only') ? 'none' : '';
    if (thSalePrice) thSalePrice.style.display = hasSale ? '' : 'none';

    // Build combinations
    let items = [];

    if (mode === 'color-size') {
      if (colors.length === 0 || activeSizes.length === 0) {
        variantsTableBody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted"><i class="mdi mdi-alert-circle-outline me-1"></i> Please configure at least one color and select at least one size.</td></tr>`;
        updateStockTotals();
        return;
      }

      colors.forEach(col => {
        activeSizes.forEach(sz => {
          const key = `${col.name}_${sz}`;
          const defaultSku = `${baseSku}-${sanitizeCode(col.name)}-${sanitizeCode(sz)}`;
          items.push({
            key: key,
            color: col,
            size: sz,
            defaultSku: defaultSku
          });
        });
      });

    } else if (mode === 'color-only') {
      if (colors.length === 0) {
        variantsTableBody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted"><i class="mdi mdi-alert-circle-outline me-1"></i> Please add at least one color.</td></tr>`;
        updateStockTotals();
        return;
      }

      colors.forEach(col => {
        const key = `${col.name}`;
        const defaultSku = `${baseSku}-${sanitizeCode(col.name)}`;
        items.push({
          key: key,
          color: col,
          size: null,
          defaultSku: defaultSku
        });
      });

    } else if (mode === 'size-only') {
      if (activeSizes.length === 0) {
        variantsTableBody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted"><i class="mdi mdi-alert-circle-outline me-1"></i> Please select at least one size.</td></tr>`;
        updateStockTotals();
        return;
      }

      activeSizes.forEach(sz => {
        const key = `${sz}`;
        const defaultSku = `${baseSku}-${sanitizeCode(sz)}`;
        items.push({
          key: key,
          color: null,
          size: sz,
          defaultSku: defaultSku
        });
      });
    }

    // Render HTML rows
    let html = '';

    items.forEach(item => {
      if (!variantsStore[item.key]) {
        // Smart data inheritance from existing combinations without discarding data
        let inherited = null;
        if (mode === 'color-only' && item.color) {
          const matchingKey = Object.keys(variantsStore).find(k => k.startsWith(item.color.name + '_'));
          if (matchingKey) inherited = variantsStore[matchingKey];
        } else if (mode === 'size-only' && item.size) {
          const matchingKey = Object.keys(variantsStore).find(k => k.endsWith('_' + item.size));
          if (matchingKey) inherited = variantsStore[matchingKey];
        } else if (mode === 'color-size') {
          if (item.color && variantsStore[item.color.name]) {
            inherited = variantsStore[item.color.name];
          } else if (item.size && variantsStore[item.size]) {
            inherited = variantsStore[item.size];
          }
        }

        variantsStore[item.key] = {
          sku: (inherited && inherited.sku) ? inherited.sku : item.defaultSku,
          priceOverride: (inherited && inherited.priceOverride !== undefined) ? inherited.priceOverride : '',
          salePriceOverride: (inherited && inherited.salePriceOverride !== undefined) ? inherited.salePriceOverride : '',
          stock: (inherited && inherited.stock !== undefined) ? inherited.stock : 20,
          active: (inherited && inherited.active !== undefined) ? inherited.active : true
        };
      }

      const v = variantsStore[item.key];
      const currentSku = v.sku || item.defaultSku;
      const isUnavailable = !v.active;
      const rowClass = isUnavailable ? 'variant-tr align-middle variant-disabled' : 'variant-tr align-middle';

      // Color cell HTML with assigned/general images preview
      let colorCellHtml = '';
      if (mode !== 'size-only' && item.color) {
        let assignedImages = [];
        if (item.color.selectedImageIds && item.color.selectedImageIds.length > 0) {
          assignedImages = productImages.filter(img => item.color.selectedImageIds.includes(img.id));
        }

        let microThumbs = '';
        let mediaCaption = '';

        if (assignedImages.length > 0) {
          const maxPreview = 3;
          assignedImages.slice(0, maxPreview).forEach(img => {
            microThumbs += `<img src="${img.url}" class="variant-color-thumb me-1" title="${img.name} (Assigned to ${item.color.name})">`;
          });
          const remaining = assignedImages.length - maxPreview;
          if (remaining > 0) {
            microThumbs += `<span class="badge bg-label-secondary fs-9" style="padding: 2px 4px;">+${remaining}</span>`;
          }
          mediaCaption = `<small class="text-success fs-9 fw-medium">${assignedImages.length} color photos</small>`;
        } else {
          // Fallback to general images
          const generalImgs = productImages.slice(0, 2);
          generalImgs.forEach(img => {
            microThumbs += `<img src="${img.url}" class="variant-color-thumb me-1 opacity-75" title="${img.name} (General product image)">`;
          });
          mediaCaption = `<small class="text-muted fs-9 fst-italic">General (${productImages.length})</small>`;
        }

        colorCellHtml = `
          <td>
            <div class="d-flex align-items-center gap-2">
              <span class="rounded-circle border flex-shrink-0" style="width: 20px; height: 20px; background-color: ${item.color.hex}; display: inline-block;"></span>
              <div>
                <span class="fw-medium text-dark fs-8 d-block">${item.color.name}</span>
                <div class="d-flex align-items-center gap-1 mt-1">
                  ${microThumbs}
                  ${mediaCaption}
                </div>
              </div>
            </div>
          </td>
        `;
      }

      // Size cell HTML
      let sizeCellHtml = '';
      if (mode !== 'color-only') {
        sizeCellHtml = `
          <td>
            <span class="badge bg-label-secondary fs-8">${item.size || 'Standard'}</span>
          </td>
        `;
      }

      // Regular Price Cell
      const hasRegOverride = v.priceOverride !== '' && v.priceOverride !== null && !isNaN(parseFloat(v.priceOverride));
      const regPlaceholder = baseRegPrice > 0 ? `Inherit ($${baseRegPrice.toFixed(2)})` : 'Inherit base';
      const regHintHtml = hasRegOverride
        ? `<div class="price-override-tag"><i class="mdi mdi-tag-outline me-1"></i>Override: $${parseFloat(v.priceOverride).toFixed(2)}</div>`
        : `<div class="price-inherited-tag">Inherits $${baseRegPrice.toFixed(2)}</div>`;

      // Sale Price Cell
      const hasSaleOverride = v.salePriceOverride !== '' && v.salePriceOverride !== null && !isNaN(parseFloat(v.salePriceOverride));
      const salePlaceholder = baseSalePrice > 0 ? `Inherit ($${baseSalePrice.toFixed(2)})` : 'Optional';
      const saleHintHtml = hasSaleOverride
        ? `<div class="price-override-tag"><i class="mdi mdi-tag-outline me-1"></i>Override: $${parseFloat(v.salePriceOverride).toFixed(2)}</div>`
        : (baseSalePrice > 0 ? `<div class="price-inherited-tag">Inherits $${baseSalePrice.toFixed(2)}</div>` : `<div class="price-inherited-tag">None</div>`);

      const saleCellHtml = hasSale ? `
        <td>
          <input type="number" step="0.01" class="form-control form-control-sm variant-sale-price-input" data-key="${item.key}" value="${v.salePriceOverride || ''}" placeholder="${salePlaceholder}">
          ${saleHintHtml}
        </td>
      ` : '';

      html += `
        <tr class="${rowClass}" data-key="${item.key}">
          ${colorCellHtml}
          ${sizeCellHtml}
          <td>
            <input type="text" class="form-control form-control-sm variant-sku-input" data-key="${item.key}" value="${currentSku}">
          </td>
          <td>
            <input type="number" step="0.01" class="form-control form-control-sm variant-price-input" data-key="${item.key}" value="${v.priceOverride || ''}" placeholder="${regPlaceholder}">
            ${regHintHtml}
          </td>
          ${saleCellHtml}
          <td>
            <input type="number" min="0" class="form-control form-control-sm variant-stock-input" data-key="${item.key}" value="${v.stock !== undefined ? v.stock : 20}">
          </td>
          <td>
            <div class="d-flex align-items-center gap-1">
              <div class="form-check form-switch mb-0">
                <input class="form-check-input variant-active-toggle" type="checkbox" data-key="${item.key}" ${v.active ? 'checked' : ''} title="Toggle availability">
              </div>
              <span class="badge ${v.active ? 'bg-label-success' : 'bg-label-secondary'} fs-9 variant-status-badge">
                ${v.active ? 'Available' : 'Disabled'}
              </span>
            </div>
          </td>
          <td class="text-end">
            <button type="button" class="btn btn-sm btn-icon text-danger btn-remove-variant" data-key="${item.key}" title="Delete Combination"><i class="mdi mdi-close fs-5"></i></button>
          </td>
        </tr>
      `;
    });

    variantsTableBody.innerHTML = html;
    attachMatrixEvents();
    updateStockTotals();
  }

  function attachMatrixEvents() {
    variantsTableBody.querySelectorAll('.variant-active-toggle').forEach(toggle => {
      toggle.addEventListener('change', function () {
        const key = this.dataset.key;
        if (variantsStore[key]) {
          variantsStore[key].active = this.checked;
        }

        const tr = this.closest('tr');
        const badge = tr.querySelector('.variant-status-badge');
        if (this.checked) {
          tr.classList.remove('variant-disabled');
          if (badge) {
            badge.className = 'badge bg-label-success fs-9 variant-status-badge';
            badge.textContent = 'Available';
          }
        } else {
          tr.classList.add('variant-disabled');
          if (badge) {
            badge.className = 'badge bg-label-secondary fs-9 variant-status-badge';
            badge.textContent = 'Disabled';
          }
        }

        updateStockTotals();
      });
    });

    variantsTableBody.querySelectorAll('.variant-sku-input').forEach(input => {
      input.addEventListener('input', function () {
        const key = this.dataset.key;
        if (variantsStore[key]) {
          variantsStore[key].sku = this.value;
        }
      });
    });

    variantsTableBody.querySelectorAll('.variant-price-input').forEach(input => {
      input.addEventListener('input', function () {
        const key = this.dataset.key;
        if (variantsStore[key]) {
          variantsStore[key].priceOverride = this.value;
        }
        updateVariantPricePlaceholders();
      });
    });

    variantsTableBody.querySelectorAll('.variant-sale-price-input').forEach(input => {
      input.addEventListener('input', function () {
        const key = this.dataset.key;
        if (variantsStore[key]) {
          variantsStore[key].salePriceOverride = this.value;
        }
        updateVariantPricePlaceholders();
      });
    });

    variantsTableBody.querySelectorAll('.variant-stock-input').forEach(input => {
      input.addEventListener('input', function () {
        const key = this.dataset.key;
        if (variantsStore[key]) {
          variantsStore[key].stock = parseInt(this.value, 10) || 0;
        }
        updateStockTotals();
      });
    });

    variantsTableBody.querySelectorAll('.btn-remove-variant').forEach(btn => {
      btn.addEventListener('click', function () {
        const key = this.dataset.key;
        if (variantsStore[key]) {
          delete variantsStore[key];
        }
        this.closest('tr').remove();
        updateStockTotals();
      });
    });
  }

  function updateVariantPricePlaceholders() {
    const baseRegPrice = parseFloat(regularPriceInput?.value) || 0;
    const baseSalePrice = parseFloat(baseSalePriceInput?.value) || 0;

    variantsTableBody.querySelectorAll('tr.variant-tr').forEach(tr => {
      const key = tr.dataset.key;
      const v = variantsStore[key] || {};

      const regInput = tr.querySelector('.variant-price-input');
      if (regInput) {
        regInput.placeholder = baseRegPrice > 0 ? `Inherit ($${baseRegPrice.toFixed(2)})` : 'Inherit base';
        const hasRegOverride = v.priceOverride !== '' && v.priceOverride !== null && !isNaN(parseFloat(v.priceOverride));
        const parent = regInput.parentNode;
        const oldHint = parent.querySelector('.price-override-tag, .price-inherited-tag');
        if (oldHint) oldHint.remove();
        const newHint = document.createElement('div');
        newHint.className = hasRegOverride ? 'price-override-tag' : 'price-inherited-tag';
        newHint.innerHTML = hasRegOverride
          ? `<i class="mdi mdi-tag-outline me-1"></i>Override: $${parseFloat(v.priceOverride).toFixed(2)}`
          : `Inherits $${baseRegPrice.toFixed(2)}`;
        parent.appendChild(newHint);
      }

      const saleInput = tr.querySelector('.variant-sale-price-input');
      if (saleInput) {
        saleInput.placeholder = baseSalePrice > 0 ? `Inherit ($${baseSalePrice.toFixed(2)})` : 'Optional';
        const hasSaleOverride = v.salePriceOverride !== '' && v.salePriceOverride !== null && !isNaN(parseFloat(v.salePriceOverride));
        const parent = saleInput.parentNode;
        const oldHint = parent.querySelector('.price-override-tag, .price-inherited-tag');
        if (oldHint) oldHint.remove();
        const newHint = document.createElement('div');
        newHint.className = hasSaleOverride ? 'price-override-tag' : 'price-inherited-tag';
        newHint.innerHTML = hasSaleOverride
          ? `<i class="mdi mdi-tag-outline me-1"></i>Override: $${parseFloat(v.salePriceOverride).toFixed(2)}`
          : (baseSalePrice > 0 ? `Inherits $${baseSalePrice.toFixed(2)}` : 'None');
        parent.appendChild(newHint);
      }
    });
  }

  function updateStockTotals() {
    const mode = getCurrentVariantMode();

    if (mode === 'none') {
      const baseStock = baseStockInput ? parseInt(baseStockInput.value, 10) || 0 : 0;
      if (sidebarTotalStock) sidebarTotalStock.textContent = baseStock;
      if (activeVariantsCount) activeVariantsCount.textContent = '0';
      if (totalVariantStockBadge) totalVariantStockBadge.style.display = 'none';
      return;
    }

    let totalStock = 0;
    let activeCount = 0;

    variantsTableBody.querySelectorAll('tr.variant-tr').forEach(tr => {
      const key = tr.dataset.key;
      const v = variantsStore[key];
      if (v && v.active) {
        totalStock += (v.stock || 0);
        activeCount++;
      }
    });

    if (totalVariantStockCount) totalVariantStockCount.textContent = totalStock;
    if (totalVariantStockBadge) totalVariantStockBadge.style.display = 'inline-block';
    if (sidebarTotalStock) sidebarTotalStock.textContent = totalStock;
    if (activeVariantsCount) activeVariantsCount.textContent = activeCount;
  }

  // Matrix Action Buttons
  if (btnGenerateVariants) {
    btnGenerateVariants.addEventListener('click', renderVariantsMatrix);
  }

  if (btnEnableAllVariants) {
    btnEnableAllVariants.addEventListener('click', function () {
      Object.keys(variantsStore).forEach(k => {
        variantsStore[k].active = true;
      });
      renderVariantsMatrix();
    });
  }

  if (btnDisableAllVariants) {
    btnDisableAllVariants.addEventListener('click', function () {
      Object.keys(variantsStore).forEach(k => {
        variantsStore[k].active = false;
      });
      renderVariantsMatrix();
    });
  }

  // Base Stock input changes
  if (baseStockInput) {
    baseStockInput.addEventListener('input', function () {
      if (getCurrentVariantMode() === 'none') {
        updateStockTotals();
      }
    });
  }

  // Variant scenario change handler with seamless data preservation
  function handleVariantScenarioChange(radio) {
    if (!radio.checked) return;
    const targetMode = radio.value;
    if (targetMode === activeVariantMode) return;

    activeVariantMode = targetMode;
    syncVariantModeUI();
  }

  // Toggle Has Variants handler
  function handleToggleVariantsChange(checkbox) {
    const isNowChecked = checkbox.checked;
    if (isNowChecked === variantsEnabled) return;

    variantsEnabled = isNowChecked;
    syncVariantModeUI();
  }

  // Feature switches
  if (toggleVariants) {
    toggleVariants.addEventListener('change', function () {
      handleToggleVariantsChange(this);
    });
  }

  if (variantModeRadios) {
    variantModeRadios.forEach(radio => {
      radio.addEventListener('change', function () {
        handleVariantScenarioChange(this);
      });
    });
  }

  if (toggleStock && cardStock) {
    toggleStock.addEventListener('change', function () {
      cardStock.style.display = this.checked ? 'block' : 'none';
    });
  }

  if (togglePreorder && cardPreorder) {
    togglePreorder.addEventListener('change', function () {
      cardPreorder.style.display = this.checked ? 'block' : 'none';
    });
  }

  if (toggleSizeGuide && cardSizeGuide) {
    toggleSizeGuide.addEventListener('change', function () {
      cardSizeGuide.style.display = this.checked ? 'block' : 'none';
    });
  }

  if (toggleSale) {
    toggleSale.addEventListener('change', function () {
      salePriceFields.forEach(el => {
        el.style.display = this.checked ? 'block' : 'none';
      });
      renderVariantsMatrix();
    });
  }

  if (regularPriceInput) {
    regularPriceInput.addEventListener('input', updateVariantPricePlaceholders);
  }
  if (baseSalePriceInput) {
    baseSalePriceInput.addEventListener('input', updateVariantPricePlaceholders);
  }
  if (productSkuInput) {
    productSkuInput.addEventListener('input', renderVariantsMatrix);
  }

  // ====================================================
  // 8. Catalog Size Guide Integration
  // ====================================================
  const productSizeGuideSelect = document.getElementById('productSizeGuideSelect');
  const btnClearSizeGuide = document.getElementById('btnClearSizeGuide');
  const btnOpenGuideModalPreview = document.getElementById('btnOpenGuideModalPreview');
  const productSizeGuidePreviewContainer = document.getElementById('productSizeGuidePreviewContainer');
  const assignedGuideBadge = document.getElementById('assignedGuideBadge');
  const guideUnitIndicator = document.getElementById('guideUnitIndicator');

  // Customer Preview Modal Elements
  const previewModalGuideTitle = document.getElementById('previewModalGuideTitle');
  const previewModalSubtitle = document.getElementById('previewModalSubtitle');
  const previewModalBody = document.getElementById('previewModalBody');

  let selectedSizeGuideId = '';

  function populateSizeGuideDropdown() {
    if (!productSizeGuideSelect) return;
    if (typeof window.SizeGuideStore === 'undefined') return;

    const guides = window.SizeGuideStore.getAll();
    productSizeGuideSelect.innerHTML = '<option value="">-- No Size Guide Assigned --</option>';

    guides.forEach(g => {
      const opt = document.createElement('option');
      opt.value = g.id;
      const unit = g.attributes && g.attributes[0] ? g.attributes[0].unit : 'cm';
      const statusSuffix = g.status !== 'Active' ? ` [${g.status}]` : '';
      opt.textContent = `${g.title} (${g.id}) • ${unit.toUpperCase()}${statusSuffix}`;
      productSizeGuideSelect.appendChild(opt);
    });

    // Default to SG-1003 (Dining Tables) on demo product pages if available
    const defaultGuide = guides.find(g => g.id === 'SG-1003');
    if (defaultGuide) {
      selectedSizeGuideId = defaultGuide.id;
      productSizeGuideSelect.value = defaultGuide.id;
      renderAssignedSizeGuide(defaultGuide.id);
    } else {
      renderAssignedSizeGuide('');
    }
  }

  function renderAssignedSizeGuide(guideId) {
    selectedSizeGuideId = guideId || '';
    if (!productSizeGuidePreviewContainer) return;

    if (!guideId || typeof window.SizeGuideStore === 'undefined') {
      if (assignedGuideBadge) {
        assignedGuideBadge.className = 'badge bg-label-secondary fs-9';
        assignedGuideBadge.textContent = 'None Assigned';
      }
      if (guideUnitIndicator) guideUnitIndicator.style.display = 'none';
      if (btnOpenGuideModalPreview) btnOpenGuideModalPreview.disabled = true;

      productSizeGuidePreviewContainer.innerHTML = `
        <div class="text-center py-4 rounded border border-dashed" style="background-color: var(--beige-bg-alt); border-color: var(--beige-border-subtle) !important;">
          <i class="mdi mdi-ruler-square fs-2 text-muted mb-2 d-block"></i>
          <div class="fw-semibold text-dark fs-8 mb-1">No Size Guide Assigned</div>
          <p class="text-muted fs-9 mb-0">Select an existing catalog size guide above to display measurements to your customers.</p>
        </div>
      `;
      return;
    }

    // Retrieve public guide: strictly strips adminDetails
    const publicGuide = window.SizeGuideStore.getPublicGuide(guideId, true);
    if (!publicGuide) {
      productSizeGuidePreviewContainer.innerHTML = `
        <div class="alert alert-warning py-2 px-3 fs-8 mb-0">
          <i class="mdi mdi-alert-circle-outline me-1"></i> Selected size guide was not found in the catalog.
        </div>
      `;
      return;
    }

    const primaryUnit = (publicGuide.attributes && publicGuide.attributes[0]) ? publicGuide.attributes[0].unit : 'cm';

    if (assignedGuideBadge) {
      assignedGuideBadge.className = publicGuide.status === 'Active' ? 'badge bg-label-success fs-9' : 'badge bg-label-secondary fs-9';
      assignedGuideBadge.textContent = `${publicGuide.title} (${publicGuide.status})`;
    }

    if (guideUnitIndicator) {
      guideUnitIndicator.textContent = `Unit: ${primaryUnit.toUpperCase()}`;
      guideUnitIndicator.style.display = 'inline-block';
    }

    if (btnOpenGuideModalPreview) btnOpenGuideModalPreview.disabled = false;

    // Build attributes columns
    const attributes = publicGuide.attributes || [];
    const sizes = publicGuide.sizes || [];

    let tableHeadersHTML = '<th style="min-width: 110px;">Size</th>';
    attributes.forEach(attr => {
      tableHeadersHTML += `<th>${attr.name} <span class="badge bg-label-secondary fs-9 ms-1">${attr.unit}</span></th>`;
    });

    let tableRowsHTML = '';
    if (sizes.length === 0) {
      tableRowsHTML = `<tr><td colspan="${attributes.length + 1}" class="text-center py-3 text-muted">No measurement sizes defined.</td></tr>`;
    } else {
      sizes.forEach(s => {
        tableRowsHTML += `<tr><td class="fw-bold text-dark">${s.size}</td>`;
        attributes.forEach(attr => {
          const val = (s.measurements && s.measurements[attr.key]) ? s.measurements[attr.key] : '—';
          tableRowsHTML += `<td>${val}</td>`;
        });
        tableRowsHTML += `</tr>`;
      });
    }

    const customerNotesHTML = publicGuide.customerDetails ? `
      <div class="alert alert-secondary py-2 px-3 fs-9 mb-3 d-flex align-items-start gap-2" style="background-color: var(--beige-bg-alt); border: 1px solid var(--beige-border-subtle); color: var(--beige-text-primary);">
        <i class="mdi mdi-information-outline text-primary fs-6 mt-0"></i>
        <div>
          <strong>Customer Notes:</strong> ${publicGuide.customerDetails}
        </div>
      </div>
    ` : '';

    productSizeGuidePreviewContainer.innerHTML = `
      ${customerNotesHTML}
      <div class="table-responsive rounded border mb-2" style="border-color: var(--beige-border-subtle) !important;">
        <table class="table align-middle fs-8 mb-0 table-striped">
          <thead class="table-light">
            <tr>${tableHeadersHTML}</tr>
          </thead>
          <tbody>
            ${tableRowsHTML}
          </tbody>
        </table>
      </div>
      <div class="d-flex align-items-center justify-content-between pt-2">
        <small class="text-muted fs-9"><i class="mdi mdi-tag-outline me-1"></i> Key: <code>${publicGuide.key}</code></small>
        <a href="size-guide-details.html?id=${publicGuide.id}" target="_blank" class="fs-9 text-primary hover-underline">
          <i class="mdi mdi-open-in-new me-1"></i> View Full Guide Specifications
        </a>
      </div>
    `;
  }

  function openCustomerGuideModal() {
    if (!selectedSizeGuideId || typeof window.SizeGuideStore === 'undefined') return;
    const publicGuide = window.SizeGuideStore.getPublicGuide(selectedSizeGuideId, true);
    if (!publicGuide) return;

    if (previewModalGuideTitle) {
      previewModalGuideTitle.textContent = publicGuide.title;
    }
    if (previewModalSubtitle) {
      previewModalSubtitle.textContent = `${publicGuide.category || 'Apparel & Goods'} • Storefront Customer View`;
    }

    if (previewModalBody) {
      const attributes = publicGuide.attributes || [];
      const sizes = publicGuide.sizes || [];

      let thHTML = '<th style="min-width: 100px;">Size</th>';
      attributes.forEach(attr => {
        thHTML += `<th>${attr.name} (${attr.unit})</th>`;
      });

      let trHTML = '';
      sizes.forEach(s => {
        trHTML += `<tr><td class="fw-bold text-dark">${s.size}</td>`;
        attributes.forEach(attr => {
          const val = (s.measurements && s.measurements[attr.key]) ? s.measurements[attr.key] : '—';
          trHTML += `<td>${val}</td>`;
        });
        trHTML += `</tr>`;
      });

      previewModalBody.innerHTML = `
        <div class="alert alert-light border d-flex align-items-center gap-2 mb-3 py-2 px-3 fs-8" style="background-color: var(--beige-bg-alt); border-color: var(--beige-border-subtle) !important;">
          <i class="mdi mdi-shield-check text-success fs-5"></i>
          <div>
            <strong>Storefront Safe:</strong> Only customer measurements and guidance are displayed. All admin-only details remain strictly confidential.
          </div>
        </div>

        ${publicGuide.customerDetails ? `
          <div class="mb-3">
            <h6 class="fs-8 fw-bold text-dark mb-1"><i class="mdi mdi-text-box-outline text-primary me-1"></i> Fit & Measurement Guidance</h6>
            <p class="text-muted fs-8 mb-0">${publicGuide.customerDetails}</p>
          </div>
        ` : ''}

        <h6 class="fs-8 fw-bold text-dark mb-2"><i class="mdi mdi-table me-1 text-primary"></i> Measurement Chart (${attributes[0]?.unit || 'cm'})</h6>
        <div class="table-responsive rounded border mb-3">
          <table class="table table-sm align-middle fs-8 mb-0 table-striped">
            <thead class="table-light">
              <tr>${thHTML}</tr>
            </thead>
            <tbody>
              ${trHTML}
            </tbody>
          </table>
        </div>

        <div class="p-3 rounded" style="background-color: var(--beige-bg-alt); border: 1px solid var(--beige-border-subtle);">
          <div class="fw-bold fs-8 text-dark mb-1"><i class="mdi mdi-help-circle-outline text-primary me-1"></i> How to Measure for Best Fit</div>
          <p class="text-muted fs-9 mb-0">For best accuracy, measure using a soft tape measure while standing naturally. Compare your body measurements with the chart above to find your recommended size.</p>
        </div>
      `;
    }

    const modalEl = document.getElementById('modalCustomerSizeGuide');
    if (modalEl && typeof bootstrap !== 'undefined') {
      const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      modal.show();
    }
  }

  if (productSizeGuideSelect) {
    productSizeGuideSelect.addEventListener('change', function () {
      renderAssignedSizeGuide(this.value);
    });
  }

  if (btnClearSizeGuide) {
    btnClearSizeGuide.addEventListener('click', function () {
      if (productSizeGuideSelect) {
        productSizeGuideSelect.value = '';
        renderAssignedSizeGuide('');
      }
    });
  }

  if (btnOpenGuideModalPreview) {
    btnOpenGuideModalPreview.addEventListener('click', openCustomerGuideModal);
  }

  // Populate dropdown on startup
  populateSizeGuideDropdown();

  // ====================================================
  // 9. Custom Attributes Builder
  // ====================================================
  const btnAddAttribute = document.getElementById('btnAddAttribute');
  const attributesContainer = document.getElementById('attributesContainer');

  if (btnAddAttribute && attributesContainer) {
    btnAddAttribute.addEventListener('click', function () {
      const row = document.createElement('div');
      row.className = 'row g-2 mb-2 align-items-center attribute-row';
      row.innerHTML = `
        <div class="col-5">
          <input type="text" class="form-control form-control-sm" placeholder="Key (e.g. Finish)">
        </div>
        <div class="col-6">
          <input type="text" class="form-control form-control-sm" placeholder="Value (e.g. Matte Wax Finish)">
        </div>
        <div class="col-1 text-end">
          <button type="button" class="btn btn-sm btn-icon text-danger btn-remove-attr"><i class="mdi mdi-trash-can-outline fs-5"></i></button>
        </div>
      `;
      attributesContainer.appendChild(row);
      row.querySelector('.btn-remove-attr').addEventListener('click', function () {
        row.remove();
      });
    });

    document.querySelectorAll('.btn-remove-attr').forEach(btn => {
      btn.addEventListener('click', function () {
        this.closest('.attribute-row').remove();
      });
    });
  }

  // ====================================================
  // 10. Save / Submit Handler
  // ====================================================
  const productForm = document.getElementById('productForm');
  if (productForm) {
    productForm.addEventListener('submit', function (e) {
      e.preventDefault();
      const mode = getCurrentVariantMode();
      let msg = isEditPage ? 'Product updated successfully!' : 'Product created successfully!';
      msg += `\nVariant Configuration: ${mode.toUpperCase()}`;
      msg += `\nTotal Images Uploaded: ${productImages.length} (1 Main + ${productImages.length - 1} Additional)`;
      if (mode !== 'none') {
        const activeVariants = Object.values(variantsStore).filter(v => v.active).length;
        msg += `\nActive Sellable Variants: ${activeVariants}`;
      }
      if (selectedSizeGuideId) {
        msg += `\nAssigned Size Guide: ${selectedSizeGuideId}`;
      }
      alert(msg);
      window.location.href = 'products.html';
    });
  }

  // ====================================================
  // 11. Initialization
  // ====================================================
  renderMainImage();
  renderAdditionalImages();
  renderColorsList();
  attachSizeEvents();
  syncVariantModeUI();

  // Expose state API for programmatic testing
  window.__CATALOG_APP__ = {
    get productImages() { return productImages; },
    get colors() { return colors; },
    get activeSizes() { return activeSizes; },
    get variantsStore() { return variantsStore; },
    get activeVariantMode() { return activeVariantMode; },
    set activeVariantMode(val) { activeVariantMode = val; },
    get variantsEnabled() { return variantsEnabled; },
    set variantsEnabled(val) { variantsEnabled = val; },
    get selectedSizeGuideId() { return selectedSizeGuideId; },
    set selectedSizeGuideId(val) { 
      selectedSizeGuideId = val;
      if (productSizeGuideSelect) productSizeGuideSelect.value = val;
      renderAssignedSizeGuide(val);
    },
    populateSizeGuideDropdown,
    renderAssignedSizeGuide,
    openCustomerGuideModal,
    handleVariantScenarioChange,
    handleToggleVariantsChange,
    renderMainImage,
    renderAdditionalImages,
    renderColorsList,
    renderVariantsMatrix,
    removeProductImage,
    getCurrentVariantMode,
    syncVariantModeUI,
    updateStockTotals
  };

});
