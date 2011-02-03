#!/usr/bin/env python
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# Scraper for the guardian and observer, including commentisfree and blogs
#
# NOTE: guardian unlimited has updated their site. They were using
# vignette storyserver, but have now written their own I think.
#
# Main RSS page doesn't seem be be updated with feeds from new sections
# (maybe it'll be rejigged once the transition is complete)
# For the new-style sections, there is usually one feed for the main
# section frontpage, and then an extra feed for each subsection.
# ../hacks/guardian-scrape-rsslist.py crawls the site looking for the
# RSS feeds.
#
# TODO:
# - extract journo names from descriptions if possible...
# - sort out guardian/observer from within Extract fn
# - For new-format articles, could use class attr in body element to
#   ignore polls and other cruft. <body class="article"> is probably
#   the only one we should accept...

import re
from datetime import date,datetime,timedelta
import time
import sys
import urlparse

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import DB,ScraperUtils,ukmedia



# guardian non-blog feedlist automatically scraped by ./guardian-scrape-rsslist.py
# (run 2009-02-04 15:10:03)
# got 598 feeds
rssfeeds = [
    ("News", "http://feeds.guardian.co.uk/theguardian/rss"),
    ("Environment", "http://feeds.guardian.co.uk/theguardian/environment/rss"),
    ("Environment / Ethical living", "http://www.guardian.co.uk/environment/ethical-living/rss"),
    ("Environment / Climate change", "http://www.guardian.co.uk/environment/climate-change/rss"),
    ("News / Politics", "http://feeds.guardian.co.uk/theguardian/politics/rss"),
    ("News / World news", "http://feeds.guardian.co.uk/theguardian/world/rss"),
    ("News / World news", "http://www.guardian.co.uk/world/africa/roundup/rss"),
    ("News / Main section", "http://www.guardian.co.uk/theguardian/2009/feb/04/mainsection/rss"),
    ("Environment / Waste", "http://www.guardian.co.uk/environment/waste/rss"),
    ("Environment / Conservation", "http://www.guardian.co.uk/environment/conservation/rss"),
    ("News / Technology", "http://feeds.guardian.co.uk/theguardian/technology/rss"),
    ("News / World news", "http://www.guardian.co.uk/world/europe/roundup/rss"),
    ("Environment / Endangered species", "http://www.guardian.co.uk/environment/endangeredspecies/rss"),
    ("News / World news", "http://www.guardian.co.uk/world/asiapacific/roundup/rss"),
    ("Travel", "http://feeds.guardian.co.uk/theguardian/travel/rss"),
    ("News / Politics", "http://www.guardian.co.uk/politics/page/2007/dec/18/1/rss"),
    ("Environment / Plastic bags", "http://www.guardian.co.uk/environment/plasticbags/rss"),
    ("Environment / Pollution", "http://www.guardian.co.uk/environment/pollution/rss"),
    ("Comment is free", "http://feeds.guardian.co.uk/theguardian/commentisfree/rss"),
    ("News / Technology / Mobile phones", "http://www.guardian.co.uk/technology/mobilephones/rss"),
    ("Life & style", "http://www.guardian.co.uk/lifeandstyle/rss"),
    ("Environment / Fossil fuels", "http://www.guardian.co.uk/environment/fossil-fuels/rss"),
    ("Environment / Carbon emissions", "http://www.guardian.co.uk/environment/carbon-emissions/rss"),
    ("Travel / Hotels", "http://www.guardian.co.uk/travel/hotels/rss"),
    ("Comment is free", "http://www.guardian.co.uk/commentisfree/belief/rss"),
    ("News / Science", "http://feeds.guardian.co.uk/theguardian/science/rss"),
    ("Travel", "http://www.guardian.co.uk/travel/bookatrip/rss"),
    ("News / Technology / Gadgets", "http://www.guardian.co.uk/technology/gadgets/rss"),
    ("News / Technology / Motoring", "http://www.guardian.co.uk/technology/motoring/rss"),
    ("News / Politics", "http://www.guardian.co.uk/politics/comment/rss"),
    ("Sport", "http://feeds.guardian.co.uk/theguardian/sport/rss"),
    ("Comment is free", "http://www.guardian.co.uk/commentisfree+world/islam/rss"),
    ("Life & style / Food & drink", "http://www.guardian.co.uk/lifeandstyle/food-and-drink/rss"),
    ("Business", "http://feeds.guardian.co.uk/theguardian/business/rss"),
    ("Travel / Late offers", "http://www.guardian.co.uk/travel/lateoffers/rss"),
    ("News / World news / United States", "http://www.guardian.co.uk/world/usa/rss"),
    ("News / Media", "http://feeds.guardian.co.uk/theguardian/media/rss"),
    ("News / Technology / Engineering", "http://www.guardian.co.uk/technology/engineering/rss"),
    ("News / UK news", "http://feeds.guardian.co.uk/theguardian/uk/rss"),
    ("Life & style / Fashion", "http://www.guardian.co.uk/lifeandstyle/fashion/rss"),
    ("News / World news", "http://www.guardian.co.uk/world/middleeast/roundup/rss"),
    ("News / Science / Neuroscience", "http://www.guardian.co.uk/science/neuroscience/rss"),
    ("Life & style / Relationships", "http://www.guardian.co.uk/lifeandstyle/relationships/rss"),
    ("Comment is free", "http://www.guardian.co.uk/commentisfree/america/rss"),
    ("News / Technology / Robots", "http://www.guardian.co.uk/technology/robots/rss"),
    ("Life & style / Observer Food Monthly", "http://www.guardian.co.uk/theobserver/2009/jan/25/foodmonthly/rss"),
    ("Environment / Carbon capture and storage (CCS)", "http://www.guardian.co.uk/environment/carbon-capture-and-storage/rss"),
    ("Sport / US sport", "http://www.guardian.co.uk/sport/us-sport/rss"),
    ("Environment / Tread lightly", "http://www.guardian.co.uk/environment/pledges/rss"),
    ("News / Technology / Tech Weekly", "http://www.guardian.co.uk/technology/series/techweekly/rss"),
    ("News / From the Observer", "http://feeds.guardian.co.uk/theguardian/rss"),
    ("Business / David Gow on Europe", "http://www.guardian.co.uk/business/series/davidgowoneurope/rss"),
    ("Environment / Recycling", "http://www.guardian.co.uk/environment/recycling/rss"),
    ("News / Science / Medical research", "http://www.guardian.co.uk/science/medical-research/rss"),
    ("Environment / Wildlife", "http://www.guardian.co.uk/environment/wildlife/rss"),
    ("News / Education", "http://feeds.guardian.co.uk/theguardian/education/rss"),
    ("News / World news / Fair trade", "http://www.guardian.co.uk/world/fairtrade/rss"),
    ("Business / Viewpoint column", "http://www.guardian.co.uk/business/series/viewpointcolumn/rss"),
    ("Environment", "http://www.guardian.co.uk/environment/rssfeeds/rss"),
    ("Environment / Water", "http://www.guardian.co.uk/environment/water/rss"),
    ("News / Education / Higher education", "http://www.guardian.co.uk/education/higher-education/rss"),
    ("Comment is free", "http://www.guardian.co.uk/commentisfree+world/anglicanism/rss"),
    ("Sport / Motor sport", "http://www.guardian.co.uk/sport/motorsports/rss"),
    ("News / Media / Media Monkey", "http://www.guardian.co.uk/media/mediamonkeyblog/rss"),
    ("News / World news", "http://www.guardian.co.uk/world/southandcentralasia/roundup/rss"),
    ("News / Media / Digital media", "http://feeds.guardian.co.uk/theguardian/media/digital-media/rss"),
    ("Life & style / Guide to dating", "http://www.guardian.co.uk/lifeandstyle/series/dating/rss"),
    ("Business / Economics", "http://www.guardian.co.uk/business/economics/rss"),
    ("Travel / United States", "http://www.guardian.co.uk/travel/usa+hotels/rss"),
    ("News / Education / Further education", "http://www.guardian.co.uk/education/further-education/rss"),
    ("Environment / Travel and transport", "http://www.guardian.co.uk/environment/travel-and-transport/rss"),
    ("Life & style / Homes", "http://www.guardian.co.uk/lifeandstyle/homes/rss"),
    ("News / Education / GCSEs", "http://www.guardian.co.uk/education/gcses/rss"),
    ("News / World news / Atheism", "http://www.guardian.co.uk/world/atheism/rss"),
    ("Environment / Biofuels", "http://www.guardian.co.uk/environment/biofuels/rss"),
    ("Business / Economics on Monday", "http://www.guardian.co.uk/business/series/economicsmonday/rss"),
    ("News / Media / Marketing & PR", "http://www.guardian.co.uk/media/marketingandpr/rss"),
    ("News / Education / A-levels", "http://www.guardian.co.uk/education/alevels/rss"),
    ("Sport / Football", "http://feeds.guardian.co.uk/theguardian/football/rss"),
    ("News / Politics", "http://www.guardian.co.uk/politics/page/2007/dec/17/1/rss"),
    ("Life & style / Fitness", "http://www.guardian.co.uk/lifeandstyle/fitness/rss"),
    ("News / Education / School tables", "http://www.guardian.co.uk/education/school-tables/rss"),
    ("Comment is free", "http://www.guardian.co.uk/commentisfree/libertycentral/rss"),
    ("Life & style / Fashion Statement", "http://www.guardian.co.uk/lifeandstyle/series/fashiondiary/rss"),
    ("News / World news / Catholicism", "http://www.guardian.co.uk/world/catholicism/rss"),
    ("News / Technology / Amazon.com", "http://www.guardian.co.uk/technology/amazon/rss"),
    ("Life & style / Restaurants", "http://www.guardian.co.uk/lifeandstyle/restaurants+tone/reviews/rss"),
    ("News / Science / Infectious diseases", "http://www.guardian.co.uk/science/infectiousdiseases/rss"),
    ("Comment is free", "http://www.guardian.co.uk/commentisfree/middleeast/rss"),
    ("Comment is free", "http://www.guardian.co.uk/commentisfree/belief/rss"),
    ("Sport / Horse racing", "http://www.guardian.co.uk/sport/horse-racing/rss"),
    ("Sport / Cricket", "http://www.guardian.co.uk/sport/cricket/rss"),
    ("Sport / Football / Football Weekly", "http://www.guardian.co.uk/football/series/footballweekly/rss"),
    ("News / Education / Colleges", "http://www.guardian.co.uk/education/colleges/rss"),
    ("News / World news / Religion", "http://www.guardian.co.uk/world/religion/rss"),
    ("News / Science / Embryos and stem cells", "http://www.guardian.co.uk/science/embryos-and-stem-cells/rss"),
    ("News / Education / Tefl", "http://www.guardian.co.uk/education/tefl/rss"),
    ("News / Media / Newspapers & magazines", "http://www.guardian.co.uk/media/pressandpublishing/rss"),
    ("News / Science / Cancer", "http://www.guardian.co.uk/science/cancer/rss"),
    ("News / Technology / Artificial intelligence (AI)", "http://www.guardian.co.uk/technology/artificialintelligenceai/rss"),
    ("Life & style / Ethical food", "http://www.guardian.co.uk/lifeandstyle/ethicalfood/rss"),
    ("Comment is free", "http://www.guardian.co.uk/commentisfree/commentisfree+world/judaism/rss"),
    ("Comment is free", "http://feeds.guardian.co.uk/theguardian/commentisfree/michaeltomasky/rss"),
    ("Business / Andrew Clark on America", "http://www.guardian.co.uk/business/series/andrewclarkonamerica/rss"),
    ("News / Technology / Research and development", "http://www.guardian.co.uk/technology/research/rss"),
    ("News / World news / Philosophy", "http://www.guardian.co.uk/world/philosophy/rss"),
    ("News / Technology / Sony", "http://www.guardian.co.uk/technology/sony/rss"),
    ("Life & style / Ethical fashion", "http://www.guardian.co.uk/lifeandstyle/ethicalfashion/rss"),
    ("News / Education / Mortarboard blog", "http://www.guardian.co.uk/education/mortarboard/rss"),
    ("Life & style / Restaurants", "http://www.guardian.co.uk/lifeandstyle/restaurants/rss"),
    ("News / Science / Drugs", "http://www.guardian.co.uk/science/drugs/rss"),
    ("Life & style / Fashion week", "http://www.guardian.co.uk/lifeandstyle/fashion-week/rss"),
    ("Life & style / New York fashion week", "http://www.guardian.co.uk/lifeandstyle/newyorkfashionweek/rss"),
    ("News / Media / PDA", "http://www.guardian.co.uk/media/pda/rss"),
    ("Environment / Drought", "http://www.guardian.co.uk/environment/drought/rss"),
    ("Sport / Football", "http://www.guardian.co.uk/football/clubs/rss"),
    ("Sport / Speedway", "http://www.guardian.co.uk/sport/speedway/rss"),
    ("News / Education / Sats", "http://www.guardian.co.uk/education/sats/rss"),
    ("Travel / Washington DC", "http://www.guardian.co.uk/travel/washingtondc/rss"),
    ("News / World news / Obama administration", "http://www.guardian.co.uk/world/obama-administration/rss"),
    ("Environment / Renewable energy", "http://www.guardian.co.uk/environment/renewableenergy/rss"),
    ("Travel / United States", "http://www.guardian.co.uk/travel/usa+budget/rss"),
    ("News / From the Observer / World news", "http://www.guardian.co.uk/theobserver/news/worldnews/rss"),
    ("Life & style / Beauty", "http://www.guardian.co.uk/lifeandstyle/beauty/rss"),
    ("Life & style / Emma Cook on beauty", "http://www.guardian.co.uk/lifeandstyle/series/emmacookonbeauty/rss"),
    ("News / World news / Christianity", "http://www.guardian.co.uk/world/christianity/rss"),
    ("News / Education / RAE", "http://www.guardian.co.uk/education/rae/rss"),
    ("Sport / Formula one", "http://www.guardian.co.uk/sport/formulaone/rss"),
    ("Travel / New York", "http://www.guardian.co.uk/travel/newyork/rss"),
    ("News / Media / Television", "http://www.guardian.co.uk/media/television/rss"),
    ("Sport / Lewis Hamilton", "http://www.guardian.co.uk/sport/lewis-hamilton/rss"),
    ("Money / Cash", "http://www.guardian.co.uk/theobserver/businessandmedia/cash/rss"),
    ("Life & style / Health & wellbeing", "http://www.guardian.co.uk/lifeandstyle/health-and-wellbeing/rss"),
    ("Travel / United States", "http://www.guardian.co.uk/travel/usa+roadtrips/rss"),
    ("News / Education / New schools", "http://www.guardian.co.uk/education/new-schools/rss"),
    ("Comment is free", "http://www.guardian.co.uk/commentisfree/series/a-z-of-legislation/rss"),
    ("Life & style / Sexual healing", "http://www.guardian.co.uk/lifeandstyle/series/sexualhealing/rss"),
    ("News / Education / Schools", "http://www.guardian.co.uk/education/schools/rss"),
    ("Life & style / Milan fashion week", "http://www.guardian.co.uk/lifeandstyle/milan-fashion-week/rss"),
    ("Travel / New York", "http://www.guardian.co.uk/travel/newyork+bars/rss"),
    ("Life & style / Private lives", "http://www.guardian.co.uk/lifeandstyle/series/privatelives/rss"),
    ("Life & style / Nutrition", "http://www.guardian.co.uk/lifeandstyle/nutrition/rss"),
    ("News / Education / University funding", "http://www.guardian.co.uk/education/universityfunding/rss"),
    ("Culture / Observer Review", "http://www.guardian.co.uk/theobserver/2009/feb/01/review/rss"),
    ("News / Education / Clearing", "http://www.guardian.co.uk/education/clearing/rss"),
    ("Life & style / My space", "http://www.guardian.co.uk/lifeandstyle/series/myspace/rss"),
    ("News / Education / The Fresher 2008", "http://www.guardian.co.uk/theguardian/2008/aug/14/fresher2008/rss"),
    ("Money / Let's move to ...", "http://www.guardian.co.uk/money/series/letsmoveto/rss"),
    ("Environment / Desertification", "http://www.guardian.co.uk/environment/desertification/rss"),
    ("Life & style", "http://www.guardian.co.uk/lifeandstyle/rss"),
    ("Business / Europe", "http://www.guardian.co.uk/business/europe/rss"),
    ("Sport / County Championship Division One", "http://www.guardian.co.uk/sport/countychampionship1stdivisioncricket/rss"),
    ("Sport / Touring cars", "http://www.guardian.co.uk/sport/touringcars/rss"),
    ("Life & style / Fashion", "http://www.guardian.co.uk/lifeandstyle/fashion/rss"),
    ("Sport / Rallying", "http://www.guardian.co.uk/sport/rallying/rss"),
    ("News / World news / Animals", "http://www.guardian.co.uk/world/animals/rss"),
    ("Environment / Wave, tidal and hydropower", "http://www.guardian.co.uk/environment/waveandtidalpower/rss"),
    ("News / World news / Globalisation", "http://www.guardian.co.uk/world/globalisation/rss"),
    ("News / Education", "http://www.guardian.co.uk/education/comment/rss"),
    ("Life & style / Training programmes", "http://www.guardian.co.uk/lifeandstyle/training-programmes/rss"),
    ("News / World news / Pope Benedict XVI", "http://www.guardian.co.uk/world/pope-benedict-xvi/rss"),
    ("Life & style / Be the best at ...", "http://www.guardian.co.uk/lifeandstyle/series/bethebestat/rss"),
    ("News / Education / Ofsted", "http://www.guardian.co.uk/education/ofsted/rss"),
    ("Environment / Solar power", "http://www.guardian.co.uk/environment/solarpower/rss"),
    ("Life & style / Models", "http://www.guardian.co.uk/lifeandstyle/models/rss"),
    ("Business / US economy", "http://www.guardian.co.uk/business/useconomy/rss"),
    ("News / World news / Judaism", "http://www.guardian.co.uk/world/judaism/rss"),
    ("Comment is free", "http://www.guardian.co.uk/commentisfree/series/noticeboard/rss"),
    ("Sport / Football / Fantasy Football", "http://www.guardian.co.uk/fantasyfootball/rss"),
    ("Life & style / Getting fit", "http://www.guardian.co.uk/lifeandstyle/series/getting-fit/rss"),
    ("Life & style / Rowing", "http://www.guardian.co.uk/lifeandstyle/rowing/rss"),
    ("News / World news / US foreign policy", "http://www.guardian.co.uk/world/usforeignpolicy/rss"),
    ("Life & style / Running", "http://www.guardian.co.uk/lifeandstyle/running/rss"),
    ("Comment is free", "http://www.guardian.co.uk/commentisfree/series/modern-liberty/rss"),
    ("Culture / Books / Philosophy", "http://www.guardian.co.uk/books/philosophy/rss"),
    ("Life & style / Homes", "http://www.guardian.co.uk/lifeandstyle/homes/rss"),
    ("News / UK news / Animal welfare", "http://www.guardian.co.uk/uk/animalwelfare/rss"),
    ("News / Education / Private schools", "http://www.guardian.co.uk/education/private-schools/rss"),
    ("Life & style / Ask Hadley", "http://www.guardian.co.uk/lifeandstyle/series/askhadley/rss"),
    ("Life & style / Women", "http://www.guardian.co.uk/lifeandstyle/women/rss"),
    ("Sport / Observer Sport Monthly", "http://www.guardian.co.uk/theobserver/2009/feb/01/sportmonthly/rss"),
    ("Environment / Forests", "http://www.guardian.co.uk/environment/forests/rss"),
    ("Life & style / Triathlon", "http://www.guardian.co.uk/lifeandstyle/triathlon/rss"),
    ("Environment / Endangered habitats", "http://www.guardian.co.uk/environment/endangered-habitats/rss"),
    ("News / World news / US elections 2008", "http://www.guardian.co.uk/world/us-elections-2008/rss"),
    ("News / World news / US elections 2008", "http://www.guardian.co.uk/world/uselections2008+content/interactive/rss"),
    ("News / World news / Hillary Clinton", "http://www.guardian.co.uk/world/hillaryclinton/rss"),
    ("News / World news / Ethics", "http://www.guardian.co.uk/world/ethics/rss"),
    ("News / World news / G8", "http://www.guardian.co.uk/world/g8/rss"),
    ("Life & style / Ethical beauty", "http://www.guardian.co.uk/lifeandstyle/ethical-beauty/rss"),
    ("News / From the Observer / Main section", "http://www.guardian.co.uk/theobserver/2009/feb/01/news/rss"),
    ("Life & style / Paris fashion week", "http://www.guardian.co.uk/lifeandstyle/paris-fashion-week/rss"),
    ("News / World news / US elections video 2008", "http://www.guardian.co.uk/news/series/uselectionsvideo2008/rss"),
    ("Sport / England Cricket Team", "http://www.guardian.co.uk/sport/england-cricket-team/rss"),
    ("News / Politics / Women in politics", "http://www.guardian.co.uk/politics/women/rss"),
    ("News / Science / Science Weekly", "http://www.guardian.co.uk/science/series/science/rss"),
    ("Travel / New York", "http://www.guardian.co.uk/travel/newyork+shoppingtrips/rss"),
    ("News / Education / Early years education", "http://www.guardian.co.uk/education/earlyyearseducation/rss"),
    ("Business / European banks", "http://www.guardian.co.uk/business/europeanbanks/rss"),
    ("News / Education / Teaching", "http://www.guardian.co.uk/education/teaching/rss"),
    ("News / Science / Science blog", "http://www.guardian.co.uk/science/blog/rss"),
    ("Life & style / Dear Mariella", "http://www.guardian.co.uk/lifeandstyle/series/dearmariella/rss"),
    ("UNKNOWN", "http://www.guardian.co.uk/profile/kiracochrane/rss"),
    ("News / Technology / Computing", "http://www.guardian.co.uk/technology/computing/rss"),
    ("Sport / Football / Champions League", "http://www.guardian.co.uk/football/championsleague/rss"),
    ("News / World news / Gender", "http://www.guardian.co.uk/world/gender/rss"),
    ("News / Media / Advertising", "http://www.guardian.co.uk/media/advertising/rss"),
    ("News / Education / School admissions", "http://www.guardian.co.uk/education/schooladmissions/rss"),
    ("News / World news / Buddhism", "http://www.guardian.co.uk/world/buddhism/rss"),
    ("Life & style / Celebrity", "http://www.guardian.co.uk/lifeandstyle/celebrity/rss"),
    ("News / World news", "http://www.guardian.co.uk/world/americas/roundup/rss"),
    ("Sport / Rugby union", "http://www.guardian.co.uk/sport/rugby-union/rss"),
    ("Life & style / Nibbles", "http://www.guardian.co.uk/lifeandstyle/series/nibbles/rss"),
    ("News / UK news / Rural affairs", "http://www.guardian.co.uk/uk/ruralaffairs/rss"),
    ("News / Science / Controversies in science", "http://www.guardian.co.uk/science/controversiesinscience/rss"),
    ("Environment / Fishing", "http://www.guardian.co.uk/environment/fishing/rss"),
    ("News / Education / Students", "http://www.guardian.co.uk/education/students/rss"),
    ("Life & style / Sidelines", "http://www.guardian.co.uk/lifeandstyle/series/sidelines/rss"),
    ("Money", "http://www.guardian.co.uk/money/rss"),
    ("News / Society", "http://feeds.guardian.co.uk/theguardian/society/rss"),
    ("Sport / Indian Premier League", "http://www.guardian.co.uk/sport/indianpremierleague/rss"),
    ("News / Technology / Internet", "http://www.guardian.co.uk/technology/internet/rss"),
    ("Money / Property", "http://www.guardian.co.uk/money/property/rss"),
    ("News / Technology / Dell", "http://www.guardian.co.uk/technology/dell/rss"),
    ("News / Education / School funding", "http://www.guardian.co.uk/education/school-funding/rss"),
    ("Environment / Food", "http://www.guardian.co.uk/environment/food/rss"),
    ("Business", "http://www.guardian.co.uk/business/markets/rss"),
    ("Life & style / Family", "http://www.guardian.co.uk/lifeandstyle/family/rss"),
    ("Travel / New York", "http://www.guardian.co.uk/travel/newyork+budget/rss"),
    ("Environment / Organics", "http://www.guardian.co.uk/environment/organics/rss"),
    ("Culture", "http://feeds.guardian.co.uk/theguardian/culture/rss"),
    ("Sport / Heineken Cup", "http://www.guardian.co.uk/sport/heineken-cup/rss"),
    ("News / World news / Barack Obama", "http://www.guardian.co.uk/world/barack-obama/rss"),
    ("Environment / Environment blog", "http://www.guardian.co.uk/environment/blog/rss"),
    ("Money / Ask the experts: Homebuying", "http://www.guardian.co.uk/money/series/expertsproperty/rss"),
    ("News / Science / Climate change", "http://www.guardian.co.uk/science/scienceofclimatechange/rss"),
    ("Business / Global economy", "http://www.guardian.co.uk/business/global-economy/rss"),
    ("Travel / Observer Escape", "http://www.guardian.co.uk/theobserver/2009/feb/01/escape/rss"),
    ("Sport / Rugby league", "http://www.guardian.co.uk/sport/rugbyleague/rss"),
    ("Comment is free", "http://www.guardian.co.uk/commentisfree/commentisfree+world/catholicism/rss"),
    ("Travel / Restaurants", "http://www.guardian.co.uk/travel/restaurants/rss"),
    ("Life & style / My body and soul", "http://www.guardian.co.uk/lifeandstyle/series/mybodyandsoul/rss"),
    ("Travel / Short breaks", "http://www.guardian.co.uk/travel/short-breaks/rss"),
    ("News / Technology / Games", "http://www.guardian.co.uk/technology/games/rss"),
    ("News / Politics", "http://www.guardian.co.uk/politics/all/rss"),
    ("Life & style / Around Britain with a fork", "http://www.guardian.co.uk/lifeandstyle/series/aroundbritainwithafork/rss"),
    ("Life & style / Cycling", "http://www.guardian.co.uk/lifeandstyle/cycling/rss"),
    ("Money / Snooping around", "http://www.guardian.co.uk/money/series/snoopingaround/rss"),
    ("Sport / Golf", "http://www.guardian.co.uk/sport/golf/rss"),
    ("Business / Credit crunch", "http://www.guardian.co.uk/business/credit-crunch/rss"),
    ("News / World news / Debt relief", "http://www.guardian.co.uk/world/debt-relief/rss"),
    ("News / Education / Further education", "http://www.guardian.co.uk/education/furthereducation+tone/comment/rss"),
    ("News / From the Observer / Comment", "http://www.guardian.co.uk/theobserver/news/comment/rss"),
    ("News / Technology / Wi-Fi", "http://www.guardian.co.uk/technology/wifi/rss"),
    ("Environment / Ask Leo", "http://www.guardian.co.uk/environment/series/askleo/rss"),
    ("Environment / Carbon footprints", "http://www.guardian.co.uk/environment/carbonfootprints/rss"),
    ("News / World news / US Congress", "http://www.guardian.co.uk/world/congress/rss"),
    ("News / Education / Student health", "http://www.guardian.co.uk/education/studenthealth/rss"),
    ("News / Technology / Apple", "http://www.guardian.co.uk/technology/apple/rss"),
    ("News / Comment & debate", "http://www.guardian.co.uk/theguardian/mainsection/commentanddebate/rss"),
    ("News / Society / Local government", "http://www.guardian.co.uk/society/localgovernment/rss"),
    ("News / Education / International education news", "http://www.guardian.co.uk/education/internationaleducationnews/rss"),
    ("News / World news / Deadline USA blog", "http://www.guardian.co.uk/world/deadlineusa/rss"),
    ("Environment / Energy efficiency", "http://www.guardian.co.uk/environment/energyefficiency/rss"),
    ("Environment / Glaciers", "http://www.guardian.co.uk/environment/glaciers/rss"),
    ("News / Science / Charles Darwin", "http://www.guardian.co.uk/science/charles-darwin/rss"),
    ("News / Science / Evolution", "http://www.guardian.co.uk/science/evolution/rss"),
    ("Business / Dan Roberts on business", "http://www.guardian.co.uk/business/dan-roberts-on-business-blog/rss"),
    ("News / Education / Faith schools", "http://www.guardian.co.uk/education/faithschools/rss"),
    ("Culture / Film", "http://feeds.guardian.co.uk/theguardian/film/rss"),
    ("Culture / Film / Trailer park", "http://www.guardian.co.uk/film/trailerpark/rss"),
    ("News / Education / Access to university", "http://www.guardian.co.uk/education/accesstouniversity/rss"),
    ("Sport / Tennis", "http://www.guardian.co.uk/sport/tennis/rss"),
    ("Sport / Super League", "http://www.guardian.co.uk/sport/superleague/rss"),
    ("Culture / Books", "http://feeds.guardian.co.uk/theguardian/books/rss"),
    ("Environment / George Monbiot's blog", "http://www.guardian.co.uk/environment/georgemonbiot/rss"),
    ("News / Technology / E-commerce", "http://www.guardian.co.uk/technology/efinance/rss"),
    ("Environment / Carbon offsetting", "http://www.guardian.co.uk/environment/carbon-offset-projects/rss"),
    ("News / Society / Equality", "http://www.guardian.co.uk/society/equality/rss"),
    ("Money / Work & careers", "http://www.guardian.co.uk/money/work-and-careers/rss"),
    ("Comment is free", "http://www.guardian.co.uk/commentisfree/series/liberty-clinic/rss"),
    ("News / World news / Joe Biden", "http://www.guardian.co.uk/world/joebiden/rss"),
    ("News / Society / Social care", "http://www.guardian.co.uk/society/social-care/rss"),
    ("News / Science / Agriculture", "http://www.guardian.co.uk/science/agriculture/rss"),
    ("Culture / Film", "http://www.guardian.co.uk/film/film+tone/reviews/rss"),
    ("News / Society / Disability", "http://www.guardian.co.uk/society/disability/rss"),
    ("News / Science / Taxonomy", "http://www.guardian.co.uk/science/taxonomy/rss"),
    ("Culture", "http://www.guardian.co.uk/culture/cultureblogs/roundup/rss"),
    ("News / Science / Animal research", "http://www.guardian.co.uk/science/animal-research/rss"),
    ("Life & style / Five ways to ...", "http://www.guardian.co.uk/lifeandstyle/series/fivewaysto/rss"),
    ("Life & style / Haute couture", "http://www.guardian.co.uk/lifeandstyle/haute-couture/rss"),
    ("News / World news / WTO", "http://www.guardian.co.uk/world/wto/rss"),
    ("Environment / Whaling", "http://www.guardian.co.uk/environment/whaling/rss"),
    ("Life & style / The reluctant dieter", "http://www.guardian.co.uk/lifeandstyle/series/thereluctantdieter/rss"),
    ("News / UK news / Dave Hill's London blog", "http://www.guardian.co.uk/uk/davehillblog/rss"),
    ("Environment / Polar regions", "http://www.guardian.co.uk/environment/poles/rss"),
    ("Money / Consumer affairs", "http://www.guardian.co.uk/money/consumer-affairs/rss"),
    ("News / World news / John McCain", "http://www.guardian.co.uk/world/johnmccain/rss"),
    ("Culture / Art and design", "http://www.guardian.co.uk/artanddesign/rss"),
    ("News / From the Observer / Pendennis", "http://www.guardian.co.uk/theobserver/series/pendennis/rss"),
    ("News / World news / Anglicanism", "http://www.guardian.co.uk/world/anglicanism/rss"),
    ("Sport / Guinness Premiership", "http://www.guardian.co.uk/sport/premiership/rss"),
    ("Environment / GM crops", "http://www.guardian.co.uk/environment/gmcrops/rss"),
    ("Life & style / Health & wellbeing", "http://www.guardian.co.uk/lifeandstyle/health-and-wellbeing/rss"),
    ("UNKNOWN", "http://www.guardian.co.uk/profile/georgemonbiot/rss"),
    ("Money / First-time buyers", "http://www.guardian.co.uk/money/firsttimebuyers/rss"),
    ("Money / Town and country", "http://www.guardian.co.uk/money/series/town-and-country/rss"),
    ("Business / Market turmoil", "http://www.guardian.co.uk/business/marketturmoil/rss"),
    ("Business / Observer Business, Media & Cash", "http://www.guardian.co.uk/theobserver/2009/feb/01/businessandmedia/rss"),
    ("Sport / NFL", "http://www.guardian.co.uk/sport/nfl/rss"),
    ("News / Technology / Web 2.0", "http://www.guardian.co.uk/technology/web20/rss"),
    ("News / World news / Sarah Palin", "http://www.guardian.co.uk/world/sarahpalin/rss"),
    ("News / Technology / PlayStation", "http://www.guardian.co.uk/technology/playstation/rss"),
    ("News / Science / Biodiversity", "http://www.guardian.co.uk/science/biodiversity/rss"),
    ("Sport / County Championship Division Two", "http://www.guardian.co.uk/sport/countychampionship2nddivisioncricket/rss"),
    ("News / Science / Plants", "http://www.guardian.co.uk/science/plants/rss"),
    ("Environment / Alternative energy", "http://www.guardian.co.uk/environment/alternativeenergy/rss"),
    ("News / Technology / Nintendo", "http://www.guardian.co.uk/technology/nintendo/rss"),
    ("Travel / New York", "http://www.guardian.co.uk/travel/newyork+restaurants/rss"),
    ("Culture / Film / Oscars", "http://www.guardian.co.uk/film/oscars/rss"),
    ("News / Shortcuts", "http://www.guardian.co.uk/theguardian/series/shortcuts/rss"),
    ("News / From the Observer / News", "http://www.guardian.co.uk/theobserver/news/uknews/rss"),
    ("News / Media / Radio", "http://www.guardian.co.uk/media/radio/rss"),
    ("News / Education / University teaching", "http://www.guardian.co.uk/education/universityteaching/rss"),
    ("Travel / New York", "http://www.guardian.co.uk/travel/newyork+culturaltrips/rss"),
    ("Comment is free", "http://www.guardian.co.uk/commentisfree/green/rss"),
    ("Life & style / Ethical fashion directory", "http://www.guardian.co.uk/lifeandstyle/page/ethicalfashiondirectory/rss"),
    ("News / Technology / Games blog", "http://www.guardian.co.uk/technology/gamesblog/rss"),
    ("Business / Recession", "http://www.guardian.co.uk/business/recession/rss"),
    ("Life & style / Swimming", "http://www.guardian.co.uk/lifeandstyle/swimming/rss"),
    ("Environment / Practical advice", "http://www.guardian.co.uk/environment/practicaladvice/rss"),
    ("Money / Maternity & paternity rights", "http://www.guardian.co.uk/money/maternitypaternityrights/rss"),
    ("Sport / Observer Sport", "http://www.guardian.co.uk/theobserver/2009/feb/01/sport/rss"),
    ("Life & style / Catwalk", "http://www.guardian.co.uk/lifeandstyle/catwalk/rss"),
    ("Money / Borrowing & debt", "http://www.guardian.co.uk/money/debt/rss"),
    ("News / Education / Philosophy", "http://www.guardian.co.uk/education/philosophy/rss"),
    ("Culture / Books", "http://www.guardian.co.uk/books/books+tone/features/rss"),
    ("News / Technology / Technology blog", "http://www.guardian.co.uk/technology/blog/rss"),
    ("Life & style / Gardens", "http://www.guardian.co.uk/lifeandstyle/gardens/rss"),
    ("Culture / Books", "http://www.guardian.co.uk/books/books+tone/reviews/rss"),
    ("News / Technology / Steve Jobs", "http://www.guardian.co.uk/technology/stevejobs/rss"),
    ("Sport / A1GP", "http://www.guardian.co.uk/sport/a1gp/rss"),
    ("Travel / United Kingdom", "http://www.guardian.co.uk/travel/uk+restaurants/rss"),
    ("Life & style / Love by numbers", "http://www.guardian.co.uk/lifeandstyle/series/lovebynumbers/rss"),
    ("Life & style / Observer Magazine", "http://www.guardian.co.uk/theobserver/2009/feb/01/magazine/rss"),
    ("News / Media / Organ Grinder", "http://www.guardian.co.uk/media/organgrinder/rss"),
    ("Culture / Music", "http://feeds.guardian.co.uk/theguardian/music/rss"),
    ("News / Science / Genetics", "http://www.guardian.co.uk/science/genetics/rss"),
    ("Business / Banking", "http://www.guardian.co.uk/business/banking/rss"),
    ("News / Education / 14 - 19 education", "http://www.guardian.co.uk/education/14-19-education/rss"),
    ("News", "http://feeds.guardian.co.uk/theguardian/america/rss"),
    ("Travel / New York", "http://www.guardian.co.uk/travel/newyork+hotels/rss"),
    ("News / Education / International students", "http://www.guardian.co.uk/education/internationalstudents/rss"),
    ("Money / Work-life balance", "http://www.guardian.co.uk/money/work-life-balance/rss"),
    ("Environment / Wind power", "http://www.guardian.co.uk/environment/windpower/rss"),
    ("News / Society / Health", "http://www.guardian.co.uk/society/health/rss"),
    ("Sport / Superbikes", "http://www.guardian.co.uk/sport/superbikes/rss"),
    ("News / Education / The business of research", "http://www.guardian.co.uk/education/businessofresearch/rss"),
    ("Environment / Energy", "http://www.guardian.co.uk/environment/energy/rss"),
    ("Travel / Top 10 city guides", "http://www.guardian.co.uk/travel/series/top10cityguides/rss"),
    ("News / World news / Oliver Burkeman blog", "http://www.guardian.co.uk/world/oliverburkemanblog/rss"),
    ("Travel / Rail travel", "http://www.guardian.co.uk/travel/railtravel/rss"),
    ("Life & style / Fashion designers", "http://www.guardian.co.uk/lifeandstyle/fashion-designers/rss"),
    ("Sport / GP2", "http://www.guardian.co.uk/sport/gp2/rss"),
    ("Money / Savings", "http://www.guardian.co.uk/money/savings/rss"),
    ("News / Science / Fossils", "http://www.guardian.co.uk/science/fossils/rss"),
    ("Business / US housing and sub-prime crisis", "http://www.guardian.co.uk/business/subprimecrisis/rss"),
    ("News / Media / Greenslade", "http://www.guardian.co.uk/media/greenslade/rss"),
    ("News / World news / Joe Biden", "http://www.guardian.co.uk/world/joebiden+barackobama/rss"),
    ("Life & style / Food & drink", "http://www.guardian.co.uk/lifeandstyle/food-and-drink/rss"),
    ("News / Science / Animal behaviour", "http://www.guardian.co.uk/science/animalbehaviour/rss"),
    ("News / Education / Student housing", "http://www.guardian.co.uk/education/studenthousing/rss"),
    ("News / Politics / Politics blog", "http://www.guardian.co.uk/politics/blog+pmqs/rss"),
    ("Sport / Football / Rumour Mill", "http://www.guardian.co.uk/football/series/rumourmill/rss"),
    ("Life & style / Health question", "http://www.guardian.co.uk/lifeandstyle/series/healthquestion/rss"),
    ("News / Society / NHS", "http://www.guardian.co.uk/society/nhs/rss"),
    ("Culture / Books / Original writing", "http://www.guardian.co.uk/books/original-writing/rss"),
    ("News / From the Observer / Beauty Queen", "http://www.guardian.co.uk/theobserver/series/beautyqueen/rss"),
    ("News / UK news / Hunting", "http://www.guardian.co.uk/uk/hunting/rss"),
    ("Business / Interest rates", "http://www.guardian.co.uk/business/interest-rates/rss"),
    ("News / Education / University administration", "http://www.guardian.co.uk/education/administration/rss"),
    ("Sport / Football / Chalkboards", "http://www.guardian.co.uk/football/chalkboards/rss"),
    ("Environment / Nuclear power", "http://www.guardian.co.uk/environment/nuclearpower/rss"),
    ("Travel / United Kingdom", "http://www.guardian.co.uk/travel/uk+hotels/rss"),
    ("News / Education / Research", "http://www.guardian.co.uk/education/research/rss"),
    ("Life & style / Best beauty buys", "http://www.guardian.co.uk/lifeandstyle/series/bestbeautybuys/rss"),
    ("News / World news / Younge America: the view from Roanoke", "http://www.guardian.co.uk/world/series/youngeamerica/rss"),
    ("Culture / Music", "http://www.guardian.co.uk/music/music+tone/albumreview/rss"),
    ("News / Media / Media business", "http://www.guardian.co.uk/media/mediabusiness/rss"),
    ("Culture / Stage", "http://www.guardian.co.uk/stage/rss"),
    ("Environment / Flooding", "http://www.guardian.co.uk/environment/flooding/rss"),
    ("Sport / Challenge Cup", "http://www.guardian.co.uk/sport/challengecup/rss"),
    ("Culture / Stage / Comedy", "http://www.guardian.co.uk/stage/comedy/rss"),
    ("News / Science / Mars", "http://www.guardian.co.uk/science/mars/rss"),
    ("UNKNOWN", "http://www.guardian.co.uk/tone/blog/rss"),
    ("Sport / MotoGP", "http://www.guardian.co.uk/sport/motogp/rss"),
    ("Money", "http://www.guardian.co.uk/money/careerstalk/rss"),
    ("Business / Ethical business", "http://www.guardian.co.uk/business/ethicalbusiness/rss"),
    ("Life & style / London fashion week", "http://www.guardian.co.uk/lifeandstyle/londonfashionweek/rss"),
    ("News / Science / Energy", "http://www.guardian.co.uk/science/energy/rss"),
    ("News / World news / Islam", "http://www.guardian.co.uk/world/islam/rss"),
    ("Life & style / Doctor, doctor", "http://www.guardian.co.uk/lifeandstyle/series/doctordoctor/rss"),
    ("News / Society / Communities", "http://www.guardian.co.uk/society/communities/rss"),
    ("Money / Pensions", "http://www.guardian.co.uk/money/pensions/rss"),
    ("Life & style / This Muslim life", "http://www.guardian.co.uk/lifeandstyle/series/thismuslimlife/rss"),
    ("Travel / Wales", "http://www.guardian.co.uk/travel/wales/rss"),
    ("Money / Buying to let", "http://www.guardian.co.uk/money/buying-to-let/rss"),
    ("News / Politics / The Backbencher", "http://www.guardian.co.uk/politics/thebackbencher/rss"),
    ("Travel / United Kingdom", "http://www.guardian.co.uk/travel/uk/rss"),
    ("Culture / Art and design / Art", "http://www.guardian.co.uk/artanddesign/art/rss"),
    ("News / World news / On the road to the White House", "http://www.guardian.co.uk/world/uselectionroadtrip/rss"),
    ("News / World news / Sarah Palin", "http://www.guardian.co.uk/world/sarahpalin+tone/comment/rss"),
    ("Travel / United Kingdom", "http://www.guardian.co.uk/travel/uk+shortbreaks/rss"),
    ("Sport / Tiger Woods", "http://www.guardian.co.uk/sport/tigerwoods/rss"),
    ("Money / Student finance", "http://www.guardian.co.uk/money/student-finance/rss"),
    ("Money / Insurance", "http://www.guardian.co.uk/money/insurance/rss"),
    ("News / Society / Long-term care", "http://www.guardian.co.uk/society/longtermcare/rss"),
    ("News / Politics / Economic policy", "http://www.guardian.co.uk/politics/economy/rss"),
    ("Sport / England rugby league team", "http://www.guardian.co.uk/sport/england-rugby-league-team/rss"),
    ("News / Technology / Software", "http://www.guardian.co.uk/technology/software/rss"),
    ("News / Technology / Second Life", "http://www.guardian.co.uk/technology/secondlife/rss"),
    ("Money / Scams", "http://www.guardian.co.uk/money/scamsandfraud/rss"),
    ("News / Education / Commonwealth universities", "http://www.guardian.co.uk/education/commonwealthuniversities/rss"),
    ("News / Science / Physics", "http://www.guardian.co.uk/science/physics/rss"),
    ("Money / Child trust funds", "http://www.guardian.co.uk/money/childtrustfunds/rss"),
    ("Money / Internet, phones & broadband", "http://www.guardian.co.uk/money/internetphonesbroadband/rss"),
    ("Money / Capital letters", "http://www.guardian.co.uk/money/series/capitalletters/rss"),
    ("Culture / Film", "http://www.guardian.co.uk/film/film+tone/features/rss"),
    ("News / Education / Gap years", "http://www.guardian.co.uk/education/gapyears/rss"),
    ("Culture / Stage", "http://www.guardian.co.uk/stage/stage+tone/reviews/rss"),
    ("News / Education / Tuition fees", "http://www.guardian.co.uk/education/tuition-fees/rss"),
    ("Environment / Green building", "http://www.guardian.co.uk/environment/greenbuilding/rss"),
    ("Money / Consumer affairs", "http://www.guardian.co.uk/money/consumer-affairs/rss"),
    ("Culture / Music", "http://www.guardian.co.uk/music/music+tone/features/rss"),
    ("Money / Pay", "http://www.guardian.co.uk/money/pay/rss"),
    ("Money / Trading up, trading down", "http://www.guardian.co.uk/money/series/tradinguptradingdown/rss"),
    ("Money / Investments", "http://www.guardian.co.uk/money/moneyinvestments/rss"),
    ("News / Society / Joe Public blog", "http://www.guardian.co.uk/society/joepublic/rss"),
    ("Culture / Film / Film Weekly", "http://www.guardian.co.uk/film/series/filmweekly/rss"),
    ("Life & style / Celebrity", "http://www.guardian.co.uk/lifeandstyle/celebrity+tone/interview/rss"),
    ("Comment is free", "http://www.guardian.co.uk/commentisfree/commentisfree+environment/flooding/rss"),
    ("Money / Occupational pensions", "http://www.guardian.co.uk/money/occupational-pensions/rss"),
    ("Culture / Art and design", "http://www.guardian.co.uk/artanddesign/artanddesign+tone/news/rss"),
    ("Culture / Art and design / Heritage", "http://www.guardian.co.uk/artanddesign/heritage/rss"),
    ("News / Technology / Microsoft", "http://www.guardian.co.uk/technology/microsoft/rss"),
    ("Travel / United Kingdom", "http://www.guardian.co.uk/travel/uk+bars/rss"),
    ("Sport / Andy Murray", "http://www.guardian.co.uk/sport/andymurray/rss"),
    ("Money / Mortgages", "http://www.guardian.co.uk/money/mortgages/rss"),
    ("Money / Credit cards", "http://www.guardian.co.uk/money/creditcards/rss"),
    ("Culture / Film", "http://www.guardian.co.uk/film/film+tone/news/rss"),
    ("UNKNOWN", "http://www.guardian.co.uk/tone/leaders/rss"),
    ("Sport / England rugby union team", "http://www.guardian.co.uk/sport/england-rugby-union-team/rss"),
    ("Sport / GB rugby league team", "http://www.guardian.co.uk/sport/gb-rugby-league-team/rss"),
    ("News / Technology / Hacking", "http://www.guardian.co.uk/technology/hacking/rss"),
    ("Money / Work & careers", "http://www.guardian.co.uk/money/work-and-careers/rss"),
    ("News / Society / MRSA and superbugs", "http://www.guardian.co.uk/society/mrsa/rss"),
    ("Culture / Books", "http://www.guardian.co.uk/books/books+tone/news/rss"),
    ("Money / House prices", "http://www.guardian.co.uk/money/houseprices/rss"),
    ("Money / Annuities", "http://www.guardian.co.uk/money/annuities/rss"),
    ("Life & style / Gardening blog", "http://www.guardian.co.uk/lifeandstyle/gardening-blog/rss"),
    ("Culture / Art and design / Photography", "http://www.guardian.co.uk/artanddesign/photography/rss"),
    ("Business / Banking", "http://www.guardian.co.uk/business/banking+uk/uk/rss"),
    ("News / Politics", "http://www.guardian.co.uk/politics/page/2008/jan/03/1/rss"),
    ("UNKNOWN", "http://www.guardian.co.uk/tone/letters/rss"),
    ("News / Science / Astronomy", "http://www.guardian.co.uk/science/astronomy/rss"),
    ("News / Society / Voluntary sector", "http://www.guardian.co.uk/society/voluntarysector/rss"),
    ("Life & style / The Comic", "http://www.guardian.co.uk/lifeandstyle/comic/rss"),
    ("News / Technology / Digital music and audio", "http://www.guardian.co.uk/technology/digital-music-and-audio/rss"),
    ("News / Technology / iPod", "http://www.guardian.co.uk/technology/ipod/rss"),
    ("UNKNOWN", "http://www.guardian.co.uk/profile/michaelwhite+politics/blog/rss"),
    ("News / Science / Meteorology", "http://www.guardian.co.uk/science/meteorology/rss"),
    ("News / Society / Children", "http://www.guardian.co.uk/society/children/rss"),
    ("Money / Redundancy", "http://www.guardian.co.uk/money/redundancy/rss"),
    ("Money / State pensions", "http://www.guardian.co.uk/money/state-pensions/rss"),
    ("Culture / Television", "http://www.guardian.co.uk/culture/television/rss"),
    ("News / Politics / Opinion polls", "http://www.guardian.co.uk/politics/polls/rss"),
    ("Culture / Music / Classical music and opera", "http://www.guardian.co.uk/music/classicalmusicandopera/rss"),
    ("Culture / Art and design / Private view podcast", "http://www.guardian.co.uk/artanddesign/series/privateview/rss"),
    ("Money / Shares", "http://www.guardian.co.uk/money/shares/rss"),
    ("Business / Banking", "http://www.guardian.co.uk/business/banking+world/usa/rss"),
    ("News / Society / Mental health", "http://www.guardian.co.uk/society/mental-health/rss"),
    ("News / Society / Social exclusion", "http://www.guardian.co.uk/society/socialexclusion/rss"),
    ("Business / Banking", "http://www.guardian.co.uk/business/banking+world/ireland/rss"),
    ("Environment / Monbiot meets ...", "http://www.guardian.co.uk/environment/series/monbiot-meets/rss"),
    ("News / Technology / iPhone", "http://www.guardian.co.uk/technology/iphone/rss"),
    ("Environment / Activists", "http://www.guardian.co.uk/environment/activists/rss"),
    ("News / Politics / Development", "http://www.guardian.co.uk/politics/development/rss"),
    ("Culture / Film / Pickard of the flicks", "http://www.guardian.co.uk/film/series/pickardoftheflicks/rss"),
    ("News / Science / Zoology", "http://www.guardian.co.uk/science/zoology/rss"),
    ("Money / Interest rates", "http://www.guardian.co.uk/money/interestrates/rss"),
    ("News / Science / Richard Dawkins", "http://www.guardian.co.uk/science/dawkins/rss"),
    ("Culture / Music", "http://www.guardian.co.uk/music/music+tone/news/rss"),
    ("News / Technology / Windows", "http://www.guardian.co.uk/technology/windows/rss"),
    ("Money / Ethical money", "http://www.guardian.co.uk/money/ethical-money/rss"),
    ("Culture / Stage / Theatre", "http://www.guardian.co.uk/stage/theatre/rss"),
    ("Money / Travel insurance", "http://www.guardian.co.uk/money/travelinsurance/rss"),
    ("Money / Home insurance", "http://www.guardian.co.uk/money/homeinsurance/rss"),
    ("Travel / United Kingdom", "http://www.guardian.co.uk/travel/uk+budget/rss"),
    ("News / Work", "http://www.guardian.co.uk/theguardian/2009/jan/31/work/rss"),
    ("Money / Personal pensions", "http://www.guardian.co.uk/money/personalpensions/rss"),
    ("News / Society / Learning disability", "http://www.guardian.co.uk/society/learningdisability/rss"),
    ("Culture / Music / Music Weekly", "http://www.guardian.co.uk/music/series/musicweekly/rss"),
    ("Money / Household bills", "http://www.guardian.co.uk/money/household-bills/rss"),
    ("Culture / Art and design / Architecture", "http://www.guardian.co.uk/artanddesign/architecture/rss"),
    ("Culture / Stage / Dance", "http://www.guardian.co.uk/stage/dance/rss"),
    ("News / Technology / Ask Jack", "http://www.guardian.co.uk/technology/askjack/rss"),
    ("Travel / London", "http://www.guardian.co.uk/travel/london/rss"),
    ("Money / Banks and building societies", "http://www.guardian.co.uk/money/banks/rss"),
    ("News / World news / Joe Biden", "http://www.guardian.co.uk/world/joebiden+tone/comment/rss"),
    ("News / Society / Public sector careers", "http://www.guardian.co.uk/society/public-sector-careers/rss"),
    ("Life & style / Clippings", "http://www.guardian.co.uk/lifeandstyle/series/clippings/rss"),
    ("Money / With-profits funds", "http://www.guardian.co.uk/money/with-profits-funds/rss"),
    ("Travel / Ireland", "http://www.guardian.co.uk/travel/ireland/rss"),
    ("Money / Isas", "http://www.guardian.co.uk/money/isas/rss"),
    ("News / World news / Obama inauguration", "http://www.guardian.co.uk/world/obama-inauguration/rss"),
    ("Business / Banking", "http://www.guardian.co.uk/business/banking+world/iceland/rss"),
    ("News / World news / Sarah Palin", "http://www.guardian.co.uk/world/sarahpalin+johnmccain/rss"),
    ("News / Science / Geology", "http://www.guardian.co.uk/science/geology/rss"),
    ("Culture / Art and design / Design", "http://www.guardian.co.uk/artanddesign/design/rss"),
    ("News / Society / Housing", "http://www.guardian.co.uk/society/housing/rss"),
    ("Money / Tax", "http://www.guardian.co.uk/money/tax/rss"),
    ("Business / Bank of England", "http://www.guardian.co.uk/business/bankofenglandgovernor/rss"),
    ("News / Society / Youth justice", "http://www.guardian.co.uk/society/youthjustice/rss"),
    ("News / Society / Volunteering", "http://www.guardian.co.uk/society/volunteering/rss"),
    ("Travel / Scotland", "http://www.guardian.co.uk/travel/scotland/rss"),
    ("News / Science / Hubble Space Telescope", "http://www.guardian.co.uk/science/hubble-space-telescope/rss"),
    ("Money / Investment funds", "http://www.guardian.co.uk/money/investmentfunds/rss"),
    ("News / Science / Reproduction", "http://www.guardian.co.uk/science/reproduction/rss"),
    ("Money / Motor insurance", "http://www.guardian.co.uk/money/motorinsurance/rss"),
    ("Money / Life insurance", "http://www.guardian.co.uk/money/lifeinsurance/rss"),
    ("Culture / Music", "http://www.guardian.co.uk/music/music+culture/festivals/rss"),
    ("Travel / London", "http://www.guardian.co.uk/travel/london+shoppingtrips/rss"),
    ("Culture / Stage / Comedy", "http://www.guardian.co.uk/stage/comedy+tone/comment/rss"),
    ("Travel / London", "http://www.guardian.co.uk/travel/london+hotels/rss"),
    ("Culture / Music", "http://www.guardian.co.uk/music/music+tone/livemusicreview/rss"),
    ("News / Society / Sexual health", "http://www.guardian.co.uk/society/sexual-health/rss"),
    ("News / Science / Space exploration", "http://www.guardian.co.uk/science/space-exploration/rss"),
    ("News / Society / Regeneration", "http://www.guardian.co.uk/society/regeneration/rss"),
    ("Culture / Stage / Dance", "http://www.guardian.co.uk/stage/dance+tone/reviews/rss"),
    ("News / Politics / Alistair Darling", "http://www.guardian.co.uk/politics/alistairdarling/rss"),
    ("News / Technology / Bill Gates", "http://www.guardian.co.uk/technology/billgates/rss"),
    ("News / Science / Particle physics", "http://www.guardian.co.uk/science/particlephysics/rss"),
    ("Money / Pensions bill 2006", "http://www.guardian.co.uk/money/pensionsbill2006/rss"),
    ("Culture / Art and design / My best shot", "http://www.guardian.co.uk/artanddesign/series/mybestshot/rss"),
    ("Money / Capital gains tax", "http://www.guardian.co.uk/money/capitalgainstax/rss"),
    ("Travel / London", "http://www.guardian.co.uk/travel/london+familyholidays/rss"),
    ("News / Technology / Intellectual property", "http://www.guardian.co.uk/technology/intellectual-property/rss"),
    ("Money", "http://www.guardian.co.uk/money/rss"),
    ("News / Society / Child protection", "http://www.guardian.co.uk/society/childprotection/rss"),
    ("News / Science / Chemistry", "http://www.guardian.co.uk/science/chemistry/rss"),
    ("Culture / Stage / Comedy", "http://www.guardian.co.uk/stage/comedy+tone/reviews/rss"),
    ("News / Society / Homelessness", "http://www.guardian.co.uk/society/homelessness/rss"),
    ("Culture / Radio", "http://www.guardian.co.uk/culture/radio/rss"),
    ("Travel / London", "http://www.guardian.co.uk/travel/london+budget/rss"),
    ("Travel / London", "http://www.guardian.co.uk/travel/london+restaurants/rss"),
    ("Culture / Stage", "http://www.guardian.co.uk/stage/stage+tone/news/rss"),
    ("Travel / Edinburgh", "http://www.guardian.co.uk/travel/edinburgh/rss"),
    ("News / Society / Gangs", "http://www.guardian.co.uk/society/gangs/rss"),
    ("Money / Health insurance", "http://www.guardian.co.uk/money/healthinsurance/rss"),
    ("Travel / Scotland", "http://www.guardian.co.uk/travel/scotland+shortbreaks/rss"),
    ("UNKNOWN", "http://www.guardian.co.uk/profile/annapickard/rss"),
    ("Travel / Glasgow", "http://www.guardian.co.uk/travel/glasgow/rss"),
    ("Culture / Music / Classical music and opera", "http://www.guardian.co.uk/music/classicalmusicandopera+tone/albumreview/rss"),
    ("Culture / Art and design / Sebasti?o Salgado: Genesis", "http://www.guardian.co.uk/artanddesign/series/sebastiaosalgadogenesis/rss"),
    ("Life & style / Allotment blog", "http://www.guardian.co.uk/lifeandstyle/allotment/rss"),
    ("News / Society / Social enterprises", "http://www.guardian.co.uk/society/socialenterprises/rss"),
    ("Money / Income tax", "http://www.guardian.co.uk/money/incometax/rss"),
    ("Money / Family finances", "http://www.guardian.co.uk/money/family-finances/rss"),
    ("News / Technology / Digital video", "http://www.guardian.co.uk/technology/digitalvideo/rss"),
    ("News / Society / Young people", "http://www.guardian.co.uk/society/youngpeople/rss"),
    ("Money / Council tax", "http://www.guardian.co.uk/money/counciltax/rss"),
    ("Culture / Edinburgh festival", "http://www.guardian.co.uk/culture/edinburghfestival/rss"),
    ("Culture / Music / Classical music and opera", "http://www.guardian.co.uk/music/classicalmusicandopera+tone/livemusicreview/rss"),
    ("Travel / London", "http://www.guardian.co.uk/travel/london+culturaltrips/rss"),
    ("Travel / Edinburgh", "http://www.guardian.co.uk/travel/edinburgh+hotels/rss"),
    ("News / Society / International aid and development", "http://www.guardian.co.uk/society/international-aid-and-development/rss"),
    ("Travel / London", "http://www.guardian.co.uk/travel/london+bars/rss"),
    ("Culture / Stage / Theatre", "http://www.guardian.co.uk/stage/theatre+tone/reviews/rss"),
    ("UNKNOWN", "http://feeds.guardian.co.uk/theguardian/profile/charliebrooker/rss"),
    ("Travel / Scotland", "http://www.guardian.co.uk/travel/scotland+hotels/rss"),
    ("Culture / Edinburgh festival", "http://www.guardian.co.uk/culture/edinburghfestival+tone/reviews/rss"),
    ("Culture / Stage / Dance", "http://www.guardian.co.uk/stage/dance+tone/comment/rss"),
    ("Travel / Edinburgh", "http://www.guardian.co.uk/travel/edinburgh+budget/rss"),
    ("Travel / Edinburgh", "http://www.guardian.co.uk/travel/edinburgh+bars/rss"),
    ("Culture / Stage / The Guardian Live at the Gilded Balloon", "http://www.guardian.co.uk/stage/series/comedypodcast/rss"),
    ("News / Society / Prisons and probation", "http://www.guardian.co.uk/society/prisons-and-probation/rss"),
    ("News / World news / Barack Obama", "http://www.guardian.co.uk/world/barackobama+tone/comment/rss"),
    ("Money / Tax credits", "http://www.guardian.co.uk/money/taxcredits/rss"),
    ("News / Society / Climbi? inquiry", "http://www.guardian.co.uk/society/climbie/rss"),
    ("Money / Inheritance tax", "http://www.guardian.co.uk/money/inheritancetax/rss"),
    ("News / Science / Cern", "http://www.guardian.co.uk/science/cern/rss"),
    ("News / Science / Aeronautics", "http://www.guardian.co.uk/science/aeronautics/rss"),
    ("News / Society / Drugs and alcohol", "http://www.guardian.co.uk/society/drugs-and-alcohol/rss"),
    ("Travel / Edinburgh", "http://www.guardian.co.uk/travel/edinburgh+restaurants/rss"),
    ("Culture / Stage / Theatre", "http://www.guardian.co.uk/stage/theatre+culture/edinburghfestival/rss"),
    ("News / Society / Emergency planning", "http://www.guardian.co.uk/society/emergencyplanning/rss"),
    ("News / Society / Public services policy", "http://www.guardian.co.uk/society/policy/rss"),
    ("Culture / Stage / Comedy", "http://www.guardian.co.uk/stage/comedy+culture/edinburghfestival/rss"),
]




