ALTER TABLE article ADD COLUMN status CHARACTER(1) DEFAULT 'a' CHECK (status='a' OR status='h');

CREATE TABLE article_dupe (
	article_id integer PRIMARY KEY,
	dupeof_id integer REFERENCES article(id) NOT NULL
);

