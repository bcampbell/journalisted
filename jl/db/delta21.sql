

CREATE TABLE article_bloglink (
	id serial NOT NULL PRIMARY KEY,
	article_id integer REFERENCES article(id) NOT NULL,
	nearestpermalink text NOT NULL default '',
	title text NOT NULL default '',
	blogname text NOT NULL,
	blogurl text NOT NULL,
--	linkurl text NOT NULL,
	linkcreated timestamp NOT NULL,
	excerpt text NOT NULL default '',
	source text NOT NULL default ''
);

