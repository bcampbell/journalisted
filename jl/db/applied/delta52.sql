CREATE TABLE missing_articles (
    id serial NOT NULL PRIMARY KEY,
    journo_id integer REFERENCES journo(id) ON DELETE CASCADE,  -- could be null
    url text NOT NULL,
    submitted timestamp NOT NULL default NOW()
);

