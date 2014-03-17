-- set journo.modified if journo_pageviews is changed
CREATE TRIGGER journo_pageviews_delete AFTER DELETE ON journo_pageviews FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_ondelete();
CREATE TRIGGER journo_pageviews_insert AFTER INSERT ON journo_pageviews FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_oninsert();
CREATE TRIGGER journo_pageviews_update AFTER UPDATE ON journo_pageviews FOR EACH ROW EXECUTE PROCEDURE journo_setmodified_onupdate();
