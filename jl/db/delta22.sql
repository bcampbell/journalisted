-- for linking article to comment pages on other sites (digg, reddit, etc...)
CREATE TABLE article_commentlink (
	article_id integer REFERENCES article(id) NOT NULL,
	source text NOT NULL DEFAULT '',
	comment_url text NOT NULL DEFAULT '',
	num_comments integer,
	score integer,
	PRIMARY KEY (article_id, source)
);

