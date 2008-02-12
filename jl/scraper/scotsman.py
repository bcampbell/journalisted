#!/usr/bin/env python2.4
#
# Scraper for The Scotsman
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#

import sys
import re
from datetime import datetime
import sys

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup,BeautifulStoneSoup
from JL import ukmedia, ScraperUtils




scotsman_rssfeeds = {
	"Aberdeen - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=6995",
	"Arts - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7096",
	"Athletics - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7087",
	"Banking & Insurance - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7079",
	"Banking - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7058",
	"Books - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7006",
	"Boxing - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7098",
	"Breaking News - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7055",
	"BT Cups - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7099",
	"Business - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=6982",
	"Business Top Stories - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=6983",
#	"Cartoon - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7031",
	"Celebrities - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7016",
	"Comedy - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7001",
	"Comment - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7007",
	"Credit Cards - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7105",
	"Cricket - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7032",
	"Critique": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=9817",
	"Culture - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7048",
	"Digital - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7067",
	"Division 1 - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7034",
	"Division 2 - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7023",
	"Division 3 - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7035",
	"Drink - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7065",
	"Dundee - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7030",
	"e-business - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7080",
	"Economics - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7012",
	"Edinburgh - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7029",
	"Education - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=6988",
	"Energy & Utilities - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7014",
	"English - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7026",
	"Entertainment - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7010",
	"Environment - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=10193",
	"Environment - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=10336",
	"European Club - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7027",
	"Fashion - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7111",
	"Features - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=6996",
	"Features - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7114",
	"Festival - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7100",
	"Film - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7003",
	"Food - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7109",
	"Food, Drink & Agriculture - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7077",
	"Football - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=6991",
	"Formula One - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7020",
	"Gadgets - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7068",
	# don't support other languages yet... 
#	"Gaelic - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7004",
	"Games - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=6999",
	"Games - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7112",
	"Genealogy - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7070",
	"Glasgow - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7009",
	"Golf - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7072",
	"Great Scots - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7102",
	"Health - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=6989",
	"Health - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7066",
	"Heritage - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7045",
	"Historic Sites - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7103",
	"Homes & Gardens - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7069",
	"Horse Racing - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=6984",
	"Industry - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7081",
	"Ingenuity - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7047",
	"Insurance - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7107",
	"Int'l Football - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7093",
	"International - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7000",
	"International - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=11293",
	"Inverness - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7085",
# These are all Press Association articles:
#	"Latest East Anglia News - National": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=10966",
#	"Latest East Midlands News - National": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=10965",
#	"Latest Entertainment Video - National": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=6438",
#	"Latest Irish News - National": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=5909",
#	"Latest London News - National": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=10968",
#	"Latest National News - National": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=4068",
#	"Latest National Sport - National": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=4069",
#	"Latest North East News - National": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=10964",
#	"Latest Scottish News - National": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=5908",
#	"Latest South East News - National": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=10967",
#	"Latest South West News - National": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=5905",
#	"Latest Sport Video - National": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=6437",
#	"Latest UK News Video - National": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=6436",
#	"Latest West Midlands News - National": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=5906",
#	"Latest York and Humberside News - National": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=5907",
	"Leaders - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7076",
	"League Cup - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7092",
#	"Letters - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7008",
	"Life Insurance - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7060",
	"Loans - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7106",
	"Management - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7082",
	"Market Reports - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7086",
	"Media & Leisure - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7078",
	"Mortgages - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7056",
	"Motorbikes - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7021",
	"Motorsport - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=6994",
	"Movies - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7097",
	"Movies - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=11291",
	"Music - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7002",
	"Music - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7018",
	"My Story - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7115",
	"Myths - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7104",
	"Natural - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7049",
	"Nature - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7041",
	"News - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=6985",
	"News - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7024",
	"News - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7050",
	"Odd - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7108",
	"Olympics - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7073",
	"Online - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7113",
	"Opinion - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7074",
	"Other sports - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7101",
	"Outdoors - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7017",
	"Pensions - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7059",
	"People - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7046",
	"People - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7095",
	"Performing Arts - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7063",
	"Personal Finance - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7075",
	"Politics - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=6990",
	"Premiership 1 - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7088",
	"Premiership 2 - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7036",
	"Premiership 3 - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7089",
	"Previews - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7040",
	"Profiles - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7043",
	"Rallying - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7033",
#	"Reader Offers - National": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=10083",
	"Recipes - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7110",
	"Restaurants - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7064",
	"Retail - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7083",
	"Reviews - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7005",
	"Reviews - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7039",
	"Rugby - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=6993",
	"Savings - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7057",
	"Sci-Tech - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=6998",
	"Science - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7091",
	"Scotland - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7042",
	"Scotsman Magazine": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=9819",
	"Scottish Cup - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=6987",
	"Showbiz - National": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=4070",
	"Six Nations/Int'l - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7037",
	"Snooker - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7019",
	"SoS Review - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=9821",
	"Spectrum - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=9820",
	"SPL - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7022",
	"Sport - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=6980",
	"Sport Top Stories - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=6981",
	"Superteams - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7090",
	"Tax - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7061",
	"Technology - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7038",
	"Technology - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7084",
	"Tennis - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=6992",
	"Top Stories - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=6986",
	"Traditions - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7051",
	"Transport - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7013",
	"Transport - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=10186",
	"Transport - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=10337",
	"Travel - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7015",
	"TV & Radio - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7094",
	"UK - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7071",
	"Visual Arts - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7062",
	"World - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=12007",
	"World Cup - Scotland": "http://thescotsman.scotsman.com/getFeed.aspx?Format=rss&sectionid=7028",
}



