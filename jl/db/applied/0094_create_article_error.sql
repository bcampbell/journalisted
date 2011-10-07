BEGIN;


CREATE TABLE article_error (
  id serial PRIMARY KEY,
  url text NOT NULL,
  reason_code text NOT NULL,
  submitted timestamp NOT NULL DEFAULT NOW(),
  submitted_by integer REFERENCES person(id) ON DELETE SET NULL,
  article_id integer REFERENCES article(id) ON DELETE SET NULL,
  expected_journo integer REFERENCES journo(id) ON DELETE SET NULL
);

CREATE INDEX article_error_url_idx ON article_error(url);
CREATE INDEX article_error_expected_journo_idx ON article_error(expected_journo);
CREATE INDEX article_error_article_id_idx ON article_error(article_id);
CREATE INDEX article_error_submitted_by_idx ON article_error(submitted_by);
COMMIT;

