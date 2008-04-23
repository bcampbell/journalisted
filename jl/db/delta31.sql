-- I forgot to set NOT NULL on the 'approved' and 'type' columns.

ALTER TABLE journo_weblink
    ALTER COLUMN approved  SET NOT NULL,
    ALTER COLUMN type      SET NOT NULL
;
