/**
 * engagement-store.js
 * Centralized data store and CRUD logic for the Engagement Module:
 * - Reviews
 * - Wishlists
 * - Subscribers
 * - Contacts
 * 
 * Uses localStorage to maintain persistent demo state with fallbacks.
 */

const STORAGE_KEYS = {
  REVIEWS: 'aus_ecom_reviews',
  WISHLISTS: 'aus_ecom_wishlists',
  SUBSCRIBERS: 'aus_ecom_subscribers',
  CONTACTS: 'aus_ecom_contacts'
};

// Initial Seed Data
const SEED_DATA = {
  reviews: [
    {
      id: "REV-1001",
      customerId: "CUST-501",
      customerName: "Sophia Anderson",
      customerEmail: "sophia.anderson@example.com",
      customerAvatar: "1.png",
      productId: "PROD-201",
      productName: "Ergonomic Leather Executive Chair",
      productSku: "EL-EXCH-01",
      productImage: "assets/img/products/1.png",
      productPrice: 299.99,
      rating: 5,
      reviewText: "Absolute game changer for long work sessions! Lumbar support is incredible and assembly was seamless. Highly recommended for anyone working remotely.",
      images: [
        "https://images.unsplash.com/photo-1580481072645-022f9a6d83d0?w=500&auto=format&fit=crop&q=60",
        "https://images.unsplash.com/photo-1505797149-43b0069ec26b?w=500&auto=format&fit=crop&q=60"
      ],
      date: "2026-10-06 14:20",
      status: "Approved",
      adminNote: "Featured review on homepage hero section. Verified purchase."
    },
    {
      id: "REV-1002",
      customerId: "CUST-502",
      customerName: "Marcus Vance",
      customerEmail: "marcus.vance@techcorp.io",
      customerAvatar: "2.png",
      productId: "PROD-202",
      productName: "Sony PlayStation 5 Console",
      productSku: "PS5-DISC-AU",
      productImage: "assets/img/products/sony-play-station-5.png",
      productPrice: 749.00,
      rating: 4,
      reviewText: "Console works great and load times are blazing fast. Box came slightly creased at the corner, but internal packaging was intact.",
      images: [],
      date: "2026-10-07 10:15",
      status: "Pending",
      adminNote: "Awaiting customer support follow-up regarding packaging condition."
    },
    {
      id: "REV-1003",
      customerId: "CUST-503",
      customerName: "Emily Chen",
      customerEmail: "emily.chen@outlook.com",
      customerAvatar: "3.png",
      productId: "PROD-203",
      productName: "Apple iPhone 13 Pro 256GB",
      productSku: "IPH13P-256GB",
      productImage: "assets/img/products/apple-iPhone-13-pro.png",
      productPrice: 1299.00,
      rating: 5,
      reviewText: "Battery life easily lasts two full days of normal usage. Camera quality and cinematic video mode are top tier!",
      images: ["https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=500&auto=format&fit=crop&q=60"],
      date: "2026-10-08 09:45",
      status: "Approved",
      adminNote: "Verified buyer review."
    },
    {
      id: "REV-1004",
      customerId: "CUST-504",
      customerName: "Liam Wilson",
      customerEmail: "liam.w@gmail.com",
      customerAvatar: "4.png",
      productId: "PROD-204",
      productName: "Amazon Echo Dot 4th Gen",
      productSku: "ECHO-DOT-4G",
      productImage: "assets/img/products/amazon-echo-dot.png",
      productPrice: 79.00,
      rating: 1,
      reviewText: "Received wrong color and couldn't connect to 5GHz Wi-Fi band. Extremely disappointed.",
      images: [],
      date: "2026-10-08 17:30",
      status: "Rejected",
      adminNote: "Customer issue resolved via replacement unit; review rejected as per policy."
    },
    {
      id: "REV-1005",
      customerId: "CUST-505",
      customerName: "Hannah Taylor",
      customerEmail: "hannah.t@designstudio.au",
      customerAvatar: "5.png",
      productId: "PROD-205",
      productName: "Apple iMac 24\" 4.5K Retina",
      productSku: "IMAC-24-SLV",
      productImage: "assets/img/products/apple-iMac-4k.png",
      productPrice: 1899.00,
      rating: 5,
      reviewText: "Stunning display color accuracy and sleek design. Perfect for graphic design and video editing work.",
      images: [],
      date: "2026-10-08 19:10",
      status: "Approved",
      adminNote: "Verified corporate customer."
    },
    {
      id: "REV-1006",
      customerId: "CUST-506",
      customerName: "Oliver Brown",
      customerEmail: "oliver.b@business.com",
      customerAvatar: "6.png",
      productId: "PROD-206",
      productName: "Samsung Galaxy Watch 4",
      productSku: "SGW-4-44MM",
      productImage: "assets/img/products/samsung-watch-4.png",
      productPrice: 349.00,
      rating: 3,
      reviewText: "Great health tracking features, but battery life requires daily charging if GPS is active.",
      images: [],
      date: "2026-10-09 08:05",
      status: "Pending",
      adminNote: ""
    }
  ],

  wishlists: [
    {
      id: "WISH-2001",
      customerId: "CUST-501",
      customerName: "Sophia Anderson",
      customerEmail: "sophia.anderson@example.com",
      customerAvatar: "1.png",
      productId: "PROD-205",
      productName: "Apple iMac 24\" 4.5K Retina",
      productSku: "IMAC-24-SLV",
      productImage: "assets/img/products/apple-iMac-4k.png",
      variant: "Silver / 16GB RAM / 512GB SSD",
      price: 1899.00,
      addedDate: "2026-10-08 12:40",
      availability: "In Stock"
    },
    {
      id: "WISH-2002",
      customerId: "CUST-502",
      customerName: "Marcus Vance",
      customerEmail: "marcus.vance@techcorp.io",
      customerAvatar: "2.png",
      productId: "PROD-207",
      productName: "Xbox Series X 1TB",
      productSku: "XBOX-SX-1TB",
      productImage: "assets/img/products/xbox-series-x.png",
      variant: "Standard Black Edition",
      price: 749.00,
      addedDate: "2026-10-07 14:10",
      availability: "Out of Stock"
    },
    {
      id: "WISH-2003",
      customerId: "CUST-503",
      customerName: "Emily Chen",
      customerEmail: "emily.chen@outlook.com",
      customerAvatar: "3.png",
      productId: "PROD-208",
      productName: "Apple Watch Series 7 GPS",
      productSku: "AWS7-45-MID",
      productImage: "assets/img/products/apple-watch-7.png",
      variant: "45mm Midnight Aluminum",
      price: 599.00,
      addedDate: "2026-10-06 16:05",
      availability: "In Stock"
    },
    {
      id: "WISH-2004",
      customerId: "CUST-504",
      customerName: "Liam Wilson",
      customerEmail: "liam.w@gmail.com",
      customerAvatar: "4.png",
      productId: "PROD-209",
      productName: "Nintendo Switch OLED Model",
      productSku: "NSW-OLED-WHT",
      productImage: "assets/img/products/nintendo-switch.png",
      variant: "White Joy-Con Edition",
      price: 539.00,
      addedDate: "2026-10-05 18:22",
      availability: "In Stock"
    },
    {
      id: "WISH-2005",
      customerId: "CUST-505",
      customerName: "Hannah Taylor",
      customerEmail: "hannah.t@designstudio.au",
      customerAvatar: "5.png",
      productId: "PROD-210",
      productName: "Dell Inspiron 15 3000 Laptop",
      productSku: "DELL-INSP-15",
      productImage: "assets/img/products/dell-inspiron-3000.png",
      variant: "Intel i7 / 16GB RAM / 1TB SSD",
      price: 1199.00,
      addedDate: "2026-10-04 11:15",
      availability: "In Stock"
    }
  ],

  subscribers: [
    {
      id: "SUB-3001",
      email: "olivia.bennett@example.com",
      name: "Olivia Bennett",
      subscriptionDate: "2026-10-08 11:20",
      status: "Subscribed",
      source: "Footer Newsletter",
      notes: "Subscribed during autumn catalog promotion launch."
    },
    {
      id: "SUB-3002",
      email: "jackson.miller@agency.au",
      name: "Jackson Miller",
      subscriptionDate: "2026-10-07 15:45",
      status: "Subscribed",
      source: "Checkout Popup",
      notes: "Opted-in during guest checkout step 2."
    },
    {
      id: "SUB-3003",
      email: "chloe.davis@webmail.com",
      name: "Chloe Davis",
      subscriptionDate: "2026-10-06 09:30",
      status: "Unsubscribed",
      source: "Landing Page Modal",
      notes: "Unsubscribed via automated footer link on Oct 8."
    },
    {
      id: "SUB-3004",
      email: "noah.smith@corporate.com",
      name: "Noah Smith",
      subscriptionDate: "2026-10-05 14:10",
      status: "Subscribed",
      source: "Header Banner Bar",
      notes: "Lead magnet promo download opt-in."
    },
    {
      id: "SUB-3005",
      email: "ava.wilson@creative.io",
      name: "Ava Wilson",
      subscriptionDate: "2026-10-04 18:50",
      status: "Subscribed",
      source: "Manual Entry",
      notes: "Added manually by admin staff after phone inquiry."
    }
  ],

  contacts: [
    {
      id: "CON-4001",
      name: "Marcus Vance",
      email: "marcus.vance@techcorp.io",
      phone: "+61 412 345 678",
      subject: "Bulk Corporate Purchase Inquiry",
      message: "Hi Team, we are planning to equip our new Brisbane office with 40 ergonomic work setups. Can you provide a wholesale price list and lead times?",
      date: "2026-10-08 16:45",
      status: "New",
      isRead: false,
      adminNote: "High priority commercial lead assigned to sales team."
    },
    {
      id: "CON-4002",
      name: "Sarah Jenkins",
      email: "sarah.j@designhub.com",
      phone: "+61 423 876 543",
      subject: "Shipping Delay Inquiry for Order #ORD-8821",
      message: "Hello, my tracking number has not updated for 3 days. Could you check with the courier company?",
      date: "2026-10-07 11:10",
      status: "In Progress",
      isRead: true,
      adminNote: "Escalated to logistics manager."
    },
    {
      id: "CON-4003",
      name: "David Miller",
      email: "david.m@techsolution.com.au",
      phone: "+61 434 987 654",
      subject: "Warranty & Return Policy Question",
      message: "I bought an iMac last week and would like to know if extended AppleCare warranty can be added through your store.",
      date: "2026-10-06 14:25",
      status: "Resolved",
      isRead: true,
      adminNote: "Sent AppleCare brochure & direct purchase link."
    },
    {
      id: "CON-4004",
      name: "Jessica Taylor",
      email: "jessica.t@gmail.com",
      phone: "+61 445 123 789",
      subject: "Website Account Password Reset Help",
      message: "I am unable to reset my password using the automatic email link. Can support reset it for me?",
      date: "2026-10-05 09:15",
      status: "Closed",
      isRead: true,
      adminNote: "Issue resolved; user logged in successfully."
    }
  ]
};

