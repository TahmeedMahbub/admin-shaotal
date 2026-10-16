/**
 * marketing-content-store.js
 * Centralized data store and CRUD logic for Marketing & Content Modules:
 * - Offers / Discounts
 * - Coupons
 * - Banners
 * - Site Content (Predefined Locations)
 * - Standalone Pages
 * - Navigation Items
 * - Homepage Config
 * 
 * Uses localStorage to maintain persistent state.
 */

const MC_STORAGE_KEYS = {
  OFFERS: 'aus_ecom_offers',
  COUPONS: 'aus_ecom_coupons',
  BANNERS: 'aus_ecom_banners',
  SITE_CONTENT: 'aus_ecom_site_content',
  PAGES: 'aus_ecom_pages',
  NAVIGATION: 'aus_ecom_navigation',
  HOMEPAGE: 'aus_ecom_homepage'
};

const MC_SEED_DATA = {
  offers: [
    {
      id: "OFF-1001",
      name: "Spring Tech Clearance Sale",
      description: "Get up to 20% off selected laptops, desktop computers, and high performance accessories.",
      discountType: "Percentage",
      discountValue: 20,
      applicableProducts: ["Apple iMac 24\"", "Dell Inspiron 15 3000"],
      applicableCategories: ["Electronics", "Computers"],
      startDate: "2026-10-01",
      endDate: "2026-10-31",
      status: "Active"
    },
    {
      id: "OFF-1002",
      name: "Executive Ergonomic Chair Special",
      description: "Flat $50 instant discount on executive ergonomic chairs for remote work.",
      discountType: "Fixed Amount",
      discountValue: 50,
      applicableProducts: ["Ergonomic Leather Executive Chair"],
      applicableCategories: ["Furniture"],
      startDate: "2026-10-05",
      endDate: "2026-11-15",
      status: "Active"
    },
    {
      id: "OFF-1003",
      name: "Cyber Gaming Madness",
      description: "15% discount on gaming consoles and wireless controllers.",
      discountType: "Percentage",
      discountValue: 15,
      applicableProducts: ["Sony PlayStation 5", "Xbox Series X", "Nintendo Switch"],
      applicableCategories: ["Gaming"],
      startDate: "2026-11-20",
      endDate: "2026-11-30",
      status: "Scheduled"
    },
    {
      id: "OFF-1004",
      name: "Winter Furniture Flash Sale",
      description: "Winter end-of-season furniture discount event.",
      discountType: "Fixed Amount",
      discountValue: 30,
      applicableProducts: [],
      applicableCategories: ["Furniture"],
      startDate: "2026-08-01",
      endDate: "2026-08-31",
      status: "Expired"
    }
  ],

  coupons: [
    {
      id: "CPN-2001",
      code: "WELCOME10",
      description: "10% off welcome discount for first-time online shoppers",
      discountType: "Percentage",
      discountValue: 10,
      minOrderValue: 50.00,
      maxDiscount: 30.00,
      usageLimit: 500,
      usedCount: 142,
      perCustomerLimit: 1,
      startDate: "2026-01-01",
      endDate: "2026-12-31",
      status: "Active"
    },
    {
      id: "CPN-2002",
      code: "SAVE50NOW",
      description: "Flat $50 off on total cart orders over $300",
      discountType: "Fixed Amount",
      discountValue: 50,
      minOrderValue: 300.00,
      maxDiscount: 50.00,
      usageLimit: 200,
      usedCount: 89,
      perCustomerLimit: 2,
      startDate: "2026-10-01",
      endDate: "2026-10-31",
      status: "Active"
    },
    {
      id: "CPN-2003",
      code: "BLACKFRIDAY30",
      description: "Black Friday 30% storewide discount code",
      discountType: "Percentage",
      discountValue: 30,
      minOrderValue: 100.00,
      maxDiscount: 150.00,
      usageLimit: 1000,
      usedCount: 0,
      perCustomerLimit: 1,
      startDate: "2026-11-27",
      endDate: "2026-11-30",
      status: "Scheduled"
    },
    {
      id: "CPN-2004",
      code: "AUTUMN2026",
      description: "Autumn seasonal promo discount voucher",
      discountType: "Percentage",
      discountValue: 15,
      minOrderValue: 75.00,
      maxDiscount: 45.00,
      usageLimit: 150,
      usedCount: 150,
      perCustomerLimit: 1,
      startDate: "2026-09-01",
      endDate: "2026-09-30",
      status: "Expired"
    }
  ],

  banners: [
    {
      id: "BAN-3001",
      title: "Upgrade Your Home Office Setup",
      image: "https://images.unsplash.com/photo-1593062096033-9a26b09da705?w=1000&auto=format&fit=crop&q=60",
      placement: "Homepage Hero Slider",
      destinationType: "Category",
      destinationValue: "Furniture & Chairs",
      buttonText: "Shop Ergonomic Chairs",
      shortDesc: "Premium executive leather chairs with free shipping across Australia.",
      startDate: "2026-10-01",
      endDate: "2026-12-31",
      status: "Active"
    },
    {
      id: "BAN-3002",
      title: "Next-Gen Gaming Consoles In Stock",
      image: "https://images.unsplash.com/photo-1606813907291-d86efa9b94db?w=1000&auto=format&fit=crop&q=60",
      placement: "Homepage Hero Slider",
      destinationType: "Product",
      destinationValue: "Sony PlayStation 5 Console",
      buttonText: "Buy Now",
      shortDesc: "Experience ultra-high-speed SSD loading & 4K gaming graphics.",
      startDate: "2026-10-05",
      endDate: "2026-11-15",
      status: "Active"
    },
    {
      id: "BAN-3003",
      title: "Spring Tech Super Sale - Up to 20% Off",
      image: "https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=1000&auto=format&fit=crop&q=60",
      placement: "Category Header Banner",
      destinationType: "Offer",
      destinationValue: "Spring Tech Clearance Sale",
      buttonText: "Explore Offers",
      shortDesc: "Limited time savings on Retina iMacs and high performance laptops.",
      startDate: "2026-10-01",
      endDate: "2026-10-31",
      status: "Active"
    },
    {
      id: "BAN-3004",
      title: "Subscribe & Save 10% On Your Order",
      image: "https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=1000&auto=format&fit=crop&q=60",
      placement: "Sidebar Promo Card",
      destinationType: "Custom URL",
      destinationValue: "https://ausecom.com.au/newsletter",
      buttonText: "Join Newsletter",
      shortDesc: "Sign up today for exclusive promo drops.",
      startDate: "2026-09-15",
      endDate: "2026-12-31",
      status: "Active"
    }
  ],

  siteContent: [
    {
      slug: "footer-about",
      title: "Footer About Us Text",
      status: "Enabled",
      content: "Aus Ecom is Australia's premier online retailer for premium electronics, ergonomics, and lifestyle products. Fast nationwide dispatch with full Australian manufacturer warranty."
    },
    {
      slug: "footer-shipping",
      title: "Footer Shipping & Delivery Notice",
      status: "Enabled",
      content: "Free standard shipping on orders over $99 nationwide. Fast express courier delivery options available at checkout."
    },
    {
      slug: "footer-returns",
      title: "Footer 30-Day Money-Back Policy",
      status: "Enabled",
      content: "Hassle-free 30-day returns on all unused store items in original packaging. Australian customer support team available 7 days a week."
    },
    {
      slug: "footer-contact",
      title: "Footer Contact Information",
      status: "Enabled",
      content: "Customer Helpline: 1300 888 ECOM | Email: support@ausecom.com.au | Address: Level 12, 100 Barangaroo Ave, Sydney NSW 2000"
    },
    {
      slug: "top-announcement-bar",
      title: "Header Top Announcement Bar Text",
      status: "Enabled",
      content: "SPRING SALE IS LIVE! Use code WELCOME10 for 10% OFF your first order."
    }
  ],

  pages: [
    {
      id: "PAG-5001",
      title: "About Us",
      slug: "about-us",
      featuredImage: "https://images.unsplash.com/photo-1497366216548-37526070297c?w=800&auto=format&fit=crop&q=60",
      status: "Published",
      content: "<h2>Welcome to Aus Ecom</h2><p>Founded in Sydney, Aus Ecom is dedicated to providing high quality tech and lifestyle products across Australia...</p>"
    },
    {
      id: "PAG-5002",
      title: "Privacy Policy",
      slug: "privacy-policy",
      featuredImage: "",
      status: "Published",
      content: "<h2>Privacy Policy</h2><p>Your privacy is important to us. This privacy policy explains how Aus Ecom collects, uses, and protects your personal data...</p>"
    },
    {
      id: "PAG-5003",
      title: "Terms & Conditions",
      slug: "terms-and-conditions",
      featuredImage: "",
      status: "Published",
      content: "<h2>Terms of Service</h2><p>By browsing or purchasing from Aus Ecom, you agree to comply with our terms and conditions of sale...</p>"
    },
    {
      id: "PAG-5004",
      title: "Store Locations & Pick-up Hubs",
      slug: "store-locations",
      featuredImage: "https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=800&auto=format&fit=crop&q=60",
      status: "Draft",
      content: "<h2>Our Showrooms</h2><p>Visit our flagship Sydney and Melbourne showrooms to try out ergonomic chairs and test audio equipment live.</p>"
    }
  ],

  navigation: [
    {
      id: "NAV-6001",
      title: "Home",
      destination: "index.html",
      parent: "Root",
      sortOrder: 1,
      status: "Active"
    },
    {
      id: "NAV-6002",
      title: "Shop All Products",
      destination: "products.html",
      parent: "Root",
      sortOrder: 2,
      status: "Active"
    },
    {
      id: "NAV-6003",
      title: "Computers & Laptops",
      destination: "categories.html?cat=computers",
      parent: "Shop All Products",
      sortOrder: 1,
      status: "Active"
    },
    {
      id: "NAV-6004",
      title: "Audio & Headphones",
      destination: "categories.html?cat=audio",
      parent: "Shop All Products",
      sortOrder: 2,
      status: "Active"
    },
    {
      id: "NAV-6005",
      title: "Offers & Discounts",
      destination: "offers.html",
      parent: "Root",
      sortOrder: 3,
      status: "Active"
    },
    {
      id: "NAV-6006",
      title: "Contact Us",
      destination: "contacts.html",
      parent: "Footer Quick Links",
      sortOrder: 1,
      status: "Active"
    }
  ],

  homepage: {
    heroTitle: "Australia's Premier Tech & Ergonomic Store",
    heroSubtitle: "Shop the latest high-performance electronics, executive office furniture, and wireless gadgets.",
    bannerSliderActive: true,
    featuredSectionTitle: "Trending Products This Season",
    showcaseCategory: "Electronics & Ergonomics",
    announcementActive: true,
    announcementText: "Free Express Shipping on Orders over $99 nationwide!"
  }
};