def FindBlogFeeds():
    """special case for guardian blogs - try and download the list dynamically each run"""
    feeds = []

    html = ukmedia.FetchURL( 'http://www.guardian.co.uk/tone/blog' )
    soup = BeautifulSoup( html )

    # some of the blogs are still listed as being at blogs.guardian.co.uk
    # but in fact most of them remap to a new-style location:
    oldblog_remaps = {
        '/sport/': 'http://www.guardian.co.uk/sport/blog/rss',
        '/usa/': 'http://www.guardian.co.uk/world/deadlineusa/rss',
        '/greenslade/': 'http://www.guardian.co.uk/media/greenslade/rss',
        '/mediamonkey/': 'http://www.guardian.co.uk/media/mediamonkeyblog/rss',
        '/organgrinder/': 'http://www.guardian.co.uk/media/organgrinder/rss',
        '/digitalcontent/': 'http://www.guardian.co.uk/media/pda/rss',
        '/askjack/': 'http://www.guardian.co.uk/technology/askjack/rss',
        '/games/': 'http://www.guardian.co.uk/technology/gamesblog/rss',
        '/technology/': 'http://www.guardian.co.uk/technology/blog/rss',
        '/katine/': 'http://www.guardian.co.uk/society/katineblog/rss',
        # "blogging the quran" still in old form...
        '/quran/': 'http://blogs.guardian.co.uk/quran/atom.xml',
    }

    # overall feed
