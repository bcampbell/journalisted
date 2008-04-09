-- Altering the email approval system to work like the bios.
ALTER TABLE journo_email  ADD COLUMN approved BOOLEAN  NOT NULL DEFAULT false;
