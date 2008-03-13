#!/usr/bin/env python2.4

NAME = 'Technorati/Python'
VERSION = '0.05'

# Copyright (C) 2003 Phillip Pearson

URL = 'http://www.myelin.co.nz/technorati_py/'

# Permission is hereby granted, free of charge, to any person
# obtaining a copy of this software and associated documentation files
# (the "Software"), to deal in the Software without restriction,
# including without limitation the rights to use, copy, modify, merge,
# publish, distribute, sublicense, and/or sell copies of the Software,
# and to permit persons to whom the Software is furnished to do so,
# subject to the following conditions:

# The above copyright notice and this permission notice shall be
# included in all copies or substantial portions of the Software.

# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
# EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
# MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
# NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS
# BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN
# ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
# CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
# SOFTWARE.

# Related work:
#
#    PyTechnorati by Mark Pilgrim:
#        http://diveintomark.org/projects/pytechnorati/
#
#    xmltramp/technorati.py by Aaron Swartz
#        http://www.aaronsw.com/2002/xmltramp/technorati.py
#
#    Technorati API documentation
#        http://developers.technorati.com/wiki/CosmosQuery

__history__ = '''

v0.05 (changes by Kevin Marks - this is a merge from the modified 0.03 version distributed by Technorati)

    - supports getUserInfo functions

v0.04 (changes by Mike Linksvayer)

    - raises TechnoratiError when 'error' found in response

    - print status messages to stderr

    - API as specified at
      http://developers.technorati.com/wiki/CosmosQuery
      (no version=, added support for limit=, current=, and
      type=)

v0.03

    - now supporting the new 'search' command.

v0.02

    - now using the latest version of the API (no .xml URLs, format=
      and version= arguments)

    - you can now get more than just the first page of cosmos results
      (use start= or -s / --start)

    - now throwing an exception when we get an HTTP error

    - '--cosmos' command-line option added (same as --inbound)

    - now supporting all license key locations used by PyTechnorati

v0.01

    initial release
    http://www.myelin.co.nz/post/2003/5/12/#200305124

'''

import urllib, sgmllib, os, sys
from pprint import pprint

def setLicense(license_key):
    "Set the license key"
    global LICENSE_KEY
    LICENSE_KEY = license_key

def findkey(license_key=None):
    "Find out the current user's API key"
    class GotIt(Exception):
        def __init__(self, key):
            self.key = key
    def tryvar(key):
        if key:
            raise GotIt(key)
    def tryfile(fn):
        if DEBUG: print >>sys.__stderr__,"trying",fn
        if os.path.exists(fn):
            tryvar(open(fn).readline().strip())
    def modulepath():
        return os.path.split(os.path.abspath(sys.argv[0]))[0]
    try:
        tryvar(license_key)
        tryvar(LICENSE_KEY)
        tryvar(os.environ.get('TECHNORATI_LICENSE_KEY', None))
        for path in ('.',
                     os.path.expanduser('~'),
                     modulepath()):
            for leaf in ('.technoratikey',
                         'technoratikey.txt',
                         'apikey.txt'):
                tryfile(os.path.join(path, leaf))
    except GotIt, g:
        setLicense(g.key)
        return LICENSE_KEY
    raise Exception, "Can't find license key"

LICENSE_KEY = None
DEBUG = 0

class opener(urllib.FancyURLopener):
    version = '%s v%s; %s' % (NAME, VERSION, URL)
    def http_error_default(self, url, fp, errcode, errmsg, headers, data=None):
        raise IOError, "HTTP error %s fetching http:%s" % (errcode, url)

callcache = {}
try:
    callcache = eval(open('cache.txt').read())
except:
    pass

class BadUrlError(Exception):
    pass
    
