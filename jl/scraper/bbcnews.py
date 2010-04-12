#!/usr/bin/env python
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# Scraper for BBC News site
#
# TODO:
# make use of:
#   http://news.bbc.co.uk/rss/feeds.opml
# (~1600 feeds, multiple languages)
#
# NOTE: we scrape the low-graphics version of the page - much easier.
# some pages give 404 errors for their low-graphics counterpart...
# I _think_ these are video pages (only text is a small caption)

import re
from datetime import datetime
import sys
import urlparse

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup, Comment
from JL import ukmedia, ScraperUtils


# bbc blog feedlist automatically scraped by ./bbcblogs-scrape-rsslist.py
# (run 2009-02-16 12:03:24)
# got 95 feeds
# THEN HAND EDITED!
blog_feeds = [
#    ("BBC Internet blog", "http://www.bbc.co.uk/blogs/bbcinternet/rss.xml"),
#    ("BBCi Labs", "http://www.bbc.co.uk/blogs/bbcilabs/rss.xml"),
    ("The Editors", "http://www.bbc.co.uk/blogs/theeditors/rss.xml"),
    ("JZ's diary (Radio Scotland)", "http://www.bbc.co.uk/blogs/jeffzycinski/index.xml"),
#    ("Radio Labs", "http://www.bbc.co.uk/blogs/radiolabs/index.xml"),
    ("Sport Editors' blog", "http://www.bbc.co.uk/blogs/sporteditors/index.xml"),
#    ("Ouch", "http://www.bbc.co.uk/blogs/ouch/rss.xml"),
    ("Peston's Picks", "http://www.bbc.co.uk/blogs/thereporters/robertpeston/rss.xml"),
    ("The Devenport Diaries", "http://www.bbc.co.uk/blogs/thereporters/markdevenport/rss.xml"),
#    ("Stuart Bailie", "http://www.bbc.co.uk/blogs/stuartbailie/rss.xml"),
    ("Will & Testament", "http://www.bbc.co.uk/blogs/ni/index.xml"),
    ("Blether with Brian", "http://www.bbc.co.uk/blogs/thereporters/briantaylor/rss.xml"),
#    ("Bryan Burnett", "http://www.bbc.co.uk/blogs/bryanburnett//index.xml"),
#    ("JZ's diary", "http://www.bbc.co.uk/blogs/jeffzycinski/index.xml"),
    ("Pauline McLean", "http://www.bbc.co.uk/blogs/thereporters/paulinemclean/rss.xml"),
#    ("Scottish Symphony Orchestra", "http://www.bbc.co.uk/blogs/bbcsso//index.xml"),
    ("Betsan's blog", "http://www.bbc.co.uk/blogs/thereporters/betsanpowys/rss.xml"),
#    ("Blog C2", "http://www.bbc.co.uk/blogs/c2/rss.xml"),
#    ("North-east Wales weblog", "http://blogs.bbc.co.uk/walesnortheast/index.xml"),

# WELSH
###     ("Vaughan Roderick", "http://www.bbc.co.uk/blogs/thereporters/vaughanroderick/rss.xml"),

#    ("Wales Nature", "http://www.bbc.co.uk/blogs/gardenersworld/alysfowlerrss.xml"),
#    ("Bobby Friction", "http://www.bbc.co.uk/blogs/friction//index.xml"),
#    ("Bollywood blog", "http://www.bbc.co.uk/blogs/bollywood//index.xml"),
#    ("Chart blog", "http://www.bbc.co.uk/blogs/chartblog//index.xml"),
#    ("The Culture Show", "http://www.bbc.co.uk/blogs/thecultureshow/rss.xml"),
#    ("Introducing", "http://www.bbc.co.uk/blogs/introducing//index.xml"),
#    ("Kermode Uncut", "http://www.bbc.co.uk/blogs/markkermode/rss.xml"),
#    ("Mike Harding's Folk blog", "http://www.bbc.co.uk/blogs/folk//index.xml"),
#    ("Pauline McLean", "http://www.bbc.co.uk/blogs/thereporters/paulinemclean/rss.xml"),
#    ("Stuart Bailie", "http://www.bbc.co.uk/blogs/stuartbailie/rss.xml"),
#    ("Writers' Room", "http://www.bbc.co.uk/blogs/writersroom/index.xml"),
#    ("Scottish Symphony Orchestra", "http://www.bbc.co.uk/blogs/bbcsso//index.xml"),
#    ("Betsan's blog", "http://www.bbc.co.uk/blogs/thereporters/betsanpowys/rss.xml"),
#    ("Blether with Brian", "http://www.bbc.co.uk/blogs/thereporters/briantaylor/rss.xml"),
#    ("The Devenport Diaries", "http://www.bbc.co.uk/blogs/thereporters/markdevenport/rss.xml"),
#    ("Magazine Monitor", "http://www.bbc.co.uk/blogs/magazinemonitor/rss.xml"),
#    ("Magazine Monitor", "http://www.bbc.co.uk/blogs/magazinemonitor/10_things/rss.xml"),
#    ("Magazine Monitor", "http://www.bbc.co.uk/blogs/magazinemonitor/caption_comp/rss.xml"),
#    ("Magazine Monitor", "http://www.bbc.co.uk/blogs/magazinemonitor/crunch_creep/rss.xml"),
#    ("Magazine Monitor", "http://www.bbc.co.uk/blogs/magazinemonitor/daily_miniquiz/rss.xml"),
#    ("Magazine Monitor", "http://www.bbc.co.uk/blogs/magazinemonitor/housekeeping/rss.xml"),
#    ("Magazine Monitor", "http://www.bbc.co.uk/blogs/magazinemonitor/how_to_say/rss.xml"),
#    ("Magazine Monitor", "http://www.bbc.co.uk/blogs/magazinemonitor/paper_monitor/rss.xml"),
#    ("Magazine Monitor", "http://www.bbc.co.uk/blogs/magazinemonitor/quote_of_the_day/rss.xml"),
#    ("Magazine Monitor", "http://www.bbc.co.uk/blogs/magazinemonitor/random_stat/rss.xml"),
#    ("Magazine Monitor", "http://www.bbc.co.uk/blogs/magazinemonitor/your_letters/rss.xml"),
    ("Mark Easton's UK", "http://www.bbc.co.uk/blogs/thereporters/markeaston/rss.xml"),
    ("Mark Urban", "http://www.bbc.co.uk/blogs/newsnight/markurban/index.xml"),
    ("Michael Crick", "http://www.bbc.co.uk/blogs/newsnight/michaelcrick/index.xml"),
    ("Nick Robinson's Newslog", "http://blogs.bbc.co.uk/nickrobinson/rss.xml"),
    ("Open Secrets", "http://www.bbc.co.uk/blogs/opensecrets/rss.xml"),
    ("James Reynolds' China", "http://www.bbc.co.uk/blogs/thereporters/jamesreynolds/rss.xml"),
    ("Justin Webb's America", "http://www.bbc.co.uk/blogs/thereporters/justinwebb/rss.xml"),
    ("Mark Mardell's Europe", "http://www.bbc.co.uk/blogs/thereporters/markmardell/rss.xml"),
    ("Nick Bryant's Australia", "http://www.bbc.co.uk/blogs/thereporters/nickbryant/rss.xml"),
    ("5 Live Breakfast", "http://www.bbc.co.uk/blogs/fivelivebreakfast/index.xml"),
#    ("Ace & Vis (1Xtra)", "http://www.bbc.co.uk/blogs/aceandvis/index.xml"),
#    ("Bryan Burnett (Radio Scotland)", "http://www.bbc.co.uk/blogs/bryanburnett//index.xml"),
#    ("Chris Evans (Radio 2)", "http://www.bbc.co.uk/blogs/chrisevans//index.xml"),
#    ("Chris Moyles (Radio 1)", "http://www.bbc.co.uk/blogs/chrismoyles/index.xml"),
#    ("Greg James (Radio 1)", "http://www.bbc.co.uk/blogs/gregjames//index.xml"),

# UNSURE if we should do iPM blog
###    ("iPM (Radio 4)", "http://www.bbc.co.uk/blogs/ipm//index.xml"),
#    ("Jo Whiley (Radio 1)", "http://www.bbc.co.uk/blogs/jowhiley/index.xml"),
#    ("Mistajam (1Xtra)", "http://www.bbc.co.uk/blogs/mistajam/rss.xml"),
    ("PM (Radio 4)", "http://www.bbc.co.uk/blogs/pm/index.xml"),
#    ("Pods and Blogs (Radio 5 Live)", "http://www.bbc.co.uk/blogs/podsandblogs/index.xml"),
#    ("Steve Lamacq (6 Music)", "http://www.bbc.co.uk/blogs/stevelamacq/index.xml"),
    ("Today - Evan Davis (Radio 4)", "http://www.bbc.co.uk/blogs/today/evandavis/index.xml"),
    ("Today - Tom Feilden (Radio 4)", "http://www.bbc.co.uk/blogs/today/tomfeilden/index.xml"),
    ("Today - Jim Naughtie (Radio 4)", "http://www.bbc.co.uk/blogs/today/jimnaughtie/index.xml"),
#    ("Victoria Derbyshire (Radio 5 Live)", "http://www.bbc.co.uk/blogs/victoriaderbyshire/index.xml"),
    ("World Tonight (Radio 4)", "http://www.bbc.co.uk/blogs/worldtonight//index.xml"),
#    ("World Update (World Service)", "http://www.bbc.co.uk/blogs/worldupdate/index.xml"),
#    ("Toby Buckland (gardening)", "http://www.bbc.co.uk/blogs/gardenersworld/tobybuckland/rss.xml"),
#    ("Alys Fowler (gardening)", "http://www.bbc.co.uk/blogs/gardenersworld/alysfowler/rss.xml"),
#    ("Joe Swift (gardening)", "http://www.bbc.co.uk/blogs/gardenersworld/joeswift/rss.xml"),
#    ("Euro 2008", "http://www.bbc.co.uk/blogs/football/index.xml"),

    # MIHIR BOSE link is wrong!
#    ("Mihir Bose", "http://www.bbc.co.uk/blogs/mihirbose/rss.xml"),
    # corrected:
    ("Mihir Bose", "http://www.bbc.co.uk/blogs/thereporters/mihirbose/rss.xml"),

#    ("Olympics", "http://www.bbc.co.uk/blogs/olympics/rss.xml"),
#    ("Test Match Special", "http://www.bbc.co.uk/blogs/tms/index.xml"),
    ("Dot.life", "http://www.bbc.co.uk/blogs/technology/rss.xml"),
#    ("Amazon", "http://www.bbc.co.uk/blogs/amazon/rss.xml"),
#    ("Autumnwatch", "http://www.bbc.co.uk/blogs/gardenersworld/rss.xml"),
#    ("The Culture Show", "http://www.bbc.co.uk/blogs/thecultureshow/rss.xml"),
#    ("Gardeners' World", "http://www.bbc.co.uk/blogs/gardenersworld/rss.xml"),
#    ("Last Chance to See", "http://www.bbc.co.uk/blogs/lastchancetosee/rss.xml"),
    ("Newsnight", "http://www.bbc.co.uk/blogs/newsnight/index.xml"),
#    ("The One Show - Backstage", "http://www.bbc.co.uk/blogs/theoneshow/backstage/rss.xml"),
#    ("The One Show - Consumer", "http://www.bbc.co.uk/blogs/theoneshow/consumer/rss.xml"),
#    ("The One Show - One Passions", "http://www.bbc.co.uk/blogs/theoneshow/onepassions/rss.xml"),
#    ("Springwatch", "http://www.bbc.co.uk/blogs/gardenersworld/rss.xml"),
#    ("Watchdog", "http://www.bbc.co.uk/blogs/watchdog/styles.css"),
#    ("BBC Brazil", "http://www.bbc.co.uk/blogs/portuguese/index.xml"),
#    ("BBC Mundo", "http://www.bbc.co.uk/blogs/spanish/index.xml"),
#    ("BBC Urdu", "http://www.bbc.co.uk/blogs/urdu/index.xml"),

    # HMMM.. she isn't listed on bbc.co.uk/blogs...
    ("Razia Iqbal", "http://www.bbc.co.uk/blogs/thereporters/raziaiqbal/rss.xml" ),
]





