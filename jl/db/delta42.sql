-- new fields for organisation contact details
ALTER TABLE organisation ADD COLUMN phone text NOT NULL DEFAULT '';
ALTER TABLE organisation ADD COLUMN email_format text NOT NULL DEFAULT '';

BEGIN;
UPDATE organisation SET phone='0208 743 8000', email_format='{FIRST}.{LAST}@bbc.co.uk' WHERE id=10;
UPDATE organisation SET phone='0207 278 2332', email_format='{FIRST}.{LAST}@observer.co.uk' WHERE id=11;
UPDATE organisation SET phone='0207 293 3000', email_format='{FIRST}.{LAST}@sundaymirror.co.uk, {FIRST}.{LAST}@mirror.co.uk' WHERE id=12;
UPDATE organisation SET phone='0207 931 2000', email_format='{FIRST}.{LAST}@telegraph.co.uk' WHERE id=13;
UPDATE organisation SET phone='0871 434 1010', email_format='{FIRST}.{LAST}@express.co.uk' WHERE id=3;
UPDATE organisation SET phone='0207 005 2000', email_format='{INITIAL}.{LAST}@independent.co.uk, {FIRST}.{LAST}@independent.co.uk' WHERE id=1;
UPDATE organisation SET phone='0207 938 6000', email_format='{FIRST}.{LAST}@dailymail.co.uk, {INITIAL}.{LAST}@dailymail.co.uk' WHERE id=2;
UPDATE organisation SET phone='0207 278 2332', email_format='{FIRST}.{LAST}@guardian.co.uk' WHERE id=4;
UPDATE organisation SET phone='0207 293 3000', email_format='{FIRST}.{LAST}@mirror.co.uk, {FIRST}.{LAST}@sundaymirror.co.uk' WHERE id=5;
UPDATE organisation SET phone='0207 782 4000', email_format='{FIRST}.{LAST}@the-sun.co.uk ' WHERE id=6;
UPDATE organisation SET phone='0207 782 5000', email_format='{FIRST}.{LAST}@thetimes.co.uk ' WHERE id=8;
UPDATE organisation SET phone='0207 782 5000', email_format='{FIRST}.{LAST}@sunday-times.co.uk ' WHERE id=9;
UPDATE organisation SET phone='0207 931 2000', email_format='{FIRST}.{LAST}@telegraph.co.uk' WHERE id=7;
UPDATE organisation SET phone='0207 705 3000', email_format='{FIRST}.{LAST}@bskyb.com' WHERE id=14;
UPDATE organisation SET phone='0131 620 8620', email_format='{FIRST}.{LAST}@scotsman.com, {INITIAL}{LAST}@scotsman.com' WHERE id=15;
UPDATE organisation SET phone='0131 620 8620', email_format='{FIRST}.{LAST}@scotlandonsunday.com' WHERE id=16;
UPDATE organisation SET phone='0207 873 3000', email_format='{FIRST}.{LAST}@ft.com' WHERE id=18;
UPDATE organisation SET phone='0141 302 7000', email_format='{FIRST}.{LAST}@theherald.co.uk ' WHERE id=19;
COMMIT;

