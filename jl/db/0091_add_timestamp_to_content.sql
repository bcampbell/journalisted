BEGIN;
ALTER TABLE article_content ADD COLUMN scraped timestamp;
UPDATE article_content SET scraped=article.lastscraped FROM article WHERE article.id=article_content.article_id;
ALTER TABLE article_content ALTER COLUMN scraped SET NOT NULL;
CREATE INDEX article_content_scraped_idx ON article_content(scraped);
COMMIT;

