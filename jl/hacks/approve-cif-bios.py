'''
Approving all Comment Is Free bios that don't have an approved wikipedia bio.
'''

import site; site.addsitedir('../pylib')
from JL import DB

conn = DB.Connect()
c = conn.cursor()
c.execute('SELECT journo_id, id, type, approved FROM journo_bio')
rows = c.fetchall()
fixthese = set()
fixproof = set()
for journo_id, bio_id, bio_type, approved in rows:
    if bio_type=='cif:contributors-az' and not approved:
        fixthese.add(journo_id)
    elif bio_type=='wikipedia:journo' and approved:
        fixproof.add(journo_id)

def showset(x):
    return '%s...' % sorted(list(x))[:10]

print 'Not approving these due to approved wikipedia bio:', showset(fixthese.intersection(fixproof))

fixthese -= fixproof
print 'Approving', showset(fixthese)

if raw_input('Enter "yes" to start... ')=='yes':
    c.execute('BEGIN')
    c.execute('UPDATE journo_bio SET approved=true WHERE id IN (%s)' %
              ','.join(str(x) for x in fixthese))
    c.execute('COMMIT')
    c.execute('SELECT id FROM journo_bio WHERE approved=true AND id IN (%s)' %
              ','.join(str(x) for x in fixthese))
    fixed = set([row[0] for row in c.fetchall()])
    assert set(fixthese)==fixed, showset(fixed)
    c.execute('COMMIT')
else:
    print 'Not updating database.'
