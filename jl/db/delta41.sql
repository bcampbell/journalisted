-- to speed up fulltext search by finding recently-scraped articles quickly
create index article_lastscraped_idx on article (lastscraped);

