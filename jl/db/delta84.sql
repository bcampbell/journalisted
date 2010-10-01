BEGIN;

CREATE table pub_adr (
    id serial PRIMARY KEY,
    pub_id integer REFERENCES organisation(id) ON DELETE CASCADE,
    adr text DEFAULT '' NOT NULL
);

CREATE INDEX pub_adr_pub_id_idx ON pub_adr( pub_id );
COMMIT;

