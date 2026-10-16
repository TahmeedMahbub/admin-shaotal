/**
 * size-guide-store.js
 * Centralized data store and CRUD logic for Aus Ecom Size Guide Module:
 * - Dynamic Measurement Attributes (name, key, unit, sortOrder)
 * - Dynamic Sizes & Measurements Matrix
 * - Title, Unique Key, Customer Details, Admin-Only Private Notes, Status
 * - Storefront-safe public sanitizer (strictly hides admin-only details)
 * - Persistent state via localStorage with rich seed data fallbacks
 */

const SG_STORAGE_KEY = 'aus_ecom_size_guides';

// Seed Data with different measurement structures
const SG_SEED_DATA = [
  {
    id: "SG-1001",
    title: "Men's T-Shirts & Tops Size Chart",
    key: "mens-tshirt-standard",
    category: "Apparel — Tops",
    customerDetails: "All measurements are taken flat in centimetres (cm). Our apparel is tailored for a relaxed Australian fit. If you prefer a tighter fit, we recommend sizing down.",
    adminDetails: "Pattern grading base: Size M. Supplier tolerance: ±1.2cm on chest, ±1.0cm on length. Pre-shrunk 100% organic combed cotton specs from Dhaka Artisan Mill.",
    status: "Active",
    createdAt: "2026-09-15",
    updatedAt: "2026-10-10",
    assignedProductCount: 14,
    attributes: [
      { name: "Chest Width", key: "chest", unit: "cm", sortOrder: 1 },
      { name: "Body Length", key: "length", unit: "cm", sortOrder: 2 },
      { name: "Shoulder Width", key: "shoulder", unit: "cm", sortOrder: 3 },
      { name: "Sleeve Length", key: "sleeve", unit: "cm", sortOrder: 4 }
    ],
    sizes: [
      {
        size: "S",
        measurements: { chest: "96 - 100", length: "70", shoulder: "44", sleeve: "21" }
      },
      {
        size: "M",
        measurements: { chest: "101 - 106", length: "72", shoulder: "46", sleeve: "22" }
      },
      {
        size: "L",
        measurements: { chest: "107 - 112", length: "74", shoulder: "48", sleeve: "23" }
      },
      {
        size: "XL",
        measurements: { chest: "113 - 118", length: "76", shoulder: "50", sleeve: "24" }
      },
      {
        size: "2XL",
        measurements: { chest: "119 - 124", length: "78", shoulder: "52", sleeve: "25" }
      }
    ]
  },
  {
    id: "SG-1002",
    title: "Men's Casual Pants & Trousers Guide",
    key: "pants-waist-length",
    category: "Apparel — Bottoms",
    customerDetails: "Measurements indicate waist circumferences in inches and standard inner inseam lengths. Handcrafted with semi-elastic waistband for ease of wear.",
    adminDetails: "Cotton twill and linen blends. Shrinkage factor of 2.5% accounted for in raw cut. Factory wash standard #40 applied before final QA packaging.",
    status: "Active",
    createdAt: "2026-09-20",
    updatedAt: "2026-10-08",
    assignedProductCount: 8,
    attributes: [
      { name: "Waist Circumference", key: "waist", unit: "inch", sortOrder: 1 },
      { name: "Inseam Length", key: "inseam", unit: "inch", sortOrder: 2 },
      { name: "Hip Circumference", key: "hip", unit: "inch", sortOrder: 3 },
      { name: "Thigh Width", key: "thigh", unit: "inch", sortOrder: 4 }
    ],
    sizes: [
      {
        size: "30",
        measurements: { waist: "30 - 31", inseam: "31", hip: "38", thigh: "23" }
      },
      {
        size: "32",
        measurements: { waist: "32 - 33", inseam: "32", hip: "40", thigh: "24" }
      },
      {
        size: "34",
        measurements: { waist: "34 - 35", inseam: "32", hip: "42", thigh: "25" }
      },
      {
        size: "36",
        measurements: { waist: "36 - 37", inseam: "33", hip: "44", thigh: "26" }
      },
      {
        size: "38",
        measurements: { waist: "38 - 39", inseam: "33", hip: "46", thigh: "27" }
      }
    ]
  },
  {
    id: "SG-1003",
    title: "Dining Tables & Living Dimensions",
    key: "dining-table-size",
    category: "Furniture — Living & Dining",
    customerDetails: "Table dimensions are stated as Length x Width x Height in centimetres. Please ensure adequate clearance (at least 90cm) around the table for chair movement.",
    adminDetails: "Solid White Oak and Teak timber. Flat-pack export carton dimensions: Length + 15cm, Width + 12cm, Height 22cm. Weight exceeds 45kg for 8-seater (requires two-man Australian courier).",
    status: "Active",
    createdAt: "2026-09-28",
    updatedAt: "2026-10-12",
    assignedProductCount: 5,
    attributes: [
      { name: "Table Length", key: "length", unit: "cm", sortOrder: 1 },
      { name: "Table Width", key: "width", unit: "cm", sortOrder: 2 },
      { name: "Table Height", key: "height", unit: "cm", sortOrder: 3 },
      { name: "Seating Capacity", key: "capacity", unit: "cm", sortOrder: 4 }
    ],
    sizes: [
      {
        size: "4-Seater",
        measurements: { length: "140", width: "85", height: "76", capacity: "4 Persons" }
      },
      {
        size: "6-Seater",
        measurements: { length: "180", width: "90", height: "76", capacity: "6 Persons" }
      },
      {
        size: "8-Seater",
        measurements: { length: "220", width: "100", height: "76", capacity: "8 Persons" }
      },
      {
        size: "10-Seater",
        measurements: { length: "260", width: "105", height: "76", capacity: "10 Persons" }
      }
    ]
  },
  {
    id: "SG-1004",
    title: "Women's Heritage Tops & Tunics",
    key: "womens-heritage-tops",
    category: "Apparel — Women",
    customerDetails: "Artisanal hand-embroidered tops and relaxed tunics. Measure bust around the fullest part of your chest. Designed with breathable cotton-linen weave.",
    adminDetails: "Vendor: Nakshi Guild. Embellished bib requires gentle hand-block dye setting. Pattern grade increment: 4cm bust per step.",
    status: "Draft",
    createdAt: "2026-10-05",
    updatedAt: "2026-10-05",
    assignedProductCount: 0,
    attributes: [
      { name: "Bust Width", key: "bust", unit: "cm", sortOrder: 1 },
      { name: "Waist", key: "waist", unit: "cm", sortOrder: 2 },
      { name: "Garment Length", key: "length", unit: "cm", sortOrder: 3 }
    ],
    sizes: [
      {
        size: "XS",
        measurements: { bust: "84 - 88", waist: "68 - 72", length: "66" }
      },
      {
        size: "S",
        measurements: { bust: "89 - 93", waist: "73 - 77", length: "68" }
      },
      {
        size: "M",
        measurements: { bust: "94 - 98", waist: "78 - 82", length: "70" }
      },
      {
        size: "L",
        measurements: { bust: "99 - 104", waist: "83 - 87", length: "72" }
      }
    ]
  }
];

