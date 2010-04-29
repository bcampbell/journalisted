#!/usr/bin/env python
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
#
# telegraph blogs are hosted by onesite.com
#
# TODO:
#
# - better sundaytelegraph detection?
#
# - tidy URLs ( strip jsessionid etc)
#     http://www.telegraph.co.uk/earth/main.jhtml?view=DETAILS&grid=&xml=/earth/2007/07/19/easeabird119.xml
#     (strip view param)
#
# - handle multi-page articles (currently only pick up first page) (is this a problem with new website format too?)
#

import re
from datetime import datetime
import sys
import os
import urlparse

import site
site.addsitedir("../pylib")
import BeautifulSoup
from JL import ukmedia, ScraperUtils



# telegraph non-blog feedlist automatically scraped by ./telegraph-scrape-rsslist.py
# (run 2009-02-06 16:50:56)
# got 301 feeds
rssfeeds = [
    ("Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&nodeID=201610"),
    ("Peter Foster : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=9521426"),
    ("Ways Blogs", "http://rss.blogs.telegraph.co.uk/rest/svcLeague?action=getLeagueContent&nodeID=201610&contentType=blog_post&RSS=1&name=Ways"),
    ("Damian Thompson : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=9521595"),
    ("Economic Blogs", "http://rss.blogs.telegraph.co.uk/rest/svcLeague?action=getLeagueContent&nodeID=201610&contentType=blog_post&RSS=1&name=Economic"),
    ("Shane Richmond : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=9388403"),
    ("Mick Brown : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=16630507"),
    ("Julie Henry : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=15494517"),
    ("Erin Baker : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=9388861"),
    ("Sarah Marcus : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=10398397"),
    ("Toby Harnden : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=9388895"),
    ("Bruno Waterfield in Brussels : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=9522038"),
    ("frame Blogs", "http://rss.blogs.telegraph.co.uk/rest/svcLeague?action=getLeagueContent&nodeID=201610&contentType=blog_post&RSS=1&name=frame"),
    ("Couch Blogs", "http://rss.blogs.telegraph.co.uk/rest/svcLeague?action=getLeagueContent&nodeID=201610&contentType=blog_post&RSS=1&name=Couch"),
    ("Rugby Blogs", "http://rss.blogs.telegraph.co.uk/rest/svcLeague?action=getLeagueContent&nodeID=201610&contentType=blog_post&RSS=1&name=Rugby"),
    ("Richard Spencer : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=9388393"),
    ("Home feed", "http://www.telegraph.co.uk/rss"),
    ("Lifestyle feed", "http://www.telegraph.co.uk/lifestyle/rss"),
    ("The Blogs", "http://rss.blogs.telegraph.co.uk/rest/svcLeague?action=getLeagueContent&nodeID=201610&contentType=blog_post&RSS=1&name=The"),
    ("Malcolm Moore : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=10919787"),
    ("Christopher Howse : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=9521551"),
    ("Lucy Jones : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=13758667"),
    ("reel Blogs", "http://rss.blogs.telegraph.co.uk/rest/svcLeague?action=getLeagueContent&nodeID=201610&contentType=blog_post&RSS=1&name=reel"),
    ("Motoring feed", "http://www.telegraph.co.uk/motoring/rss"),
    ("Faithbook Blogs", "http://rss.blogs.telegraph.co.uk/rest/svcLeague?action=getLeagueContent&nodeID=201610&contentType=blog_post&RSS=1&name=Faithbook/"),
    ("Milo Yiannopoulos : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=31131067"),
    ("Jon  Doust : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=9522046"),
    ("Gerald Warner : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=10283411"),
    ("Three Blogs", "http://rss.blogs.telegraph.co.uk/rest/svcLeague?action=getLeagueContent&nodeID=201610&contentType=blog_post&RSS=1&name=Three"),
    ("Ian Douglas : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=9388408"),
    ("Audio Blogs", "http://rss.blogs.telegraph.co.uk/rest/svcLeague?action=getLeagueContent&nodeID=201610&contentType=blog_post&RSS=1&name=Audio"),
    ("Boxing and MMA Blogs", "http://rss.blogs.telegraph.co.uk/rest/svcLeague?action=getLeagueContent&nodeID=201610&contentType=blog_post&RSS=1&name=Boxing and MMA"),
    ("Peter Wedderburn : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=14403307"),
    ("Emma Hartley : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=15945127"),
    ("brassneck Blogs", "http://rss.blogs.telegraph.co.uk/rest/svcLeague?action=getLeagueContent&nodeID=201610&contentType=blog_post&RSS=1&name=brassneck/"),
    ("Between Blogs", "http://rss.blogs.telegraph.co.uk/rest/svcLeague?action=getLeagueContent&nodeID=201610&contentType=blog_post&RSS=1&name=Between"),
    ("Olympics Blogs", "http://rss.blogs.telegraph.co.uk/rest/svcLeague?action=getLeagueContent&nodeID=201610&contentType=blog_post&RSS=1&name=Olympics"),
    ("George Pitcher : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=9522263"),
    ("The Blogs", "http://rss.blogs.telegraph.co.uk/rest/svcLeague?action=getLeagueContent&nodeID=201610&contentType=blog_post&RSS=1&name=The"),
    ("Gimson Unbound by Andrew Gimson : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=9389047"),
    ("Stephen Hough : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=16903337"),
    ("Constance Harding : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=9522185"),
    ("Property feed", "http://www.telegraph.co.uk/property/rss"),
    ("Food and Drink feed", "http://www.telegraph.co.uk/foodanddrink/rss"),
    ("F1 Blogs", "http://rss.blogs.telegraph.co.uk/rest/svcLeague?action=getLeagueContent&nodeID=201610&contentType=blog_post&RSS=1&name=F1"),
    ("Fashion feed", "http://www.telegraph.co.uk/fashion/rss"),
    ("Pubs feed", "http://www.telegraph.co.uk/foodanddrink/pubs/rss"),
    ("Culture feed", "http://www.telegraph.co.uk/culture/rss"),
    ("Food and Drink Advice feed", "http://www.telegraph.co.uk/foodanddrink/foodanddrinkadvice/rss"),
    ("Motor Sport feed", "http://www.telegraph.co.uk/motoring/motorsport/rss"),
    ("Recipes feed", "http://www.telegraph.co.uk/foodanddrink/recipes/rss"),
    ("Adrian Michaels : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=15456377"),
    ("Beauty feed", "http://www.telegraph.co.uk/fashion/beauty/rss"),
    ("Richard Tyler : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=9411543"),
    ("Gardening feed", "http://www.telegraph.co.uk/gardening/rss"),
    ("Family feed", "http://www.telegraph.co.uk/family/rss"),
    ("Martin Webb : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=16566047"),
    ("Sport feed", "http://www.telegraph.co.uk/sport/rss"),
    ("Property Video feed", "http://www.telegraph.co.uk/property/propertyvideo/rss"),
    ("Horse Racing feed", "http://www.telegraph.co.uk/sport/horseracing/rss"),
    ("Tim Butcher : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=16562187"),
    ("Style feed", "http://www.telegraph.co.uk/fashion/style/rss"),
    ("Motorbikes feed", "http://www.telegraph.co.uk/motoring/motorbikes/rss"),
    ("Other Sports feed", "http://www.telegraph.co.uk/sport/othersports/rss"),
    ("Restaurants feed", "http://www.telegraph.co.uk/foodanddrink/restaurants/rss"),
    ("Cricket feed", "http://www.telegraph.co.uk/sport/cricket/rss"),
    ("Twenty20 feed", "http://www.telegraph.co.uk/sport/cricket/twenty20/rss"),
    ("Global London Blogs", "http://rss.blogs.telegraph.co.uk/rest/svcLeague?action=getLeagueContent&nodeID=201610&contentType=blog_post&RSS=1&name=Global London"),
#    ("Fashion Picture Galleries feed", "http://www.telegraph.co.uk/fashion/fashionpicturegalleries/rss"),
    ("TV and Radio feed", "http://www.telegraph.co.uk/culture/tvandradio/rss"),
    ("Paper Blogs", "http://rss.blogs.telegraph.co.uk/rest/svcLeague?action=getLeagueContent&nodeID=201610&contentType=blog_post&RSS=1&name=Paper"),
    ("Sport Video feed", "http://www.telegraph.co.uk/sport/sportvideo/rss"),
    ("Culture Video feed", "http://www.telegraph.co.uk/culture/culturevideo/rss"),
    ("Football feed", "http://www.telegraph.co.uk/sport/football/rss"),
    ("Janet Daley : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=9399851"),
    ("Dean Nelson : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=30670037"),
    ("Car Reviews feed", "http://www.telegraph.co.uk/motoring/carreviews/rss"),
    ("Ambrose Evans-Pritchard : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=9389088"),
    ("Rick Maybury : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=15790067"),
    ("Health feed", "http://www.telegraph.co.uk/health/rss"),
    ("International feed", "http://www.telegraph.co.uk/sport/cricket/international/rss"),
    ("Henry Samuel : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=16376717"),
    ("Football feed", "http://www.telegraph.co.uk/sport/football/rss"),
    ("Cricket Blogs", "http://rss.blogs.telegraph.co.uk/rest/svcLeague?action=getLeagueContent&nodeID=201610&contentType=blog_post&RSS=1&name=Cricket"),
    ("Formula One feed", "http://www.telegraph.co.uk/sport/motorsport/formulaone/rss"),
    ("Eagle Blogs", "http://rss.blogs.telegraph.co.uk/rest/svcLeague?action=getLeagueContent&nodeID=201610&contentType=blog_post&RSS=1&name=Eagle"),
    ("Cash Blogs", "http://rss.blogs.telegraph.co.uk/rest/svcLeague?action=getLeagueContent&nodeID=201610&contentType=blog_post&RSS=1&name=Cash"),
    ("Music Video feed", "http://www.telegraph.co.uk/culture/culturevideo/musicvideo/rss"),
    ("Paris Haute Couture Week feed", "http://www.telegraph.co.uk/fashion/paris-haute-couture-week/rss"),
    ("Telegraph TV feed", "http://www.telegraph.co.uk/telegraphtv/rss"),
    ("Travel feed", "http://www.telegraph.co.uk/travel/rss"),
    ("Telegraph Cricket Academy feed", "http://www.telegraph.co.uk/telegraphtv/telegraphcricketacademy/rss"),
    ("Technotes Blogs", "http://rss.blogs.telegraph.co.uk/rest/svcLeague?action=getLeagueContent&nodeID=201610&contentType=blog_post&RSS=1&name=Technotes"),
    ("Bryony Gordon : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=9521597"),
    ("News feed", "http://www.telegraph.co.uk/news/rss"),
    ("UK Politics Video feed", "http://www.telegraph.co.uk/news/newstopics/politics/ukpoliticsvideo/rss"),
    ("News Video feed", "http://www.telegraph.co.uk/news/newsvideo/rss"),
    ("Leagues feed", "http://www.telegraph.co.uk/sport/football/leagues/rss"),
    ("Health News feed", "http://www.telegraph.co.uk/health/healthnews/rss"),
    ("Technology Video feed", "http://www.telegraph.co.uk/scienceandtechnology/technology/technologyvideo/rss"),
    ("Culture Minute feed", "http://www.telegraph.co.uk/culture/culturevideo/cultureminute/rss"),
    ("TV on demand feed", "http://www.telegraph.co.uk/culture/tvandradio/tv-on-demand/rss"),
    ("Sister2Sister feed", "http://www.telegraph.co.uk/family/sister2sister/rss"),
    ("Shopping and Fashion feed", "http://www.telegraph.co.uk/fashion/shoppingandfashion/rss"),
    ("Car Accessories feed", "http://www.telegraph.co.uk/motoring/caraccessories/rss"),
    ("Books feed", "http://www.telegraph.co.uk/culture/books/rss"),
    ("Olympics Video feed", "http://www.telegraph.co.uk/sport/sportvideo/olympicsvideo/rss"),
    ("Labour feed", "http://www.telegraph.co.uk/news/newstopics/politics/labour/rss"),
    ("Politics feed", "http://www.telegraph.co.uk/news/newstopics/politics/rss"),
    ("Video Game Reviews and Previews feed", "http://www.telegraph.co.uk/scienceandtechnology/technology/technologyreviews/videogamereviewsandpreviews/rss"),
    ("Celebrity Video feed", "http://www.telegraph.co.uk/news/newstopics/celebritynews/celebrityvideo/rss"),
    ("Property News feed", "http://www.telegraph.co.uk/property/propertynews/rss"),
    ("Finance Video feed", "http://www.telegraph.co.uk/finance/financevideo/rss"),
    ("DVD Trailers feed", "http://www.telegraph.co.uk/culture/film/dvdtrailers/rss"),
    ("Family Video feed", "http://www.telegraph.co.uk/family/familyvideo/rss"),
    ("Daniel Hannan : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=9389136"),
    ("News Topics feed", "http://www.telegraph.co.uk/news/newstopics/rss"),
    ("Demotix : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=11455697"),
    ("Gillian Reynolds feed", "http://www.telegraph.co.uk/culture/culturecritics/gillianreynolds/rss"),
    ("Personal Finance feed", "http://www.telegraph.co.uk/finance/personalfinance/rss"),
    ("Dance feed", "http://www.telegraph.co.uk/culture/theatre/dance/rss"),
    ("Hilary Alexander feed", "http://www.telegraph.co.uk/fashion/hilaryalexander/rss"),
    ("Music feed", "http://www.telegraph.co.uk/culture/music/rss"),
    ("Insurance feed", "http://www.telegraph.co.uk/finance/personalfinance/insurance/rss"),
    ("Acting Blogs", "http://rss.blogs.telegraph.co.uk/rest/svcLeague?action=getLeagueContent&nodeID=201610&contentType=blog_post&RSS=1&name=Acting"),
    ("Gardening Equipment feed", "http://www.telegraph.co.uk/gardening/gardeningequipment/rss"),
    ("Book Reviews feed", "http://www.telegraph.co.uk/culture/books/bookreviews/rss"),
    ("News feed", "http://www.telegraph.co.uk/motoring/news/rss"),
    ("Football Blogs", "http://rss.blogs.telegraph.co.uk/rest/svcLeague?action=getLeagueContent&nodeID=201610&contentType=blog_post&RSS=1&name=Football"),
    ("Car Advice feed", "http://www.telegraph.co.uk/motoring/caradvice/rss"),
    ("Comment feed", "http://www.telegraph.co.uk/comment/rss"),
    ("Football feed", "http://www.telegraph.co.uk/sport/football/rss"),
    ("Tracy Corrigan : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=15871807"),
    ("Overseas Property feed", "http://www.telegraph.co.uk/property/overseasproperty/rss"),
    ("Destinations feed", "http://www.telegraph.co.uk/travel/destinations/rss"),
    ("Consumer Tips feed", "http://www.telegraph.co.uk/finance/personalfinance/consumertips/rss"),
    ("The Asia File : Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=14402577"),
    ("Classical Music feed", "http://www.telegraph.co.uk/culture/music/classicalmusic/rss"),
    ("Finance feed", "http://www.telegraph.co.uk/finance/rss"),
    ("Technology feed", "http://www.telegraph.co.uk/scienceandtechnology/technology/rss"),
    ("Art feed", "http://www.telegraph.co.uk/culture/art/rss"),
    ("Motoring Video feed", "http://www.telegraph.co.uk/motoring/motoringvideo/rss"),
    ("Lifestyle Video feed", "http://www.telegraph.co.uk/lifestyle/lifestylevideo/rss"),
    ("UK News feed", "http://www.telegraph.co.uk/news/uknews/rss"),
    ("Travel Video feed", "http://www.telegraph.co.uk/travel/travelvideo/rss"),
    ("Celebrity news feed", "http://www.telegraph.co.uk/news/newstopics/celebritynews/rss"),
    ("Business Bullet feed", "http://www.telegraph.co.uk/finance/financevideo/businessbullet/rss"),
    ("Earth Video feed", "http://www.telegraph.co.uk/earth/earthvideo/rss"),
    ("Science Video feed", "http://www.telegraph.co.uk/scienceandtechnology/science/sciencevideo/rss"),
    ("Olympics feed", "http://www.telegraph.co.uk/sport/othersports/olympics/rss"),
    ("sport Blogs", "http://rss.blogs.telegraph.co.uk/rest/svcLeague?action=getLeagueContent&nodeID=201610&contentType=blog_post&RSS=1&name=sport/"),
    ("Lewis Hamilton feed", "http://www.telegraph.co.uk/sport/motorsport/formulaone/lewishamilton/rss"),
    ("Football Transfers feed", "http://www.telegraph.co.uk/sport/football/football-transfers/rss"),
    ("Pre-Budget Report '08 feed", "http://www.telegraph.co.uk/finance/financevideo/prebudgetreport/rss"),
    ("Labels feed", "http://www.telegraph.co.uk/fashion/labels/rss"),
    ("Technology Reviews feed", "http://www.telegraph.co.uk/scienceandtechnology/technology/technologyreviews/rss"),
    ("Tennis feed", "http://www.telegraph.co.uk/sport/tennis/rss"),
    ("Theatre feed", "http://www.telegraph.co.uk/culture/theatre/rss"),
    ("Conservative feed", "http://www.telegraph.co.uk/news/newstopics/politics/conservative/rss"),
    ("Personal View feed", "http://www.telegraph.co.uk/comment/personal-view/rss"),
    ("Formula One feed", "http://www.telegraph.co.uk/sport/motorsport/formulaone/rss"),
    ("Comedy feed", "http://www.telegraph.co.uk/culture/theatre/comedy/rss"),
    ("Film feed", "http://www.telegraph.co.uk/culture/film/rss"),
    ("Earth Comment feed", "http://www.telegraph.co.uk/earth/earthcomment/rss"),
    ("Business Features feed", "http://www.telegraph.co.uk/finance/financevideo/businessfeatures/rss"),
    ("Technology News feed", "http://www.telegraph.co.uk/scienceandtechnology/technology/technologynews/rss"),
    ("Travel News feed", "http://www.telegraph.co.uk/travel/travelnews/rss"),
#    ("Earth Picture Galleries feed", "http://www.telegraph.co.uk/earth/earthpicturegalleries/rss"),
    ("Gardening Video feed", "http://www.telegraph.co.uk/gardening/gardeningvideo/rss"),
    ("Golf feed", "http://www.telegraph.co.uk/sport/golf/rss"),
    ("Obituaries feed", "http://www.telegraph.co.uk/news/obituaries/rss"),
    ("Your Money Their Hands feed", "http://www.telegraph.co.uk/finance/financevideo/yourmoneytheirhands/rss"),
    ("Recession feed", "http://www.telegraph.co.uk/finance/financetopics/recession/rss"),
    ("Rugby Union feed", "http://www.telegraph.co.uk/sport/rugbyunion/rss"),
    ("Comment feed", "http://www.telegraph.co.uk/finance/comment/rss"),
    ("Cinelan Video feed", "http://www.telegraph.co.uk/news/newsvideo/cinelanvideo/rss"),
    ("News Now Video feed", "http://www.telegraph.co.uk/news/newsvideo/newsnowvideo/rss"),
    ("London 2012 feed", "http://www.telegraph.co.uk/sport/othersports/olympics/london2012/rss"),
    ("Family feed", "http://www.telegraph.co.uk/family/rss"),
    ("Get feed updates", "http://www.telegraph.co.uk/family/rss"),
    ("Stars and Stories feed", "http://www.telegraph.co.uk/culture/film/starsandstories/rss"),
    ("Football Video feed", "http://www.telegraph.co.uk/sport/sportvideo/footballvideo/rss"),
    ("Liberal Democrats feed", "http://www.telegraph.co.uk/news/newstopics/politics/liberaldemocrats/rss"),
    ("Wine feed", "http://www.telegraph.co.uk/foodanddrink/wine/rss"),
    ("Earth News feed", "http://www.telegraph.co.uk/earth/earthnews/rss"),
    ("Hotels feed", "http://www.telegraph.co.uk/travel/hotels/rss"),
    ("Football feed", "http://www.telegraph.co.uk/sport/football/rss"),
    ("Gardens to Visit feed", "http://www.telegraph.co.uk/gardening/gardenstovisit/rss"),
    ("Letters feed", "http://www.telegraph.co.uk/comment/letters/rss"),
    ("Food and Drink Video feed", "http://www.telegraph.co.uk/foodanddrink/foodanddrinkvideo/rss"),
    ("Rock and Jazz Music feed", "http://www.telegraph.co.uk/culture/music/rockandjazzmusic/rss"),
    ("Fashion Video feed", "http://www.telegraph.co.uk/fashion/fashionvideo/rss"),
    ("Economics feed", "http://www.telegraph.co.uk/finance/economics/rss"),
    ("Columnists feed", "http://www.telegraph.co.uk/travel/columnists/rss"),
    ("Gardening Advice feed", "http://www.telegraph.co.uk/gardening/gardeningadvice/rss"),
    ("Savings feed", "http://www.telegraph.co.uk/finance/personalfinance/savings/rss"),
    ("Diet and Fitness feed", "http://www.telegraph.co.uk/health/dietandfitness/rss"),
    ("Stage Video feed", "http://www.telegraph.co.uk/culture/culturevideo/stagevideo/rss"),
    ("Science News feed", "http://www.telegraph.co.uk/scienceandtechnology/science/sciencenews/rss"),
    ("Books Video feed", "http://www.telegraph.co.uk/culture/culturevideo/booksvideo/rss"),
    ("TV and Radio Video feed", "http://www.telegraph.co.uk/culture/culturevideo/tvandradiovideo/rss"),
    ("How about that? feed", "http://www.telegraph.co.uk/news/newstopics/howaboutthat/rss"),
    ("Opera feed", "http://www.telegraph.co.uk/culture/music/opera/rss"),
    ("How to Grow feed", "http://www.telegraph.co.uk/gardening/howtogrow/rss"),
    ("Football feed", "http://www.telegraph.co.uk/sport/football/rss"),
    ("Telegraph View feed", "http://www.telegraph.co.uk/comment/telegraph-view/rss"),
    ("Finance Topics feed", "http://www.telegraph.co.uk/finance/financetopics/rss"),
    ("International feed", "http://www.telegraph.co.uk/sport/rugbyunion/international/rss"),
    ("Art Sales feed", "http://www.telegraph.co.uk/culture/art/artsales/rss"),
    ("Football feed", "http://www.telegraph.co.uk/sport/football/rss"),
    ("Counties feed", "http://www.telegraph.co.uk/sport/cricket/counties/rss"),
    ("US Politics Video feed", "http://www.telegraph.co.uk/news/newstopics/politics/uspoliticsvideo/rss"),
    ("Rugby Video feed", "http://www.telegraph.co.uk/sport/sportvideo/rugbyvideo/rss"),
    ("CD Reviews feed", "http://www.telegraph.co.uk/culture/music/cdreviews/rss"),
    ("Cinema Trailers feed", "http://www.telegraph.co.uk/culture/film/cinematrailers/rss"),
    ("Oil Prices feed", "http://www.telegraph.co.uk/finance/financetopics/oilprices/rss"),
    ("Corduroy Mansions by Alexander McCall Smith feed", "http://www.telegraph.co.uk/culture/books/corduroymansionsbyalexandermcca/rss"),
    ("Video Game Trailers feed", "http://www.telegraph.co.uk/scienceandtechnology/technology/technologyreviews/videogametrailers/rss"),
    ("Borrowing feed", "http://www.telegraph.co.uk/finance/personalfinance/borrowing/rss"),
    ("Columnists feed", "http://www.telegraph.co.uk/comment/columnists/rss"),
    ("Liz Hunt feed", "http://www.telegraph.co.uk/comment/columnists/lizhunt/rss"),
    ("Boxing and MMA Video feed", "http://www.telegraph.co.uk/sport/sportvideo/boxingandmmavideo/rss"),
    ("Olympic Events feed", "http://www.telegraph.co.uk/sport/othersports/olympics/olympicsevents/rss"),
    ("Olympics feed", "http://www.telegraph.co.uk/sport/othersports/olympics/rss"),
    ("Weird Stuff Video feed", "http://www.telegraph.co.uk/news/newsvideo/weirdstuffvideo/rss"),
    ("News Features Video feed", "http://www.telegraph.co.uk/news/newsvideo/newsfeaturesvideo/rss"),
    ("ATP Tour feed", "http://www.telegraph.co.uk/sport/tennis/atptour/rss"),
    ("Six Nations feed", "http://www.telegraph.co.uk/sport/rugbyunion/international/sixnations/rss"),
    ("Non-Fiction Reviews feed", "http://www.telegraph.co.uk/culture/books/non_fictionreviews/rss"),
    ("Formula One Video feed", "http://www.telegraph.co.uk/sport/sportvideo/formulaonevideo/rss"),
    ("Types of Trips feed", "http://www.telegraph.co.uk/travel/typesoftrips/rss"),
    ("Theatre Trailers feed", "http://www.telegraph.co.uk/culture/theatre/theatretrailers/rss"),
    ("Wellbeing feed", "http://www.telegraph.co.uk/health/wellbeing/rss"),
    ("Earth feed", "http://www.telegraph.co.uk/earth/rss"),
    ("Cricket Video feed", "http://www.telegraph.co.uk/sport/sportvideo/cricketvideo/rss"),
    ("Damian Reece feed", "http://www.telegraph.co.uk/finance/comment/damianreece/rss"),
    ("Rugby Union feed", "http://www.telegraph.co.uk/sport/rugbyunion/rss"),
    ("Investing feed", "http://www.telegraph.co.uk/finance/personalfinance/investing/rss"),
    ("Film Video feed", "http://www.telegraph.co.uk/culture/culturevideo/filmvideo/rss"),
    ("Road and rail transport feed", "http://www.telegraph.co.uk/news/uknews/road-and-rail-transport/rss"),
    ("Health Advice feed", "http://www.telegraph.co.uk/health/healthadvice/rss"),
    ("Art Video feed", "http://www.telegraph.co.uk/culture/culturevideo/artvideo/rss"),
    ("On This Day Video feed", "http://www.telegraph.co.uk/telegraphtv/onthisdayvideo/rss"),
    ("Markets feed", "http://www.telegraph.co.uk/finance/markets/rss"),
    ("Charles Moore feed", "http://www.telegraph.co.uk/comment/columnists/charlesmoore/rss"),
    ("Environment feed", "http://www.telegraph.co.uk/earth/environment/rss"),
    ("Technology Advice feed", "http://www.telegraph.co.uk/scienceandtechnology/technology/technologyadvice/rss"),
    ("February feed", "http://www.telegraph.co.uk/telegraphtv/onthisdayvideo/february/rss"),
    ("World News feed", "http://www.telegraph.co.uk/news/worldnews/rss"),
    ("Christopher Howse feed", "http://www.telegraph.co.uk/comment/columnists/christopherhowse/rss"),
    ("Questor feed", "http://www.telegraph.co.uk/finance/markets/questor/rss"),
    ("Telegraph Blogs", "http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&nodeID=201610"),
    ("Matthew d'Ancona feed", "http://www.telegraph.co.uk/comment/columnists/matthewd_ancona/rss"),
    ("May feed", "http://www.telegraph.co.uk/telegraphtv/onthisdayvideo/may/rss"),
    ("News by Sector feed", "http://www.telegraph.co.uk/finance/newsbysector/rss"),
    ("Education feed", "http://www.telegraph.co.uk/education/rss"),
    ("Your Business feed", "http://www.telegraph.co.uk/finance/yourbusiness/rss"),
    ("Fiction Reviews feed", "http://www.telegraph.co.uk/culture/books/fictionreviews/rss"),
    ("Wildlife feed", "http://www.telegraph.co.uk/earth/wildlife/rss"),
    ("Film Reviews feed", "http://www.telegraph.co.uk/culture/film/filmreviews/rss"),
    ("Charles Clover feed", "http://www.telegraph.co.uk/earth/earthcomment/charlesclover/rss"),
    ("Primary education feed", "http://www.telegraph.co.uk/education/primaryeducation/rss"),
    ("Pensions feed", "http://www.telegraph.co.uk/finance/personalfinance/pensions/rss"),
    ("Andy Murray feed", "http://www.telegraph.co.uk/sport/tennis/andymurray/rss"),
    ("WTA Tour feed", "http://www.telegraph.co.uk/sport/tennis/wtatour/rss"),
    ("Jenny McCartney feed", "http://www.telegraph.co.uk/comment/columnists/jennymccartney/rss"),
    ("October feed", "http://www.telegraph.co.uk/telegraphtv/onthisdayvideo/october/rss"),
    ("Paralympic Sport feed", "http://www.telegraph.co.uk/sport/othersports/paralympicsport/rss"),
    ("George Pitcher feed", "http://www.telegraph.co.uk/comment/columnists/georgepitcher/rss"),
    ("Drama feed", "http://www.telegraph.co.uk/culture/theatre/drama/rss"),
    ("Cracknell's Beijing  feed", "http://www.telegraph.co.uk/sport/othersports/olympics/cracknellsbeijing/rss"),
    ("Club feed", "http://www.telegraph.co.uk/sport/rugbyunion/club/rss"),
    ("Philip Johnston feed", "http://www.telegraph.co.uk/comment/columnists/philipjohnston/rss"),
    ("Iain Martin feed", "http://www.telegraph.co.uk/comment/columnists/iainmartin/rss"),
    ("Christopher Booker feed", "http://www.telegraph.co.uk/comment/columnists/christopherbooker/rss"),
    ("British and Irish Lions Rugby feed", "http://www.telegraph.co.uk/sport/rugbyunion/international/britishandirishlionsrugby/rss"),
    ("Con Coughlin feed", "http://www.telegraph.co.uk/comment/columnists/concoughlin/rss"),
    ("Ambrose Evans-Pritchard feed", "http://www.telegraph.co.uk/finance/comment/ambroseevans_pritchard/rss"),
    ("September feed", "http://www.telegraph.co.uk/telegraphtv/onthisdayvideo/september/rss"),
    ("Rugby Union feed", "http://www.telegraph.co.uk/sport/rugbyunion/rss"),
    ("Mary Riddell feed", "http://www.telegraph.co.uk/comment/columnists/maryriddell/rss"),
    ("March feed", "http://www.telegraph.co.uk/telegraphtv/onthisdayvideo/march/rss"),
    ("June feed", "http://www.telegraph.co.uk/telegraphtv/onthisdayvideo/june/rss"),
    ("July feed", "http://www.telegraph.co.uk/telegraphtv/onthisdayvideo/july/rss"),
    ("April feed", "http://www.telegraph.co.uk/telegraphtv/onthisdayvideo/april/rss"),
    ("House Prices feed", "http://www.telegraph.co.uk/finance/economics/houseprices/rss"),
    ("Secondary education feed", "http://www.telegraph.co.uk/education/secondaryeducation/rss"),
    ("Tracy Corrigan feed", "http://www.telegraph.co.uk/finance/comment/tracycorrigan/rss"),
    ("Simon Heffer feed", "http://www.telegraph.co.uk/comment/columnists/simonheffer/rss"),
    ("December feed", "http://www.telegraph.co.uk/telegraphtv/onthisdayvideo/december/rss"),
    ("Boris Johnson feed", "http://www.telegraph.co.uk/comment/columnists/borisjohnson/rss"),
    ("January feed", "http://www.telegraph.co.uk/telegraphtv/onthisdayvideo/january/rss"),
    ("November feed", "http://www.telegraph.co.uk/telegraphtv/onthisdayvideo/november/rss"),
    ("Jeff Randall feed", "http://www.telegraph.co.uk/finance/comment/jeffrandall/rss"),
    ("Janet Daley feed", "http://www.telegraph.co.uk/comment/columnists/janetdaley/rss"),
    ("Bryony Gordon feed", "http://www.telegraph.co.uk/comment/columnists/bryonygordon/rss"),
    ("Andrew Pierce feed", "http://www.telegraph.co.uk/comment/columnists/andrewpierce/rss"),
    ("August feed", "http://www.telegraph.co.uk/telegraphtv/onthisdayvideo/august/rss"),
    ("FTSE 100 feed", "http://www.telegraph.co.uk/finance/markets/ftse100/rss"),
    ("Market Report feed", "http://www.telegraph.co.uk/finance/markets/marketreport/rss"),
    ("University education feed", "http://www.telegraph.co.uk/education/universityeducation/rss"),
]

