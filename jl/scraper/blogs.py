#!/usr/bin/env python2.4
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# Scraper for BBC News blogs site
#
# TODO:
#

import getopt
import re
from datetime import datetime
import sys

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup, Comment
from JL import ArticleDB,ukmedia

#10	bbcnews	BBC News
#11	observer	The Observer
#12	sundaymirror	The Sunday Mirror
#13	sundaytelegraph	The Sunday Telegraph
#3	express	The Daily Express
#1	independent	The Independent
#2	dailymail	The Daily Mail
#4	guardian	The Guardian
#5	mirror	The Mirror
#6	sun	The Sun
#8	times	The Times
#9	sundaytimes	The Sunday Times
#7	telegraph	The Daily Telegraph

# sources used by FindArticles
rssfeedGroups = {

	# Times Online pattern		
	u'times':
	{
		'rssfeeds':
		{
			u'Charles Bremner':							'http://timescorrespondents.typepad.com/charles_bremner/rss.xml',	# 'http://timescorrespondents.typepad.com/charles_bremner/',
			u'Leo Lewis':								'http://timesonline.typepad.com/urban_dirt/rss.xml',				# 'http://timesonline.typepad.com/urban_dirt/',
			u'Ruth Gledhill':							'http://timescolumns.typepad.com/gledhill/rss.xml',
			u'Peter Stothard':							'http://www.timescolumns.typepad.com/stothard/rss.xml',
			u'David Aaronovitch':						'http://timesonline.typepad.com/david_aaronovitch/rss.xml',
			u'Anna Shepherd':							'http://timesonline.typepad.com/eco_worrier/rss.xml',
			u'Gerard Baker':								'http://timescorrespondents.typepad.com/baker/rss.xml'
		}
		,
		'regexp':
		[
			u'''
				(?:
					<meta\ name="description"\ content="
		    			(?P<author>[^,.]+)
			    	.*?
			    )?
			    (?:
			    	<h2\ id="banner-description">
			    		(?P<author2>[^,.]+)
			    	.*?
			    )?
	    		<h2\ class="date-header">
					(?P<date>[^<]+)
				.*?
				<h3\ class="entry-header">
					(?P<title>[^<]+)
				.*?
				<div\ class="entry-body">
					(?P<content>.*?)
				<!--\ technorati\ tags\ -->
			'''
		]
	},
	u'guardian':
	{
		'rssfeeds':
		{
			u'Guardian Blogs (various)':					'http://blogs.guardian.co.uk/atom.xml' 						# 'http://blogs.guardian.co.uk/index.html'
		},
		'regexp':
		[
			u'''
				<h2>
					(?P<author>[^<]+)
				</h2>
				.*?
				<h1>
					(?P<title>[^<]+)
				</h1>
				\s*
				<div\ class="blogs-article-excerpt">
					(?P<description>[^<]*)
				.*?
				<div\ class="blogs-article-date">			
					(?P<date>[^<]+)
				.*?
				<div\ class="blogs-article-content">
					(?P<content>.*?)
				</div>
			''',
			u'''
				<h1>
					(?P<title>[^<]+)
				</h1>
				\s*
				<p\ class="standfirst">
					(?P<description>[^<]*)
				.*?
				<h2>
					(?:<a[^>]*>)?
						(?P<author>[^<]+)
				.*?
				<div\ id="twocolumnleftcolumntopbaselinetext">
					(?P<date>[^|<]+)
				.*?
				</div>
					(?P<content>.*?)
				(?:</div>|<small>)
			'''
		]
	},

	u'bbcnews':
	{
		'rssfeeds':
		{
		    u'The Editors (split out by name)':	        'http://www.bbc.co.uk/blogs/theeditors/rss.xml',                # 'http://www.bbc.co.uk/blogs/theeditors/',
		    u'Evan Davies':	                            'http://www.bbc.co.uk/blogs/thereporters/evandavis/rss.xml',    # 'http://www.bbc.co.uk/blogs/thereporters/evandavis/',
		    u'Five Live Breakfast (split out by name)':	'http://www.bbc.co.uk/blogs/fivelivebreakfast/index.xml',       # 'http://www.bbc.co.uk/blogs/fivelivebreakfast/',
		    u'Mark Mardell':	                            'http://www.bbc.co.uk/blogs/thereporters/markmardell/rss.xml',  # 'http://www.bbc.co.uk/blogs/thereporters/markmardell/',
		    u'Mihir Bose':	                            'http://www.bbc.co.uk/blogs/thereporters/mihirbose/rss.xml',    # 'http://www.bbc.co.uk/blogs/thereporters/mihirbose/',
		    u'Nick Robinson':	                        'http://blogs.bbc.co.uk/nickrobinson/rss.xml',                  # 'http://www.bbc.co.uk/blogs/nickrobinson/',
		    u'Mark Devenport':	                        'http://www.bbc.co.uk/blogs/thereporters/markdevenport/rss.xml',# 'http://www.bbc.co.uk/blogs/thereporters/markdevenport/',
		    u'Robert Peston':	                        'http://www.bbc.co.uk/blogs/thereporters/robertpeston/rss.xml',	# 'http://www.bbc.co.uk/blogs/thereporters/robertpeston/',
		    u'PM Blog (Eddie Mair & others)':	        'http://www.bbc.co.uk/blogs/pm/index.xml',						# 'http://www.bbc.co.uk/blogs/pm/',
		    u'Martin Rosenbaum':	                        'http://www.bbc.co.uk/blogs/opensecrets/rss.xml',				# 'http://www.bbc.co.uk/blogs/opensecrets/',
		    u'Brian Taylor':	                            'http://www.bbc.co.uk/blogs/thereporters/briantaylor/rss.xml',	# 'http://www.bbc.co.uk/blogs/thereporters/briantaylor/',
		    u'Sports editors blogs (Roger Mosey et al)':	'http://www.bbc.co.uk/blogs/sporteditors/index.xml',			# 'http://www.bbc.co.uk/blogs/sporteditors/',
		    u'Newsnight blog (Peter Barron et al)':	    'http://www.bbc.co.uk/blogs/newsnight/index.xml',				# 'http://www.bbc.co.uk/blogs/newsnight/',
		    u'Betsan Powys blog':	                    'http://www.bbc.co.uk/blogs/thereporters/betsanpowys/rss.xml',	# 'http://www.bbc.co.uk/blogs/thereporters/betsanpowys/',
		    u'World Have Your Say (Ros Atkins et al)':	'http://blogs.bbc.co.uk/worldhaveyoursay/index.xml'				# 'http://www.bbc.co.uk/blogs/worldhaveyoursay/'
		},
		'regexp':
		[
			# BBC News blogs pattern:
			u'''
				<div\s+class="entry
				.*?
				<h[^>]*>
					\s*
					(?:<a[^>]*>)?
						(?P<title>[^<]+)
				.*?
				<li\s+class="author">
					\s*
					(?:<a[^>]*>)?
						(?P<author>[^<]+)
				.*?
				<li\s+class="date">
					(?P<date>[^<]+)
					</li>
					\s*
				</ul>
				\s*
				(?P<content>.*?)
				\s*
				(?:
					(?:</div>)
				|
					(?:<ul\ class="ami_social_bookmarks">)
				|
					(?:<p><strong>You\ can\ comment\ on\ this\ entry)
				)
			'''
		]
	},


	u'skynews':
	{
		'rssfeeds':
		{
			u'Adam Boulton & Co':						'http://adamboulton.typepad.com/my_weblog/index.rdf', # 'http://adamboulton.typepad.com/',
			u'Martin Brunt':							'http://skynews4.typepad.com/my_weblog/index.rdf',
			u'Frontline blog (various journalists)':	'http://skynews6.typepad.com/my_weblog/index.rdf',
			u'Editors blog (various editors)':			'http://skynews7.typepad.com/my_weblog/index.rdf',
			u'Technology blogs (various)':				'http://skynews.typepad.com/technologyblog/my_weblog/index.rdf',
			u'Michael Wilson':							'http://www.skynews5.typepad.com/my_weblog/index.rdf',
			u'Paul Bromley':							'http://skynews3.typepad.com/my_weblog/index.rdf',
			u'Greg Milam':								'http://skynews8.typepad.com/my_weblog/index.rdf',
			u'Tim Marshall':							'http://martinstanford.typepad.com/foreign_matters/my_weblog/index.rdf',
		},
		'regexp':
		[
			# Sky News pattern:
			u'''
				<span\ class="entry_header">
					.*?
					<[bB]>
						(?P<title>[^<]+)
				.*?
				<span\ class="mainBlack">
					(?P<date>[^<]+)
					.*?
				<div\ class="entry-body">
				.*?
				(?:
					<strong>
						(?:<img[^>]*>)?					
						(?P<author>[^<]+)
					.*?
				)?
				(?P<content>.*?)
				<div\ class="entry-comments"
			'''
		]
#		<strong>By\ Sky\ News\ 
#			([a-z ]+)
#			([^<]+)
	},

	u'telegraph':
	{
		'rssfeeds':
		{
			u'(Telegraph Blogs)':				'http://blogs.telegraph.co.uk/Feed.rss'	# 'http://blogs.telegraph.co.uk/',
		},
		'regexp':
		[
			u'''
				<h1>
					(?P<blogname>[^<]+)
				.*?
				(?:
					<h2>
						(?P<author>[^<]+)
					.*?
				)?
				<div\ id="bhDescription"><p>
					(?P<author_description>[^<]+)
				.*?
				<h2>
					<a[^>]*>
						(?P<title>[^<]+)
					</a>
				.*?
				\ on\ (?P<date>[^<]+)
				.*?
				<div\ class="postDetails">
					(?P<content>.*?)
				</div>
			'''
		]
	},


	u'independent':
	{
		'rssfeeds':
		{
			u'Independent blogs (various)':				'http://indyblogs.typepad.com/independent/index.rdf' # 'http://indyblogs.typepad.com/',
		},
		'regexp':
		[
			# Indy pattern:
			u'''
				<h2\ class="date-header">
					(?P<date>[^<]+)
				</h2>
				.*?
				<h3\ class="entry-header">
					(?P<title>[^<]+)
				</h3>
				.*?
				<div\ class="entry-body">
					.*?
					>By\ (?P<author>[^<]+)
					.*?
				</a>
					(?P<content>.*?)
				<div\ class="entry-footer">	
			'''
		]
	},

	
	u'dailymail':
	{
		'rssfeeds':
		{
			u'Benedict Brogan':							'http://broganblog.dailymail.co.uk/rss.xml',
			u'Peter Hitchens':							'http://hitchensblog.mailonsunday.co.uk/rss.xml',
			u'Baz Bamigboye':							'http://bazblog.dailymail.co.uk/rss.xml',
			u'Katie Nicholl':							'http://katienicholl.mailonsunday.co.uk/rss.xml',
			u'Natalie Theo':								'http://fashionblog.dailymail.co.uk/rss.xml',
			u'Stephen Wright':							'http://bikeride.dailymail.co.uk/rss.xml',

			u'This is Money':							'http://feeds.feedburner.com/ThisIsMoneyBlog'
#			'Daily Mail blogs (7 of them)':				'http://www.dailymail.co.uk/pages/live/blogs/dailymailblogs.html?in_page_id=1983'
		},
		'regexp':
		[
			# Daily Mail blogs pattern:
			u'''
				<h1>
					(?:<a[^>]*>)?
						(?P<title>[^<]+)
				</h1>
				\s*
				<span\s+class="artByline">by\s+
					(?P<author>[^<]+)
				.*?
				<span\s+class="artDate">
					Last\ updated\ at\s+(?P<date>[^<]+)			
				.*?
				Comments\s+\(\d+\)</a>
					(?P<content>.*?)
				<div\s+id="social_links_sub">
			''',
			u'''
				<h2\ class="date-header">
					(?P<date>[^<]+)
				</h2>
				.*?
				<h3\ class="entry-header">
					(?P<title>[^<]+)
				</h3>
				.*?
				<div\ class="entry-body">
					(?P<content>.*?)
				<(?:p|div)\ class="entry-footer">	
			'''
			# '''
		]
	}
	
}



