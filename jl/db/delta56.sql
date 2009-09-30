CREATE TABLE journo_admired (
    id serial PRIMARY KEY,
    journo_id integer NOT NULL REFERENCES journo(id),
    admired_name text,
    admired_id integer REFERENCES journo(id)
);

