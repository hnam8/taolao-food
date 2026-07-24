CREATE DATABASE IF NOT EXISTS taolao_food_db;
USE taolao_food_db;

-- Table 1: users
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('customer', 'admin') NOT NULL DEFAULT 'customer'
);

-- Table 2: menu_items
CREATE TABLE menu_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255) NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1  -- soft delete thay vì xoá cứng
);

-- Table 3: orders
CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Pending', -- Pending, Preparing, Done
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table 4: order_details
CREATE TABLE order_details (
    detail_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    item_id INT NOT NULL,
    item_name_snapshot VARCHAR(100) NOT NULL, -- snapshot pattern: bảo toàn tên món tại thời điểm mua
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,          -- giá tại thời điểm mua, không tham chiếu giá hiện tại
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES menu_items(item_id) ON DELETE SET NULL
);

-- Mock data để test
INSERT INTO menu_items (item_name, description, price, image_url) VALUES
('Phở Bò', 'Phở bò truyền thống Hà Nội', 45000.00, 'assets/images/pho.jpg'),
('Bánh Mì Thịt Nướng', 'Bánh mì kẹp thịt nướng, rau thơm', 25000.00, 'assets/images/banhmi.jpg'),
('Cơm Tấm Sườn', 'Cơm tấm sườn nướng, trứng ốp la', 40000.00, 'assets/images/comtam.jpg'),
('Trà Sữa Trân Châu', 'Trà sữa truyền thống', 30000.00, 'assets/images/tra.jpg');