#    feeds.append( ('All guardian.co.uk blog posts', 'http://blogs.guardian.co.uk/atom.xml') )


    bloglist = soup.find( 'div', {'id':'editor-zone-1'} )

    for a in bloglist.findAll( 'a', {'class': 'link-text'} ):
        url = a['href']
        title = ukmedia.FromHTML( a.renderContents( None ) )

        o = urlparse.urlparse( url )
        if o[1] == 'blogs.guardian.co.uk':
            if o[2] not in oldblog_remaps:
                ukmedia.DBUG( "UHOH - SKIPPING unknown old-style blog: %s" % (url ) )
                continue
            url = oldblog_remaps[ o[2] ]
        else:
            url = url + "/rss"

        feeds.append( (title, url) )

    return feeds


# eg "http://www.guardian.co.uk/crime/article/0,,2212646,00.html"
urlpat_storyserver = re.compile( u".*/\w*,\w*,\w*,\w*\.html", re.UNICODE )

# eg "http://www.guardian.co.uk/environment/2007/nov/17/climatechange.carbonemissions1"
#urlpat_newformat = re.compile(  u".*/.*?(?![.]html)", re.UNICODE )


def WhichFormat( url ):
    """ figure out which format the article is going to be in """
    if urlpat_storyserver.match( url ):
        return 'storyserver'

    o = urlparse.urlparse( url )

    if o[1] == 'blogs.guardian.co.uk':
        if '/sport/' in url:
            return 'sportblog'
        else:
            return 'blog'

    if not o[2].endswith( '.html' ):
        return 'newformat'


    return 'UNKNOWN'