def Extract( html, context ):
	"""Parse the html of a single article

	html -- the article html
	context -- any extra info we have about the article (from the rss feed)
	"""

	art = context

	soup = BeautifulSoup( html )

#	meta = soup.find( 'meta', { 'name': 'Headline' } )
#	art['title'] = ukmedia.DescapeHTML( meta[ 'content' ] ).strip()

#	meta = soup.find( 'meta', { 'name': 'OriginalPublicationDate' } )
#	art['pubdate'] = ukmedia.ParseDateTime( meta['content'] )

	# TODO: could use first paragraph for a more verbose description
#	meta = soup.find( 'meta', { 'name': 'Description' } )
#	art['description'] = ukmedia.DescapeHTML( meta[ 'content' ] ).strip()

	# byline
#	byline = u''
#	spanbyl = soup.find( 'span', {'class':'byl'} )
#	if spanbyl:	# eg "By Paul Rincon"
#		byline = spanbyl.renderContents(None).strip()
#	spanbyd = soup.find( 'span', {'class':'byd'} )
#	if spanbyd:	# eg "Science reporter, BBC News, Houston"
#		byline = byline + u', ' + spanbyd.renderContents(None).strip()
#	art['byline'] = ukmedia.FromHTML( byline )


#               <div class="entry" id="entry-18926">
#                   <img src="http://www.bbc.co.uk/blogs/theeditors/includes/images/peterhorrocks.jpg" border="0" width="58" height="55" alt="Peter Horrocks" style="border=0;float:left;padding:6px 0 0 10px;margin-right:10px;" />
#               <h3 style="clear:none;width:365px;"><a href="http://www.bbc.co.uk/blogs/theeditors/2007/10/flying_solo.html">Flying solo</a></h3>#
#				<ul class="entrydetails">
#					<li class="author"><a href="http://www.bbc.co.uk/blogs/theeditors/peter_horrocks/">Peter Horrocks</a></li>
#					<li class="date">15 Oct 07, 05:05 PM</li>
#				</ul><br clear="all" />
#				<p>We've ....
				
				
	# just use regexes to extract the article text
	txt = soup.renderContents(None)
