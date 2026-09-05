-- Read-only integrity checks for DeductibleLog (PostgreSQL, table prefix oc_).
-- Run inside the Nextcloud AIO database container, e.g.:
--   docker exec -i nextcloud-aio-database psql -U nextcloud -d nextcloud_database < integrity-check.sql
-- Every query should return zero rows on a healthy database.

-- 1. Item-donation header total that disagrees with the sum of its lines,
--    or a donation with no lines at all (v0.1.3-era partial writes).
SELECT d.id, d.user_id, d.tax_year, d.date, d.total_value,
       COALESCE(SUM(l.total_value), 0) AS lines_total, COUNT(l.id) AS n_lines
FROM oc_deductiblelog_item_donations d
LEFT JOIN oc_deductiblelog_item_donation_lines l ON l.donation_id = d.id
GROUP BY d.id
HAVING d.total_value <> COALESCE(SUM(l.total_value), 0) OR COUNT(l.id) = 0;

-- 2. Line rows whose parent donation is gone.
SELECT l.*
FROM oc_deductiblelog_item_donation_lines l
LEFT JOIN oc_deductiblelog_item_donations d ON d.id = l.donation_id
WHERE d.id IS NULL;

-- 3. Line total that is not quantity × unit value.
SELECT id, donation_id, quantity, unit_value, total_value
FROM oc_deductiblelog_item_donation_lines
WHERE total_value <> ROUND(unit_value * quantity, 2);

-- 4. Donations pointing at a charity that no longer exists (or another user's).
SELECT 'cash' AS kind, d.id, d.user_id, d.charity_id, d.date, d.amount AS value
FROM oc_deductiblelog_cash_donations d
LEFT JOIN oc_deductiblelog_charities c ON c.id = d.charity_id AND c.user_id = d.user_id
WHERE c.id IS NULL
UNION ALL
SELECT 'item', d.id, d.user_id, d.charity_id, d.date, d.total_value
FROM oc_deductiblelog_item_donations d
LEFT JOIN oc_deductiblelog_charities c ON c.id = d.charity_id AND c.user_id = d.user_id
WHERE c.id IS NULL;

-- 5. Records pointing at a family member that no longer exists.
SELECT 'mileage' AS kind, r.id, r.user_id, r.family_member_id FROM oc_deductiblelog_mileage_logs r
LEFT JOIN oc_deductiblelog_family_members f ON f.id = r.family_member_id AND f.user_id = r.user_id
WHERE r.family_member_id IS NOT NULL AND f.id IS NULL
UNION ALL
SELECT 'medical', r.id, r.user_id, r.family_member_id FROM oc_deductiblelog_medical_expenses r
LEFT JOIN oc_deductiblelog_family_members f ON f.id = r.family_member_id AND f.user_id = r.user_id
WHERE r.family_member_id IS NOT NULL AND f.id IS NULL
UNION ALL
SELECT 'business', r.id, r.user_id, r.family_member_id FROM oc_deductiblelog_business_expenses r
LEFT JOIN oc_deductiblelog_family_members f ON f.id = r.family_member_id AND f.user_id = r.user_id
WHERE r.family_member_id IS NOT NULL AND f.id IS NULL;

-- 6. tax_year that disagrees with the record's date (wrong-year return exposure).
SELECT 'cash' AS kind, id, user_id, date, tax_year FROM oc_deductiblelog_cash_donations WHERE EXTRACT(YEAR FROM date) <> tax_year
UNION ALL SELECT 'item', id, user_id, date, tax_year FROM oc_deductiblelog_item_donations WHERE EXTRACT(YEAR FROM date) <> tax_year
UNION ALL SELECT 'mileage', id, user_id, date, tax_year FROM oc_deductiblelog_mileage_logs WHERE EXTRACT(YEAR FROM date) <> tax_year
UNION ALL SELECT 'medical', id, user_id, date, tax_year FROM oc_deductiblelog_medical_expenses WHERE EXTRACT(YEAR FROM date) <> tax_year
UNION ALL SELECT 'business', id, user_id, date, tax_year FROM oc_deductiblelog_business_expenses WHERE EXTRACT(YEAR FROM date) <> tax_year;

-- 7. Mileage rows with a zero rate or a deduction that is not miles × rate.
SELECT id, user_id, date, miles, rate_cents, deduction_amount
FROM oc_deductiblelog_mileage_logs
WHERE rate_cents <= 0 OR deduction_amount <> ROUND(miles * rate_cents / 100, 2);

-- 8. Receipt rows whose parent record is gone.
SELECT r.* FROM oc_deductiblelog_receipts r
WHERE (r.entity_type = 'cash_donation' AND NOT EXISTS (SELECT 1 FROM oc_deductiblelog_cash_donations x WHERE x.id = r.entity_id AND x.user_id = r.user_id))
   OR (r.entity_type = 'item_donation' AND NOT EXISTS (SELECT 1 FROM oc_deductiblelog_item_donations x WHERE x.id = r.entity_id AND x.user_id = r.user_id))
   OR (r.entity_type = 'mileage'       AND NOT EXISTS (SELECT 1 FROM oc_deductiblelog_mileage_logs x WHERE x.id = r.entity_id AND x.user_id = r.user_id))
   OR (r.entity_type = 'medical'       AND NOT EXISTS (SELECT 1 FROM oc_deductiblelog_medical_expenses x WHERE x.id = r.entity_id AND x.user_id = r.user_id))
   OR (r.entity_type = 'business'      AND NOT EXISTS (SELECT 1 FROM oc_deductiblelog_business_expenses x WHERE x.id = r.entity_id AND x.user_id = r.user_id));
