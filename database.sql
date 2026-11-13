CREATE DATABASE IF NOT EXISTS inventory_app
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE inventory_app;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(40) NOT NULL,
    name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    sku VARCHAR(64) NOT NULL,
    category VARCHAR(100) DEFAULT NULL,
    unit_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    reorder_level INT UNSIGNED NOT NULL DEFAULT 5,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_sku (sku),
    KEY idx_products_active_name (is_active, name),
    CONSTRAINT fk_products_created_by FOREIGN KEY (created_by)
        REFERENCES users (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_in (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    movement_date DATE NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(20, 2) NOT NULL,
    note VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_stock_in_date (movement_date, id),
    KEY idx_stock_in_product (product_id),
    CONSTRAINT chk_stock_in_quantity CHECK (quantity > 0),
    CONSTRAINT fk_stock_in_product FOREIGN KEY (product_id)
        REFERENCES products (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_stock_in_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_out (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    movement_date DATE NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(20, 2) NOT NULL,
    note VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_stock_out_date (movement_date, id),
    KEY idx_stock_out_product (product_id),
    CONSTRAINT chk_stock_out_quantity CHECK (quantity > 0),
    CONSTRAINT fk_stock_out_product FOREIGN KEY (product_id)
        REFERENCES products (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_stock_out_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT IGNORE INTO users (username, name, password_hash)
VALUES (
    '25RP00508',
    '25RP00508',
    '$2y$10$KtPRx8VDWuGKy7Dx3SFpyOpuEb3bGjQ8rVHuCI6gq3aggCYdcbGaS'
);
