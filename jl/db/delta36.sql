-- add a short, one line description to journo, for displaying alongside their name
-- eg "The Times, The Daily Mail"
ALTER TABLE journo ADD COLUMN oneliner text NOT NULL DEFAULT '';