#anny shaw's blog not linked from rest of site, so manually added here:
rssfeeds.append(
    ( "Anny Shaw's blog", 'http://rss.blogs.telegraph.co.uk/rest/ugcBlog?action=getBlogs&rss=1&pCount=3&pullFrom=1&userID=16103657' ) );


def Extract( html, context ):
    # blog url format: (handled by blogs.py)
    # http://blogs.telegraph.co.uk/politics/threelinewhip/feb/speakerfurorenotclasswarfare.htm

    o = urlparse.urlparse( context['srcurl'] )

    if o[1] == 'blogs.telegraph.co.uk':
        return Extract_blog( html, context )

    if o[2].endswith( ".html" ):
        # HTML article url format:
        #   http://www.telegraph.co.uk/travel/africaandindianocean/maldives/759764/Maldives-family-holiday-Game-Boys-v-snorkels.html
        return Extract_HTML_Article( html, context )

    if o[2].endswith( ".jhtml" ):
        # XML article url format (OLD format):
        #   http://www.telegraph.co.uk/news/main.jhtml?xml=/news/2008/02/25/ncameron125.xml
        return Extract_XML_Article( html, context )

    raise Exception, "Uh-oh... don't know how to handle url '%s'" % (context['srcurl'])


def Extract_HTML_Article( html, context ):
    art = context


    # cull out video section before we do anything
    vidpat = re.compile( r"<!-- Start of Brightcove Player -->.*?<!-- End of Brightcove Player -->", re.DOTALL )
    html = vidpat.sub( '', html )

    soup = BeautifulSoup.BeautifulSoup( html )

    # 'storyHead' div contains headline and description
    storyheaddiv = soup.find( 'div', {'class': 'storyHead' } )
    if storyheaddiv is None:     # picture gallery?
        if soup.find( 'div', {'id':'tmglBody' } ) is None and soup.find( 'div',{'class':'tmglSlideshow'} ) is not None:
