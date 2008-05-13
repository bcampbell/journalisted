-- add missing unique id to journo_jobtitle

ALTER TABLE journo_jobtitle ADD COLUMN id SERIAL PRIMARY KEY;

