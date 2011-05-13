BEGIN;

-- find duplicate permalinks
-- select id,wordcount,permalink from article where permalink in (select permalink from article group by permalink having count(permalink)>1) order by permalink;

-- plonk existing urls into article_url:
-- BEGIN;
-- INSERT INTO article_url (url,article_id) (SELECT permalink AS url, id AS article_id FROM article UNION SELECT srcurl AS url, id AS article_id FROM article);
-- COMMIT;





BEGIN;
-- use a temp table to move all permalink and srcurl values into the new article_url table
CREATE TABLE tmp_article_urls ( url text NOT NULL, article_id integer );
INSERT INTO tmp_article_urls (url,article_id) (SELECT permalink AS url, id AS article_id FROM article);
INSERT INTO tmp_article_urls (url,article_id) (SELECT srcurl AS url, id AS article_id FROM article);
-- index to speed up deduping
-- CREATE INDEX tmp_article_urls_url_article_id_idx ON tmp_article_urls (url, article_id);

CREATE TABLE article_url (
  id serial PRIMARY KEY,
  url text NOT NULL,
  article_id integer NOT NULL REFERENCES article(id) ON DELETE CASCADE
);

INSERT INTO article_url (url,article_id) (SELECT DISTINCT url,article_id FROM tmp_article_urls);

CREATE INDEX article_url_article_id_idx ON article_url (article_id);
CREATE INDEX article_url_url_idx ON article_url (url);

COMMIT;

