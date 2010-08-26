BEGIN;

CREATE TABLE org_alias (
  id serial PRIMARY KEY,
  org_id integer NOT NULL REFERENCES organisation(id) ON DELETE CASCADE,
  alias text NOT NULL
);

CREATE INDEX org_alias_alias_idx ON org_alias (alias);

CREATE TABLE org_domain (
  id serial PRIMARY KEY,
  org_id integer NOT NULL REFERENCES organisation(id) ON DELETE CASCADE,
  domain text NOT NULL
);

CREATE INDEX org_domain_domain_idx ON org_domain (domain);


CREATE TABLE org_email_format (
  id serial PRIMARY KEY,
  org_id integer NOT NULL REFERENCES organisation(id) ON DELETE CASCADE,
  fmt text NOT NULL
);

CREATE TABLE org_phone (
  id serial PRIMARY KEY,
  org_id integer NOT NULL REFERENCES organisation(id) ON DELETE CASCADE,
  phone text NOT NULL
);





COMMIT;

