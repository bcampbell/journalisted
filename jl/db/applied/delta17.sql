-- index the article title column to speed up duplicate handling
-- (and maybe other stuff too)
CREATE INDEX article_title_idx ON article(title)

