CREATE TABLE article_image (
    id SERIAL PRIMARY KEY,
    article_id integer NOT NULL REFERENCES article(id) ON DELETE CASCADE,
    url text NOT NULL,
    caption text NOT NULL DEFAULT ''
);
