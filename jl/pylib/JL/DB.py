import sys
sys.path.append("../../pylib")

from pyPgSQL import PgSQL

import mysociety.config
mysociety.config.set_file("../conf/general")

def Connect():
	# all a bit hacky...
	u = mysociety.config.get('JL_DB_USER')
	pwd = mysociety.config.get('JL_DB_PASS', 'UNSET' )
	db = mysociety.config.get('JL_DB_NAME')
	p = mysociety.config.get('JL_DB_PORT', 'UNSET')

	if pwd == 'UNSET':
		# for dev machine
		conn = PgSQL.connect( user=u, database=db, port=p )
	else:
		conn = PgSQL.connect( user=u, password=pwd, database=db )


	return conn