news_feeds = {
    "BBC News | Also in the news | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/also_in_the_news/rss.xml",
    "BBC News | Business | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/business/rss.xml",
    "BBC News | Africa | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/business/africa/rss.xml",
    "BBC News | Americas | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/business/americas/rss.xml",
    "BBC News | Asia-Pacific | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/business/asia-pacific/rss.xml",
    "BBC News | Business | Companies | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/business/companies/rss.xml",
    "BBC News | Business | Economy | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/business/economy/rss.xml",
    "BBC News | Europe | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/business/europe/rss.xml",
#   "BBC News | Business | Market Data | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/business/market_data/rss.xml",
    "BBC News | Middle East | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/business/middle_east/rss.xml",
    "BBC News | South Asia | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/business/south_asia/rss.xml",
    "BBC News | Business | Your Money | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/business/your_money/rss.xml",
    "BBC News | Education | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/education/rss.xml",
    "BBC News | Education | League Tables | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/education/league_tables/rss.xml",
    "BBC News | England | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/rss.xml",
    "BBC News | England | Beds/Bucks/Herts | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/beds/bucks/herts/rss.xml",
    "BBC News | England | Berkshire | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/berkshire/rss.xml",
    "BBC News | England | Bradford | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/bradford/rss.xml",
    "BBC News | England | Bristol | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/bristol/rss.xml",
    "BBC News | England | Cambridgeshire | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/cambridgeshire/rss.xml",
    "BBC News | England | Cornwall | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/cornwall/rss.xml",
    "BBC News | England | Coventry/Warwickshire | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/coventry_warwickshire/rss.xml",
    "BBC News | England | Cumbria | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/cumbria/rss.xml",
    "BBC News | England | Derbyshire | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/derbyshire/rss.xml",
    "BBC News | England | Devon | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/devon/rss.xml",
    "BBC News | England | Dorset | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/dorset/rss.xml",
    "BBC News | England | Essex | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/essex/rss.xml",
    "BBC News | England | Gloucestershire | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/gloucestershire/rss.xml",
    "BBC News | England | Hampshire | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/hampshire/rss.xml",
    "BBC News | England | Hereford/Worcs | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/hereford/worcs/rss.xml",
    "BBC News | England | Humber | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/humber/rss.xml",
    "BBC News | England | Kent | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/kent/rss.xml",
    "BBC News | England | Lancashire | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/lancashire/rss.xml",
    "BBC News | England | Leicestershire | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/leicestershire/rss.xml",
    "BBC News | England | Lincolnshire | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/lincolnshire/rss.xml",
    "BBC News | England | London | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/london/rss.xml",
    "BBC News | England | Manchester | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/manchester/rss.xml",
    "BBC News | England | Merseyside | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/merseyside/rss.xml",
    "BBC News | England | Norfolk | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/norfolk/rss.xml",
    "BBC News | England | Northamptonshire | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/northamptonshire/rss.xml",
    "BBC News | England | North Yorkshire | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/north_yorkshire/rss.xml",
    "BBC News | England | Nottinghamshire | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/nottinghamshire/rss.xml",
    "BBC News | England | Oxfordshire | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/oxfordshire/rss.xml",
    "BBC News | England | Shropshire | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/shropshire/rss.xml",
    "BBC News | England | Somerset | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/somerset/rss.xml",
    "BBC News | England | Southern Counties | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/southern_counties/rss.xml",
    "BBC News | England | South Yorkshire | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/south_yorkshire/rss.xml",
    "BBC News | England | Staffordshire | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/staffordshire/rss.xml",
    "BBC News | England | Suffolk | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/suffolk/rss.xml",
    "BBC News | England | Surrey | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/surrey/rss.xml",
    "BBC News | England | Sussex | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/sussex/rss.xml",
    "BBC News | England | Tees | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/tees/rss.xml",
    "BBC News | Travel | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/travel/rss.xml",
    "BBC News | England | Tyne | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/tyne/rss.xml",
    "BBC News | England | Wear | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/wear/rss.xml",
    "BBC News | England | West Midlands | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/west_midlands/rss.xml",
    "BBC News | England | West Midlands | Black country | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/west_midlands/black_country/rss.xml",
    "BBC News | England | West Yorkshire | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/west_yorkshire/rss.xml",
    "BBC News | England | Wiltshire | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/wiltshire/rss.xml",
    "BBC News | Entertainment | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/entertainment/rss.xml",
    "BBC News | News Front Page | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/front_page/rss.xml",
    "BBC News | Health | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/health/rss.xml",
#   "BBC News | Health | Medical notes | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/health/medical_notes/rss.xml",
    "BBC News | Special Reports | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/in_depth/rss.xml",
    "BBC News | Latest Published Stories | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/latest_published_stories/rss.xml",
#   "BBC News | Most Emailed Stories | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/livestats/most_emailed/rss.xml",
#   "BBC News | Most Popular Stories | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/livestats/most_read/rss.xml",
    "BBC News | Magazine | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/magazine/rss.xml",
    "BBC News | Magazine | A Point of View | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/magazine/views/a_point_of_view/rss.xml",
    "BBC News | Northern Ireland | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/northern_ireland/rss.xml",
    "BBC News | Media reports | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/not_in_website/syndication/monitoring/media_reports/rss.xml",
    "BBC News | Science/Nature | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/sci/tech/rss.xml",
    "BBC News | Sci/Tech | Climate Change | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/sci/tech/portal/climate_change/rss.xml",
    "BBC News | Scotland | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/scotland/rss.xml",
    "BBC News | Scotland | Edinburgh, East and Fife | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/scotland/edinburgh_and_east/rss.xml",
    "BBC News | Scotland | Glasgow, Lanarkshire and West | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/scotland/glasgow_and_west/rss.xml",
    "BBC News | Scotland | Highlands and Islands | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/scotland/highlands_and_islands/rss.xml",
    "BBC News | Scotland | North East/N Isles | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/scotland/north_east/rss.xml",
    "BBC News | Scotland | Scotland Video and Audio | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/scotland/scotland_video_and_audio/rss.xml",
    "BBC News | Scotland | South of Scotland | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/scotland/south_of_scotland/rss.xml",
    "BBC News | Scotland | Tayside and Central | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/scotland/tayside_and_central/rss.xml",
    "BBC News | Technology | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/technology/rss.xml",
    "BBC News | UK | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/uk/rss.xml",
    "BBC News | Politics | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/uk_politics/rss.xml",
    "BBC News | UK Politics | Northern Ireland politics | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/uk_politics/northern_ireland/rss.xml",
    "BBC News | UK Politics | Scotland politics | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/uk_politics/scotland/rss.xml",
    "BBC News | UK Politics | Wales politics | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/uk_politics/wales/rss.xml",
    "BBC News | Wales | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/wales/rss.xml",
    "BBC News | Wales | Mid Wales | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/wales/mid/rss.xml",
    "BBC News | Wales | North East Wales | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/wales/north_east/rss.xml",
    "BBC News | Wales | North West Wales | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/wales/north_west/rss.xml",
    "BBC News | Wales | South East Wales | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/wales/south_east/rss.xml",
    "BBC News | Wales | South West Wales | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/wales/south_west/rss.xml",
    "BBC News | World | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/world/rss.xml",
    "BBC News | World | Africa | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/world/africa/rss.xml",
#   "BBC News | World | Africa | Country profiles | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/world/africa/country_profiles/rss.xml",
    "BBC News | World | Americas | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/world/americas/rss.xml",
#   "BBC News | World | Americas | Country profiles | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/world/americas/country_profiles/rss.xml",
    "BBC News | World | Asia-Pacific | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/world/asia-pacific/rss.xml",
#   "BBC News | World | Asia-Pacific | Country profiles | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/world/asia-pacific/country_profiles/rss.xml",
    "BBC News | World | Europe | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/world/europe/rss.xml",
#   "BBC News | World | Europe | Country profiles | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/world/europe/country_profiles/rss.xml",
    "BBC News | World | Europe | Guernsey | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/world/europe/guernsey/rss.xml",
    "BBC News | World | Europe | Isle of Man | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/world/europe/isle_of_man/rss.xml",
    "BBC News | World | Europe | Jersey | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/world/europe/jersey/rss.xml",
    "BBC News | World | Middle East | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/world/middle_east/rss.xml",
#   "BBC News | World | Middle East | Country profiles | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/world/middle_east/country_profiles/rss.xml",
    "BBC News | World | South Asia | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/world/south_asia/rss.xml",
#   "BBC News | World | South Asia | Country profiles | UK Edition": "http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/world/south_asia/country_profiles/rss.xml",
#   "BBC Sport | 606 | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/606/rss.xml",
    "BBC Sport | Sport Academy | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/academy/rss.xml",
    "BBC Sport | Athletics | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/athletics/rss.xml",
    "BBC Sport | Boxing | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/boxing/rss.xml",
    "BBC Sport | Cricket | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/cricket/rss.xml",
    "BBC Sport | Berkshire | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/england/berkshire/rss.xml",
    "BBC Sport | Birmingham | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/england/birmingham/rss.xml",
    "BBC Sport | Black Country | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/england/black_country/rss.xml",
    "BBC Sport | Bristol | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/england/bristol/rss.xml",
    "BBC Sport | Cornwall | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/england/cornwall/rss.xml",
    "BBC Sport | Coventry and Warwickshire | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/england/coventry_and_warwickshire/rss.xml",
    "BBC Sport | Cumbria | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/england/cumbria/rss.xml",
    "BBC Sport | Derbyshire | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/england/derbyshire/rss.xml",
    "BBC Sport | Devon | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/england/devon/rss.xml",
    "BBC Sport | Gloucestershire | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/england/gloucestershire/rss.xml",
    "BBC Sport | Hampshire | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/england/hampshire/rss.xml",
    "BBC Sport | Hereford and Worcestershire | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/england/hereford_and_worcestershire/rss.xml",
    "BBC Sport | Isle of Man | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/england/isle_of_man/rss.xml",
    "BBC Sport | Kent | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/england/kent/rss.xml",
    "BBC Sport | Leicestershire | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/england/leicestershire/rss.xml",
    "BBC Sport | Nottinghamshire | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/england/nottinghamshire/rss.xml",
    "BBC Sport | Oxfordshire | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/england/oxfordshire/rss.xml",
    "BBC Sport | Shropshire | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/england/shropshire/rss.xml",
    "BBC Sport | Somerset | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/england/somerset/rss.xml",
    "BBC Sport | Southern Counties | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/england/southern_counties/rss.xml",
    "BBC Sport | Stoke and Staffordshire | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/england/stoke_and_staffordshire/rss.xml",
    "BBC Sport | Tees | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/england/tees/rss.xml",
    "BBC Sport | Tyne | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/england/tyne/rss.xml",
    "BBC Sport | Wear | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/england/wear/rss.xml",
    "BBC Sport | Wiltshire | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/england/wiltshire/rss.xml",
    "BBC Sport | Football | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/football/rss.xml",
    "BBC Sport | Sport Homepage | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/front_page/rss.xml",
    "BBC Sport | Front page features | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/front_page_features/rss.xml",
    "BBC Sport | Golf | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/golf/rss.xml",
    "BBC Sport | Latest Published Stories | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/latest_published_stories/rss.xml",
    "BBC Sport | Motorsport | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/motorsport/rss.xml",
    "BBC Sport | Northern Ireland | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/northern_ireland/rss.xml",
    "BBC Sport | Olympics & Olympic sport | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/olympics/rss.xml",
    "BBC Sport | Other sport... | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/other_sports/rss.xml",
    "BBC Sport | Other Sports | American Football | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/other_sports/american_football/rss.xml",
    "BBC Sport | Other Sports | Basketball | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/other_sports/basketball/rss.xml",
    "BBC Sport | Other Sports | Bowls | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/other_sports/bowls/rss.xml",
    "BBC Sport | Other Sports | Cycling | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/other_sports/cycling/rss.xml",
    "BBC Sport | Other Sports | Darts | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/other_sports/darts/rss.xml",
    "BBC Sport | Other Sports | Disability sport | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/other_sports/disability_sport/rss.xml",
    "BBC Sport | Other Sports | Horse Racing | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/other_sports/horse_racing/rss.xml",
    "BBC Sport | Other Sports | Ice Hockey | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/other_sports/ice_hockey/rss.xml",
    "BBC Sport | Other Sports | Snooker | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/other_sports/snooker/rss.xml",
    "BBC Sport | Other Sports | Squash | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/other_sports/squash/rss.xml",
    "BBC Sport | Other Sports | Winter Sports | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/other_sports/winter_sports/rss.xml",
    "BBC Sport | Rugby League | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/rugby_league/rss.xml",
    "BBC Sport | Rugby Union | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/rugby_union/rss.xml",
    "BBC Sport | Syndication | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/rugby_union/syndication/rss.xml",
    "BBC Sport | Scotland | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/scotland/rss.xml",
    "BBC Sport | Tennis | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/tennis/rss.xml",
    "BBC Sport | Wales | UK Edition": "http://newsrss.bbc.co.uk/rss/sportonline_uk_edition/wales/rss.xml",
}



