#!/usr/bin/env python2.4
#
# Copyright (c) 2008 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# Feeds URLs from feeds listed in CIF XML into commentisfree.py.
# A shell script driver implemented in Python for portability.

import sys, os, subprocess

args = sys.argv[1:]

if '--dryrun' in args:
    args.remove('--dryrun')
    dryrun = ['--dryrun']
else:
    dryrun = []

if len(args) != 1:
    sys.exit('usage: cif.py [--dryrun] CIF_XML\n'
             'Scrapes Comment Is Free articles.\n'
             'CIF_XML is output from ../bin/comment-is-free.')

cif_xml = args[0]

if sys.platform=='win32':
    prefix = ['python']
else:
    prefix = []

cmdline = prefix + ['../bin/read-cif-feed', '--urlonly', 'all', cif_xml]

proc = subprocess.Popen(cmdline, stdout=subprocess.PIPE)
for line in proc.stdout:
    url = line.strip()
    # We could probably just use commentisfree.py directly, but might as well
    # use guardianfront.py for safety.
    subcmdline = ' '.join(prefix + ['./guardianfront.py'] + dryrun + ['--url', url])
    print subcmdline
    os.system(subcmdline)
