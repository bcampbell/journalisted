BEGIN;
CREATE TABLE journo_pageviews (
    id serial PRIMARY KEY,
    journo_id integer NOT NULL REFERENCES journo(id) ON DELETE CASCADE,
    num_views_week integer NOT NULL default 0
);

CREATE INDEX journo_pageviews_journo_id_idx ON journo_pageviews(journo_id);

COMMIT;

