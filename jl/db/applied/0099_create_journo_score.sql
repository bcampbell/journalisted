BEGIN;
CREATE TABLE journo_score (
    id serial PRIMARY KEY,
    journo_id integer NOT NULL REFERENCES journo(id) ON DELETE CASCADE,
    num_alerts integer NOT NULL default 0,
    num_admirers integer NOT NULL default 0,
    num_views_week integer NOT NULL default 0,
    score real NOT NULL default 0.0
);

CREATE INDEX journo_score_journo_id_idx ON journo_score(journo_id);
CREATE INDEX journo_score_score_idx ON journo_score(score);

COMMIT;