#            ukmedia.DBUG2( "IGNORE picture gallery '%s' (%s)\n" % (art['title'], art['srcurl']) );
            return None



    title = storyheaddiv.h1.renderContents( None )
    title = ukmedia.FromHTML( title )
    title = u' '.join( title.split() )
    art['title'] = title

    desctxt = u''
    h2 = storyheaddiv.find('h2')
    if h2:
        desctxt = h2.renderContents(None)
        desctxt = ukmedia.FromHTML( desctxt )
        desctxt = u' '.join( desctxt.split() )

    # 'story' div contains byline and main article text
    storydiv = soup.find( 'div', {'class': 'story' } )
    bylinediv = storydiv.find( 'div', {'class':'byline'} )
    # byline div contains both byline and pubdate
    txt = bylinediv.renderContents(None)
    txt = ukmedia.FromHTML( txt )
    txt = u' '.join( txt.split() )
    m = re.match( r"\s*(.*?)\s*(?:(?:Last Updated)|(?:Published)):\s+(.*)", txt )
    art['byline'] = m.group(1)
    pubdatetxt = m.group(2) # eg "11:52PM BST 22 Jul 2008"
    art['pubdate'] = ukmedia.ParseDateTime( pubdatetxt )



    # images
    art['images'] = []
    for ssimg in storydiv.findAll( 'div', {'class': re.compile('ssImg')} ):
        img = ssimg.find('img')
        if img is None:
            continue
        img_url = img['src']
        cap = u''
        cap_span = ssimg.find( 'span', {'class':'caption'} )
        if cap_span is not None:
            cap = ukmedia.FromHTMLOneLine( cap_span.renderContents(None) )

        cred = u''
        credit_span = ssimg.find( 'span', {'class':'credit'} )
        if credit_span is not None:
            cred = ukmedia.FromHTMLOneLine( credit_span.renderContents(None) )

        art['images'].append( { 'url': img_url, 'caption': cap, 'credit': cred } )


    # comments
    art['commentlinks'] = []
    comments_a = bylinediv.find('a',{'href':'#comments'})
    if comments_a:
        num_comments = int( comments_a.renderContents(None) )
        comment_url = urlparse.urljoin( art['srcurl'], comments_a['href'] )
        art['commentlinks'].append( {'num_comments':num_comments, 'comment_url':comment_url} )

    # cull out cruft from the story div:
    bylinediv.extract()
    for cruft in storydiv.findAll( 'div', {'class': re.compile(r'\bslideshow\b') } ):
        cruft.extract()
    for cruft in storydiv.findAll( 'div', {'class': re.compile(r'\brelated_links_inline\b') } ):
        cruft.extract()
    for cruft in storydiv.findAll( 'ul', {'class': 'storylist'} ):
        cruft.extract()
    # inskin ad delivery thingy which wraps around brightcove video player
    for cruft in storydiv.findAll( 'div', {'id':'skin'} ):
        cruft.extract()
    contenttxt = storydiv.renderContents(None)
    contenttxt = ukmedia.SanitiseHTML( contenttxt )
    art['content'] = contenttxt

    if desctxt == u'':
        desctxt = ukmedia.FirstPara( art['content'] )
    art['description'] = desctxt

    return art




