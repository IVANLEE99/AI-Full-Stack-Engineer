-- Daily practice skeletons for 7-day MySQL tutorial
-- Try yourself first; reference answers are below each section.
USE shop_lab;

/* =========================================================
   Day 01 — DDL / CRUD
   ========================================================= */

-- Q1: create tags table (id, name unique, created_at)
-- YOUR SQL:


-- Ref:
-- CREATE TABLE tags (
--   id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
--   name VARCHAR(64) NOT NULL,
--   created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
--   UNIQUE KEY uk_tags_name (name)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


/* =========================================================
   Day 02 — filter / aggregate
   ========================================================= */

-- Q1: top 5 on-sale products price >= 100, price desc
-- YOUR SQL:


-- Ref:
-- SELECT id, name, price
-- FROM products
-- WHERE status = 1 AND price >= 100
-- ORDER BY price DESC
-- LIMIT 5;

-- Q2: order count by status
-- Ref:
-- SELECT status, COUNT(*) AS cnt
-- FROM orders
-- GROUP BY status
-- ORDER BY status;

-- Q3: users with paid_sum conceptually via HAVING on order count >= 2
-- Ref:
-- SELECT user_id, COUNT(*) AS cnt, SUM(total_amount) AS amount_sum
-- FROM orders
-- WHERE status IN (1, 2, 3)
-- GROUP BY user_id
-- HAVING COUNT(*) >= 2
-- ORDER BY amount_sum DESC;


/* =========================================================
   Day 03 — JOIN / subquery
   ========================================================= */

-- Q1: order lines with user and city
-- Ref:
-- SELECT o.order_no, u.name AS user_name, a.city, oi.product_name, oi.quantity
-- FROM orders o
-- JOIN users u ON u.id = o.user_id
-- LEFT JOIN order_addresses a ON a.order_id = o.id
-- JOIN order_items oi ON oi.order_id = o.id
-- ORDER BY o.id, oi.id;

-- Q2: item_cnt / item_qty per order
-- Ref:
-- SELECT o.order_no,
--        COUNT(oi.id) AS item_cnt,
--        COALESCE(SUM(oi.quantity), 0) AS item_qty
-- FROM orders o
-- LEFT JOIN order_items oi ON oi.order_id = o.id
-- GROUP BY o.id, o.order_no;

-- Q3: users with no orders
-- Ref:
-- SELECT u.id, u.name
-- FROM users u
-- LEFT JOIN orders o ON o.user_id = u.id
-- WHERE o.id IS NULL;

-- Q4: users who bought USB-C 线
-- Ref:
-- SELECT DISTINCT u.email
-- FROM users u
-- JOIN orders o ON o.user_id = u.id
-- JOIN order_items oi ON oi.order_id = o.id
-- WHERE oi.product_name = 'USB-C 线';


/* =========================================================
   Day 05 — EXPLAIN
   ========================================================= */

-- Run and record type/key/rows/Extra:
EXPLAIN
SELECT id, order_no, status, total_amount, created_at
FROM orders
WHERE user_id = 1
ORDER BY created_at DESC, id DESC
LIMIT 10;

EXPLAIN FORMAT=TREE
SELECT id, order_no, status, total_amount, created_at
FROM orders
WHERE user_id = 1
ORDER BY created_at DESC, id DESC
LIMIT 10;


/* =========================================================
   Day 06 — transaction demo (single session)
   ========================================================= */

-- START TRANSACTION;
-- UPDATE products SET stock = stock - 1 WHERE id = 3 AND stock >= 1;
-- INSERT INTO orders (order_no, user_id, status, total_amount)
-- VALUES ('O-LAB-TX-001', 1, 0, 29.90);
-- -- ROLLBACK;  -- try rollback first
-- -- COMMIT;
