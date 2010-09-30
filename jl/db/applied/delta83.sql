BEGIN;
CREATE INDEX article_srcorg_idx ON article(srcorg);
COMMIT;
