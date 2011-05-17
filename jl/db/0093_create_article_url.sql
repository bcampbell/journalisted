BEGIN;

-- find duplicate permalinks
-- select id,wordcount,permalink from article where permalink in (select permalink from article group by permalink having count(permalink)>1) order by permalink;

-- plonk existing urls into article_url:
-- BEGIN;
-- INSERT INTO article_url (url,article_id) (SELECT permalink AS url, id AS article_id FROM article UNION SELECT srcurl AS url, id AS article_id FROM article);
-- COMMIT;


CREATE TABLE article_url (
  id serial PRIMARY KEY,
  url text NOT NULL,
  article_id integer NOT NULL REFERENCES article(id) ON DELETE CASCADE
);

-- USE ../hacks/dump_article_urls to migrate over article URLS into new table
-- (was too slow doing it all in DB). Might want to leave these indexes
-- off until the data is loaded in.

CREATE INDEX article_url_article_id_idx ON article_url (article_id);
CREATE INDEX article_url_url_idx ON article_url (url);
COMMIT;

