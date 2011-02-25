BEGIN;

-- find duplicate permalinks
-- select id,wordcount,permalink from article where permalink in (select permalink from article group by permalink having count(permalink)>1) order by permalink;

-- plonk existing urls into article_url:
-- insert into article_url (SELECT permalink as url, id as article_id FROM article UNION SELECT srcurl as url, id as article_id FROM article);


CREATE TABLE article_url (
  id serial PRIMARY KEY,
  url text NOT NULL,
  article_id integer NOT NULL REFERENCES article(id) ON DELETE CASCADE
);

CREATE INDEX article_url_article_id_idx ON article_url (article_id);


COMMIT;