def Extract_XML_Article( html, context ):
    # Sometimes the telegraph has missing articles.
    # But the website doesn't return proper 404 (page not found) errors.
    # Instead, it redirects to an error page which has a 200 (OK) code.
    # Sigh.
    # there do seem to be a few borked pages on the site, so we'll treat it
    # as non-fatal (so it won't contribute toward the error count/abort)
    if re.search( """<title>.*404 Error: file not found</title>""", html ):
        raise ukmedia.NonFatal, ("missing article (telegraph doesn't return proper 404s)")

    art = context



    soup = BeautifulSoup.BeautifulSoup( html )

    headline = soup.find( 'h1' )
    if not headline:
        # is it a blog? if so, skip it for now (no byline, so less important to us)
        # TODO: update scraper to handle blog page format
        hd = soup.find( 'div', {'class': 'bloghd'} )
        if hd:
            raise ukmedia.NonFatal, ("scraper doesn't yet handle blog pages (%s) on feed %s" % (context['srcurl'],context['feedname']) );
        # gtb:
        raise ukmedia.NonFatal, ("couldn't find headline to scrape (%s) on feed %s" % (context['srcurl'],context['feedname']) );

    title = ukmedia.DescapeHTML( headline.renderContents(None) )
    # strip out excess whitespace (and compress to one line)
    title = u' '.join( title.split() )
    art['title'] = title

    # try to get pubdate from the page:
    #    Last Updated: <span style="color:#000">2:43pm BST</span>&nbsp;16/04/2007
    filedspan = soup.find( 'span', { 'class': 'filed' } )
    if filedspan:
        # clean it up before passing to ParseDateTime...
        datetext = filedspan.renderContents(None)
        datetext = datetext.replace( "&nsbp;", " " )
        datetext = ukmedia.FromHTML( datetext )
        datetext = re.sub( "Last Updated:\s+", "", datetext )
        pubdate = ukmedia.ParseDateTime( datetext )
        art['pubdate'] = pubdate
    # else just use one from context, if any... (eg from rss feed)


    # NOTE: in a lot of arts, motoring etc... we could get writer from
    # the first paragraph ("... Fred Smith reports",
    # "... talks to Fred Smith" etc)

    bylinespan = soup.find( 'span', { 'class': 'storyby' } )
    byline = u''
    if bylinespan:
        byline = bylinespan.renderContents( None )

        #if re.search( u',\\s+Sunday\\s+Telegraph\\s*$', byline ):
            # byline says it's the sunday telegraph
        #   if art['srcorgname'] != 'sundaytelegraph':
        #       raise Exception, ( "Byline says Sunday Telegraph!" )
        #else:
        #   if art['srcorgname'] != 'telegraph':
        #       raise Exception, ( "Byline says Telegraph!" )

        # don't need ", Sunday Telegraph" on end of byline
        byline = re.sub( u',\\s+Sunday\\s+Telegraph\\s*$', u'', byline )
        byline = ukmedia.FromHTML(byline)
        # single line, compress whitespace, strip leading/trailing space
        byline = u' '.join( byline.split() )

    #
    if byline == u'' and 'dulwich mum' in art['title'].lower():
        byline = u'Bea Parry-Jones';

    art['byline'] = byline


    # Some articles have a hidden bit where the author name is stored:  
    # fill in author name:
    if not byline:
        # cv.c6="/property/features/article/2007/10/25/lpsemi125.xml|Max+Davidson";
        authorMatch = re.search(u'cv.c6=".*?\|(.*?)";', html)
        if authorMatch:
            author = authorMatch.group(1)
            author = re.sub(u'\+',' ',author)                                       # convert + signs to spaces
            author = re.sub(u'\\b([A-Z][a-z]{3,})([A-Z][a-z]+)\\b', '\\1-\\2', author)  # convert SparckJones to Sparck-Jones (that's how they encode it)
            # n.b. {3,} makes McTaggart not go to Mc-Taggart... bit hacky

            # discard "healthtelegraph", "fashiontelegraph" etc...
            if author.lower().find( 'telegraph' ) == -1:
                art['byline'] = unicode( author )

    # text (all paras use 'story' or 'story2' class, so just discard everything else!)
    # build up a new soup with only the story text in it
    textpart = BeautifulSoup.BeautifulSoup()

    art['description'] = ExtractParas( soup, textpart )


    if (not ('byline' in art)) or art['byline']==u'':
        author = ukmedia.ExtractAuthorFromParagraph(art['description'])
        if author!=u'':
            art['byline'] = author

    
