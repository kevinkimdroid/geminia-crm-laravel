-- Run this manually if php artisan migrate fails (e.g. DB config issue)
-- Execute in your vtiger/MySQL database

CREATE TABLE IF NOT EXISTS maturities_cache (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    policy_number VARCHAR(64) NULL,
    life_assured VARCHAR(255) NULL,
    product VARCHAR(255) NULL,
    maturity DATE NULL,
    synced_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX (policy_number),
    INDEX (product),
    INDEX (maturity),
    INDEX (maturity, product)
);