def Extract( html, context ):
    fmt = WhichFormat( context['srcurl'] )
    if fmt == 'storyserver':
        return Extract_storyserver( html, context )
    if fmt == 'newformat':
        return Extract_newformat( html, context )
    if fmt == 'blog':
        return Extract_blog( html, context )
    if fmt == 'sportblog':
        return Extract_sportblog( html, context )



def Extract_newformat( html, context ):
    """ Extract function for guardians new CMS system (developed in-house) """

    art = context
    soup = BeautifulSoup( html )

    # this contains headline, stand-first
    artinfodiv = soup.find( 'div', id="main-article-info" )

    # find title
    title = artinfodiv.h1.renderContents(None)
    title = ukmedia.FromHTML(title)
    art[ 'title' ] = title


    contentdiv = soup.find( 'div', id="content" )


    # article-attributes
    # contains byline, date, publication...
    attrsdiv = contentdiv.find( 'ul', {'class':re.compile("""\\barticle-attributes\\b""")} )
    blogbylinediv = soup.find( 'div', {'class':'blog-byline'} )

    if attrsdiv:
        # byline
        byline = attrsdiv.find( 'li', { 'class':'byline' } )
        if byline:
            art['byline'] = ukmedia.FromHTML( byline.renderContents(None) )
        else:
            # TODO: could search for journo in description or "stand-first"
            # para in main-article-info div.
            art['byline'] = u''

        # guardian or observer?
        # (guardian is the catchall - we use it for web-only content too)
        publicationli = attrsdiv.find( 'li', { 'class':'publication' } )
        publication = publicationli.a.string
        if u'The Observer' in publication:
            art['srcorgname'] = u'observer'
        else:
            art['srcorgname'] = u'guardian'

        # date (sometimes is actually in the "publication" bit)
        dateli = attrsdiv.find( 'li', { 'class':'date' } )
        if dateli:
            pubdate = dateli.find.renderContents(None).strip()
            art['pubdate'] = ukmedia.ParseDateTime( pubdate )
        else:
            foo = publicationli.renderContents(None)
            # TODO: should use a regex to extract just the date part of the string!
            art['pubdate'] = ukmedia.ParseDateTime( foo )

    
        # now strip out all non-text bits of content div
        attrsdiv.extract()
    elif blogbylinediv:
        # might be a blog...
        raw = ukmedia.FromHTMLOneLine( blogbylinediv.renderContents(None) )

        # seen at least three different "Posted by" formats so far:
        # "Posted by Lee Glendinning Tuesday August 19 2008 1:07 pm"
        # "Posted by Steve Cram Tuesday October 14 2008 00.01 BST"
        # "Posted by James Meikle Tuesday 16 December 2008 16.23 GMT"
        bylinepat = re.compile( ur'Posted\s+(.*?)\s+(\w+day\s+\S+\s+\S+\s+\d{4}\s+.*?)$', re.UNICODE )

        m = bylinepat.search( raw )
        byline = m.group(1)
        art['pubdate'] = ukmedia.ParseDateTime( m.group(2) )
