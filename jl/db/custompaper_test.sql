DROP TABLE custompaper_criteria_journo;
DROP TABLE custompaper_criteria_text;
DROP TABLE custompaper;

CREATE TABLE custompaper (
    id SERIAL PRIMARY KEY,
    owner integer NOT NULL REFERENCES person(id),
    name text NOT NULL DEFAULT '',
    description text NOT NULL DEFAULT '',
    is_public boolean NOT NULL default false
);

CREATE TABLE custompaper_criteria_journo (
    id SERIAL PRIMARY KEY,
    paper_id integer NOT NULL REFERENCES custompaper(id) ON DELETE CASCADE,
    journo_id integer NOT NULL REFERENCES journo(id) ON DELETE CASCADE
);

CREATE TABLE custompaper_criteria_text (
    id SERIAL PRIMARY KEY,
    paper_id integer NOT NULL REFERENCES custompaper(id) ON DELETE CASCADE,
    query text NOT NULL
);

