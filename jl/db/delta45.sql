-- new column to track when (if ever) comment count/url was scraped from this article
alter table article add column last_comment_check timestamp;

