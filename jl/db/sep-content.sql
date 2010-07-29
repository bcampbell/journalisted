BEGIN;
INSERT INTO article_content (article_id,content) SELECT id,content from article;
COMMIT;

