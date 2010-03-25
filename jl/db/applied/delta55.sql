CREATE TABLE person_permission (
    id serial PRIMARY KEY,
    person_id integer NOT NULL REFERENCES person(id),
    journo_id integer REFERENCES journo(id),
    permission text
);

