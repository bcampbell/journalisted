CREATE TABLE journo_admired (
    id serial PRIMARY KEY,
    journo_id integer NOT NULL REFERENCES journo(id),
    admired_name text,
    admired_id integer REFERENCES journo(id)
);


CREATE TABLE journo_education (
    id serial PRIMARY KEY,
    journo_id integer NOT NULL REFERENCES journo(id),
    school text NOT NULL default '',
    field text NOT NULL default '',
    qualification text NOT NULL default '',
    year_from smallint,
    year_to smallint
);

CREATE TABLE journo_employment (
    id serial PRIMARY KEY,
    journo_id integer NOT NULL REFERENCES journo(id),
    employer text NOT NULL default '',
    job_title text NOT NULL default '',
    year_from smallint,
    year_to smallint
);



CREATE TABLE journo_awards (
    id serial PRIMARY KEY,
    journo_id integer NOT NULL REFERENCES journo(id),
    award text NOT NULL default ''
);


CREATE TABLE journo_books (
    id serial PRIMARY KEY,
    journo_id integer NOT NULL REFERENCES journo(id),
    title text NOT NULL default '',
    publisher text NOT NULL default '',
    year_published smallint
);

