BEGIN;
DROP TABLE IF EXISTS news;

CREATE TABLE news (
    id serial PRIMARY KEY,
    status char(1) NOT NULL DEFAULT 'u',
    title text NOT NULL DEFAULT '',
    author text NOT NULL DEFAULT '',
    slug text NOT NULL DEFAULT '',
    posted timestamp NOT NULL DEFAULT NOW(),
    content text NOT NULL DEFAULT ''
);

COMMIT;

