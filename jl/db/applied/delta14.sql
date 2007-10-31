-- field to classify tags
ALTER TABLE article_tag ADD COLUMN kind character(1) NOT NULL DEFAULT ' ';

CREATE INDEX article_tag_article_id_idx ON article_tag(article_id);
CREATE INDEX article_tag_tag_idx ON article_tag(tag);