# example bbc news url:
# "http://news.bbc.co.uk/1/hi/world/africa/7268903.stm"
news_srcid_pat = re.compile( '/(\d+)\.stm$' )


# some blog url patterns:
# http://www.bbc.co.uk/blogs/thereporters/robertpeston/2009/02/chelsea_reduces_dependence_on.html
# http://www.bbc.co.uk/blogs/pm/2009/02/pmtv.shtml
blog_srcid_pat = re.compile( 'http://(?:www[.])?bbc[.]co[.]uk/blogs(/.*[.]s?html)$' )


def CalcSrcID( url ):
    """ Extract unique srcid from url. Returns None if this scraper doesn't handle it."""

    m = blog_srcid_pat.match(url)
    if m:
        return "bbcblogs_" + m.group(1)

    o = urlparse.urlparse(url)
    if o[1] != 'news.bbc.co.uk':
        return None

    m = news_srcid_pat.search( url )
    if not m:
        return None     # suppress this article (probably a blog)

    return 'bbcnews_' + m.group(1)


def Extract( html, context ):
    if '/low/' in context['srcurl']:
        return Extract_low( html, context )
    if 'bbc.co.uk/blogs' in context['srcurl']:
        return Extract_blog( html, context )
    else:
        # NOTE: hi-graphics extract version needs work to handle
        # embedded video - at the moment these pages confuse it and
        # cause it to fail (and lots of pages have embedded video now)
        raise Exception( 'poo' )
