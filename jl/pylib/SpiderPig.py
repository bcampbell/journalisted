import sys
import urllib2
import re
import urlparse

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup


class SpiderPig:
	"""Quick and nasty web spider class"""
	def __init__(self, url_handler, userdata=None, logfunc=None ):
		"""
		params:
			url_handler - callabck function to decide how to handle discovered urls
					fn( userdata, url, depth, a )
					userdata - the value passed into SpiderPig ctor
					url - the url to consider
					depth - how many links inward the url refers to
					a - BeautifulSoup Element (so fn can examine anchor attrs/text)
				If the callback decides the url should be followed, then it should
				return it.
				If the fn doesn't want to follow the url, it should return None.
			userdata - any data you want passed into the callback
			logfunc - callback to display debug info (takes a single string param)
		"""
		self.active = {}
		self.seen = set()
		self.url_handler = url_handler
		self.userdata = userdata
		self.logfunc = None

	def AddSeed( self, url ):
		"""add an initial url to the list of pages to spider"""
		self.active[url] = 0

	def Go( self ):
		"""do the spidering, calling the callback fn for each discovered url"""
		while 1:
			if not self.DoOne():
				break

	# The rest of these are implementation

	def LOG( self, s ):
		if self.logfunc:
			self.logfunc(s)
		pass

	def FollowURL( self, url, depth ):
		""" add a url to the list of ones to spider """
		if url in self.active:
			if self.active[url] > depth:
				self.active[url] = depth
		else:
			self.active[url] = depth

	def DoOne( self ):
		if not self.active:
			return False	# finished!
		url,depth = self.active.popitem();

		pad = '-' * depth
		self.LOG( "%sVisiting %s\n" % (pad, url) )

		f = urllib2.urlopen(url)
		realurl = f.geturl()

		if url != realurl:
			self.LOG( "%s(redirected '%s' => '%s')\n" % ( pad,url,realurl) )
		html = f.read()

		self.ProcessPage( realurl, depth, html ) 

		return True



	def ProcessPage( self, url, depth, html ):
		pad = '..' * depth
		soup = BeautifulSoup( html )

		self.seen.add(url)

		for a in soup.findAll( 'a' ):
			if not a.has_key('href'):
				continue

			newurl = urlparse.urljoin( url, a['href'] )
			o = urlparse.urlparse( url )
			newo = urlparse.urlparse( newurl )
			# trim off fragments (eg '#comments')
			newurl = urlparse.urlunparse( (newo[0], newo[1], newo[2], newo[3], newo[4],'') )
			newo = urlparse.urlparse( newurl )

			#self.LOG( "%s  %s: " %(pad,newurl) )
			if newurl in self.seen:
				#self.LOG( "seen\n" )
				continue

			if newo[0].lower() not in ( 'http', 'https' ):
				#self.LOG( "ignore (not http|https)\n" )
				continue
			if newo[1] != o[1]:
				#self.LOG( "ignore (offsite)\n" )
				continue

			u = self.url_handler( self.userdata, newurl, depth+1, a )
			if u:
				self.FollowURL( u, depth+1 )
				#self.LOG( "follow\n" )
			#else:
				#self.LOG( "reject\n" )


