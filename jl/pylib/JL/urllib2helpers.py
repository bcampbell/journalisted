import sys
import time
import re
import os
import urllib2
import urlparse
import httplib
import unittest
from hashlib import md5

import StringIO




class CollectingRedirectHandler(urllib2.HTTPRedirectHandler):
    """ follows redirects, collecting urls and http 3xx codes

    response will return with an extra attribute, 'redirects', which
    will contain a list of (code,url) pairs for any redirects followed,
    in the order in which they were followed.
    """

    def redirect_request(self, req, fp, code, msg, headers, newurl):
        # we want to migrate 'redirects' attr to the new request
        redirects = getattr(req,'redirects',[])
        newreq = urllib2.HTTPRedirectHandler.redirect_request(self, req, fp, code, msg, headers, newurl)
        newreq.redirects = redirects
        return newreq


    def http_error_301(self, req, fp, code, msg, headers):
        loc = headers['Location']
        loc = urlparse.urljoin(req.get_full_url(), loc)

        # accumulate the redirects on the request
        # (default HTTPRedirectHandler already does something similar
        # by adding a 'redirect_dict' attr for loop detection, but it
        # doesn't record the HTTP code, which we want)
        redirects = getattr(req,'redirects',[])
        redirects.append((code,loc))
        req.redirects = redirects

        func = getattr(urllib2.HTTPRedirectHandler, 'http_error_'+str(code))
        resp = func( self, req, fp, code, msg, headers)

        # add the redirects to the response
        resp.redirects = redirects
        return resp

    http_error_302= http_error_303 = http_error_307 = http_error_301

    def http_response(self, request, response):
        # make sure that even non-redirected responses get a 'redirect' attr
        if not hasattr(response,'redirects'):
            response.redirects = []
        return response


# ThrottlingProcessor and CacheHandler by Staffan Malmgren <staffan@tomtebo.org>
#http://code.activestate.com/recipes/491261-caching-and-throttling-for-urllib2/
# (under the PSF license)

class ThrottlingProcessor(urllib2.BaseHandler):
    """Prevents overloading the remote web server by delaying requests.

    Causes subsequent requests to the same web server to be delayed
    a specific amount of seconds. The first request to the server
    always gets made immediately"""
    __shared_state = {}
    def __init__(self,throttleDelay=5):
        """The number of seconds to wait between subsequent requests"""
        # Using the Borg design pattern to achieve shared state
        # between object instances:
        self.__dict__ = self.__shared_state
        self.throttleDelay = throttleDelay
        if not hasattr(self,'lastRequestTime'):
            self.lastRequestTime = {}
        
    def default_open(self,request):
        currentTime = time.time()
        if ((request.host in self.lastRequestTime) and
            (time.time() - self.lastRequestTime[request.host] < self.throttleDelay)):
            self.throttleTime = (self.throttleDelay -
                                 (currentTime - self.lastRequestTime[request.host]))
#            print "ThrottlingProcessor: Sleeping for %s seconds" % self.throttleTime
            time.sleep(self.throttleTime)
        self.lastRequestTime[request.host] = currentTime

        return None


    def http_response(self,request,response):
        if hasattr(self,'throttleTime'):
            response.info().addheader("x-throttling", "%s seconds" % self.throttleTime)
            del(self.throttleTime)
        return response



class CacheHandler(urllib2.BaseHandler):
    """Stores responses in a persistant on-disk cache.

    If a subsequent GET request is made for the same URL, the stored
    response is returned, saving time, resources and bandwith"""
    def __init__(self,cacheLocation):
        """The location of the cache directory"""
        self.cacheLocation = cacheLocation
        if not os.path.exists(self.cacheLocation):
            os.mkdir(self.cacheLocation)
            
    def default_open(self,request):
        if ((request.get_method() == "GET") and 
            (CachedResponse.ExistsInCache(self.cacheLocation, request.get_full_url()))):
            return CachedResponse(self.cacheLocation, request.get_full_url(), setCacheHeader=True)	
        else:
            return None # let the next handler try to handle the request

    def http_response(self, request, response):
        if request.get_method() == "GET":
            if 'x-cachehandler' not in response.info():
                CachedResponse.StoreInCache(self.cacheLocation, request.get_full_url(), response)
                return CachedResponse(self.cacheLocation, request.get_full_url(), setCacheHeader=False)
            else:
                return CachedResponse(self.cacheLocation, request.get_full_url(), setCacheHeader=True)
        else:
            return response
    
