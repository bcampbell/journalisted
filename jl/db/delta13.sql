-- cache for storing static html fragments
create table htmlcache (
	name varchar(10) primary key,
	content text,
	gentime timestamp NOT NULL DEFAULT NOW()
);

