import commands

def php_metaphone( s ):
    """ metaphone function which matches the output of the php one. Matches it _very_ closely. """
    cmd = """php -r "print metaphone( '%s' );" """ %(s,)
    return commands.getoutput( cmd ).strip()


