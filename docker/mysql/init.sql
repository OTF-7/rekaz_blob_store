-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS rekaz CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user and grant privileges
CREATE USER IF NOT EXISTS 'rekaz_user'@'%' IDENTIFIED BY 'rekaz_password';
GRANT ALL PRIVILEGES ON rekaz.* TO 'rekaz_user'@'%';

-- Grant privileges for testing database
GRANT ALL PRIVILEGES ON rekaz_test.* TO 'rekaz_user'@'%';

-- Flush privileges
FLUSH PRIVILEGES;

-- Use the rekaz database
USE rekaz;

-- Set default charset and collation
ALTER DATABASE rekaz CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;