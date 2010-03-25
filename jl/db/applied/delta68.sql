BEGIN;

CREATE TABLE recently_viewed (
    id serial PRIMARY KEY,
    journo_id integer REFERENCES journo(id) ON DELETE CASCADE,
    view_time timestamp NOT NULL DEFAULT NOW()
);

COMMIT;

