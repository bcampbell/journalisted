-- data cleanups for journo_jobtitle

BEGIN;
-- zap dupes
DELETE FROM journo_jobtitle WHERE id in ( SELECT bad_rows.id FROM journo_jobtitle AS bad_rows INNER JOIN ( SELECT MIN(firstseen) AS min_firstseen, journo_id,org_id,jobtitle,COUNT(jobtitle) FROM journo_jobtitle GROUP BY journo_id, org_id, jobtitle HAVING COUNT(*)>1 ) AS good_rows ON good_rows.journo_id=bad_rows.journo_id AND good_rows.org_id=bad_rows.org_id AND good_rows.jobtitle=bad_rows.jobtitle AND bad_rows.firstseen <> good_rows.min_firstseen );

-- cleanup byline cracker borkage
delete from journo_jobtitle where jobtitle ilike '%, and %';
delete from journo_jobtitle where jobtitle ilike 'and %';

COMMIT;

