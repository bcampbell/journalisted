BEGIN;


CREATE TABLE article_error (
  id serial PRIMARY KEY,
  url text NOT NULL,
  reason_code text NOT NULL,
  extra_data text NOT NULL DEFAULT '',
  submitted timestamp NOT NULL DEFAULT NOW(),
  article_id integer REFERENCES article(id) ON DELETE CASCADE,
  expected_journo integer REFERENCES journo(id) ON DELETE CASCADE
);

CREATE INDEX article_error_url_idx ON article_error(url);
CREATE INDEX article_error_expected_journo_idx ON article_error(expected_journo);
CREATE INDEX article_error_article_id_idx ON article_error(article_id);
COMMIT;

