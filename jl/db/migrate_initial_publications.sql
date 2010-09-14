-- split out initial organisation data into the new "publication" tables
begin;
insert into pub_alias (pub_id,alias) SELECT id,prettyname FROM organisation;
insert into pub_phone (pub_id,phone) SELECT id,phone FROM organisation;
insert into pub_email_format (pub_id,fmt) SELECT id,email_format FROM organisation;
-- insert into pub_domain (pub_id,domain) select id,SUBSTRING(home_url FROM 'http://([^/]*).*') as domainname from organisation;
INSERT INTO pub_domain (pub_id,domain) SELECT DISTINCT srcorg AS pub_id,SUBSTRING(LOWER(permalink) FROM 'http://([^/]*).*') AS domain FROM article WHERE SUBSTRING(LOWER(permalink) FROM 'http://([^/]*).*') IS NOT NULL;


-- some special cases to aid migration of journo_other_articles
INSERT INTO pub_alias (pub_id,alias) VALUES (7,'Daily Telegraph');
INSERT INTO pub_alias (pub_id,alias) VALUES ( 7, 'Telegraph' );
INSERT INTO pub_alias (pub_id,alias) VALUES ( 13, 'Sunday Telegraph' );
INSERT INTO pub_alias (pub_id,alias) VALUES (12,'Sunday Mirror');
INSERT INTO pub_alias (pub_id,alias) VALUES ( 4,'Guardian' );
INSERT INTO pub_alias (pub_id,alias) VALUES ( 8, 'Times Online' );

commit;