#        return Extract_hi( html, context )

def Extract_low( html, context ):
    """parse html of a low-graphics page"""

    art = context
    page_enc = 'ISO-8859-1'

    # pubdate
    pubdate_pat = re.compile( r'<meta\s+name="OriginalPublicationDate"\s+content="(.*?)"\s*/?>' )
    m = pubdate_pat.search( html )
    art['pubdate'] = ukmedia.ParseDateTime( m.group(1) )

    # title
    headline_pat = re.compile( r'<a name="startcontent"></a>\s*<h\d>(.*?)</h\d>', re.DOTALL )
    m = headline_pat.search(html)
    art['title'] = m.group(1).strip().decode( page_enc )

    # byline
    byline = u''
    byline_pat = re.compile( r'<!-- S IBYL -->(.*?)<!-- E IBYL -->', re.DOTALL )
    m = byline_pat.search( html )
    if m:
        byline = m.group(1).decode( page_enc )

        # trim off possible leading all-caps cruft (eg "<b>WHO, WHAT, WHY?</b><br />")
        byline = re.sub( r'<b>[^a-z]+</b>\s*<br\s*/>', '', byline )
        # replace <br /> with a comma to retain a little more context when we strip html tags
        byline = re.sub( ur'<br\s*/>', u',', byline )
        byline = ukmedia.FromHTMLOneLine(byline)
        byline = re.sub( u'\s+,', u',', byline )
        byline = re.sub( u',$', u'', byline )
        byline = byline.strip()
        html = byline_pat.sub( '', html )
    art['byline'] = byline

    # images
    # NOTE: low-graphics version of page has no caption, but alt attr is OKish.
    art['images'] = []
    image_pat = re.compile( r'<!-- S IIMA -->(.*?)<!-- E IIMA -->', re.DOTALL )
    for im in image_pat.finditer( html ):
        imtxt = im.group(1)
        m = re.search( r'src="(.*?)"', imtxt )
        img_url = m.group(1)
        m = re.search( r'alt="(.*?)"', imtxt )
        img_caption = unicode( m.group(1), page_enc )
        art['images'].append( { 'url': img_url, 'caption': img_caption, 'credit': u'' } )
    html = image_pat.sub( '', html )

    # main text
    main_pat = re.compile( r'(?:<!-- S BO -->)+(.*?)<!-- E BO -->', re.DOTALL )
    m = main_pat.search(html)
    art['content'] = m.group(1).decode( page_enc )

    art['description'] = ukmedia.FirstPara( art['content'] )

    # if description came up blank, maybe it's because it was a gallery page
    if art['description'] == u'':
        picpage = False
        for foo in ( r'\bpictures\b',r'\bphotos\b', r'\bgallery\b' ):
            pat = re.compile( foo, re.IGNORECASE )
            if pat.search( art['title'] ):
                picpage = True
                break
        if picpage:
            ukmedia.DBUG2( "IGNORE pictures/photos page ( %s )\n" %( art['srcurl'] ) )
            return None

    return art



