-- v7: Potvrda e-maila pri registraciji + reset lozinke (sigurni tokeni).
-- Tokeni se čuvaju SAMO kao SHA-256 hash (u linku je plaintext); jednokratni + istek.
-- Postojeći računi se grandfathaju (smatraju potvrđenima) da se nitko ne zaključa.
ALTER TABLE customers ADD COLUMN email_verified_at DATETIME NULL;
UPDATE customers SET email_verified_at = created_at WHERE email_verified_at IS NULL;

CREATE TABLE IF NOT EXISTS customer_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    type ENUM('verify','reset') NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ct_hash (token_hash),
    INDEX idx_ct_cust (customer_id, type),
    CONSTRAINT fk_ct_cust FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_croatian_ci;