# DEBUG:
#   if ('byline2' in art) and ('byline' in art) and art['byline2']!=art['byline']:
#       print "byline2: "+art['byline2']+" ("+art['byline']
#   elif ('byline2' in art):
#       print "byline2: "+art['byline2']

    # Deal with Multiple authors:
    # e.g."Borrowing money is becoming ever more difficult, say Harry Wallop and Faith Archer"

    # Deal with ones with no verb clue but there's only one name:
    #     "Many readers complain that the financial
    #         institutions that are keen to take their money are less willing to
    #         answer legitimate questions. Sometimes the power of the press, in
    #         the shape of Jessica Gorst-Williams, can help"


#################

    # TODO: support multi-page articles
    # check for and grab other pages here!!!
    # (note: printable version no good - only displays 1st page)

    if textpart.find('p') == None:
        # no text!
        if html.find( """<script src="/portal/featurefocus/RandomSlideShow.js">""" ) != -1 or art['title'] == 'Slideshowxl':
            # it's a slideshow, we'll quietly ignore it
            return None
        else:
            raise Exception, 'No text found'


    content = textpart.prettify(None)
    content = ukmedia.DescapeHTML( content )
    content = ukmedia.SanitiseHTML( content )
    art['content'] = content

    return art


# pull out the article body paragraphs in soup and append to textpart
# returns description (taken from first nonblank paragraph)
def ExtractParas( soup, textpart ):
    desc = u''
    for para in soup.findAll( 'p', { 'class': re.compile( 'story2?' ) } ):

        # skip title/byline
        if para.find( 'h1' ):
            continue

        # quit if we hit one with the "post this story" links in it
        if para.find( 'div', { 'class': 'post' } ):
            break

        textpart.insert( len(textpart.contents), para )

        # we'll use first nonblank paragraph as description
        if desc == u'':
            desc = ukmedia.FromHTML( para.renderContents(None) )
            
    # gtb: replace all whitespace (including newlines) by one space... 
    # (needed for author extraction from description)
    desc = re.sub(u'\s+',u' ', desc)
    return desc



