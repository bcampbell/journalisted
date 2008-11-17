-- add some extra data to organisation (sop=statement of principles)
alter table organisation add column home_url text not null default '';
alter table organisation add column sop_name text not null default '';
alter table organisation add column sop_url text not null default '';

