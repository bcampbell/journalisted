-- add (optional) organisation id to jobtitle
alter table journo_jobtitle add column org_id integer;
alter table journo_jobtitle add FOREIGN KEY (org_id) REFERENCES organisation(id);