class SizeGuideStoreManager {
  constructor() {
    this.init();
  }

  init() {
    try {
      if (!localStorage.getItem(SG_STORAGE_KEY)) {
        localStorage.setItem(SG_STORAGE_KEY, JSON.stringify(SG_SEED_DATA));
      }
    } catch (e) {
      console.warn('LocalStorage not available for size guide store. Using memory seed.');
    }
  }

  getAll() {
    try {
      const data = localStorage.getItem(SG_STORAGE_KEY);
      return data ? JSON.parse(data) : SG_SEED_DATA;
    } catch (e) {
      return SG_SEED_DATA;
    }
  }

  saveAll(items) {
    try {
      localStorage.setItem(SG_STORAGE_KEY, JSON.stringify(items));
      return true;
    } catch (e) {
      console.error('Failed to save size guides to localStorage:', e);
      return false;
    }
  }

  getById(id) {
    if (!id) return null;
    const items = this.getAll();
    return items.find(item => item.id.toLowerCase() === id.toString().toLowerCase()) || null;
  }

  getByKey(key) {
    if (!key) return null;
    const items = this.getAll();
    return items.find(item => item.key.toLowerCase() === key.toString().toLowerCase()) || null;
  }

  create(guideData) {
    const items = this.getAll();
    
    // Generate new unique ID
    let maxNum = 1000;
    items.forEach(item => {
      const match = item.id.match(/SG-(\d+)/i);
      if (match) {
        const num = parseInt(match[1], 10);
        if (num > maxNum) maxNum = num;
      }
    });
    const newId = `SG-${maxNum + 1}`;

    // Normalize unique key
    let uniqueKey = (guideData.key || guideData.title || `guide-${newId}`)
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9_-]/g, '-')
      .replace(/-+/g, '-');

    // Ensure key uniqueness
    let counter = 1;
    let testKey = uniqueKey;
    while (items.some(item => item.key === testKey)) {
      testKey = `${uniqueKey}-${counter}`;
      counter++;
    }
    uniqueKey = testKey;

    const now = new Date().toISOString().split('T')[0];

    const newGuide = {
      id: newId,
      title: guideData.title ? guideData.title.trim() : 'Untitled Size Guide',
      key: uniqueKey,
      category: guideData.category || 'General',
      customerDetails: guideData.customerDetails || '',
      adminDetails: guideData.adminDetails || '',
      status: guideData.status || 'Active',
      createdAt: now,
      updatedAt: now,
      assignedProductCount: guideData.assignedProductCount || 0,
      attributes: Array.isArray(guideData.attributes) ? guideData.attributes : [],
      sizes: Array.isArray(guideData.sizes) ? guideData.sizes : []
    };

    items.unshift(newGuide);
    this.saveAll(items);
    return newGuide;
  }

  update(id, guideData) {
    const items = this.getAll();
    const index = items.findIndex(item => item.id.toLowerCase() === id.toString().toLowerCase());
    if (index === -1) return null;

    const existing = items[index];

    // If key is being modified, ensure uniqueness
    let newKey = existing.key;
    if (guideData.key && guideData.key !== existing.key) {
      let candidateKey = guideData.key
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9_-]/g, '-')
        .replace(/-+/g, '-');
      
      let counter = 1;
      let testKey = candidateKey;
      while (items.some(item => item.key === testKey && item.id !== id)) {
        testKey = `${candidateKey}-${counter}`;
        counter++;
      }
      newKey = testKey;
    }

    const updatedGuide = {
      ...existing,
      ...guideData,
      id: existing.id, // Immutable ID
      key: newKey,
      updatedAt: new Date().toISOString().split('T')[0]
    };

    items[index] = updatedGuide;
    this.saveAll(items);
    return updatedGuide;
  }

  delete(id) {
    const items = this.getAll();
    const filtered = items.filter(item => item.id.toLowerCase() !== id.toString().toLowerCase());
    if (filtered.length === items.length) return false;
    this.saveAll(filtered);
    return true;
  }

  toggleStatus(id) {
    const guide = this.getById(id);
    if (!guide) return null;
    const newStatus = guide.status === 'Active' ? 'Inactive' : 'Active';
    return this.update(id, { status: newStatus });
  }

  /**
   * STOREFRONT SANITIZER
   * Returns ONLY customer-safe data.
   * STRICTLY strips adminDetails and internal metadata.
   */
  getPublicGuide(keyOrId, allowDraft = false) {
    const guide = this.getByKey(keyOrId) || this.getById(keyOrId);
    if (!guide) return null;
    if (!allowDraft && guide.status !== 'Active') return null;

    return {
      id: guide.id,
      title: guide.title,
      key: guide.key,
      category: guide.category,
      customerDetails: guide.customerDetails,
      status: guide.status,
      attributes: (guide.attributes || []).map(attr => ({
        name: attr.name,
        key: attr.key,
        unit: attr.unit || 'cm',
        sortOrder: attr.sortOrder || 0
      })).sort((a, b) => (a.sortOrder || 0) - (b.sortOrder || 0)),
      sizes: (guide.sizes || []).map(s => ({
        size: s.size,
        measurements: { ...(s.measurements || {}) }
      }))
    };
  }

  /**
   * Reset store to initial seed data
   */
  resetToSeed() {
    this.saveAll(SG_SEED_DATA);
    return SG_SEED_DATA;
  }
}

// Global Export
window.SizeGuideStore = new SizeGuideStoreManager();
