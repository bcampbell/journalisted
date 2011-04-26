import sys
sys.path.append("../../pylib")

#from pyPgSQL import PgSQL
import psycopg2
import psycopg2.extras as pe


# TODO: Phase this out...
class PgSQL:
    '''
    A PgSQL-compatible wrapper over psycopg2.
    The only difference is that PgSQL supports cursor.execute(query, nonseq).
    '''
    @staticmethod
    def connect(**kwargs):
        return FakeConn(psycopg2.connect(**kwargs))

class FakeConn(object):
    def __init__(self, conn):
        self.conn = conn
    def cursor(self):
        return FakeCursor(self.conn.cursor(cursor_factory=pe.DictCursor))
    def commit(self):
        return self.cursor().execute('COMMIT')
    def rollback(self):
        return self.cursor().execute('ROLLBACK')


class FakeCursor(object):
    def __init__(self, cur):
        self.cur = cur
    def __getattr__(self, name):
        if name in ('execute', 'commit', 'rollback'):
            return lambda *args, **kwargs: getattr(FakeCursor, name)(self, *args, **kwargs)
        method = getattr(self.cur, name)
        return lambda *args, **kwargs: method(*args, **kwargs)

    def execute(self, query, *args, **kwargs):
        if len(args)!=1 or not isinstance(args[0], (dict, list, tuple)):
            args = [args]
        return self.cur.execute(query, *args, **kwargs)
    def commit(self):
        return self.cur.execute('COMMIT')
    def rollback(self):
        return self.cur.execute('ROLLBACK')


import mysociety.config
mysociety.config.set_file("../conf/general")


# TODO: Phase this out...
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



_conn = None
def conn():
    """ get the shared connection object for DB """
    global _conn

    if _conn is None:
        # all a bit hacky...
        u = mysociety.config.get('JL_DB_USER')
        pwd = mysociety.config.get('JL_DB_PASS', 'UNSET' )
        db = mysociety.config.get('JL_DB_NAME')
        p = mysociety.config.get('JL_DB_PORT', 'UNSET')

        if pwd == 'UNSET':
            _conn = psycopg2.connect( user=u, database=db, port=p, connection_factory=pe.DictConnection )
        else:
            _conn = psycopg2.connect( user=u, password=pwd, database=db, connection_factory=pe.DictConnection )

    return _conn

