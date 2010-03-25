BEGIN;

CREATE TABLE article_needs_indexing (
   article_id integer references article(id) ON DELETE CASCADE PRIMARY KEY
);

INSERT INTO article_needs_indexing (article_id) SELECT id FROM article WHERE needs_indexing=true;

END;


