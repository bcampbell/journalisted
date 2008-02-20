-- add a status column to journo
-- a: active
-- i: inctive
-- h: hidden
ALTER TABLE journo ADD COLUMN status CHARACTER(1) DEFAULT 'i' CHECK (status='a' OR status='h' OR status='i');

-- only activate those journos who've got more than one article
UPDATE journo SET status='a' WHERE id in ( SELECT j.id FROM ( journo j INNER JOIN journo_attr attr ON j.id=attr.journo_id ) GROUP BY j.id HAVING COUNT(*) >1 );
