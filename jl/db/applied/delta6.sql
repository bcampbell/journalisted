-- table to cache average journo stats

CREATE TABLE journo_average_cache (
	last_updated timestamp,
	wc_total real,
	wc_avg real,
	wc_min real,
	wc_max real,
	num_articles real
);

