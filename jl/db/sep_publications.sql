BEGIN;

CREATE TABLE pub_alias (
  id serial PRIMARY KEY,
  pub_id integer NOT NULL REFERENCES organisation(id) ON DELETE CASCADE,
  alias text NOT NULL
);

CREATE INDEX pub_alias_alias_idx ON pub_alias (alias);

CREATE TABLE pub_domain (
  id serial PRIMARY KEY,
  pub_id integer NOT NULL REFERENCES organisation(id) ON DELETE CASCADE,
  domain text NOT NULL
);

CREATE INDEX pub_domain_domain_idx ON pub_domain (domain);


CREATE TABLE pub_email_format (
  id serial PRIMARY KEY,
  pub_id integer NOT NULL REFERENCES organisation(id) ON DELETE CASCADE,
  fmt text NOT NULL
);

CREATE TABLE pub_phone (
  id serial PRIMARY KEY,
  pub_id integer NOT NULL REFERENCES organisation(id) ON DELETE CASCADE,
  phone text NOT NULL
);





COMMIT;

