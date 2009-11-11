-- trigger fns are from delta51.sql

BEGIN;

CREATE TABLE image (
    id serial PRIMARY KEY,
    filename text NOT NULL,
    width integer,
    height integer
);

CREATE TABLE journo_picture (
    id serial PRIMARY KEY,
    journo_id integer NOT NULL REFERENCES journo(id) ON DELETE CASCADE,
    image_id integer NOT NULL REFERENCES image(id) ON DELETE CASCADE
);

-- journo_picture triggers
DROP TRIGGER IF EXISTS journo_picture_insert ON journo_picture;
DROP TRIGGER IF EXISTS journo_picture_delete ON journo_picture;
DROP TRIGGER IF EXISTS journo_picture_update ON journo_picture;
CREATE TRIGGER journo_picture_insert AFTER INSERT ON journo_picture FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_oninsert();
CREATE TRIGGER journo_picture_delete AFTER DELETE ON journo_picture FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_ondelete();
CREATE TRIGGER journo_picture_update AFTER UPDATE ON journo_picture FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_onupdate();

COMMIT;

