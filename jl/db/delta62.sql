BEGIN;

CREATE TABLE event_log (
    id serial PRIMARY KEY,
    event_type text NOT NULL,
    event_time timestamp NOT NULL DEFAULT NOW(),
    journo_id integer DEFAULT NULL REFERENCES journo(id) ON DELETE CASCADE,
    extra text NOT NULL DEFAULT ''
);

COMMIT;

