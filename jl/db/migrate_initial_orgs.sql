-- split out initial org data into new tables
begin;
insert into org_alias (org_id,alias) SELECT id,lower(prettyname) FROM organisation;
insert into org_phone (org_id,phone) SELECT id,phone FROM organisation;
insert into org_email_format (org_id,fmt) SELECT id,email_format FROM organisation;
insert into org_domain (org_id,domain) select id,SUBSTRING(home_url FROM 'http://([^/]*).*') as domainname from organisation;
commit;