# TODO: KILL - no longer needed?
def FindColumnistArticles():
    """Columnists still use old-style section, but rss feed no longer works, so do some cheesy hackery instead for now"""
    ukmedia.DBUG( "----Telegraph----\n" )
    ukmedia.DBUG( "Fetching list of columnists..\n" )
    columnists = {}
    # fetch a list of all the columnists and their pages
    columnistpage = "http://www.telegraph.co.uk/opinion/main.jhtml?menuId=6795&menuItemId=-1&view=DISPLAYCONTENT&grid=A1&targetRule=0"
    html = ukmedia.FetchURL( columnistpage )
    soup = BeautifulSoup.BeautifulSoup(html)
    for d in soup.findAll( 'div', {'class':'menu2'} ):
        name = d.a.renderContents(None)
        url = d.a['href']
        if not "main.jhtml" in url:
            ukmedia.DBUG("  skip %s (in a new-format section)\n" % (name) )
            continue

        if not url.startswith('http://'):
            url = "http://www.telegraph.co.uk" + url
#        ukmedia.DBUG("%s: %s\n" % (name,url) )
        columnists[name] = url

    # now go through each columnist's page looking for story links
    entries = []
    for name,url in columnists.iteritems():
        ukmedia.DBUG( "  fetching stories for %s\n" % (name) )
        html = ukmedia.FetchURL( url )
        soup = BeautifulSoup.BeautifulSoup( html )
        for a in soup.findAll( 'a', {'class':'main'} ):
            art_url = a['href']
            if not art_url.startswith('http://'):
                art_url = "http://telegraph.co.uk" + art_url
            entries.append( ContextFromURL( art_url ) )
