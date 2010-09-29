begin;
alter table article_content drop constraint article_content_article_id_fkey;
alter table article_content add constraint "article_content_article_id_fkey" foreign key (article_id) references article(id) on delete cascade;
commit;

