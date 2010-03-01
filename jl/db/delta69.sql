-- some missing indexes!
create index article_similar_other_id_idx ON article_similar(other_id);
create index article_bloglink_article_id_idx ON article_bloglink(article_id);
create index article_image_article_id_idx ON article_image(article_id);


