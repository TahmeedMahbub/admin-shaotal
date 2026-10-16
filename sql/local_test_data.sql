-- LOCAL TEST DATA ONLY. Load into the scratch DB shaotal_admin_local (never the shared/remote DB):
--   mysql -uroot shaotal_admin_local < sql/ecom.sql
--   mysql -uroot shaotal_admin_local < sql/ecommerce_sample_data.sql
--   mysql -uroot shaotal_admin_local < sql/local_test_data.sql
-- Adds customers, orders, requests, etc. so every admin module has rows to exercise.
SET NAMES utf8mb4;

INSERT INTO customers (id, first_name, last_name, email, phone, password_hash, status) VALUES
 (1, 'Jane', 'Citizen', 'jane@example.com', '0400000001', '$2y$12$abcdefghijklmnopqrstuuA0000000000000000000000000000000', 'active'),
 (2, 'Sam', 'Smith', 'sam@example.com', '0400000002', '$2y$12$abcdefghijklmnopqrstuuA0000000000000000000000000000000', 'active');
INSERT INTO customer_addresses (customer_id, label, recipient_name, phone, street_address, suburb, state, postcode, is_default) VALUES
 (1, 'Home', 'Jane Citizen', '0400000001', '1 Test St', 'Sydney', 'NSW', '2000', 1);

INSERT INTO coupons (id, code, discount_type, discount_value, minimum_order_amount, usage_limit, status) VALUES
 (1, 'WELCOME10', 'percentage', 10.00, 0, 100, 'active');

INSERT INTO orders (id, order_number, customer_id, customer_email, customer_phone, shipping_recipient_name, shipping_phone, shipping_street_address, shipping_suburb, shipping_state, shipping_postcode, subtotal, discount_total, shipping_total, grand_total, payment_method, payment_status, order_status, placed_at, created_at) VALUES
 (1, 'ORD-1001', 1, 'jane@example.com', '0400000001', 'Jane Citizen', '0400000001', '1 Test St', 'Sydney', 'NSW', '2000', 52.00, 0, 9.95, 61.95, 'cod', 'pending', 'pending', NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY),
 (2, 'ORD-1002', 2, 'sam@example.com', '0400000002', 'Sam Smith', '0400000002', '2 Demo Rd', 'Melbourne', 'VIC', '3000', 78.00, 7.80, 0, 70.20, 'cod', 'paid', 'delivered', NOW() - INTERVAL 20 DAY, NOW() - INTERVAL 20 DAY);
INSERT INTO order_items (order_id, product_id, variant_id, product_name, sku, variant_color, variant_size, unit_price, unit_selling_price, quantity, line_total) VALUES
 (1, 1, 1, 'Essential Heavyweight 240GSM Tee', 'BG-0001-C001-S001', 'Black', 'S', 38.00, 26.00, 2, 52.00),
 (2, 2, NULL, 'Pure Flax Linen Casual Shirt', 'BG-0002', NULL, NULL, 55.00, 39.00, 2, 78.00);
INSERT INTO order_timeline (order_id, old_status, new_status, note) VALUES (1, NULL, 'pending', 'Order placed'), (2, NULL, 'pending', 'Order placed'), (2, 'pending', 'delivered', 'Seed');
INSERT INTO payments (order_id, provider, payment_method, amount, status) VALUES (1, 'cod', 'cod', 61.95, 'pending'), (2, 'cod', 'cod', 70.20, 'paid');

INSERT INTO carts (id, customer_id, session_token_hash, status, last_activity_at) VALUES (1, 1, SHA2('local-test-cart', 256), 'active', NOW() - INTERVAL 3 DAY);
INSERT INTO cart_items (cart_id, product_id, variant_id, quantity) VALUES (1, 1, 2, 1);
INSERT INTO wishlist_items (customer_id, product_id) VALUES (1, 3), (2, 1);
INSERT INTO newsletter_subscribers (email, status, subscribed_at, source) VALUES ('news@example.com', 'subscribed', NOW(), 'footer');
INSERT INTO contact_messages (name, email, subject, message) VALUES ('Pat Doe', 'pat@example.com', 'Delivery question', 'When will my order arrive?');
INSERT INTO product_reviews (product_id, customer_id, reviewer_name, reviewer_email, rating, title, body, status) VALUES (1, 1, 'Jane Citizen', 'jane@example.com', 5, 'Great tee', 'Soft and well made.', 'pending');

INSERT INTO sourcing_requests (id, request_number, customer_id, customer_name, email, phone, product_name, requested_quantity, target_budget, details) VALUES
 (1, 'SRC-1001', 1, 'Jane Citizen', 'jane@example.com', '0400000001', 'Handwoven jute rug', 2, 150.00, 'Looking for a 2x3m rug.');
INSERT INTO bulk_order_requests (id, request_number, customer_id, contact_name, email, phone, company_name, message) VALUES
 (1, 'BLK-1001', 2, 'Sam Smith', 'sam@example.com', '0400000002', 'Smith Gifts Pty', 'Corporate gifting order.');
INSERT INTO bulk_order_request_items (id, request_id, product_id, product_name, quantity, target_price) VALUES (1, 1, 1, 'Essential Heavyweight 240GSM Tee', 100, 20.00);
INSERT INTO bulk_order_item_variants (bulk_order_request_item_id, color_name, size_label, quantity) VALUES (1, 'Black', 'M', 60), (1, 'Black', 'L', 40);
