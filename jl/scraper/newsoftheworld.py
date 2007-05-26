#!/usr/bin/env python2.4
#
# scraper for News of the World
#



import re
from datetime import datetime
import sys

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ArticleDB,ukmedia



siteroot = "http://www.newsoftheworld.co.uk"

# NOTW doesn't seem to have any rss feeds, and the links on the front page
# are a bit rubbish to scrape (they are very generic, aren't permanent and
# link to dodgy pages (eg headlines as images etc...)
# However, there is a ticker page and a navigation page (both embedded using
# iframes). These are for breaking news and have proper links with unique
# IDs and everything. _hopefully_ the vast majority of stories will
# appear here if we scrape often enough...
seedurls = [
	siteroot + "/xml_feeds/ticker_bg.php",
	siteroot + "/xml_feeds/rnav.php"
	]



def FindArticlesOnPage( pagehtml ):
	found = []

	# example link (from ticker_b.php):
	# <a href="../breakingnews.php?article=2007-05-25T233902Z_01_L25282833_RTRIDST_0_OUKTP-UK-FRANCE-TRIAL-SHAFTESBURY.XML&image=2007-05-25T214051Z_01_NOOTR_RTRIDSP_0_OUKTP-UK-FRANCE-TRIAL-SHAFTESBURY.XML" class="ticker">Two convicted for earl&#39;s death&nbsp;</a>

	urlpat = re.compile( "<a href=[\"']\\.\\./(breakingnews.php\\?article=(.*?)&image=.*?)[\"'].*?>(.*?)(\\s*&nbsp;)?\\s*</a>" )

	for m in urlpat.finditer( pagehtml ):

		url = siteroot + "/" + m.group(1)

		context = {
			'srcurl': url,
			'permalink': url,
			'srcid': m.group(2),
			'title': m.group(3),
			'srcorgname': 'notw',
			'lastseen': datetime.now(),
			# no description field available
			}

		found.append(context)
	return found



def Extract( html, context ):
	art = context

	# pattern to pull out just the main article, without all the menus and cruft
	contentpat = re.compile( "<!--- XML stuff BOF --->\\s*(.*?)\\s*<!--- XML stuff EOF --->", re.UNICODE | re.DOTALL )
	mainhtml = contentpat.search( html ).group(1)

	soup = BeautifulSoup( mainhtml )
	td = soup.table.tr.td

	#
	headlinep = td.find( 'p', { 'align':'center', 'class':'blackbold18' } )
	title = ukmedia.FromHTML( headlinep.renderContents(None) )

	# extract pubdate (eg "26 May 2007, 02:10:36 BST")
	datep = td.find( 'p', { 'align':'left', 'class':'grey10' } )
	pubdate = ukmedia.ParseDateTime( datep.renderContents(None).strip() )


	# content is in <p class='black12'> paras
	textpart = BeautifulSoup()
	byline = None
	desc = None
	for para in soup.findAll( 'p', {'class':'black12'} ):
		# first para _might_ be byline.
		if( byline == None ):
			txt = para.renderContents(None).strip()
			m = re.match( "\\s*(By .*?)\\s*", txt, re.UNICODE|re.DOTALL )
			if m:
				byline = txt
				continue
			else:
				byline = u''

		# use first (proper) para for description
		if desc==None:
			desc = para.renderContents( None )


		textpart.append( para )

	content = textpart.prettify(None)

	art['byline' ] = byline
	art['content'] = content
	art['title'] = title
	art['pubdate'] = pubdate
	art['description'] = desc
	return art


def main():

	found = []
	for url in seedurls:
		html = ukmedia.FetchURL( url )
		found.extend( FindArticlesOnPage( html ) )

	store = ArticleDB.ArticleDB()
	ukmedia.ProcessArticles( found, store, Extract )

	return 0

if __name__ == "__main__":
    sys.exit(main())

