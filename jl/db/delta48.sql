

CREATE TABLE journo_similar (
    journo_id integer NOT NULL REFERENCES journo(id) ON DELETE CASCADE,
    other_id integer NOT NULL REFERENCES journo(id) ON DELETE CASCADE,
    score real NOT NULL,
    PRIMARY KEY( journo_id, other_id )
);

CREATE INDEX journo_similar_journo_id_idx ON journo_similar(journo_id);

ALTER TABLE journo ADD COLUMN last_similar timestamp DEFAULT NULL;


CREATE TABLE article_similar (
    article_id integer NOT NULL REFERENCES article(id) ON DELETE CASCADE,
    other_id integer NOT NULL REFERENCES article(id) ON DELETE CASCADE,
    score real NOT NULL,
    PRIMARY KEY( article_id, other_id )
);

CREATE INDEX article_similar_article_id_idx ON article_similar(article_id);

ALTER TABLE article ADD COLUMN last_similar timestamp DEFAULT NULL;