def Extract_hi( html, context ):
    """Parse the html of a single article (in hi-graphics form)

    html -- the article html
    context -- any extra info we have about the article (from the rss feed)
    """

    art = context

    soup = BeautifulSoup( html )

    meta = soup.find( 'meta', { 'name': 'Headline' } )
    if meta:
        art['title'] = ukmedia.DescapeHTML( meta[ 'content' ] ).strip()

    if soup.find('title').renderContents(None).startswith( "BBC News | In pictures:"):
        ukmedia.DBUG2( "IGNORE 'in pictures' gallery ( %s )\n" %( art['srcurl'] ) )
        return None

    gal = soup.find( 'div', {'class': 'galMain' } )
    if gal:
        ukmedia.DBUG2( "IGNORE picture gallery '%s' ( %s )\n" %( art['title'], art['srcurl'] ) )
        return None

    meta = soup.find( 'meta', { 'name': 'OriginalPublicationDate' } )
    if meta:
        art['pubdate'] = ukmedia.ParseDateTime( meta['content'] )

    # TODO: could use first paragraph for a more verbose description
    meta = soup.find( 'meta', { 'name': 'Description' } )
    if meta and 'content' in meta:
        art['description'] = ukmedia.FromHTML( meta[ 'content' ] )
    else:
        art['description'] = u''


    # byline
    byline = u''
    spanbyl = soup.find( 'span', {'class':'byl'} )
    if spanbyl: # eg "By Paul Rincon"
        byline = spanbyl.renderContents(None).strip()
    spanbyd = soup.find( 'span', {'class':'byd'} )
    if spanbyd: # eg "Science reporter, BBC News, Houston"
        byline = byline + u', ' + spanbyd.renderContents(None).strip()
    byline = ukmedia.FromHTML( byline )
    byline = u' '.join( byline.split() )
    art['byline'] = byline

    # just use regexes to extract the article text
    storybody = soup.find( "td", {'class':'storybody'} )
    if not storybody:
        # uh-oh... is it a video page?
        av = soup.find( 'div', {'class':'wideav'} )
        if av:
            ukmedia.DBUG2( "IGNORE video-only page ( %s )\n" %( art['srcurl'] ) )
            return None