#	m = re.search( u'<!--\s*S BO\s*-->(.*)<!--\s*E BO\s*-->', txt, re.UNICODE|re.DOTALL )
	
	
	
		
		

	


	# TODO strip weird non-ascii on date of telegraph


	# Use right pattern for the organisation:
	patterns = rssfeedGroups[context[u'srcorgname']]['regexp']
	
	for pattern in patterns:
		capturedPatternNames = []
		for capturedPatternName in re.finditer(u'\(\?P<([^>]+)>', pattern):
			capturedPatternNames.append(capturedPatternName.group(1))
	
	#	pattern = u'<a(.*)>'
	#	print pattern
	#	print txt.encode('latin-1','replace')
	
		m = re.search( pattern, txt, re.UNICODE|re.DOTALL|re.VERBOSE )
		if m:
			break;

	for fieldName in capturedPatternNames:
		fieldValue = ukmedia.GetGroup(m,fieldName)
	#, fieldValue in m:
#	for i in range(len(fieldOrder)):
#		print fieldOrder[i]
#		fieldValue = m.group(i+1)
#		fieldName = fieldOrder[i]
		if fieldValue:
			art[fieldName] = fieldValue.strip(" -\r\n") # strip extra - and spaces

#	art['title'] = m.group(1)
#	art['author'] = m.group(2)
#	art['date_unparsed'] = m.group(3)
#	art['content'] = m.group(4)


	# fix everything up:
	art['content'] = ukmedia.SanitiseHTML( art['content'] )
	
	def lower(s):
		return s.group(1)+s.group(2).lower()
	if ('author' in art):
		True
	elif ('author2' in art):
		art['author'] = art['author2']		# sometimes author appears in just one of two places
		del art['author2']
	elif ('blogname' in art):
		art['author'] = art['blogname']
	else:
		art['author'] = context['feedname']	# maybe author is not written in page or in RSS, we just know it because of the URL
		
	art['author'] = re.compile('\n').sub(' ', art['author'])	# get rid of newlines
	# change e.g. "DONNA McCONNELL" -> "Donna McConnell"
	art['author'] = re.sub(u'([A-Z])([A-Z]+)', lower, art['author'], re.UNICODE|re.DOTALL)
	art['byline'] = art['author']
	

