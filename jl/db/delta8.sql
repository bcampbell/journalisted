-- support for user (person) accounts using the mySociety login code.
-- this sql copied from the hearfromthem.com schema (ycml in mysoc cvs)


-- need to run this as superuser...
--CREATE TRUSTED PROCEDURAL LANGUAGE plpgsql
--   HANDLER plpgsql_call_handler
--    VALIDATOR plpgsql_validator;

-- Returns the timestamp of current time, but with possiblity of
-- overriding for test purposes.
create function ms_current_timestamp()
    returns timestamp as '
    begin
        return current_timestamp;
    end;
' language 'plpgsql';


-- users, but call the table person rather than user so we don't have to quote
-- its name in every statement....
create table person (
    id serial not null primary key,
    name text,
    email text not null,
    password text,
    website text,
    numlogins integer not null default 0
);

create unique index person_email_idx on person(email);



-- Stores randomly generated tokens and serialised hash arrays associated
-- with them.
create table token (
    scope text not null,        -- what bit of code is using this token
    token text not null,
    data bytea not null,
    created timestamp not null default current_timestamp,
    primary key (scope, token)
);

create table requeststash (
    key char(16) not null primary key,
    whensaved timestamp not null default current_timestamp,
    method text not null default 'GET' check (
            method = 'GET' or method = 'POST'
        ),
    url text not null,
    -- contents of POSTed form
    post_data bytea check (
            (post_data is null and method = 'GET') or
            (post_data is not null and method = 'POST')
        ),
    extra text
);

-- make expiring old requests quite quick
create index requeststash_whensaved_idx on requeststash(whensaved);