#    if storybody:
#        txt = storybody.renderContents(None)
#    else:
#        txt = unicode( html, soup.originalEncoding )
    txt = unicode( html, soup.originalEncoding )

    m = re.search( u'<!--\s*S BO\s*-->(.*)<!--\s*E BO\s*-->', txt, re.UNICODE|re.DOTALL )
    txt = m.group(1)

    # bbcnews has blocks denoted in comments, eg:
    # <!-- S IIMA -->
    #  ...html...
    # <!-- E IIMA -->

    # try to extract images (in IIMA block)
    # TODO: could also get images from IBOX blocks?
    art['images'] = []
    imgblock_pat = re.compile( r"<!--\s*S\s+(IIMA)\s*-->(.*?)<!--\s*E\s+\1\s*-->", re.DOTALL )
    for iima in imgblock_pat.finditer( html ):
        imhtml = unicode( iima.group(2), soup.originalEncoding )

        m = re.compile( ur'<img src="(.*?)"', re.UNICODE ).search( imhtml )
        if m:
            # credit is superimposed graphically by the beeb
            im = { 'url': m.group(1), 'caption':u'', 'credit':u'' }
            m = re.compile( ur'<div\s+class="cap"\s*>(.*?)</div>', re.IGNORECASE|re.UNICODE|re.DOTALL ).search( imhtml )
            if m:
                im['caption'] = m.group(1)
            # else could try and pull out img alt attr...

            art['images'].append( im )


    # zap assorted extra blocks from the text
    # (could be problems with nesting... but seems ok)
    # IIMA - image?
    # IBOX - quote?
    # IBYL - byline
    # IANC - anchor
    # ILIN
    # IFOR - form
    # IMED - embedded media link
    blockkillerpat = re.compile( r"<!--\s*S\s+(IIMA|IBOX|IBYL|IANC|ILIN|IFOR|IMED)\s*-->.*?<!--\s*E\s+\1\s*-->", re.UNICODE|re.DOTALL )
    txt = blockkillerpat.sub( u'', txt )

    # ITAB - table
    # IROW - table row
    # ICOL - table column
    # ICEE - preformatted? from cfax? (used for sports fixtures/results)
    # IINC - included text/image (but from where?)
    allowedblocks = ('SF','BO','ITAB', 'ICOL', 'IROW', 'ICEE', 'IINC' )
    # sanity check (might not know all block types)
    m = re.search( u'<!--\s*S (\w+)\s*-->', txt, re.UNICODE )
    if m:
        if m.group(1) not in allowedblocks:
            raise Exception, ("unknown block type encountered ('%s')" % m.group(1))

    txt = ukmedia.SanitiseHTML( txt )
    art['content'] = txt

    if art['description'] == u'':
        art['description'] = ukmedia.FromHTML( ukmedia.FirstPara( art['content'] ) )
    return art