#        byline = u' '.join( byline.split() )
#        byline = re.sub( u' , ', u', ', byline )

        art['byline'] = byline
        art['srcorgname'] = u'guardian'

#<div class="blog-byline">
#						<a href="http://www.guardian.co.uk/profile/andrewsparrow" title="Contributor's page">
#	    		<img src="http://static.guim.co.uk/sys-images/Politics/Pix/pictures/2008/01/17/Andrew_Sparrow.jpg" width="60" height="60" alt="Andrew Sparrow. Photograph: Linda Nylind" />
#	    	</a>
#			<span>Posted by</span>
#		                        <a href=http://www.guardian.co.uk/profile/andrewsparrow name="&lid={blogBylineContributor}{Andrew Sparrow}&lpos={blogBylineContributor}{1}">Andrew Sparrow</a>, senior political correspondent
#			Thursday August 21 2008
#	<span class="timestamp">10:42 am</span>
#</div>


    cruft = contentdiv.find('ul', id='article-toolbox')
    if cruft:
        cruft.extract()
    cruft = contentdiv.find('div', id='contact')
    if cruft:
        cruft.extract()
    for cruft in contentdiv.findAll( 'div', {'class': 'send'} ):
        cruft.extract()

    art['images'] = []
    caption_pat = re.compile( ur"(.*)\s*(?:photograph:|photographs:|photo:|photos:)\s*(.*)\s*$", re.UNICODE|re.IGNORECASE )
    # images
    for figure in contentdiv.findAll( 'figure' ):
        img = figure.img
        img_url = img['src']
        figcaption = figure.find( 'figcaption' )
        t = u''
        if figcaption:
            t = figcaption.renderContents( None )
            t = ukmedia.FromHTMLOneLine(t)
        m = caption_pat.match( t )
        cap = u''
        cred = u''
        if m:
            cap = m.group(1)
            cred = m.group(2)

        art['images'].append( { 'url': img_url, 'caption': cap, 'credit': cred } )
        figure.extract()

    # long articles have a folding part

    # 1) 'shower' para to control folding
    showerpara = contentdiv.find( 'p', {'class':'shower'} )
    if showerpara:
        showerpara.extract()
    # 2) the extra text is inside the 'more-article' div
    morediv = contentdiv.find( 'div', id='more-article' );
    if morediv:
        morediv.extract()

    # move all the remaining elements into a fresh soup
    textpart = BeautifulSoup()
    for element in list(contentdiv.contents):
        textpart.append(element)  # removes from contentdiv!

    # if there was a folding bit, add its contents to the new soup too
    if morediv:
        for element in list(morediv.contents):
            textpart.append(element)  # removes from morediv!

    # Description
    desc = None

    # look for first-stand para first (appears in 'main-article-info')
    descpara = artinfodiv.find( 'p', {'id':'stand-first'} )
    if descpara:
        desc = ukmedia.FromHTML( descpara.prettify(None) )

