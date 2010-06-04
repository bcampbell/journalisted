BEGIN;

CREATE table article_content (
    id serial PRIMARY KEY,
    article_id integer REFERENCES article(id),
    content text DEFAULT '' NOT NULL
);

CREATE INDEX article_content_article_id_idx ON article_content( article_id );

COMMIT;

