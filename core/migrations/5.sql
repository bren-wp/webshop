-- v5: GDPR — oznaka anonimiziranog (obrisanog) računa kupca.
-- Račun se ne briše fizički (porezni/financijski zapisi narudžbi se čuvaju),
-- nego se osobni podaci anonimiziraju, a račun deaktivira (is_active=0) + deleted_at.
ALTER TABLE customers ADD COLUMN deleted_at DATETIME NULL;