#    if not desc:  # long first para
#        # use <meta name="description" content="XXXXX">
#        meta_desc = soup.head.find('meta', {'name': 'description'})
#        if meta_desc and 'content' in dict(meta_desc.attrs):
#            desc = meta_desc['content']

#    if not desc:
#        descpara = textpart.p  # no? just use first para of text instead.
#        desc = ukmedia.FromHTML( descpara.prettify(None) )


    # that's it!
    art['content'] = ukmedia.SanitiseHTML( textpart.prettify(None) )

    if desc:
        art['description'] = ukmedia.DescapeHTML(desc)
    else:
        art['description'] = ukmedia.FirstPara( art['content'] )
    return art




def Extract_storyserver(html, context):
    '''
    A scraper for oldstyle main guardian/observer articles (Vignette storyserver, I think)
    '''

    soup = BeautifulSoup( html )

    div = soup.find('div', {'id': 'GuardianArticle'})
    descline = div.h1.findNext('font', {'size': '3'}) or u''
    if descline:
        marker = descline
        descline = descline.renderContents(None)
    else:
        marker = div.h1
    dateline = marker.findNext('font').b.renderContents(None)

    body = div.find('div', {'id': 'GuardianArticleBody'}).renderContents(None)
    try:
        bits = re.split(r'<p>\s*<b>\s*&(?:#183|middot);\s*</b>', body)  # end of article marker
        body, bios = bits[0], bits[1:]
        for bio in list(bios):
            if 'will be appearing' in bio:
                bios.remove(bio)
        if bios:
            bio = u'<p>' + u'\n\n<p>'.join([bio.lstrip() for bio in bios])  # put the <p> back
        else:
            bio = u''
    except ValueError:
        bio = u''
    # They've taken to inserting section breaks in a really unpleasant way, as in
    # http://lifeandhealth.guardian.co.uk/family/story/0,,2265583,00.html
    body = re.compile(r'<p>\s*<script.*?<a name="article_continue"></a>\s*</div>\s*',
                      flags=re.UNICODE | re.DOTALL).sub(' ', body)
    if not descline:
        descline = ukmedia.FirstPara(body)
    
    byline = None
    pos = dateline.find('<br />')
    if pos > -1:
        # Check that format appears to be "AUTHOR<br />DATE ..."
        days = 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'
        for day in days:
            if dateline[pos+len('<br />'):].startswith(day):
                byline = dateline[:pos]
    if not byline:
        byline = ukmedia.ExtractAuthorFromParagraph(descline)

    # is it observer or guardian?
    srcorgname = u'guardian'
    if 'The Observer' in dateline:
        srcorgname = u'observer'

 
    # But the javascript-generated sidebar may provide a more accurate byline,
    # and provides the all-important "blog" URL, where "blog"="articles by this author"
    # in this rather odd field.
    cifblog_url, cifblog_feed = None, None
    for script_tag in soup.findAll('script'):
        src = dict(script_tag.attrs).get('src', '')
        if re.match(r'http://.*?/\d+_twocolumnleftcolumninsideleftcolumn.js$', src):
            js = urllib2.urlopen(src).read()
            if not isinstance(js, unicode):
                js = unicode(js, 'utf-8')
            
            m = re.search(ur'articleslistitemshowalllink.setAttribute\("href", "(.*?)"\);', js, re.UNICODE)
            if m:
                cifblog_url = m.group(1)

            m = re.search(ur'webfeedfirstlink.setAttribute\("href", "(.*?)"\);', js, re.UNICODE)
            if m:
                cifblog_feed = m.group(1)

            m = re.search(ur'document\.createTextNode\("All (.*?) articles"\)', js, re.UNICODE)
            if m:
                byline = m.group(1)
            
            # And while we're at it, we might as well get a unique author id:
            m = re.search(ur'profilelinka\.setAttribute\("href", "(.*?)"\)', js, re.UNICODE)
            if m:
                context['author_id'] = m.group(1)

    art = context
    art['guardian-format'] = 'commentisfree.py (2)' ####### OVERRIDE ########
    art['title'] = ukmedia.FromHTML(div.h1.renderContents(None))
    art['description'] = ukmedia.FromHTML(descline)
    art['byline'] = byline.strip()
    art['pubdate'] = ukmedia.ParseDateTime(dateline.replace('<br />', '\n'))
    art['content'] = ukmedia.SanitiseHTML(ukmedia.DescapeHTML(body))
    art['bio'] = ukmedia.SanitiseHTML(ukmedia.DescapeHTML(bio))
    art['srcorgname'] = srcorgname
    if cifblog_url:
        art['cifblog-url'] = cifblog_url
    if cifblog_feed:
        art['cifblog-feed'] = cifblog_feed  #RSS/Atom equivalent of cifblog-url
    return art