// EngagementStore Module API
const EngagementStore = {
  // Initialization
  init() {
    if (!localStorage.getItem(STORAGE_KEYS.REVIEWS)) {
      localStorage.setItem(STORAGE_KEYS.REVIEWS, JSON.stringify(SEED_DATA.reviews));
    }
    if (!localStorage.getItem(STORAGE_KEYS.WISHLISTS)) {
      localStorage.setItem(STORAGE_KEYS.WISHLISTS, JSON.stringify(SEED_DATA.wishlists));
    }
    if (!localStorage.getItem(STORAGE_KEYS.SUBSCRIBERS)) {
      localStorage.setItem(STORAGE_KEYS.SUBSCRIBERS, JSON.stringify(SEED_DATA.subscribers));
    }
    if (!localStorage.getItem(STORAGE_KEYS.CONTACTS)) {
      localStorage.setItem(STORAGE_KEYS.CONTACTS, JSON.stringify(SEED_DATA.contacts));
    }
  },

  // Helper getters & setters
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

  // REVIEWS CRUD
  getReviews() {
    return this._get(STORAGE_KEYS.REVIEWS);
  },
  getReview(id) {
    return this.getReviews().find(r => r.id === id) || null;
  },
  saveReview(reviewData) {
    const list = this.getReviews();
    if (reviewData.id) {
      const idx = list.findIndex(r => r.id === reviewData.id);
      if (idx !== -1) {
        list[idx] = { ...list[idx], ...reviewData };
      } else {
        list.unshift(reviewData);
      }
    } else {
      reviewData.id = 'REV-' + Math.floor(1000 + Math.random() * 9000);
      reviewData.date = reviewData.date || new Date().toISOString().replace('T', ' ').substring(0, 16);
      list.unshift(reviewData);
    }
    this._set(STORAGE_KEYS.REVIEWS, list);
    return reviewData;
  },
  deleteReview(id) {
    const list = this.getReviews().filter(r => r.id !== id);
    this._set(STORAGE_KEYS.REVIEWS, list);
  },
  updateReviewStatus(idOrIds, status) {
    const list = this.getReviews();
    const ids = Array.isArray(idOrIds) ? idOrIds : [idOrIds];
    list.forEach(r => {
      if (ids.includes(r.id)) {
        r.status = status;
      }
    });
    this._set(STORAGE_KEYS.REVIEWS, list);
  },

  // WISHLIST CRUD
  getWishlists() {
    return this._get(STORAGE_KEYS.WISHLISTS);
  },
  getWishlist(id) {
    return this.getWishlists().find(w => w.id === id) || null;
  },
  saveWishlist(wishlistData) {
    const list = this.getWishlists();
    if (wishlistData.id) {
      const idx = list.findIndex(w => w.id === wishlistData.id);
      if (idx !== -1) {
        list[idx] = { ...list[idx], ...wishlistData };
      } else {
        list.unshift(wishlistData);
      }
    } else {
      wishlistData.id = 'WISH-' + Math.floor(2000 + Math.random() * 9000);
      wishlistData.addedDate = wishlistData.addedDate || new Date().toISOString().replace('T', ' ').substring(0, 16);
      list.unshift(wishlistData);
    }
    this._set(STORAGE_KEYS.WISHLISTS, list);
    return wishlistData;
  },
  deleteWishlist(id) {
    const list = this.getWishlists().filter(w => w.id !== id);
    this._set(STORAGE_KEYS.WISHLISTS, list);
  },

  // SUBSCRIBERS CRUD
  getSubscribers() {
    return this._get(STORAGE_KEYS.SUBSCRIBERS);
  },
  getSubscriber(id) {
    return this.getSubscribers().find(s => s.id === id) || null;
  },
  saveSubscriber(subData) {
    const list = this.getSubscribers();
    // Validate duplicate email
    if (this.isDuplicateSubscriberEmail(subData.email, subData.id)) {
      throw new Error('Subscriber with this email address already exists.');
    }

    if (subData.id) {
      const idx = list.findIndex(s => s.id === subData.id);
      if (idx !== -1) {
        list[idx] = { ...list[idx], ...subData };
      } else {
        list.unshift(subData);
      }
    } else {
      subData.id = 'SUB-' + Math.floor(3000 + Math.random() * 9000);
      subData.subscriptionDate = subData.subscriptionDate || new Date().toISOString().replace('T', ' ').substring(0, 16);
      list.unshift(subData);
    }
    this._set(STORAGE_KEYS.SUBSCRIBERS, list);
    return subData;
  },
  deleteSubscriber(id) {
    const list = this.getSubscribers().filter(s => s.id !== id);
    this._set(STORAGE_KEYS.SUBSCRIBERS, list);
  },
  toggleSubscriberStatus(id) {
    const list = this.getSubscribers();
    const item = list.find(s => s.id === id);
    if (item) {
      item.status = item.status === 'Subscribed' ? 'Unsubscribed' : 'Subscribed';
      this._set(STORAGE_KEYS.SUBSCRIBERS, list);
    }
    return item;
  },
  isDuplicateSubscriberEmail(email, excludeId = null) {
    if (!email) return false;
    const cleanEmail = email.trim().toLowerCase();
    return this.getSubscribers().some(s => s.email.trim().toLowerCase() === cleanEmail && s.id !== excludeId);
  },

  // CONTACTS CRUD
  getContacts() {
    return this._get(STORAGE_KEYS.CONTACTS);
  },
  getContact(id) {
    return this.getContacts().find(c => c.id === id) || null;
  },
  saveContact(contactData) {
    const list = this.getContacts();
    if (contactData.id) {
      const idx = list.findIndex(c => c.id === contactData.id);
      if (idx !== -1) {
        list[idx] = { ...list[idx], ...contactData };
      } else {
        list.unshift(contactData);
      }
    } else {
      contactData.id = 'CON-' + Math.floor(4000 + Math.random() * 9000);
      contactData.date = contactData.date || new Date().toISOString().replace('T', ' ').substring(0, 16);
      contactData.isRead = contactData.isRead !== undefined ? contactData.isRead : false;
      list.unshift(contactData);
    }
    this._set(STORAGE_KEYS.CONTACTS, list);
    return contactData;
  },
  deleteContact(id) {
    const list = this.getContacts().filter(c => c.id !== id);
    this._set(STORAGE_KEYS.CONTACTS, list);
  },
  updateContactStatus(id, status) {
    const list = this.getContacts();
    const item = list.find(c => c.id === id);
    if (item) {
      item.status = status;
      this._set(STORAGE_KEYS.CONTACTS, list);
    }
    return item;
  },
  toggleContactRead(id) {
    const list = this.getContacts();
    const item = list.find(c => c.id === id);
    if (item) {
      item.isRead = !item.isRead;
      this._set(STORAGE_KEYS.CONTACTS, list);
    }
    return item;
  }
};

// Initialize on script load
EngagementStore.init();