#            ukmedia.DBUG( "  %s\n" % (art_url) )

    return entries



def Extract_blog( html, context ):
    """extract fn for telegraph blog posts"""

    art = context
    soup = BeautifulSoup.BeautifulSoup( html )


    container = soup.find( 'div', {'id':'mainColumnContentContainer'} )

    art['title'] = ukmedia.FromHTMLOneLine( container.h1.renderContents(None) )

    smallprint = container.find( 'div',{'class':'oneBlogSmallPrint'} )
    foo = ukmedia.FromHTMLOneLine( smallprint.renderContents( None ) )
    # eg Posted By: Christian Adams at Feb 2, 2009 at 17:01:09 [ General ] Posted in: UK Correspondents , The Drawing Room Tags: snow , weather
    m = re.compile( r"Posted By:\s+(.*?)\s+at\s+(\w+ \d+, \d{4}\s+at\s+\d\d:\d\d:\d\d)" ).search( foo )
    art['byline'] = m.group(1)
    art['pubdate'] = ukmedia.ParseDateTime( m.group(2) )

    contentdiv = container.find( 'div', {'class':re.compile('fullTextContent')} )
    art['content'] = contentdiv.renderContents( None )
    art['description'] = ukmedia.FirstPara( art['content'] )

    art['commentlinks'] = []
    numcomment_div = soup.find('div',{'class':'numComments'})
    if numcomment_div:
        a = numcomment_div.a
        m = re.compile( r'(\d+)\scomment' ).search( a.renderContents(None) )
        if m:
            num_comments = int( m.group(1) )
            comment_url = urlparse.urljoin( art['srcurl'], a['href'] )
            art['commentlinks'].append( {'num_comments':num_comments, 'comment_url':comment_url} )

    return art