def Extract_blog( html, context ):
    """Parse the html of a bbc blog post page"""

    art = context
    soup = BeautifulSoup( html )
    post_div = soup.find( 'div', {'class':'post'} )
    headline_hx = post_div.find( re.compile(r'h\d') )
    art['title'] = ukmedia.FromHTMLOneLine( headline_hx.renderContents(None) )

    meta_div = post_div.find('div', {'class':'meta'} )

    author = meta_div.find('span', {'class':'vcard author'} )
    art['byline'] = ukmedia.FromHTMLOneLine( author.renderContents(None) )

    # <abbr class="published" title="2010-04-02T12:35:44+00:00">12:35 UK time, Friday,  2 April 2010</abbr>
    pub = meta_div.find('abbr', {'class':'published'} )
    art['pubdate'] = ukmedia.ParseDateTime( pub['title'] )

    #if art['byline'] == u'Nick' and '/nickrobinson/' in context['srcurl']:
    #    art['byline'] = u'Nick Robinson';

    content_div = post_div.find( 'div', {'class':"post_content"} )



    # images
    art['images'] = []
    for mt in content_div.findAll( 'div',{'class':re.compile('mt-image-enclosure' )} ):
        img = mt.img
        img_caption = u'' #ukmedia.FromHTMLOneLine( img.get( 'alt',u'' ) )
        # occasional image is just broken (usually because of a bad alt, eg alt="that isn"t cricket")
        #if not 'src' in img:
        #    continue
        img_url = img['src']
        img_credit = u''
        art['images'].append( {'url': img_url, 'caption': img_caption, 'credit': img_credit } )


    # comments
    comment_div = soup.find( 'div', {'id':'comments'} )
    if comment_div is not None:
        num_comments = 0
        # no easy total on page, so look for highest numbered comment
        comment_num_pat = re.compile( r'\s*(\d+)\s*[.]\s*');
        for c in comment_div.findAll( 'span', {'class':'comment-number'} ):
            m = comment_num_pat.match( ukmedia.FromHTMLOneLine( c.renderContents(None) ) )
            num = int( m.group(1) )
            if num > num_comments:
                num_comments = num

        comment_url = art['srcurl'] + "#comments"
        art['commentlinks'] = [ {'num_comments':num_comments, 'comment_url':comment_url} ]


    # get the text, minus assorted cruft
    for cruft in content_div.findAll( 'ul', {'class':'ami_social_bookmarks'} ):
        cruft.extract()
    for cruft in content_div.findAll( 'span', {'class':re.compile( 'mt-enclosure')} ):
        cruft.extract()
    for cruft in content_div.findAll( 'object' ):
        cruft.extract()

    # embedded bbc players are a div placeholder, followed by script
    for cruft in content_div.findAll( 'div', {'class':'player'} ):
        cruft.extract()
    for cruft in content_div.findAll( 'script' ):
        cruft.extract()

    art['content'] = content_div.renderContents(None)
    art['description'] = ukmedia.FirstPara( art['content'] )

    return art




