
CREATE TABLE article_diggs (
	article_id integer PRIMARY KEY REFERENCES article(id) NOT NULL,
	num_diggs integer NOT NULL DEFAULT 0,
	num_comments integer NOT NULL DEFAULT 0,
	digg_url text NOT NULL DEFAULT '',
	submitted timestamp NOT NULL
);

