
CREATE TABLE journo_other_articles (
    id SERIAL PRIMARY KEY,
    journo_id integer NOT NULL REFERENCES journo(id) ON DELETE CASCADE,
    url text NOT NULL,
    title text NOT NULL,
    pubdate timestamp NOT NULL,
    publication text,
    status CHARACTER(1) NOT NULL default 'u'
);

CREATE INDEX journo_other_articles_journo_id_idx ON journo_other_articles(journo_id);