def ScrubFunc( context, entry ):
    """ per-article callback for processing RSS feeds """
    # a story can have multiple paths (eg uk vs international version)
    srcid = CalcSrcID( context['srcurl'] )
    if not srcid:
        return None # suppress it

    if '/in_pictures/' in context['srcurl']:
        return None

    context['srcid'] = srcid

    o = urlparse.urlparse(context['srcurl'])
    if o[1] == 'news.bbc.co.uk':
        # news page: scrape the low-graphics version
        context['srcurl'] = re.sub( '/hi/', '/low/', context['srcurl'] )

    return context


def FindArticles():
    """ get a set of articles to scrape from the bbc rss feeds """

    articles = ScraperUtils.FindArticlesFromRSS( blog_feeds, u'bbcnews', ScrubFunc )
    articles = articles + ScraperUtils.FindArticlesFromRSS( news_feeds, u'bbcnews', ScrubFunc )
    return articles


def ContextFromURL( url ):
    """Build up an article scrape context from a bare url."""
    # NOTE: urls from the rss feed have a couple of extra components which
    # we _could_ strip out here...
    context = {}
    context['permalink'] = url
    context['srcurl'] = url
    # scrape the low-graphics version of the page
    # NOTE: a few pages give 404 errors for their low-graphics counterpart...
    # I _think_ these are video pages (only text is a small caption)
    context['srcurl'] = re.sub( '/hi/', '/low/', context['srcurl'] )
    context['srcid'] = CalcSrcID( url )
    context['srcorgname'] = u'bbcnews'
    context['lastseen'] = datetime.now()
    return context


if __name__ == "__main__":
    # high maxerrors to cope with some video-only pages which give 404 errors if you try to get the low-graphics version
    ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract, maxerrors=50 )