const MarketingContentStore = {
  init() {
    if (!localStorage.getItem(MC_STORAGE_KEYS.OFFERS)) {
      localStorage.setItem(MC_STORAGE_KEYS.OFFERS, JSON.stringify(MC_SEED_DATA.offers));
    }
    if (!localStorage.getItem(MC_STORAGE_KEYS.COUPONS)) {
      localStorage.setItem(MC_STORAGE_KEYS.COUPONS, JSON.stringify(MC_SEED_DATA.coupons));
    }
    if (!localStorage.getItem(MC_STORAGE_KEYS.BANNERS)) {
      localStorage.setItem(MC_STORAGE_KEYS.BANNERS, JSON.stringify(MC_SEED_DATA.banners));
    }
    if (!localStorage.getItem(MC_STORAGE_KEYS.SITE_CONTENT)) {
      localStorage.setItem(MC_STORAGE_KEYS.SITE_CONTENT, JSON.stringify(MC_SEED_DATA.siteContent));
    }
    if (!localStorage.getItem(MC_STORAGE_KEYS.PAGES)) {
      localStorage.setItem(MC_STORAGE_KEYS.PAGES, JSON.stringify(MC_SEED_DATA.pages));
    }
    if (!localStorage.getItem(MC_STORAGE_KEYS.NAVIGATION)) {
      localStorage.setItem(MC_STORAGE_KEYS.NAVIGATION, JSON.stringify(MC_SEED_DATA.navigation));
    }
    if (!localStorage.getItem(MC_STORAGE_KEYS.HOMEPAGE)) {
      localStorage.setItem(MC_STORAGE_KEYS.HOMEPAGE, JSON.stringify(MC_SEED_DATA.homepage));
    }
  },

  _get(key) {
    this.init();
    try {
      return JSON.parse(localStorage.getItem(key)) || [];
    } catch (e) {
      return [];
    }
  },
  _set(key, data) {
    localStorage.setItem(key, JSON.stringify(data));
  },

  // OFFERS CRUD
  getOffers() { return this._get(MC_STORAGE_KEYS.OFFERS); },
  getOffer(id) { return this.getOffers().find(o => o.id === id) || null; },
  saveOffer(offerData) {
    const list = this.getOffers();
    if (offerData.id) {
      const idx = list.findIndex(o => o.id === offerData.id);
      if (idx !== -1) list[idx] = { ...list[idx], ...offerData };
      else list.unshift(offerData);
    } else {
      offerData.id = 'OFF-' + Math.floor(1000 + Math.random() * 9000);
      list.unshift(offerData);
    }
    this._set(MC_STORAGE_KEYS.OFFERS, list);
    return offerData;
  },
  deleteOffer(id) {
    this._set(MC_STORAGE_KEYS.OFFERS, this.getOffers().filter(o => o.id !== id));
  },
  toggleOfferStatus(id) {
    const list = this.getOffers();
    const item = list.find(o => o.id === id);
    if (item) {
      item.status = item.status === 'Active' ? 'Inactive' : 'Active';
      this._set(MC_STORAGE_KEYS.OFFERS, list);
    }
    return item;
  },

  // COUPONS CRUD
  getCoupons() { return this._get(MC_STORAGE_KEYS.COUPONS); },
  getCoupon(id) { return this.getCoupons().find(c => c.id === id) || null; },
  saveCoupon(couponData) {
    const list = this.getCoupons();
    if (couponData.id) {
      const idx = list.findIndex(c => c.id === couponData.id);
      if (idx !== -1) list[idx] = { ...list[idx], ...couponData };
      else list.unshift(couponData);
    } else {
      couponData.id = 'CPN-' + Math.floor(2000 + Math.random() * 9000);
      couponData.usedCount = couponData.usedCount || 0;
      list.unshift(couponData);
    }
    this._set(MC_STORAGE_KEYS.COUPONS, list);
    return couponData;
  },
  deleteCoupon(id) {
    this._set(MC_STORAGE_KEYS.COUPONS, this.getCoupons().filter(c => c.id !== id));
  },
  toggleCouponStatus(id) {
    const list = this.getCoupons();
    const item = list.find(c => c.id === id);
    if (item) {
      item.status = item.status === 'Active' ? 'Disabled' : 'Active';
      this._set(MC_STORAGE_KEYS.COUPONS, list);
    }
    return item;
  },

  // BANNERS CRUD
  getBanners() { return this._get(MC_STORAGE_KEYS.BANNERS); },
  getBanner(id) { return this.getBanners().find(b => b.id === id) || null; },
  saveBanner(bannerData) {
    const list = this.getBanners();
    if (bannerData.id) {
      const idx = list.findIndex(b => b.id === bannerData.id);
      if (idx !== -1) list[idx] = { ...list[idx], ...bannerData };
      else list.unshift(bannerData);
    } else {
      bannerData.id = 'BAN-' + Math.floor(3000 + Math.random() * 9000);
      list.unshift(bannerData);
    }
    this._set(MC_STORAGE_KEYS.BANNERS, list);
    return bannerData;
  },
  deleteBanner(id) {
    this._set(MC_STORAGE_KEYS.BANNERS, this.getBanners().filter(b => b.id !== id));
  },
  toggleBannerStatus(id) {
    const list = this.getBanners();
    const item = list.find(b => b.id === id);
    if (item) {
      item.status = item.status === 'Active' ? 'Inactive' : 'Active';
      this._set(MC_STORAGE_KEYS.BANNERS, list);
    }
    return item;
  },

  // SITE CONTENT (Predefined Only!)
  getSiteContents() { return this._get(MC_STORAGE_KEYS.SITE_CONTENT); },
  getSiteContent(slug) { return this.getSiteContents().find(sc => sc.slug === slug) || null; },
  saveSiteContent(contentData) {
    const list = this.getSiteContents();
    const idx = list.findIndex(sc => sc.slug === contentData.slug);
    if (idx !== -1) {
      // Slug is non-editable! Only update Title, Content, Status
      list[idx].title = contentData.title;
      list[idx].content = contentData.content;
      list[idx].status = contentData.status;
      this._set(MC_STORAGE_KEYS.SITE_CONTENT, list);
      return list[idx];
    }
    throw new Error('Cannot create arbitrary site content slugs.');
  },
  toggleSiteContentStatus(slug) {
    const list = this.getSiteContents();
    const item = list.find(sc => sc.slug === slug);
    if (item) {
      item.status = item.status === 'Enabled' ? 'Disabled' : 'Enabled';
      this._set(MC_STORAGE_KEYS.SITE_CONTENT, list);
    }
    return item;
  },

  // PAGES CRUD
  getPages() { return this._get(MC_STORAGE_KEYS.PAGES); },
  getPage(id) { return this.getPages().find(p => p.id === id) || null; },
  savePage(pageData) {
    const list = this.getPages();
    if (pageData.id) {
      const idx = list.findIndex(p => p.id === pageData.id);
      if (idx !== -1) list[idx] = { ...list[idx], ...pageData };
      else list.unshift(pageData);
    } else {
      pageData.id = 'PAG-' + Math.floor(5000 + Math.random() * 9000);
      list.unshift(pageData);
    }
    this._set(MC_STORAGE_KEYS.PAGES, list);
    return pageData;
  },
  deletePage(id) {
    this._set(MC_STORAGE_KEYS.PAGES, this.getPages().filter(p => p.id !== id));
  },

  // NAVIGATION CRUD
  getNavigations() { return this._get(MC_STORAGE_KEYS.NAVIGATION); },
  getNavigation(id) { return this.getNavigations().find(n => n.id === id) || null; },
  saveNavigation(navData) {
    const list = this.getNavigations();
    if (navData.id) {
      const idx = list.findIndex(n => n.id === navData.id);
      if (idx !== -1) list[idx] = { ...list[idx], ...navData };
      else list.push(navData);
    } else {
      navData.id = 'NAV-' + Math.floor(6000 + Math.random() * 9000);
      list.push(navData);
    }
    this._set(MC_STORAGE_KEYS.NAVIGATION, list);
    return navData;
  },
  deleteNavigation(id) {
    this._set(MC_STORAGE_KEYS.NAVIGATION, this.getNavigations().filter(n => n.id !== id));
  },
  toggleNavigationStatus(id) {
    const list = this.getNavigations();
    const item = list.find(n => n.id === id);
    if (item) {
      item.status = item.status === 'Active' ? 'Inactive' : 'Active';
      this._set(MC_STORAGE_KEYS.NAVIGATION, list);
    }
    return item;
  },

  // HOMEPAGE CONFIG
  getHomepageConfig() {
    this.init();
    try {
      return JSON.parse(localStorage.getItem(MC_STORAGE_KEYS.HOMEPAGE)) || MC_SEED_DATA.homepage;
    } catch (e) {
      return MC_SEED_DATA.homepage;
    }
  },
  saveHomepageConfig(config) {
    localStorage.setItem(MC_STORAGE_KEYS.HOMEPAGE, JSON.stringify(config));
    return config;
  }
};

// Auto init on script load
MarketingContentStore.init();
