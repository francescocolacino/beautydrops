-- BeautyDrops — schema database
-- Eseguire su MySQL/MariaDB per creare le tabelle necessarie.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category ENUM('cosmetici','elettronica','abbigliamento') NOT NULL,
  brand VARCHAR(100) NOT NULL,
  name VARCHAR(150) NOT NULL,
  variants JSON NULL,
  image_path VARCHAR(255),
  stock_quantity INT NOT NULL DEFAULT 0,
  orderable_quantity INT NOT NULL DEFAULT 0,
  price DECIMAL(10,2) NULL,
  description TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_products_category (category),
  INDEX idx_products_brand (brand)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  offer_price DECIMAL(10,2) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offer_products (
  offer_id INT NOT NULL,
  product_id INT NOT NULL,
  PRIMARY KEY (offer_id, product_id),
  FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin di default: admin@beautydrops.it / beautydrops123
-- IMPORTANTE: cambiare la password al primo accesso in produzione.
INSERT INTO admins (email, password_hash) VALUES
  ('admin@beautydrops.it', '$2y$10$D5PoQY7e/lEKtxJrng5lWu3esriMRx/gxqiLkOJs3HseAg3Mvofhi')
ON DUPLICATE KEY UPDATE email = email;
