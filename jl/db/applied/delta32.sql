-- Make sure we can search by url or by journo in journo_weblink
BEGIN;
CREATE INDEX journo_weblink_idx_journo_id  ON journo_weblink(journo_id);
CREATE INDEX journo_weblink_idx_url        ON journo_weblink(url);
COMMIT;
