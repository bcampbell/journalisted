-- name field on htmlcache was too short for higher-id journos. up it from 10 to 32.
BEGIN;
ALTER TABLE htmlcache ALTER COLUMN name TYPE varchar(32);
COMMIT;

