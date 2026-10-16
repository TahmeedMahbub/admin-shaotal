/**
 * Aus Ecom Sales Module Data & Logic Helper
 * Structuring Order, Customer, and Abandoned Cart data objects for backend API readiness
 */

window.SalesData = {
  
  // ----------------------------------------------------
  // ORDERS DATA
  // ----------------------------------------------------
  orders: [
    {
      id: "ORD-9482",
      date: "Oct 07, 2026 10:14 AM",
      customerId: "CUST-1001",
      customerName: "Eleanor Vance",
      customerEmail: "eleanor@example.com",
      customerPhone: "+61 412 345 678",
      itemsCount: 2,
      subtotal: 310.00,
      discount: 20.00,
      shipping: 15.00,
      tax: 35.00,
      total: 340.00,
      paymentStatus: "Paid",
      orderStatus: "Delivered",
      paymentMethod: "Credit Card (Visa ending in 4242)",
      transactionId: "TXN-88492019",
      shippingAddress: {
        recipient: "Eleanor Vance",
        phone: "+61 412 345 678",
        street: "742 Evergreen Terrace",
        suburb: "South Yarra",
        state: "VIC",
        postcode: "3141",
        country: "Australia"
      },
      items: [
        {
          id: "PRD-OAK-01",
          name: "Minimalist Oak Dining Table",
          sku: "FUR-OAK-OLV-6S",
          color: "Olive Green",
          hex: "#6F765F",
          size: "6-Seater",
          quantity: 1,
          unitPrice: 340.00,
          discount: 60.00,
          total: 340.00,
          image: "assets/img/products/oak-table.jpg"
        }
      ],
      timeline: [
        { title: "Order Delivered", date: "Oct 09, 2026 02:30 PM", status: "completed", desc: "Package signed by customer." },
        { title: "Shipped via Australia Post", date: "Oct 08, 2026 09:15 AM", status: "completed", desc: "Tracking #AU94820194" },
        { title: "Processing & Packed", date: "Oct 07, 2026 02:00 PM", status: "completed", desc: "Order verified at Sydney Warehouse." },
        { title: "Payment Confirmed ($340.00)", date: "Oct 07, 2026 10:15 AM", status: "completed", desc: "Visa payment captured." },
        { title: "Order Placed", date: "Oct 07, 2026 10:14 AM", status: "completed", desc: "Order placed online." }
      ]
    },
    {
      id: "ORD-9481",
      date: "Oct 07, 2026 08:30 AM",
      customerId: "CUST-1002",
      customerName: "Liam Thorne",
      customerEmail: "liam.t@example.com",
      customerPhone: "+61 498 765 432",
      itemsCount: 1,
      subtotal: 120.00,
      discount: 0.00,
      shipping: 5.50,
      tax: 0.00,
      total: 125.50,
      paymentStatus: "Paid",
      orderStatus: "Processing",
      paymentMethod: "PayPal (liam.t@example.com)",
      transactionId: "PP-77492011",
      shippingAddress: {
        recipient: "Liam Thorne",
        phone: "+61 498 765 432",
        street: "18 Collins Street, Apt 4B",
        suburb: "Melbourne",
        state: "VIC",
        postcode: "3000",
        country: "Australia"
      },
      items: [
        {
          id: "HOM-TEA-04",
          name: "Ceramic Artisan Tea Set",
          sku: "HOM-TEA-GRN",
          color: "Sage Green",
          hex: "#8A9A86",
          size: "Standard Set",
          quantity: 2,
          unitPrice: 60.00,
          discount: 0.00,
          total: 120.00,
          image: "assets/img/products/tea-set.jpg"
        }
      ],
      timeline: [
        { title: "Processing & Packed", date: "Oct 07, 2026 11:00 AM", status: "current", desc: "Preparing for dispatch." },
        { title: "Payment Confirmed ($125.50)", date: "Oct 07, 2026 08:31 AM", status: "completed", desc: "PayPal payment captured." },
        { title: "Order Placed", date: "Oct 07, 2026 08:30 AM", status: "completed", desc: "Order placed online." }
      ]
    },
    {
      id: "ORD-9480",
      date: "Oct 06, 2026 04:12 PM",
      customerId: "CUST-1003",
      customerName: "Sophia Chen",
      customerEmail: "sophia.c@example.com",
      customerPhone: "+61 433 111 222",
      itemsCount: 3,
      subtotal: 850.00,
      discount: 0.00,
      shipping: 40.00,
      tax: 0.00,
      total: 890.00,
      paymentStatus: "Pending",
      orderStatus: "Pending",
      paymentMethod: "Bank Transfer",
      transactionId: "PENDING-BT-003",
      shippingAddress: {
        recipient: "Sophia Chen",
        phone: "+61 433 111 222",
        street: "45 Ocean Drive",
        suburb: "Manly",
        state: "NSW",
        postcode: "2095",
        country: "Australia"
      },
      items: [
        {
          id: "FUR-CHR-09",
          name: "Scandinavia Lounge Chair",
          sku: "FUR-CHR-OAK",
          color: "Beige Linen",
          hex: "#E5DDD3",
          size: "Single Seat",
          quantity: 2,
          unitPrice: 350.00,
          discount: 0.00,
          total: 700.00,
          image: "assets/img/products/chair.jpg"
        }
      ],
      timeline: [
        { title: "Awaiting Bank Transfer Payment", date: "Oct 06, 2026 04:12 PM", status: "current", desc: "Customer provided with invoice details." },
        { title: "Order Placed", date: "Oct 06, 2026 04:12 PM", status: "completed", desc: "Checkout completed." }
      ]
    },
    {
      id: "ORD-9479",
      date: "Oct 06, 2026 01:20 PM",
      customerId: "CUST-1004",
      customerName: "Marcus Brody",
      customerEmail: "marcus@example.com",
      customerPhone: "+61 422 999 888",
      itemsCount: 1,
      subtotal: 200.00,
      discount: 0.00,
      shipping: 10.00,
      tax: 0.00,
      total: 210.00,
      paymentStatus: "Paid",
      orderStatus: "Shipped",
      paymentMethod: "Apple Pay",
      transactionId: "AP-99201948",
      shippingAddress: {
        recipient: "Marcus Brody",
        phone: "+61 422 999 888",
        street: "12 Beach Road",
        suburb: "St Kilda",
        state: "VIC",
        postcode: "3182",
        country: "Australia"
      },
      items: [
        {
          id: "TEX-BLN-12",
          name: "Linen Throw Blanket (Beige)",
          sku: "TEX-BLN-BGE-L",
          color: "Warm Cream",
          hex: "#FAF7F2",
          size: "Large (200x220cm)",
          quantity: 4,
          unitPrice: 50.00,
          discount: 0.00,
          total: 200.00,
          image: "assets/img/products/blanket.jpg"
        }
      ],
      timeline: [
        { title: "In Transit via Toll Express", date: "Oct 07, 2026 08:00 AM", status: "current", desc: "Tracking #TOLL-99281" },
        { title: "Order Dispatched", date: "Oct 06, 2026 05:30 PM", status: "completed", desc: "Handed over to courier." },
        { title: "Order Placed", date: "Oct 06, 2026 01:20 PM", status: "completed", desc: "Payment authorized." }
      ]
    },
    {
      id: "ORD-9478",
      date: "Oct 05, 2026 11:45 AM",
      customerId: "CUST-1005",
      customerName: "Hannah Abbott",
      customerEmail: "hannah.a@example.com",
      customerPhone: "+61 411 000 333",
      itemsCount: 1,
      subtotal: 45.00,
      discount: 0.00,
      shipping: 0.00,
      tax: 0.00,
      total: 45.00,
      paymentStatus: "Refunded",
      orderStatus: "Cancelled",
      paymentMethod: "Credit Card (Mastercard ending in 1109)",
      transactionId: "REF-44920192",
      shippingAddress: {
        recipient: "Hannah Abbott",
        phone: "+61 411 000 333",
        street: "89 Park Street",
        suburb: "Brisbane",
        state: "QLD",
        postcode: "4000",
        country: "Australia"
      },
      items: [
        {
          id: "DEC-BRS-02",
          name: "Artisan Brass Candle Holder",
          sku: "DEC-BRS-02",
          color: "Antique Brass",
          hex: "#B8860B",
          size: "Medium",
          quantity: 1,
          unitPrice: 45.00,
          discount: 0.00,
          total: 45.00,
          image: "assets/img/products/candle-holder.jpg"
        }
      ],
      timeline: [
        { title: "Refund Issued ($45.00)", date: "Oct 05, 2026 01:00 PM", status: "completed", desc: "Full refund processed back to card." },
        { title: "Order Cancelled", date: "Oct 05, 2026 12:30 PM", status: "completed", desc: "Cancelled by customer request." }
      ]
    }
  ],

  // ----------------------------------------------------
  // CUSTOMERS DATA
  // ----------------------------------------------------
  customers: [
    {
      id: "CUST-1001",
      name: "Eleanor Vance",
      email: "eleanor@example.com",
      phone: "+61 412 345 678",
      totalOrders: 6,
      totalSpent: 1840.00,
      averageOrderValue: 306.66,
      lastOrderDate: "Oct 07, 2026",
      lastOrderId: "ORD-9482",
      status: "Active",
      createdDate: "Jan 12, 2025",
      address: "742 Evergreen Terrace, South Yarra, VIC 3141",
      wishlistItems: [
        { name: "Scandinavia Lounge Chair", price: "$350.00", image: "assets/img/products/chair.jpg" },
        { name: "Artisan Brass Candle Holder", price: "$45.00", image: "assets/img/products/candle-holder.jpg" }
      ],
      abandonedCart: null,
      requestsHistory: [
        { id: "REQ-2041", type: "Sourcing Request", date: "Oct 02, 2026", status: "Pending Review" }
      ]
    },
    {
      id: "CUST-1002",
      name: "Liam Thorne",
      email: "liam.t@example.com",
      phone: "+61 498 765 432",
      totalOrders: 3,
      totalSpent: 520.00,
      averageOrderValue: 173.33,
      lastOrderDate: "Oct 07, 2026",
      lastOrderId: "ORD-9481",
      status: "Active",
      createdDate: "Mar 20, 2025",
      address: "18 Collins Street, Apt 4B, Melbourne, VIC 3000",
      wishlistItems: [],
      abandonedCart: null,
      requestsHistory: []
    },
    {
      id: "CUST-1003",
      name: "Sophia Chen",
      email: "sophia.c@example.com",
      phone: "+61 433 111 222",
      totalOrders: 4,
      totalSpent: 2150.00,
      averageOrderValue: 537.50,
      lastOrderDate: "Oct 06, 2026",
      lastOrderId: "ORD-9480",
      status: "Active",
      createdDate: "Nov 04, 2024",
      address: "45 Ocean Drive, Manly, NSW 2095",
      wishlistItems: [
        { name: "Minimalist Oak Dining Table", price: "$340.00", image: "assets/img/products/oak-table.jpg" }
      ],
      abandonedCart: {
        cartId: "CRT-7749",
        date: "Oct 04, 2026",
        itemsCount: 2,
        total: 410.00
      },
      requestsHistory: [
        { id: "REQ-2039", type: "Bulk Order", date: "Sep 28, 2026", status: "In Discussion" }
      ]
    },
    {
      id: "CUST-1004",
      name: "Marcus Brody",
      email: "marcus@example.com",
      phone: "+61 422 999 888",
      totalOrders: 2,
      totalSpent: 380.00,
      averageOrderValue: 190.00,
      lastOrderDate: "Oct 06, 2026",
      lastOrderId: "ORD-9479",
      status: "Active",
      createdDate: "Feb 15, 2026",
      address: "12 Beach Road, St Kilda, VIC 3182",
      wishlistItems: [],
      abandonedCart: null,
      requestsHistory: []
    },
    {
      id: "CUST-1005",
      name: "Hannah Abbott",
      email: "hannah.a@example.com",
      phone: "+61 411 000 333",
      totalOrders: 1,
      totalSpent: 45.00,
      averageOrderValue: 45.00,
      lastOrderDate: "Oct 05, 2026",
      lastOrderId: "ORD-9478",
      status: "Inactive",
      createdDate: "Aug 10, 2026",
      address: "89 Park Street, Brisbane, QLD 4000",
      wishlistItems: [],
      abandonedCart: {
        cartId: "CRT-7742",
        date: "Oct 05, 2026",
        itemsCount: 1,
        total: 150.00
      },
      requestsHistory: []
    }
  ],

  // ----------------------------------------------------
  // ABANDONED CARTS DATA
  // ----------------------------------------------------
  abandonedCarts: [
    {
      id: "CRT-7750",
      customerId: "CUST-1008",
      customerName: "Aura Design Studio",
      customerEmail: "contact@auradesign.com.au",
      customerPhone: "+61 2 9876 5432",
      customerType: "Registered",
      itemsCount: 4,
      totalValue: 1240.00,
      lastActivity: "2 hours ago (Oct 07, 10:45 AM)",
      abandonedTime: "Oct 07, 2026 10:45 AM",
      status: "Abandoned",
      items: [
        {
          id: "PRD-OAK-01",
          name: "Minimalist Oak Dining Table",
          sku: "FUR-OAK-ESP-8S",
          color: "Espresso Walnut",
          hex: "#3B2B23",
          size: "8-Seater",
          quantity: 2,
          unitPrice: 450.00,
          total: 900.00
        },
        {
          id: "FUR-CHR-09",
          name: "Scandinavia Lounge Chair",
          sku: "FUR-CHR-OAK",
          color: "Beige Linen",
          hex: "#E5DDD3",
          size: "Single Seat",
          quantity: 1,
          unitPrice: 340.00,
          total: 340.00
        }
      ],
      activity: [
        { time: "Oct 07, 10:45 AM", text: "Customer left checkout page without completing payment." },
        { time: "Oct 07, 10:42 AM", text: "Entered shipping address details." },
        { time: "Oct 07, 10:35 AM", text: "Added Minimalist Oak Dining Table (x2) to cart." },
        { time: "Oct 07, 10:30 AM", text: "Cart session created." }
      ]
    },
    {
      id: "CRT-7749",
      customerId: "CUST-1003",
      customerName: "Sophia Chen",
      customerEmail: "sophia.c@example.com",
      customerPhone: "+61 433 111 222",
      customerType: "Registered",
      itemsCount: 2,
      totalValue: 410.00,
      lastActivity: "1 day ago (Oct 06, 03:20 PM)",
      abandonedTime: "Oct 06, 2026 03:20 PM",
      status: "Abandoned",
      items: [
        {
          id: "TEX-BLN-12",
          name: "Linen Throw Blanket (Beige)",
          sku: "TEX-BLN-BGE-L",
          color: "Warm Cream",
          hex: "#FAF7F2",
          size: "Large",
          quantity: 2,
          unitPrice: 50.00,
          total: 100.00
        },
        {
          id: "HOM-TEA-04",
          name: "Ceramic Artisan Tea Set",
          sku: "HOM-TEA-GRN",
          color: "Sage Green",
          hex: "#8A9A86",
          size: "Standard Set",
          quantity: 1,
          unitPrice: 60.00,
          total: 60.00
        }
      ],
      activity: [
        { time: "Oct 06, 03:20 PM", text: "Abandoned cart at item review phase." },
        { time: "Oct 06, 03:15 PM", text: "Added Linen Throw Blanket." }
      ]
    },
    {
      id: "CRT-7745",
      customerId: null,
      customerName: "Guest Visitor (#8491)",
      customerEmail: "guest8491@gmail.com",
      customerPhone: "Not provided",
      customerType: "Guest",
      itemsCount: 1,
      totalValue: 350.00,
      lastActivity: "2 days ago (Oct 05, 06:10 PM)",
      abandonedTime: "Oct 05, 2026 06:10 PM",
      status: "Abandoned",
      items: [
        {
          id: "FUR-CHR-09",
          name: "Scandinavia Lounge Chair",
          sku: "FUR-CHR-OAK",
          color: "Beige Linen",
          hex: "#E5DDD3",
          size: "Single Seat",
          quantity: 1,
          unitPrice: 350.00,
          total: 350.00
        }
      ],
      activity: [
        { time: "Oct 05, 06:10 PM", text: "Guest user closed tab." }
      ]
    }
  ]

};
