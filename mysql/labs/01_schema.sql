-- shop_lab schema for 7-day MySQL tutorial
-- MySQL 8.0+ / 8.4 recommended
-- charset: utf8mb4

CREATE DATABASE IF NOT EXISTS shop_lab
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE shop_lab;

SET NAMES utf8mb4;

DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS order_addresses;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email      VARCHAR(128)    NOT NULL,
  name       VARCHAR(64)     NOT NULL,
  status     TINYINT         NOT NULL DEFAULT 1 COMMENT '1=active 0=disabled',
  created_at DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
               ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  UNIQUE KEY uk_users_email (email),
  KEY idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='users';

CREATE TABLE products (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sku        VARCHAR(64)     NOT NULL,
  name       VARCHAR(128)    NOT NULL,
  price      DECIMAL(12,2)   NOT NULL,
  stock      INT UNSIGNED    NOT NULL DEFAULT 0,
  status     TINYINT         NOT NULL DEFAULT 1 COMMENT '1=on sale 0=off',
  created_at DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
               ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  UNIQUE KEY uk_products_sku (sku),
  KEY idx_products_status_price (status, price)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='product master data';

CREATE TABLE orders (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_no     VARCHAR(32)     NOT NULL,
  user_id      BIGINT UNSIGNED NOT NULL,
  status       TINYINT         NOT NULL DEFAULT 0
                 COMMENT '0=pending 1=paid 2=shipped 3=done 4=cancelled',
  total_amount DECIMAL(12,2)   NOT NULL,
  created_at   DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at   DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
                 ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  UNIQUE KEY uk_orders_order_no (order_no),
  KEY idx_orders_user_created (user_id, created_at, id),
  KEY idx_orders_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='order header';

CREATE TABLE order_items (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id     BIGINT UNSIGNED NOT NULL,
  product_id   BIGINT UNSIGNED NOT NULL,
  product_name VARCHAR(128)    NOT NULL COMMENT 'snapshot',
  unit_price   DECIMAL(12,2)   NOT NULL COMMENT 'snapshot',
  quantity     INT UNSIGNED    NOT NULL,
  created_at   DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  KEY idx_order_items_order_id (order_id),
  KEY idx_order_items_product_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='order lines with price snapshot';

CREATE TABLE order_addresses (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id       BIGINT UNSIGNED NOT NULL,
  receiver_name  VARCHAR(64)     NOT NULL,
  receiver_phone VARCHAR(32)     NOT NULL,
  province       VARCHAR(64)     NOT NULL,
  city           VARCHAR(64)     NOT NULL,
  district       VARCHAR(64)     NOT NULL,
  address_line1  VARCHAR(255)    NOT NULL,
  address_line2  VARCHAR(255)    NULL,
  created_at     DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  UNIQUE KEY uk_order_addresses_order_id (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='shipping address snapshot per order';
