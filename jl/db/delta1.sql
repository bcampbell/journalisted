ALTER TABLE journo ADD COLUMN created timestamp;
UPDATE journo SET created=now();
ALTER TABLE journo ALTER COLUMN created SET NOT NULL;

