-- ==========================================================================
-- DATABASE SCHEMA FOR SUPERGIRLS FOOD CITY MANAGEMENT SYSTEM
-- Target Database: sg_food_city_db
-- ==========================================================================

CREATE DATABASE IF NOT EXISTS `sg_food_city_db`
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE `sg_food_city_db`;

-- Drop tables in reverse order of dependency to avoid constraint violations
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `bill_item`;
DROP TABLE IF EXISTS `inventory`;
DROP TABLE IF EXISTS `bill`;
DROP TABLE IF EXISTS `customer`;
DROP TABLE IF EXISTS `security`;
DROP TABLE IF EXISTS `cashier`;
DROP TABLE IF EXISTS `admin`;
DROP TABLE IF EXISTS `employee`;
DROP TABLE IF EXISTS `product`;
DROP TABLE IF EXISTS `category`;
DROP TABLE IF EXISTS `sg_food_city`;
SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================================================
-- 1. Table: sg_food_city (Branch Identification)
-- ==========================================================================
CREATE TABLE `sg_food_city` (
  `branch_id` INT AUTO_INCREMENT PRIMARY KEY,
  `branch_code` VARCHAR(20) UNIQUE NOT NULL COMMENT 'e.g. SFC-MAIN, SFC-EAST',
  `branch_name` VARCHAR(100) NOT NULL,
  `address` VARCHAR(255) NOT NULL,
  `contact` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================================
-- 2. Table: category (Product Categories)
-- ==========================================================================
CREATE TABLE `category` (
  `category_id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_name` VARCHAR(100) UNIQUE NOT NULL,
  `division` VARCHAR(100) DEFAULT NULL COMMENT 'Aisle location or division name',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================================
-- 3. Table: product (Inventory Items/POS Catalog)
-- ==========================================================================
CREATE TABLE `product` (
  `product_id` INT AUTO_INCREMENT PRIMARY KEY,
  `sku` VARCHAR(20) UNIQUE NOT NULL COMMENT 'e.g. SFC-0010',
  `product_name` VARCHAR(150) NOT NULL,
  `price` DECIMAL(10, 2) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 0,
  `category_id` INT NOT NULL,
  `icon` VARCHAR(50) DEFAULT 'fa-box' COMMENT 'CSS FontAwesome Icon class name',
  `status` VARCHAR(20) DEFAULT 'in-stock' COMMENT 'in-stock, low-stock, out-of-stock',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Constraints & Checks
  CONSTRAINT `chk_product_price` CHECK (`price` >= 0),
  CONSTRAINT `chk_product_qty` CHECK (`quantity` >= 0),
  CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) 
    REFERENCES `category` (`category_id`) 
    ON DELETE RESTRICT 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for performance optimization
CREATE INDEX `idx_product_sku` ON `product` (`sku`);
CREATE INDEX `idx_product_category` ON `product` (`category_id`);

-- ==========================================================================
-- 4. Table: employee (Core Staff Directory)
-- ==========================================================================
CREATE TABLE `employee` (
  `employee_id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_code` VARCHAR(20) UNIQUE NOT NULL COMMENT 'e.g. EMP001',
  `employee_name` VARCHAR(100) NOT NULL,
  `contact` VARCHAR(20) NOT NULL,
  `address` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'cashier', 'inventory', 'security') NOT NULL,
  `password` VARCHAR(255) NOT NULL COMMENT 'Hashed password using password_hash()',
  `status` VARCHAR(20) DEFAULT 'Active' COMMENT 'Active, Suspended, Inactive',
  `managed_by` INT DEFAULT NULL COMMENT 'Self-referencing manager or admin reference',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================================
-- 5. Table: admin (Administrator Details)
-- ==========================================================================
CREATE TABLE `admin` (
  `admin_id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL COMMENT 'Duplicated or override credentials for admin login',
  `email` VARCHAR(100) UNIQUE NOT NULL,
  
  CONSTRAINT `fk_admin_employee` FOREIGN KEY (`employee_id`) 
    REFERENCES `employee` (`employee_id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Now add the managed_by constraint to employee pointing to admin.admin_id
ALTER TABLE `employee`
ADD CONSTRAINT `fk_employee_manager` FOREIGN KEY (`managed_by`)
  REFERENCES `admin` (`admin_id`)
  ON DELETE SET NULL
  ON UPDATE CASCADE;

-- ==========================================================================
-- 6. Table: cashier (Cashier Details)
-- ==========================================================================
CREATE TABLE `cashier` (
  `cashier_id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT UNIQUE NOT NULL,
  `contact` VARCHAR(20) NOT NULL,
  `address` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  
  CONSTRAINT `fk_cashier_employee` FOREIGN KEY (`employee_id`) 
    REFERENCES `employee` (`employee_id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================================
-- 7. Table: security (Security Personnel Details)
-- ==========================================================================
CREATE TABLE `security` (
  `security_id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT UNIQUE NOT NULL,
  `contact` VARCHAR(20) NOT NULL,
  `address` VARCHAR(255) NOT NULL,
  `email` VARCHAR(100) UNIQUE NOT NULL,
  
  CONSTRAINT `fk_security_employee` FOREIGN KEY (`employee_id`) 
    REFERENCES `employee` (`employee_id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================================
-- 8. Table: customer (Customer Registry)
-- ==========================================================================
CREATE TABLE `customer` (
  `customer_id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_code` VARCHAR(20) UNIQUE DEFAULT NULL COMMENT 'e.g. cust01 or walkin',
  `customer_name` VARCHAR(100) NOT NULL,
  `contact` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `address` VARCHAR(255) NOT NULL,
  `customer_type` ENUM('Standard', 'Loyalty Basic', 'Loyalty Premium') DEFAULT 'Standard',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================================
-- 9. Table: bill (POS Invoices)
-- ==========================================================================
CREATE TABLE `bill` (
  `bill_id` INT AUTO_INCREMENT PRIMARY KEY,
  `bill_code` VARCHAR(20) UNIQUE NOT NULL COMMENT 'e.g. TX-48192834',
  `bill_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `total_amount` DECIMAL(10, 2) NOT NULL,
  `cashier_id` INT DEFAULT NULL,
  `customer_id` INT DEFAULT NULL,
  `payment_method` ENUM('CASH', 'CARD', 'MOBILE') DEFAULT 'CASH',
  `discount_percentage` INT DEFAULT 0,
  
  CONSTRAINT `chk_bill_total` CHECK (`total_amount` >= 0),
  CONSTRAINT `chk_bill_discount` CHECK (`discount_percentage` BETWEEN 0 AND 100),
  CONSTRAINT `fk_bill_cashier` FOREIGN KEY (`cashier_id`) 
    REFERENCES `cashier` (`cashier_id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_bill_customer` FOREIGN KEY (`customer_id`) 
    REFERENCES `customer` (`customer_id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_bill_code` ON `bill` (`bill_code`);
CREATE INDEX `idx_bill_date` ON `bill` (`bill_date`);

-- ==========================================================================
-- 10. Table: inventory (Stock Ledger Tracking & Status Updates)
-- ==========================================================================
CREATE TABLE `inventory` (
  `inventory_id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNIQUE NOT NULL,
  `status` VARCHAR(50) NOT NULL COMMENT 'in-stock, low-stock, out-of-stock',
  `stock_out` INT NOT NULL DEFAULT 0 COMMENT 'Total count/frequency of stock-out events',
  `branch_id` INT DEFAULT NULL,
  `last_updated_by_admin_id` INT DEFAULT NULL,
  `last_restocked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  CONSTRAINT `chk_inventory_stockout` CHECK (`stock_out` >= 0),
  CONSTRAINT `fk_inventory_product` FOREIGN KEY (`product_id`) 
    REFERENCES `product` (`product_id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_inventory_branch` FOREIGN KEY (`branch_id`) 
    REFERENCES `sg_food_city` (`branch_id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_inventory_admin` FOREIGN KEY (`last_updated_by_admin_id`) 
    REFERENCES `admin` (`admin_id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================================
-- 11. Table: bill_item (Many-to-Many relationship details for Bills/Products)
-- ==========================================================================
CREATE TABLE `bill_item` (
  `bill_item_id` INT AUTO_INCREMENT PRIMARY KEY,
  `bill_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `unit_price` DECIMAL(10, 2) NOT NULL,
  `subtotal` DECIMAL(10, 2) NOT NULL,
  
  CONSTRAINT `chk_item_qty` CHECK (`quantity` > 0),
  CONSTRAINT `chk_item_price` CHECK (`unit_price` >= 0),
  CONSTRAINT `fk_item_bill` FOREIGN KEY (`bill_id`) 
    REFERENCES `bill` (`bill_id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_item_product` FOREIGN KEY (`product_id`) 
    REFERENCES `product` (`product_id`) 
    ON DELETE RESTRICT 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_item_bill` ON `bill_item` (`bill_id`);

-- ==========================================================================
-- SAMPLE DATA INSERTION
-- ==========================================================================

-- 1. Insert Branch details
INSERT INTO `sg_food_city` (`branch_code`, `branch_name`, `address`, `contact`, `email`) VALUES
('SFC-HQ', 'Supergirls Food City - Main Branch', '101 Empress Boulevard, Colombo 03', '+94 11 2345678', 'colombo.hq@supergirls.com'),
('SFC-EAST', 'Supergirls Food City - Eastern Mall', '45 Shopping Complex, Batticaloa', '+94 65 9876543', 'batticaloa.east@supergirls.com');

-- 2. Insert Categories
INSERT INTO `category` (`category_name`, `division`) VALUES
('Dairy & Eggs', 'Aisle 1 - Dairy Coolers'),
('Fruits & Vegetables', 'Section A - Fresh Produce Hub'),
('Bakery & Bread', 'Aisle 2 - Bread Displays'),
('Snacks & Sweets', 'Aisle 3 - Confectionery Row'),
('Beverages', 'Aisle 4 - Chilled Drinks'),
('Household & Cleaning', 'Aisle 8 - Chemical Shelving'),
('Baking & Grains', 'Aisle 5 - Dry Pantry');

-- 3. Insert Products
INSERT INTO `product` (`sku`, `product_name`, `price`, `quantity`, `category_id`, `icon`, `status`) VALUES
('SFC-0010', 'Premium Organic Milk 1L', 3.49, 120, 1, 'fa-cow', 'in-stock'),
('SFC-0011', 'Red Delicious Apples 1kg', 4.99, 45, 2, 'fa-apple-whole', 'in-stock'),
('SFC-0012', 'Artisanal Sourdough Bread', 3.75, 12, 3, 'fa-bread-slice', 'low-stock'),
('SFC-0013', 'Double Choc-Chip Cookies 250g', 2.89, 0, 4, 'fa-cookie', 'out-of-stock'),
('SFC-0014', 'Natural Greek Yogurt 500g', 4.25, 85, 1, 'fa-cow', 'in-stock'),
('SFC-0015', 'Fresh Hass Avocados (3 Pack)', 5.99, 8, 2, 'fa-apple-whole', 'low-stock'),
('SFC-0016', 'Diet Lemon-Lime Soda 6x330ml', 6.49, 140, 5, 'fa-mug-hot', 'in-stock'),
('SFC-0017', 'Eco-Friendly Dish Liquid 750ml', 3.99, 65, 6, 'fa-soap', 'in-stock'),
('SFC-0018', 'Pure Cane Sugar 1kg', 1.99, 110, 7, 'fa-box', 'in-stock'),
('SFC-0019', 'English Breakfast Tea Bags (50)', 4.50, 3, 5, 'fa-mug-hot', 'low-stock'),
('SFC-0020', 'Lavazza Espresso Coffee Beans 1kg', 18.99, 0, 5, 'fa-mug-hot', 'out-of-stock');

-- 4. Insert Employees
-- Passwords below are hashed versions of the plain-text string role names:
-- 'admin'     => $2y$10$w09aYgE8R/e/J3G4W5J8JeL0q1.qV6WqD142G6W5a1zG5M8lJy...
-- 'cashier'   => $2y$10$tM7wE1eLh3bB7Vl8J5OeN1Q8iW0.5fH24h4U654V3kG2a6s3t4u...
-- 'inventory' => $2y$10$y5O7m4f9X6OueLuF23N1Q8iW0.5fH24h4U654V3kG2a6s3t4u5v...
-- 'security'  => $2y$10$1P9L8tN1eLh3bB7Vl8J5OeN1Q8iW0.5fH24h4U654V3kG2a6s3t4u...
INSERT INTO `employee` (`employee_code`, `employee_name`, `contact`, `address`, `role`, `password`, `status`) VALUES
('EMP001', 'Sarah Jenkins', '+94 77 1234567', '78 Queens Road, Colombo 03', 'admin', '$2y$10$PndvT3M.iZ.a7YcT6w9fWOB6H.0jJt234uV654a1zG5M8lJy6g5i.', 'Active'),
('EMP002', 'Michael Chen', '+94 77 2345678', '12 Galle Road, Colombo 04', 'cashier', '$2y$10$04mF2eLh3bB7Vl8J5OeN1Q8iW0.5fH24h4U654V3kG2a6s3t4u5v.', 'Active'),
('EMP003', 'Jessica Rodriguez', '+94 77 3456789', '56 Flower Road, Colombo 07', 'inventory', '$2y$10$LuF23N1Q8iW0.5fH24h4U654V3kG2a6s3t4u5v.y5O7m4f9X6Oue', 'Active'),
('EMP004', 'David Kross', '+94 77 4567890', '90 Temple Road, Maharagama', 'security', '$2y$10$1P9L8tN1eLh3bB7Vl8J5OeN1Q8iW0.5fH24h4U654V3kG2a6s3t4u', 'Active'),
('EMP005', 'Emily Watson', '+94 77 5678901', '34 Duplication Road, Colombo 03', 'cashier', '$2y$10$04mF2eLh3bB7Vl8J5OeN1Q8iW0.5fH24h4U654V3kG2a6s3t4u5v.', 'Active');

-- 5. Insert Admins
INSERT INTO `admin` (`employee_id`, `password`, `email`) VALUES
(1, '$2y$10$PndvT3M.iZ.a7YcT6w9fWOB6H.0jJt234uV654a1zG5M8lJy6g5i.', 's.jenkins@supergirls.com');

-- Update the manager field in employee for the remaining employees (Sarah Jenkins manages the rest)
UPDATE `employee` SET `managed_by` = 1 WHERE `employee_id` > 1;

-- 6. Insert Cashiers
INSERT INTO `cashier` (`employee_id`, `contact`, `address`, `password`) VALUES
(2, '+94 77 2345678', '12 Galle Road, Colombo 04', '$2y$10$04mF2eLh3bB7Vl8J5OeN1Q8iW0.5fH24h4U654V3kG2a6s3t4u5v.'),
(5, '+94 77 5678901', '34 Duplication Road, Colombo 03', '$2y$10$04mF2eLh3bB7Vl8J5OeN1Q8iW0.5fH24h4U654V3kG2a6s3t4u5v.');

-- 7. Insert Security
INSERT INTO `security` (`employee_id`, `contact`, `address`, `email`) VALUES
(4, '+94 77 4567890', '90 Temple Road, Maharagama', 'd.kross@supergirls.com');

-- 8. Insert Customers
INSERT INTO `customer` (`customer_code`, `customer_name`, `contact`, `email`, `address`, `customer_type`) VALUES
('walkin', 'Walk-in Customer', '-', '-', '-', 'Standard'),
('cust01', 'Jane Doe', '+1 (555) 012-3456', 'jane.doe@gmail.com', '123 Forest Lane, NY', 'Loyalty Basic'),
('cust02', 'John Smith', '+1 (555) 987-6543', 'j.smith@yahoo.com', '456 Oak Avenue, CA', 'Loyalty Premium'),
('cust03', 'Alice Mercer', '+1 (555) 456-7890', 'alice.mercer@outlook.com', '789 Pine Road, TX', 'Loyalty Basic');

-- 9. Insert Inventory records synced with products
INSERT INTO `inventory` (`product_id`, `status`, `stock_out`, `branch_id`, `last_updated_by_admin_id`) VALUES
(1, 'in-stock', 0, 1, 1),
(2, 'in-stock', 0, 1, 1),
(3, 'low-stock', 2, 1, 1),
(4, 'out-of-stock', 15, 1, 1),
(5, 'in-stock', 0, 1, 1),
(6, 'low-stock', 4, 1, 1),
(7, 'in-stock', 0, 1, 1),
(8, 'in-stock', 0, 1, 1),
(9, 'in-stock', 0, 1, 1),
(10, 'low-stock', 1, 1, 1),
(11, 'out-of-stock', 8, 1, 1);

-- 10. Insert Sample Bills
INSERT INTO `bill` (`bill_code`, `bill_date`, `total_amount`, `cashier_id`, `customer_id`, `payment_method`, `discount_percentage`) VALUES
('TX-89201934', '2026-06-04 10:15:00', 10.47, 1, 2, 'CASH', 0),
('TX-47392812', '2026-06-04 11:05:00', 21.84, 2, 3, 'CARD', 10);

-- 11. Insert Sample Bill Items
INSERT INTO `bill_item` (`bill_id`, `product_id`, `quantity`, `unit_price`, `subtotal`) VALUES
(1, 1, 3, 3.49, 10.47), -- 3 x Milk @ 3.49
(2, 2, 2, 4.99, 9.98),  -- 2 x Apples @ 4.99
(2, 5, 2, 5.99, 11.98); -- 2 x Avocados @ 5.99

-- 12. Create Supplier table and items
CREATE TABLE IF NOT EXISTS `supplier` (
  `supplier_id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_name` varchar(100) NOT NULL,
  `contact` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `address` varchar(255) NOT NULL,
  `product` varchar(255) NOT NULL,
  `brand` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `supplier` (`supplier_name`, `contact`, `email`, `address`, `product`, `brand`) VALUES
('Cargills Ceylon PLC', '0112429200', 'info@cargillsceylon.com', '40 York Street, Colombo 1', 'Fresh Milk, Dairy Drinks', 'Kotmale'),
('Ceylon Biscuits Limited', '0115000000', 'cbl@ceylonbiscuits.com', 'High Level Road, Pannipitiya', 'Cream Crackers, Marie Biscuits', 'Munchee');