def Extract_blog( html, context ):
    """ Extract function for blog.guardian.co.uk format """

    art = context
    soup = BeautifulSoup( html )
    maindiv = soup.find( 'div', {'class':"blogs-article"} )

    d = soup.find( 'div', {'class':"blogs-article-author"} )
    bylinetxt = ukmedia.FromHTMLOneLine( d.h2.renderContents(None) )

    headerdiv = maindiv.find( 'div', {'class':"blogs-article-header"} )
    h1 = headerdiv.h1
    titletxt = ukmedia.FromHTMLOneLine( h1.renderContents( None ) )
    datediv = headerdiv.find( 'div', {'class':"blogs-article-date"} )

    desctxt = u''
    descdiv = headerdiv.find( 'div', {'class':"blogs-article-excerpt"} )
    if descdiv:
        desctxt = ukmedia.FromHTMLOneLine( descdiv.renderContents( None ) )

    pubdate = ukmedia.ParseDateTime( datediv.renderContents(None) )

    contentdiv = maindiv.find( 'div', {'class':"blogs-article-content"} )

    contenttxt = ukmedia.SanitiseHTML( contentdiv.renderContents( None ) )

#    print titletxt
#   print bylinetxt
#  print pubdate

    art['title'] = titletxt
    art['byline'] = bylinetxt
    art['pubdate'] = pubdate
    art['content'] = contenttxt
    if desctxt == u'':
        desctxt = ukmedia.FirstPara( contenttxt )
    art['description'] = desctxt

    return art



