-- trigger functions to set the journo "modified" flag, to indicate that
-- the cached version of the page needs rebuilding
--
-- changes to any of the below tables should cause the flag to be set
--  journo_attr
--  journo_bio
--  journo_weblinks
--  journo_email
--  journo_similar
--  journo_other_articles
-- All the above tables have the same journo_id PK, so we can just use
-- the same function(s) to handle them all. yay!
--
-- also, want journo flagged "modified" if an attributed article is updated
-- So a separate function required.


CREATE OR REPLACE FUNCTION journo_setmodified_oninsert() RETURNS TRIGGER AS $$
BEGIN
    UPDATE journo SET modified=true WHERE id=NEW.journo_id;
    return NULL;
END;
$$ LANGUAGE 'plpgsql';

CREATE OR REPLACE FUNCTION journo_setmodified_ondelete() RETURNS TRIGGER AS $$
BEGIN
    UPDATE journo SET modified=true WHERE id=OLD.journo_id;
    return NULL;
END;
$$ LANGUAGE 'plpgsql';

CREATE OR REPLACE FUNCTION journo_setmodified_onupdate() RETURNS TRIGGER AS $$
BEGIN
    UPDATE journo SET modified=true WHERE id=NEW.journo_id;
    UPDATE journo SET modified=true WHERE id=OLD.journo_id;
    return NULL;
END;
$$ LANGUAGE 'plpgsql';

-- journo_attr triggers
DROP TRIGGER IF EXISTS journo_attr_insert ON journo_attr;
DROP TRIGGER IF EXISTS journo_attr_delete ON journo_attr;
DROP TRIGGER IF EXISTS journo_attr_update ON journo_attr;

CREATE TRIGGER journo_attr_insert AFTER INSERT ON journo_attr FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_oninsert();
CREATE TRIGGER journo_attr_delete AFTER DELETE ON journo_attr FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_ondelete();
CREATE TRIGGER journo_attr_update AFTER UPDATE ON journo_attr FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_onupdate();

-- journo_bio triggers
DROP TRIGGER IF EXISTS journo_bio_insert ON journo_bio;
DROP TRIGGER IF EXISTS journo_bio_delete ON journo_bio;
DROP TRIGGER IF EXISTS journo_bio_update ON journo_bio;

CREATE TRIGGER journo_bio_insert AFTER INSERT ON journo_bio FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_oninsert();
CREATE TRIGGER journo_bio_delete AFTER DELETE ON journo_bio FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_ondelete();
CREATE TRIGGER journo_bio_update AFTER UPDATE ON journo_bio FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_onupdate();

-- journo_email triggers
DROP TRIGGER IF EXISTS journo_email_insert ON journo_email;
DROP TRIGGER IF EXISTS journo_email_delete ON journo_email;
DROP TRIGGER IF EXISTS journo_email_update ON journo_email;

CREATE TRIGGER journo_email_insert AFTER INSERT ON journo_email FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_oninsert();
CREATE TRIGGER journo_email_delete AFTER DELETE ON journo_email FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_ondelete();
CREATE TRIGGER journo_email_update AFTER UPDATE ON journo_email FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_onupdate();

-- journo_other_articles triggers
DROP TRIGGER IF EXISTS journo_other_articles_insert ON journo_other_articles;
DROP TRIGGER IF EXISTS journo_other_articles_delete ON journo_other_articles;
DROP TRIGGER IF EXISTS journo_other_articles_update ON journo_other_articles;

CREATE TRIGGER journo_other_articles_insert AFTER INSERT ON journo_other_articles FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_oninsert();
CREATE TRIGGER journo_other_articles_delete AFTER DELETE ON journo_other_articles FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_ondelete();
CREATE TRIGGER journo_other_articles_update AFTER UPDATE ON journo_other_articles FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_onupdate();

-- journo_similar triggers
DROP TRIGGER IF EXISTS journo_similar_insert ON journo_similar;
DROP TRIGGER IF EXISTS journo_similar_delete ON journo_similar;
DROP TRIGGER IF EXISTS journo_similar_update ON journo_similar;

CREATE TRIGGER journo_similar_insert AFTER INSERT ON journo_similar FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_oninsert();
CREATE TRIGGER journo_similar_delete AFTER DELETE ON journo_similar FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_ondelete();
CREATE TRIGGER journo_similar_update AFTER UPDATE ON journo_similar FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_onupdate();

-- journo_weblink triggers
DROP TRIGGER IF EXISTS journo_weblink_insert ON journo_weblink;
DROP TRIGGER IF EXISTS journo_weblink_delete ON journo_weblink;
DROP TRIGGER IF EXISTS journo_weblink_update ON journo_weblink;

CREATE TRIGGER journo_weblink_insert AFTER INSERT ON journo_weblink FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_oninsert();
CREATE TRIGGER journo_weblink_delete AFTER DELETE ON journo_weblink FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_ondelete();
CREATE TRIGGER journo_weblink_update AFTER UPDATE ON journo_weblink FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_onupdate();



-- article table trigger
CREATE OR REPLACE FUNCTION article_setjournomodified_onupdate() RETURNS TRIGGER AS $$
BEGIN
    -- whenever article is modified, set the modified flag on any attributed jounos
    UPDATE journo SET modified=true WHERE id IN (SELECT journo_id FROM journo_attr WHERE article_id=NEW.id);
    return NULL;
END;
$$ LANGUAGE 'plpgsql';

DROP TRIGGER IF EXISTS article_update ON article;
CREATE TRIGGER article_update AFTER UPDATE ON article FOR EACH ROW EXECUTE PROCEDURE article_setjournomodified_onupdate();

