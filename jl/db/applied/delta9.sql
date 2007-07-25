-- email alerts

CREATE TABLE alert (
	id serial NOT NULL PRIMARY KEY,
	person_id integer NOT NULL REFERENCES person(id) ON DELETE CASCADE,
	journo_id integer REFERENCES journo(id) ON DELETE CASCADE
);

CREATE INDEX alert_person_id_idx ON alert(person_id);

