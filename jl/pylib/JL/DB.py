import sys
sys.path.append("../../pylib")

from pyPgSQL import PgSQL

import mysociety.config
mysociety.config.set_file("../conf/general")

def Connect():
	u = mysociety.config.get('JL_DB_USER')
	pwd = mysociety.config.get('JL_DB_PASS')
	db = mysociety.config.get('JL_DB_NAME')

	if pwd == '':
		conn = PgSQL.connect( user=u, database=db )
	else:
		conn = PgSQL.connect( user=u, password=pwd, database=db )
	return conn