#	print "\n\nDATE: ",art['date'],"\n\n"

	# Parse date:
	art['pubdate'] = ukmedia.ParseDateTime( art['date'] )
	del art['date']	

	print "\n\nARTICLE (+RSS CONTEXT) FIELDS:"
	for a in art.keys():
		print "\n",a,": "
		# hack:
		if type(art[a])==type(u""):
			print art[a].encode('latin-1','replace')
		else:
			print str(art[a])

	return art




# bbc news rss feeds have lots of blogs and other things in them which
# we don't parse here. We identify news articles by the number in their
# url.
#idpat = re.compile( '/(\d+)\.stm$' )

def ScrubFunc( context, entry ):
#	print context;	
	print u"\n"
	print u"--------------------------------------------------------------------"
	print u"ARTICLE CONTEXT:"
	print u"\n"
	for key in context.keys():
		s = repr(context[key])
		print key.encode('latin-1','replace'),': ',s.encode('latin-1','replace')
	print u"\n"
	print u"ARTICLE RSS FIELDS:"
	print u"\n"
	for key in entry.keys():
		s = repr(entry[key])
		print key.encode('latin-1','replace'),': ',s.encode('latin-1','replace')

#	m = idpat.search( context['srcurl'] )
#	if not m:
#		ukmedia.DBUG2( "SUPPRESS " + context['title'] + " -- " + context['srcurl'] + "\n" )
#		return None		# suppress this article (probably a blog)

	# Also we use this number as the unique id for the beeb, as a story
	# can have multiple paths (eg uk vs international version)
	
	# gtb:
	# for blogs just use URL:
	context['srcid'] = context['srcurl'] # m.group(1)

	return context


