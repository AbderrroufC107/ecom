CREATE TABLE IF NOT EXISTS `incomplete_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(255) NOT NULL,
  `customer_phone` varchar(50) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `unit_price` float DEFAULT NULL,
  `total_price` float DEFAULT NULL,
  `selected_size` varchar(50) DEFAULT NULL,
  `selected_color` varchar(50) DEFAULT NULL,
  `wilaya` varchar(100) DEFAULT NULL,
  `commune` varchar(100) DEFAULT NULL,
  `delivery_type` varchar(50) DEFAULT NULL,
  `last_updated` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
