BEGIN;

CREATE TABLE pub_set (
    id serial PRIMARY KEY,
    name text NOT NULL
);


CREATE TABLE pub_set_map (
    id serial PRIMARY KEY,
    pub_id integer REFERENCES organisation(id) ON DELETE CASCADE,
    pub_set_id integer REFERENCES pub_set(id) ON DELETE CASCADE
);

COMMIT;
