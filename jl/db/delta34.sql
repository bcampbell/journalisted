-- oops... forgot to make article_commentlink and article_bloglink foreign
-- keys "on delete cascade" (so that the rows will automatically be deleted
-- when the article they refer to is deleted).

ALTER TABLE article_commentlink DROP CONSTRAINT article_commentlink_article_id_fkey;
ALTER TABLE article_commentlink ADD FOREIGN KEY (article_id) REFERENCES article(id) ON DELETE CASCADE;


ALTER TABLE article_bloglink DROP CONSTRAINT article_bloglink_article_id_fkey;
ALTER TABLE article_bloglink ADD FOREIGN KEY (article_id) REFERENCES article(id) ON DELETE CASCADE;