def main():
	opts, args = getopt.getopt(sys.argv[1:], "h", ["help"])
	
	DEBUG_SINGLE_TEST_CASE = False # 

	# TODO: filter out "Your Stories" page
	rssfeedGroupsToProcess = rssfeedGroups
#	rssfeedGroupsToProcess = {u'skynews': rssfeedGroups[u'skynews']}		# DEBUG
	for rssfeedGroupName, rssfeedGroup in rssfeedGroupsToProcess.iteritems():
		# e.g. rssfeedGroupName = u'bbcnews'
		
		if DEBUG_SINGLE_TEST_CASE:
			# TEST CASES:
			filename = "webpageExamples/"+rssfeedGroupName+".html"
			f = open(filename, "rb")
			html = f.read()
			f.close()
			context = {u'srcorgname': rssfeedGroupName, u'feedname': "Author Name"}
			Extract(html, context)
		else:
			rssfeeds = rssfeedGroup['rssfeeds']
			found = ukmedia.FindArticlesFromRSS( rssfeeds, rssfeedGroupName, ScrubFunc )

			print "\nFOUND:\n"
			for f in found:
				print ("%s" % ( f['title'] )).encode( "utf-8" )
			print "\n--------------------------\nFOUND IN DETAIL:\n"
			for f in found:
				print ("%s" % ( f )).encode( "utf-8" )
			print "\n--------------------------\n"
			# store = ArticleDB.ArticleDB()
			store = ArticleDB.DummyArticleDB()	# testing
			ukmedia.ProcessArticles( found, store, Extract )
		break;	# debug, just do one
		
	return 0

if __name__ == "__main__":
    sys.exit(main())

