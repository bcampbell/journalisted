-- add fields to article table which keep track of the total number of
-- bloglinks and comments

BEGIN;

ALTER TABLE article ADD COLUMN total_bloglinks INTEGER DEFAULT 0 NOT NULL;
ALTER TABLE article ADD COLUMN total_comments INTEGER DEFAULT 0 NOT NULL;

-- populate with intial values...
UPDATE article a SET total_bloglinks = ( SELECT COUNT(*) FROM article_bloglink WHERE article_id=a.id) WHERE a.id IN (SELECT DISTINCT article_id FROM article_bloglink);


UPDATE article a SET total_comments = ( SELECT SUM( COALESCE(num_comments,0) ) FROM article_commentlink WHERE article_id=a.id ) WHERE a.id IN (SELECT DISTINCT article_id FROM article_commentlink);


-- Track number of blog links to articles.
-- Add triggers to update total_bloglinks field in article table whenever
-- the article_bloglink table changes.


CREATE OR REPLACE FUNCTION article_update_total_bloglinks()
RETURNS TRIGGER AS $$
    BEGIN
        IF TG_OP = 'INSERT' THEN
            UPDATE article SET total_bloglinks=total_bloglinks+1 WHERE id=new.article_id;
        END IF;

        IF TG_OP = 'DELETE' THEN
            UPDATE article SET total_bloglinks=total_bloglinks-1 WHERE id=old.article_id;
        END IF;

        RETURN new;
    END
$$ language 'plpgsql';

DROP TRIGGER IF EXISTS article_update_total_bloglinks_on_insert ON article_bloglink;
DROP TRIGGER IF EXISTS article_update_total_bloglinks_on_delete ON article_bloglink;

CREATE TRIGGER article_update_total_bloglinks_on_insert AFTER INSERT ON article_bloglink FOR EACH ROW
EXECUTE PROCEDURE article_update_total_bloglinks();

CREATE TRIGGER article_update_total_bloglinks_on_delete AFTER DELETE ON article_bloglink FOR EACH ROW
EXECUTE PROCEDURE article_update_total_bloglinks();



-- Track number of comments on articles.
-- Add triggers to update total_comments field in article table whenever
-- the article_commentlink table changes.


CREATE OR REPLACE FUNCTION article_update_total_comments()
RETURNS TRIGGER AS $$
    BEGIN
        IF TG_OP = 'INSERT' THEN
            UPDATE article SET total_comments=total_comments+new.num_comments WHERE id=new.article_id;
        END IF;

        IF TG_OP = 'DELETE' THEN
            UPDATE article SET total_comments=total_comments-old.num_comments WHERE id=old.article_id;
        END IF;

        IF TG_OP = 'UPDATE' THEN
            UPDATE article SET total_comments=total_comments+new.num_comments-old.num_comments WHERE id=old.article_id;
        END IF;

        RETURN new;
    END
$$ language 'plpgsql';


DROP TRIGGER IF EXISTS article_update_total_comments_on_insert ON article_commentlink;
DROP TRIGGER IF EXISTS article_update_total_comments_on_delete ON article_commentlink;
DROP TRIGGER IF EXISTS article_update_total_comments_on_update ON article_commentlink;


CREATE TRIGGER article_update_total_comments_on_insert AFTER INSERT ON article_commentlink FOR EACH ROW
EXECUTE PROCEDURE article_update_total_comments();

CREATE TRIGGER article_update_total_comments_on_delete AFTER DELETE ON article_commentlink FOR EACH ROW
EXECUTE PROCEDURE article_update_total_comments();

CREATE TRIGGER article_update_total_comments_on_update AFTER UPDATE ON article_commentlink FOR EACH ROW
EXECUTE PROCEDURE article_update_total_comments();

COMMIT;

