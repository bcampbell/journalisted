begin;
    alter table journo add column fake boolean not null default false;
commit;

