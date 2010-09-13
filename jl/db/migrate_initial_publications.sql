-- split out initial organisation data into the new "publication" tables
begin;
insert into pub_alias (pub_id,alias) SELECT id,lower(prettyname) FROM organisation;
insert into pub_phone (pub_id,phone) SELECT id,phone FROM organisation;
insert into pub_email_format (pub_id,fmt) SELECT id,email_format FROM organisation;
insert into pub_domain (pub_id,domain) select id,SUBSTRING(home_url FROM 'http://([^/]*).*') as domainname from organisation;
commit;
