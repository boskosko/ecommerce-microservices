-- Create databases for each service
CREATE DATABASE IF NOT EXISTS user_service;
CREATE DATABASE IF NOT EXISTS order_service;
CREATE DATABASE IF NOT EXISTS payment_service;

-- Create user for services (opciono - za production)
-- CREATE USER IF NOT EXISTS 'laravel'@'%' IDENTIFIED BY 'secret';
-- GRANT ALL PRIVILEGES ON user_service.* TO 'laravel'@'%';
-- GRANT ALL PRIVILEGES ON order_service.* TO 'laravel'@'%';
-- GRANT ALL PRIVILEGES ON payment_service.* TO 'laravel'@'%';
-- FLUSH PRIVILEGES;