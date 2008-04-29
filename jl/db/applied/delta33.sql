-- Add a 'type' column to journo_bio.

BEGIN;

ALTER TABLE journo_bio
    ADD COLUMN type   TEXT NOT NULL default 'manual-edit',
    ADD COLUMN srcurl TEXT;

UPDATE journo_bio
    SET type='wikipedia:journo' WHERE context LIKE '%"srcorgname": "wikipedia:journo"%';

UPDATE journo_bio
    SET srcurl=w.url
    FROM journo_weblink w
    WHERE journo_bio.type='wikipedia:journo'
      AND w.journo_id=journo_bio.journo_id
      AND w.type='wikipedia:journo';

ALTER TABLE journo_bio
    ALTER COLUMN srcurl SET NOT NULL;

COMMIT;
