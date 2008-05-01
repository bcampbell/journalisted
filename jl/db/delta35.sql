-- article.srcid should be unique
ALTER TABLE article ADD CONSTRAINT article_srcid_key UNIQUE (srcid);