class CachedResponse(StringIO.StringIO):
    """An urllib2.response-like object for cached responses.

    To determine wheter a response is cached or coming directly from
    the network, check the x-cache header rather than the object type."""
    
    def ExistsInCache(cacheLocation, url):
        hash = md5(url).hexdigest()
        return (os.path.exists(os.path.join(cacheLocation, hash + ".meta")) and 
                os.path.exists(os.path.join(cacheLocation, hash + ".body")))
    ExistsInCache = staticmethod(ExistsInCache)

    def StoreInCache(cacheLocation, url, response):
        hash = md5(url).hexdigest()

        f = open(os.path.join(cacheLocation, hash + ".meta"), "w")
        f.write("%s\n%s\n%s\n" % (url,response.code, response.msg))
        headers = str(response.info())
        f.write(headers)
        f.close()
        f = open(os.path.join(cacheLocation, hash + ".body"), "w")
        f.write(response.read())
        f.close()
    StoreInCache = staticmethod(StoreInCache)
 
    def __init__(self, cacheLocation,url,setCacheHeader=True):
        self.cacheLocation = cacheLocation
        hash = md5(url).hexdigest()
        StringIO.StringIO.__init__(self, file(self.cacheLocation + "/" + hash+".body").read())

        mf = file(os.path.join(self.cacheLocation, hash+'.meta'))
        self.url = mf.readline().strip()
        assert self.url == url
        self.code = int(mf.readline().strip())
        self.msg = mf.readline().strip()

        headerbuf = mf.read()
        if setCacheHeader:
            headerbuf += "x-cachehandler: %s/%s\r\n" % (self.cacheLocation,hash)
        self.headers = httplib.HTTPMessage(StringIO.StringIO(headerbuf))

    def info(self):
        return self.headers
    def geturl(self):
        return self.url




class Tests(unittest.TestCase):
    def setUp(self):
        # Clearing cache
        if os.path.exists(".urllib2cache"):
            for f in os.listdir(".urllib2cache"):
                os.unlink("%s/%s" % (".urllib2cache", f))
        # Clearing throttling timeouts
        t = ThrottlingProcessor()
        t.lastRequestTime.clear()

    def testCache(self):
        opener = urllib2.build_opener(CacheHandler(".urllib2cache"))
        resp = opener.open("http://www.python.org/")
        self.assert_('x-cachehandler' not in resp.info())
        resp = opener.open("http://www.python.org/")
        self.assert_('x-cachehandler' in resp.info())
        
    def testThrottle(self):
        opener = urllib2.build_opener(ThrottlingProcessor(5))
        resp = opener.open("http://www.python.org/")
        self.assert_('x-throttling' not in resp.info())
        resp = opener.open("http://www.python.org/")
        self.assert_('x-throttling' in resp.info())

    def testRedirectCollection(self):
        opener = urllib2.build_opener( CollectingRedirectHandler() )
        resp = opener.open( "http://bit.ly/VDcn" )
        expected = [(301, 'http://example.com/'), (302, 'http://www.iana.org/domains/example/')]
        self.assert_(resp.redirects == expected)

    def testCombined(self):
        opener = urllib2.build_opener(CacheHandler(".urllib2cache"), ThrottlingProcessor(10))
        resp = opener.open("http://www.python.org/")
        self.assert_('x-cachehandler' not in resp.info())
        self.assert_('x-throttling' not in resp.info())
        resp = opener.open("http://www.python.org/")
        self.assert_('x-cachehandler' in resp.info())
        self.assert_('x-throttling' not in resp.info())

        # make sure they all play nice together...
        opener = urllib2.build_opener( CacheHandler(".urllib2cache"),ThrottlingProcessor(1),CollectingRedirectHandler() )
        resp = opener.open( "http://bit.ly/VDcn" )
        expected = [(301, 'http://example.com/'), (302, 'http://www.iana.org/domains/example/')]
        self.assert_(resp.redirects == expected) 



if __name__ == "__main__":
    unittest.main()
 
