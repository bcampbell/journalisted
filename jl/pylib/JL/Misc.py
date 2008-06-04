"""Misc JL functions which don't belong anywhere else

TODO: move genericish stuff from ukmedia.py into here instead
"""



cached_orgidmap = None

def GetOrgID( conn, shortname ):
    """Look up org id using shortname"""
    global cached_orgidmap

    if cached_orgidmap == None:
        cached_orgidmap = {}
        c = conn.cursor()
        c.execute( "SELECT id,shortname FROM organisation" )
        while 1:
            row = c.fetchone()
            if row == None:
                break
            cached_orgidmap[ row['shortname'] ] = row['id']
        c.close()

    return cached_orgidmap[ shortname ]

