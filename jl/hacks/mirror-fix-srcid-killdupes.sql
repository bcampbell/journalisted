-- delete all mirror/sundaymirror articles with duplicate srcid,
-- keeping the one with the highest article id.
DELETE FROM article WHERE id IN (SELECT distinct bad.id FROM article AS good INNER JOIN article AS bad ON good.srcid=bad.srcid AND good.srcorg=bad.srcorg AND (good.srcorg=5 OR good.srcorg=12) AND bad.id<good.id );

