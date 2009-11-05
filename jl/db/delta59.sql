-- uses fns from delta51.sql

BEGIN;

-- journo_admired triggers
DROP TRIGGER IF EXISTS journo_admired_insert ON journo_admired;
DROP TRIGGER IF EXISTS journo_admired_delete ON journo_admired;
DROP TRIGGER IF EXISTS journo_admired_update ON journo_admired;
CREATE TRIGGER journo_admired_insert AFTER INSERT ON journo_admired FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_oninsert();
CREATE TRIGGER journo_admired_delete AFTER DELETE ON journo_admired FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_ondelete();
CREATE TRIGGER journo_admired_update AFTER UPDATE ON journo_admired FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_onupdate();


-- journo_awards triggers
DROP TRIGGER IF EXISTS journo_awards_insert ON journo_awards;
DROP TRIGGER IF EXISTS journo_awards_delete ON journo_awards;
DROP TRIGGER IF EXISTS journo_awards_update ON journo_awards;
CREATE TRIGGER journo_awards_insert AFTER INSERT ON journo_awards FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_oninsert();
CREATE TRIGGER journo_awards_delete AFTER DELETE ON journo_awards FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_ondelete();
CREATE TRIGGER journo_awards_update AFTER UPDATE ON journo_awards FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_onupdate();

-- journo_books triggers
DROP TRIGGER IF EXISTS journo_books_insert ON journo_books;
DROP TRIGGER IF EXISTS journo_books_delete ON journo_books;
DROP TRIGGER IF EXISTS journo_books_update ON journo_books;
CREATE TRIGGER journo_books_insert AFTER INSERT ON journo_books FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_oninsert();
CREATE TRIGGER journo_books_delete AFTER DELETE ON journo_books FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_ondelete();
CREATE TRIGGER journo_books_update AFTER UPDATE ON journo_books FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_onupdate();

-- journo_education triggers
DROP TRIGGER IF EXISTS journo_education_insert ON journo_education;
DROP TRIGGER IF EXISTS journo_education_delete ON journo_education;
DROP TRIGGER IF EXISTS journo_education_update ON journo_education;
CREATE TRIGGER journo_education_insert AFTER INSERT ON journo_education FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_oninsert();
CREATE TRIGGER journo_education_delete AFTER DELETE ON journo_education FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_ondelete();
CREATE TRIGGER journo_education_update AFTER UPDATE ON journo_education FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_onupdate();

-- journo_employment triggers
DROP TRIGGER IF EXISTS journo_employment_insert ON journo_employment;
DROP TRIGGER IF EXISTS journo_employment_delete ON journo_employment;
DROP TRIGGER IF EXISTS journo_employment_update ON journo_employment;
CREATE TRIGGER journo_employment_insert AFTER INSERT ON journo_employment FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_oninsert();
CREATE TRIGGER journo_employment_delete AFTER DELETE ON journo_employment FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_ondelete();
CREATE TRIGGER journo_employment_update AFTER UPDATE ON journo_employment FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_onupdate();

-- journo_picture triggers
DROP TRIGGER IF EXISTS journo_picture_insert ON journo_picture;
DROP TRIGGER IF EXISTS journo_picture_delete ON journo_picture;
DROP TRIGGER IF EXISTS journo_picture_update ON journo_picture;
CREATE TRIGGER journo_picture_insert AFTER INSERT ON journo_picture FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_oninsert();
CREATE TRIGGER journo_picture_delete AFTER DELETE ON journo_picture FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_ondelete();
CREATE TRIGGER journo_picture_update AFTER UPDATE ON journo_picture FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_onupdate();

COMMIT;