# eg http://www.telegraph.co.uk/travel/759562/Is-cabin-air-making-us-sick.html
srcidpat_html = re.compile( "/(\d+)/[^/]+[.]html$" )

# http://www.telegraph.co.uk/earth/main.jhtml?xml=/earth/2008/03/02/earecycling102.xml
# pick out the xml=part
srcidpat_xml = re.compile( "(xml=.*[.]xml)" )

# BLOG url
# http://blogs.telegraph.co.uk/christian_adams/blog/2009/02/02/why_cartoonists_love_drawing_snow
srcidpat_blog = re.compile( "http://blogs.telegraph.co.uk(.*)" )

def CalcSrcID( url ):
    """ extract unique id from url """

    url = url.lower()

    # blog?
    m = srcidpat_blog.match( url )
    if m:
        return 'telegraph_blogs' + m.group(1)

    o = urlparse.urlparse( url )

    if not o[1].endswith( 'telegraph.co.uk' ):
        return None

    m = srcidpat_html.search( o[2] )
    if m:
        return 'telegraph_' + m.group(1)

    # pick out from the the "xml=" param
    m = srcidpat_xml.search( o[4] )
    if m:
        return 'telegraph_' + m.group(1)

    return None


def ScrubFunc( context, entry ):
    """ tidy up context, work out srcid etc... entry param not used """

    url = context['srcurl']

    o = urlparse.urlparse( url )

    # blog?
    if o[1] == 'blogs.telegraph.co.uk':
        context['srcurl'] = url
        context['permalink'] = url
        context['srcid'] = CalcSrcID( url )
        context['srcorgname'] = u'telegraph'
        return context

    # we'll assume that all articles published on a Sunday are from
    # the sunday telegraph...
    # TODO: telegraph and sunday telegraph should share srcid space...
    if ('pubdate' in context) and (context['pubdate'].strftime( '%a' ).lower() == 'sun'):
        context['srcorgname'] = u'sundaytelegraph'
    else:
        context['srcorgname'] = u'telegraph'


    # ignore obvious picture galleries (doesn't catch em all)
    bads = ( '/picturegalleries/','/propertypicturegalleries/','/gardeningpicturegalleries/', '/earthpicturegalleries/', '/fashionpicturegalleries/' )
    for b in bads:
        if b in url:
            return None


    # html article?
    if o[2].lower().endswith( ".html" ):
        # eg "http://www.telegraph.co.uk/travel/759562/Is-cabin-air-making-us-sick.html"
        # trim off all params, fragments...
        url = urlparse.urlunparse( (o[0],o[1],o[2],'','','') );
        context['srcurl'] = url
        context['permalink'] = url
        # use printer version for scraping
#        context['srcurl'] = urlparse.urlunparse( (o[0],o[1],o[2],'','service=print','') );

        context['srcid'] = CalcSrcID( url )
        return context

    # xml article?
    if o[2].lower().endswith( ".jhtml" ):
        # eg "http://www.telegraph.co.uk/money/main.jhtml?xml=/money/2008/02/26/bcnpersim126.xml"

        context['srcid'] = CalcSrcID( url )

        # suppress cruft pages
        if ('title' in context) and (context['title'] == 'Horoscopes'):
            return None

        # skip slideshow pages, eg
        # "http://www.telegraph.co.uk/health/main.jhtml?xml=/health/2007/07/10/pixbeauty110.xml",
        slideshow_pattern = pat=re.compile( '/pix\\w+[.]xml$' )
        if slideshow_pattern.search( context['srcurl'] ):
            return None

        return context

    # some unsupported page...
    return None





def ContextFromURL( url ):
    """Build up an article scrape context from a bare url."""
    context = {}
    context['srcurl'] = url
    context['permalink'] = url
    context['srcid'] = url
    context['lastseen'] = datetime.now()

    # apply the various url-munging rules :-)
    context = ScrubFunc( context, None )

    return context

def FindArticles():
    l = ScraperUtils.FindArticlesFromRSS( rssfeeds, u'telegraph', ScrubFunc )
    return l

if __name__ == "__main__":
    ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract )

#    l = FindArticles()
#    print "=====%d OLD=====" %(len(l))
#    for a in l:
#        print a['srcid'],a['title'].encode('utf-8')

#    l = NEWEST_FindArticles()
#    print "=====%d NEWEST=====" %(len(l))
#    for a in l:
#        print a['srcid'],a['title'].encode('utf-8')


