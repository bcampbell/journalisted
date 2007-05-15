import sys
sys.path.append("../../pylib")

from pyPgSQL import PgSQL

import mysociety.config
mysociety.config.set_file("../conf/general")

def Connect():
#	print "user: "+mysociety.config.get('JL_DB_USER')
#	print "pass: "+mysociety.config.get('JL_DB_PASS')
#	print "name: "+mysociety.config.get('JL_DB_NAME')

#	conn = PgSQL.connect(
#			database=mysociety.config.get('JL_DB_NAME'),
#			user=mysociety.config.get('JL_DB_USER') )


	conn = PgSQL.connect(
		user = mysociety.config.get('JL_DB_USER'),
		password = mysociety.config.get('JL_DB_PASS'),
		database = mysociety.config.get('JL_DB_NAME') )
	return conn