def Extract_sportblog( html, context ):
    art = context
    soup = BeautifulSoup( html )


    desctxt = u''
    headdiv = soup.find( 'div', {'id': "twocolumnleftcolumninsiderightcolumntop"} )
    titletxt = ukmedia.FromHTMLOneLine( headdiv.h1.renderContents(None) )

    standfirst = headdiv.find( 'p', {'class':"standfirst"} )
    if standfirst:
        desctxt = ukmedia.FromHTMLOneLine( standfirst.renderContents( None ) )

    bylinediv = soup.find( 'div', {'id':'twocolumnleftcolumninsideleftcolumn'} )
    bylinetxt = ukmedia.FromHTMLOneLine( bylinediv.h2.renderContents(None) )


    contentdiv = soup.find( 'div', {'id':"twocolumnleftcolumninsiderightcolumn"} )
    pubdatediv = contentdiv.find('div', {'id':"twocolumnleftcolumntopbaselinetext"} )
    pubdate = ukmedia.ParseDateTime( pubdatediv.renderContents( None ) )
    pubdatediv.extract()

    contenttxt = contentdiv.renderContents( None )

    art['title'] = titletxt
    art['byline'] = bylinetxt
    art['pubdate'] = pubdate
    art['content'] = contenttxt
    if desctxt == u'':
        desctxt = ukmedia.FirstPara( contenttxt )
    art['description'] = desctxt

    return art



def TidyURL( url ):
    """ Tidy up URL - trim off any extra cruft (eg rss tracking stuff) """
    o = urlparse.urlparse( url )
    url = urlparse.urlunparse( (o[0],o[1],o[2],'','','') );
    return url


# patterns to extract srcids
srcid_pats = [
    # old (storyserver) format
    # "http://education.guardian.co.uk/schools/story/0,,2261002,00.html"
    re.compile( r'.*[/]([0-9,-]+)[.]html$' ),

    # new format
    # "http://www.guardian.co.uk/world/2008/feb/29/afghanistan.terrorism"
    re.compile( r'\bguardian[.]co[.]uk/(.*?\d{4}/.*?/\d+/.*(?![.]html))$' ),

    # blogs
    # http://blogs.guardian.co.uk/games/archives/2008/07/28/has_the_iphone_made_mobile_gaming_good.html
    re.compile( 'blogs[.]guardian[.]co[.]uk/(.*[.]html)' ),
]

def CalcSrcID( url ):
    """ Extract a unique srcid from the URL """

    url = TidyURL( url )

    o = urlparse.urlparse( url )
    if not o[1].endswith( 'guardian.co.uk' ):
        return None

    for pat in srcid_pats:
        m = pat.search( url )
        if m:
            return 'guardian_' + m.group(1)

    return None


def ScrubFunc( context, entry ):
    """ fn to massage info from RSS feed """
    url = context['permalink']

    if url.startswith( "http://www.guardianfeeds.co.uk" ):
        # The Technology blog rss has it's <link> element going through guardianfeeds.co.uk,
        # which just redirects them. Luckily, the destination is given in the <guid>
        # (albeit with isPermaLink="false")
        url = entry.guid

    url = TidyURL( url )
    context['permalink'] = url;
    src_id = CalcSrcID( url )
    if src_id is None:
#        ukmedia.DBUG2( "IGNORE no srcid for '%s' (%s)\n" % (context['title'], url) );
        return None

    context['srcid'] = src_id

    if WhichFormat( url ) == 'newformat' and not url.startswith('file:'):
        # force whole article on single page
        context['srcurl'] = url + '?page=all'
    else:
        context['srcurl'] = url;

    # some items don't have pubdate
    # (they're probably special-case duds (eg flash pages), but try and
    # parse them anyway)
    if not context.has_key( 'pubdate' ):
        context['pubdate'] = datetime.now()

    # just take all articles on a sunday as being in the observer
    # (article itself should be able to tell us, but we'd like to know
    # _before_ we download the article, so we can see if it's already in the
    # DB)
    if context['pubdate'].strftime( '%a' ).lower() == 'sun':
        context['srcorgname'] = u'observer'
    else:
        context['srcorgname'] = u'guardian'

    # ---------------------
    # Some pages to ignore:
    #----------------------

    if url in ( 'http://www.guardian.co.uk/travel/typesoftrip', 'http://www.guardian.co.uk/travel/places' ):
#        ukmedia.DBUG2( "IGNORE travel section link '%s' (%s)\n" % (context['title'], url) );
        return None

    for bad in ( 'gallery', 'audio', 'audioslideshow', 'flash', 'interactive', 'video', 'quiz', 'slideshow', 'poll', 'cartoon' ):
        s = "/%s/" % (bad)
        if s in url:
#            ukmedia.DBUG2( "IGNORE %s page '%s' (%s)\n" % ( bad, context['title'], url) );
            return None

    return context


# this fn is called after the article is added to the db.
# it looks for dupes, and keeps only the one with the highest
# srcid (which is probably the latest revsion in the guardian db)
#
# TODO: this could be made a lot more elegant by adding it to the
# transaction where the article is actually added to the db (in
# ArticleDB).
#



def ContextFromURL( url ):
    """get a context ready for scraping a single url"""
    url = TidyURL( url )

    context = {}
    context['permalink'] = url
    context['srcid'] = CalcSrcID( url )

    # not a 100% reliable test...
    if url.find( "observer.guardian.co.uk" ) == -1:
        context['srcorgname'] = u'guardian'
    else:
        context['srcorgname'] = u'observer'

    if WhichFormat( url ) == 'newformat' and not url.startswith('file:'):
        # force whole article on single page
        context['srcurl'] = url + '?page=all'
    else:
        context['srcurl'] = url

    context['lastseen'] = datetime.now()

    return context





def FindArticles():
    """ get current active articles via RSS feeds """

    feeds = FindBlogFeeds() + rssfeeds
    return ScraperUtils.FindArticlesFromRSS( feeds, u'guardian', ScrubFunc, maxerrors=10 )






if __name__ == "__main__":
    ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract )

