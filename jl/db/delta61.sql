BEGIN;

CREATE TABLE journo_phone (
    id serial PRIMARY KEY,
    journo_id integer NOT NULL REFERENCES journo(id) ON DELETE CASCADE,
    phone_number text DEFAULT '' NOT NULL
);

-- DROP TRIGGER IF EXISTS journo_phone_insert ON journo_phone;
-- DROP TRIGGER IF EXISTS journo_phone_delete ON journo_phone;
-- DROP TRIGGER IF EXISTS journo_phone_update ON journo_phone;
CREATE TRIGGER journo_phone_insert AFTER INSERT ON journo_phone FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_oninsert();
CREATE TRIGGER journo_phone_delete AFTER DELETE ON journo_phone FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_ondelete();
CREATE TRIGGER journo_phone_update AFTER UPDATE ON journo_phone FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_onupdate();


CREATE TABLE journo_address (
    id serial PRIMARY KEY,
    journo_id integer NOT NULL REFERENCES journo(id) ON DELETE CASCADE,
    address text DEFAULT '' NOT NULL
);



-- DROP TRIGGER IF EXISTS journo_address_insert ON journo_address;
-- DROP TRIGGER IF EXISTS journo_address_delete ON journo_address;
-- DROP TRIGGER IF EXISTS journo_address_update ON journo_address;
CREATE TRIGGER journo_address_insert AFTER INSERT ON journo_address FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_oninsert();
CREATE TRIGGER journo_address_delete AFTER DELETE ON journo_address FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_ondelete();
CREATE TRIGGER journo_address_update AFTER UPDATE ON journo_address FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_onupdate();


COMMIT;