def call(proc, args, license_key=None):
    #if args['url'] in (None, ''):
    #    raise BadUrlError("No URL supplied")
    args['key'] = findkey(license_key)
    args['format'] = 'xml'
    url = 'http://api.technorati.com/%s?%s' % (proc, urllib.urlencode(args))
    print >>sys.__stderr__,"calling",url
    if not callcache.has_key(url):
        print >>sys.__stderr__,"(fetching)"
        o = opener()
        f = o.open(url)
        callcache[url] = f.read()
    xml = callcache[url]
    if DEBUG:
        print >>sys.__stderr__,xml
    return xml

def parse(parser, xml):
    parser.feed(xml)
    parser.close()
    return parser.data

class TechnoratiError(Exception):
    pass
    
class genericParser(sgmllib.SGMLParser):
    def __init__(self, itemsName):
        sgmllib.SGMLParser.__init__(self)
        self.data = {}
        self.inresult = self.inweblog = self.initem = 0
        self.weblog = None
        self.item = None
        self.data[itemsName] = self.items = []
        self.collector = None

    def collect(self):
        assert self.collector is None, "already collecting: parse failure!"
        self.collector = []
    def grab(self):
        s = "".join(self.collector)
        self.collector = None
        return s
    def grab_int(self):
        x = self.grab()
        if not x:
            return 0
        return int(x)

    def handle_data(self, s):
        if self.collector is not None:
            self.collector.append(s)
        
    def start_document(self, attrs):
        pass
    def end_document(self):
        pass

    def start_result(self, attrs):
        self.inresult = 1
    def end_result(self):
        self.inresult = 0

    def start_item(self, attrs):
        self.initem = 1
        self.item = {}
    def end_item(self):
        self.initem = 0
        self.items.append(self.item)
        self.item = None

    def start_nearestpermalink(self, attrs):
        assert self.initem
        self.collect()
    def end_nearestpermalink(self):
        self.item['nearestpermalink'] = self.grab()
    def start_excerpt(self, attrs):
        assert self.initem
        self.collect()
    def end_excerpt(self):
        self.item['excerpt'] = self.grab()
    def start_linkurl(self, attrs):
        assert self.initem
        self.collect()
    def end_linkurl(self):
        self.item['linkurl'] = self.grab()
    def start_linkcreated(self, attrs):
        assert self.initem
        self.collect()
    def end_linkcreated(self):
        self.item['linkcreated'] = self.grab()

    def start_weblog(self, attrs):
        assert self.initem or self.inresult, "found <weblog> element outside <result> or <item>"
        self.inweblog = 1
        self.weblog = {}
    def end_weblog(self):
        self.inweblog = 0
        if self.initem:
            self.item['weblog'] = self.weblog
            #self.weblogs.append(self.weblog)
        elif self.inresult:
            self.data['weblog'] = self.weblog
        else:
            raise AssertionFailure, "<weblog> element not in item or result...?"
        self.weblog = None

    def start_rankingstart(self, attrs):
        self.collect()
    def end_rankingstart(self):
        self.data['rankingstart'] = int(self.grab())
        
    def start_url(self, attrs):
        self.collect()
    def end_url(self):
        if self.inweblog:
            self.weblog['url'] = self.grab()
        else:
            self.data['url'] = self.grab()
    def start_name(self, attrs):
        self.collect()
    def end_name(self):
        self.weblog['name'] = self.grab()
    def start_rssurl(self, attrs):
        self.collect()
    def end_rssurl(self):
        self.weblog['rssurl'] = self.grab()
    def start_inboundblogs(self, attrs):
        self.collect()
    def end_inboundblogs(self):
        if self.inweblog:
            x = self.weblog
        elif self.inresult:
            x = self.data
        else:
            raise AssertionFailure, "<inboundblogs> element not in <result> or <weblog>"
        x['inboundblogs'] = self.grab_int()
    def start_inboundlinks(self, attrs):
        self.collect()
    def end_inboundlinks(self):
        if self.inweblog:
            x = self.weblog
        elif self.inresult:
            x = self.data
        else:
            raise AssertionFailure, "<inboundlinks> element not in <result> or <weblog>"
        x['inboundlinks'] = self.grab_int()
    def start_lastupdate(self, attrs):
        self.collect()
    def end_lastupdate(self):
        self.weblog['lastupdate'] = self.grab()
    def start_error(self, attrs):
        self.collect()
    def end_error(self):
        if self.inresult:
            raise TechnoratiError, self.grab()
        else:
            raise AssertionFailure, "<error> element not in <result>"

