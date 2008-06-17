-- table to log and control scraping of problematic articles...
CREATE TABLE error_articlescrape (
    srcid text PRIMARY KEY,
    scraper text NOT NULL,
    title text,
    srcurl text NOT NULL,
    attempts integer NOT NULL DEFAULT 1,
    report text NOT NULL,
    action CHARACTER(1) NOT NULL DEFAULT ' ',
    firstattempt timestamp NOT NULL DEFAULT NOW(),
    lastattempt timestamp NOT NULL DEFAULT NOW()
);

