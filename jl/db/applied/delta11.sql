-- tag management stuff

create table tag_blacklist (
	bannedtag text NOT NULL PRIMARY KEY
);

create table tag_synonym (
	alternate text NOT NULL PRIMARY KEY,
	tag text NOT NULL
);



insert into tag_blacklist (bannedtag) values( 'that');
insert into tag_blacklist (bannedtag) values( 'there');
insert into tag_blacklist (bannedtag) values( 'these');
insert into tag_blacklist (bannedtag) values( 'they');
insert into tag_blacklist (bannedtag) values( 'this');
insert into tag_blacklist (bannedtag) values( 'what');
insert into tag_blacklist (bannedtag) values( 'when');
insert into tag_blacklist (bannedtag) values( 'while');
insert into tag_blacklist (bannedtag) values( 'with');
insert into tag_blacklist (bannedtag) values( 'according');
insert into tag_blacklist (bannedtag) values( 'after');
insert into tag_blacklist (bannedtag) values( 'although');
insert into tag_blacklist (bannedtag) values( 'however');
insert into tag_blacklist (bannedtag) values( 'more');
insert into tag_blacklist (bannedtag) values( 'many');
insert into tag_blacklist (bannedtag) values( 'last');
insert into tag_blacklist (bannedtag) values( 'here');
insert into tag_blacklist (bannedtag) values( 'just');