def getCosmos(url, start=None, limit=None, querytype=None, current=None, license_key=None):
    "gets a blog's cosmos and returns an ApiResponse containing a Weblog object ('weblog') for the blog and a list ('inLinks') of Link objects for its neighbours"
    args = {'url': url}
    if start is not None:
        args['start'] = '%d' % start
    if limit is not None:
        args['limit'] = '%d' % limit
    if current is not None:
        args['current'] = current
    if querytype is not None:
        args['type'] = querytype
    xml = call('cosmos', args, license_key)
    data = parse(genericParser('inbound'), xml)
    return data

def getUserInfo(username, license_key=None):
    "gets info about a user and returns it as a User object"
    xml = call('getinfo', {'username': username}, license_key)
    data = parse(genericParser('user'), xml)
    return data.get('user', None)

def getBlogInfo(url, license_key=None):
    "gets info about a blog and returns it as a Weblog object"
    xml = call('bloginfo', {'url': url}, license_key)
    data = parse(genericParser('weblogs'), xml)
    return data.get('weblog', None)

def getOutboundBlogs(url, license_key=None):
    "gets a list of blogs linked to by a blog and returns an ApiResponse containing a Weblog object ('weblog') for the blog and a list ('outLinks') of Weblog objects for the linked-to blogs"
    xml = call('outbound', {'url': url}, license_key)
    data = parse(genericParser('outbound'), xml)
    return data

def search(query, license_key=None):
    xml = call('search', {'query': query}, license_key)
    data = parse(genericParser('search'), xml)
    return data

def test(url):
    if not url: url='http://epeus.blogspot.com'
    pprint(getUserInfo('kevinmarks'))
    pprint(getCosmos(url))
    pprint(getBlogInfo(url))
    pprint(getOutboundBlogs(url))
    pprint(search('"David Sifry"'))

def main():
    import sys, getopt
    opts, rest = getopt.getopt(sys.argv[1:], 'dts:u:q:c:l:', ('debug', 'test', 'inbound', 'cosmos', 'start=', 'info', 'outbound', 'url=', 'querytype=', 'current=', 'limit=', 'search', 'user'))
    arg = " ".join([x for x in rest if x.strip()])
    func = None
    start = None
    limit = None
    for opt,val in opts:
        _map = {'inbound': getCosmos,
                'cosmos': getCosmos,
                'info': getBlogInfo,
                'outbound': getOutboundBlogs,
                'search': search,
                'user': getUserInfo,
                }
        if opt in ('-u', '--url'):
            url = val
        elif opt in ('-s', '--start'):
            start = int(val)
        elif opt in ('-l', '--limit'):
            limit = int(val)
        elif opt in ('-d', '--debug'):
            global DEBUG
            DEBUG = 1
        elif opt in ('-t', '--test'):
            func = test
        elif opt.startswith('--') and _map.has_key(opt[2:]):
            assert func is None, "Only one function (url, inbound, info or outbound) may be supplied"
            func = _map[opt[2:]]
    if func is None:
        print >>sys.__stderr__,"No function supplied; --url, --inbound, --info, --search, --user or --outbound must be specified on the command line"
        return
    if start is not None:
        r = func(arg, start)
    else:
        r = func(arg)
    if func is not test:
        pprint(r)

if __name__ == '__main__':
    findkey()
    main()
    open('cache.txt', 'wt').write(`callcache`)