def Extract( html, context ):
	art = context
	# for some reason BeautifulSoup gets the encoding as ISO-8859-1...
	# but we know it's utf-8
	soup = BeautifulSoup( html, fromEncoding='utf-8' )


	artdiv = soup.find( 'div', {'id':'viewarticle'} )
	if not artdiv and html.find( "The article has been unable to display.") != -1:
		ukmedia.DBUG2( "IGNORE article ('unable to display') (%s)\n" % ( art['srcurl']) );
		return None

	h1 = artdiv.find('h1')

	headline = h1.renderContents( None )
	headline = ukmedia.FromHTML( headline )
	art['title'] = headline

	if headline in ( u'Cryptic crossword', u'Compact crossword' ):
		ukmedia.DBUG2( "IGNORE '%s' (%s)\n" % ( headline, art['srcurl']) );
		return None

	firstparadiv = artdiv.find( 'div', {'id':'ds-firstpara'} )
	desc = firstparadiv.renderContents( None )
	desc = ukmedia.FromHTML( desc )
	art['description'] = desc

	byline = u''
	bylinediv = artdiv.find( 'div', {'id':'ds-byline'} )
	bylinetextdiv = artdiv.find( 'div', {'id':'ds-bylinetext'} )
	if bylinediv:
		byline = bylinediv.renderContents( None ).strip()
		if bylinetextdiv:
			extra = bylinetextdiv.renderContents(None).strip()
			if extra:
				byline = byline + u', ' + extra
		byline = ukmedia.FromHTML( byline )

	# for some sections, try and extract journo from first para
#	if not byline and '/opinion/' in art['srcurl']:
#		byline = ukmedia.ExtractAuthorFromParagraph( ukmedia.DecapNames(desc) )


	art['byline'] = byline


	bodydiv = artdiv.find( 'div', {'id':'va-bodytext'} )

	for cruft in bodydiv.findAll( 'div', {'id':'va-inlinerightwrap'}):
		cruft.extract()
	for cruft in bodydiv.findAll( 'div', {'id':'ds-mpu'}):
		cruft.extract()

	content = firstparadiv.renderContents(None)
	content = content + bodydiv.renderContents(None)
	content = content.replace( "<br />", "<br />\n" )
	art['content'] = content


	metadiv = soup.find( 'div', {'class':'metadata'} )

	# pull out date
	# eg "<li> <strong> Published Date: </strong> 12 February 2008 </li>"
	datemarker = metadiv.find( text=re.compile('Published Date:') )
	li = datemarker.findParent('li')
	li.strong.extract()
	datetxt = li.renderContents( None ).strip()
	art['pubdate'] = ukmedia.ParseDateTime( datetxt )

	# pull out publication
	# eg"<li> <span id="spanPub"> <strong> Source: </strong> The Scotsman </span> </li>"

	return art


# pattern to extract unique id from FT urls
# eg "http://thescotsman.scotsman.com/latestnews/SNP-threatens-to-tax-supermarkets.3766548.jp"
idpat = re.compile( "/([^/]+[.][0-9]+[.]jp)" )

def CalcSrcID( url ):
	""" extract unique id from url """
	m = idpat.search( url )
	return m.group(1)


def ScrubFunc( context, entry ):
	context['srcid'] = CalcSrcID( context['srcurl'] )
	return context


def FindArticles():
	""" get a set of articles to scrape from the express rss feeds """
	return ukmedia.FindArticlesFromRSS( scotsman_rssfeeds, u'scotsman', ScrubFunc )



def ContextFromURL( url ):
	"""Build up an article scrape context from a bare url."""
	context = {}
	context['srcurl'] = url
	context['permalink'] = url
	context['srcid'] = CalcSrcID( url )
	context['srcorgname'] = u'scotsman'
	context['lastseen'] = datetime.now()
	return context


if __name__ == "__main__":
    ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract )

