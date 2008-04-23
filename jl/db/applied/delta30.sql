-- Add 'approved' and 'type' columns to journo_weblink.

ALTER TABLE journo_weblink
    
    ADD COLUMN approved  BOOLEAN  DEFAULT false,
        -- approved by an admin?
    
    ADD COLUMN type      TEXT     DEFAULT 'manual-edit'
        -- eg. 'wikipedia:journo'
;
