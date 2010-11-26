BEGIN;

CREATE TABLE link (
    id serial PRIMARY KEY,
    url text,
    title text,
    pubdate timestamp,
    publication text
);

COMMIT;

