/**
 * Aus Ecom Requests Module Data Store
 * Backend-ready data structures for Sourcing Requests & Bulk Orders
 */

window.RequestsData = {
  
  // ----------------------------------------------------
  // SOURCING REQUESTS
  // ----------------------------------------------------
  sourcingRequests: [
    {
      id: "REQ-2041",
      date: "Oct 07, 2026 09:30 AM",
      customerId: "CUST-1001",
      customerName: "Eleanor Vance",
      customerEmail: "eleanor@example.com",
      customerPhone: "+61 412 345 678",
      companyName: "Aura Living Studio",
      requestedItem: "Custom Solid Oak Executive Desk (200x90cm)",
      category: "Furniture",
      quantity: 5,
      targetBudget: 3500.00,
      deliveryLocation: "South Yarra, VIC 3141",
      status: "New", // New, Reviewing, In Progress, Completed, Rejected
      priority: "High", // Low, Medium, High, Urgent
      description: "We are looking to source 5 solid white oak executive desks with matte oil finish and integrated cable management channels for our office redesign.",
      images: [
        "assets/img/products/oak-table.jpg"
      ],
      adminNotes: "Contacted supplier for timber availability. Waiting for quote.",
      timeline: [
        { time: "Oct 07, 2026 09:30 AM", title: "Sourcing Request Submitted", desc: "Submitted via customer portal." }
      ]
    },
    {
      id: "REQ-2038",
      date: "Oct 05, 2026 02:15 PM",
      customerId: "CUST-1006",
      customerName: "David Miller",
      customerEmail: "david@habitatcraft.com",
      customerPhone: "+61 400 555 777",
      companyName: "Habitat Craft Inc.",
      requestedItem: "Handwoven Jute Rugs (Custom 3x4m size)",
      category: "Textiles",
      quantity: 12,
      targetBudget: 2400.00,
      deliveryLocation: "Brisbane, QLD 4000",
      status: "Reviewing",
      priority: "Medium",
      description: "Require 12 custom size natural jute area rugs for boutique hotel lobby project.",
      images: [],
      adminNotes: "Sent specs to artisan weaver network.",
      timeline: [
        { time: "Oct 06, 2026 10:00 AM", title: "Moved to Reviewing", desc: "Assigned to procurement team." },
        { time: "Oct 05, 2026 02:15 PM", title: "Request Received", desc: "Request logged." }
      ]
    },
    {
      id: "REQ-2035",
      date: "Oct 01, 2026 11:00 AM",
      customerId: "CUST-1007",
      customerName: "Clara Oswald",
      customerEmail: "clara@nordicinteriors.com",
      customerPhone: "+61 422 333 444",
      companyName: "Nordic Interiors Co.",
      requestedItem: "Brass Pendant Lights (Custom Brushed Finish)",
      category: "Decor",
      quantity: 30,
      targetBudget: 4500.00,
      deliveryLocation: "Sydney, NSW 2000",
      status: "In Progress",
      priority: "Urgent",
      description: "Sourcing 30 brushed brass pendant light fixtures with warm LED modules.",
      images: [],
      adminNotes: "Sample approved by customer. Final order production underway.",
      timeline: [
        { time: "Oct 04, 2026 03:00 PM", title: "Status Updated: In Progress", desc: "Factory order placed." },
        { time: "Oct 01, 2026 11:00 AM", title: "Request Received", desc: "Logged." }
      ]
    }
  ],

  // ----------------------------------------------------
  // BULK ORDERS
  // ----------------------------------------------------
  bulkOrders: [
    {
      id: "BLK-4010",
      date: "Oct 06, 2026 03:45 PM",
      customerId: "CUST-1003",
      customerName: "Sophia Chen",
      customerEmail: "sophia.c@example.com",
      customerPhone: "+61 433 111 222",
      companyName: "Urban Design Lab",
      abnTaxId: "ABN 48 920 194 821",
      deliveryAddress: "45 Ocean Drive, Manly, NSW 2095",
      status: "Quotation Sent", // New, Reviewing, Quotation Sent, Confirmed, Processing, Completed, Rejected
      priority: "High",
      totalQuantity: 30,
      estimatedValue: 10200.00,
      quotedValue: 9500.00,
      requestedProducts: [
        {
          id: "PRD-OAK-01",
          name: "Minimalist Oak Dining Table",
          sku: "FUR-OAK-OLV-6S",
          color: "Olive Green",
          hex: "#6F765F",
          size: "6-Seater",
          quantity: 10,
          targetUnitPrice: 340.00,
          total: 3400.00
        },
        {
          id: "FUR-CHR-09",
          name: "Scandinavia Lounge Chair",
          sku: "FUR-CHR-OAK",
          color: "Beige Linen",
          hex: "#E5DDD3",
          size: "Single Seat",
          quantity: 20,
          targetUnitPrice: 340.00,
          total: 6800.00
        }
      ],
      notes: "Commercial fitout project requiring delivery by Nov 20, 2026. Requesting bulk discount quote.",
      adminNotes: "Quotation #QT-9921 sent with 7% bulk discount ($9,500 total). Awaiting customer confirmation.",
      timeline: [
        { time: "Oct 07, 2026 11:30 AM", title: "Quotation Sent ($9,500.00)", desc: "Formal quote emailed to customer." },
        { time: "Oct 06, 2026 03:45 PM", title: "Bulk Order Request Received", desc: "Request logged." }
      ]
    },
    {
      id: "BLK-4008",
      date: "Oct 04, 2026 01:20 PM",
      customerId: "CUST-1002",
      customerName: "Liam Thorne",
      customerEmail: "liam.t@example.com",
      customerPhone: "+61 498 765 432",
      companyName: "Thorne Hospitality Group",
      abnTaxId: "ABN 12 345 678 901",
      deliveryAddress: "18 Collins Street, Melbourne, VIC 3000",
      status: "Confirmed",
      priority: "Urgent",
      totalQuantity: 100,
      estimatedValue: 6000.00,
      quotedValue: 5400.00,
      requestedProducts: [
        {
          id: "HOM-TEA-04",
          name: "Ceramic Artisan Tea Set",
          sku: "HOM-TEA-GRN",
          color: "Sage Green",
          hex: "#8A9A86",
          size: "Standard Set",
          quantity: 100,
          targetUnitPrice: 54.00,
          total: 5400.00
        }
      ],
      notes: "Bulk order for luxury hotel room amenities.",
      adminNotes: "Deposit payment received. Production batch scheduled.",
      timeline: [
        { time: "Oct 05, 2026 04:00 PM", title: "Order Confirmed & Deposit Received", desc: "50% deposit captured." },
        { time: "Oct 04, 2026 01:20 PM", title: "Bulk Request Logged", desc: "Initial inquiry." }
      ]
    }
  ]

};
