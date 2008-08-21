#!/usr/bin/env python2.4
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# Scraper for the guardian and observer
#
# NOTE: guardian unlimited has updated their site. They were using
# vignette storyserver, but have now written their own I think.
#
# Main RSS page doesn't seem be be updated with feeds from new sections
# (presumably it'll be rejigged once the transition is complete)
# For the new-style sections, there is usually one feed for the main
# section frontpage, and then an extra feed for each subsection. Just
# click through all the subsection frontpages and look for the RSS link.
#
# TODO:
# - Update RSS feed list - currently a mix of old and new ones. Probably
#   should scrape the list from their site, but can't do that until they
#   have a proper rss index, if they ever do...
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
# (run 2008-07-28 16:29:20)
# got 520 feeds
rssfeeds = [
    ("News", "http://www.guardian.co.uk/rss"),
    ("Environment", "http://www.guardian.co.uk/environment/rss"),
    ("Environment / Travel and transport", "http://www.guardian.co.uk/environment/travelandtransport/rss"),
    ("Environment / Ethical living", "http://www.guardian.co.uk/environment/ethicalliving/rss"),
    ("News / Politics", "http://www.guardian.co.uk/politics/rss"),
    ("News / World news", "http://www.guardian.co.uk/world/rss"),
    ("News / Main section", "http://www.guardian.co.uk/theguardian/2008/jul/28/mainsection/rss"),
    ("Environment / Waste", "http://www.guardian.co.uk/environment/waste/rss"),
    ("Environment / Conservation", "http://www.guardian.co.uk/environment/conservation/rss"),
    ("News / Technology", "http://www.guardian.co.uk/technology/rss"),
    ("Environment / Endangered species", "http://www.guardian.co.uk/environment/endangeredspecies/rss"),
    ("News / Technology / Digital music and audio", "http://www.guardian.co.uk/technology/digitalmusic/rss"),
    ("Travel", "http://www.guardian.co.uk/travel/rss"),
    ("News / Politics", "http://www.guardian.co.uk/politics/page/2007/dec/18/1/rss"),
    ("Environment / Plastic bags", "http://www.guardian.co.uk/environment/plasticbags/rss"),
    ("News / Technology / Digital video", "http://www.guardian.co.uk/technology/digitalvideo/rss"),
    ("News / Media", "http://www.guardian.co.uk/media/rss"),
    ("News / Media / Press & publishing", "http://www.guardian.co.uk/media/pressandpublishing/rss"),
    ("Travel / Hotels", "http://www.guardian.co.uk/travel/hotels/rss"),
    ("News / Technology / Intellectual property", "http://www.guardian.co.uk/technology/intellectualproperty/rss"),
    ("News / Technology / Sony", "http://www.guardian.co.uk/technology/sony/rss"),
    ("News / Science", "http://www.guardian.co.uk/science/rss"),
    ("Travel", "http://www.guardian.co.uk/travel/bookatrip/rss"),
    ("News / Technology / Gadgets", "http://www.guardian.co.uk/technology/gadgets/rss"),
    ("News / Science / Embryos and stem cells", "http://www.guardian.co.uk/science/stemcells/rss"),
    ("News / Technology / Motoring", "http://www.guardian.co.uk/technology/motoring/rss"),
    ("News / Politics", "http://www.guardian.co.uk/politics/comment/rss"),
    ("Environment / Recycling", "http://www.guardian.co.uk/environment/recycling/rss"),
    ("Sport", "http://www.guardian.co.uk/sport/rss"),
    ("Environment / Climate change", "http://www.guardian.co.uk/environment/climatechange/rss"),
    ("Business", "http://www.guardian.co.uk/business/rss"),
    ("Travel", "http://www.guardian.co.uk/travel/lateoffers/rss"),
    ("News / Media / Television", "http://www.guardian.co.uk/media/television/rss"),
    ("News / Technology / Engineering", "http://www.guardian.co.uk/technology/engineering/rss"),
    ("News / UK news", "http://www.guardian.co.uk/uk/rss"),
    ("Environment / Whaling", "http://www.guardian.co.uk/environment/whaling/rss"),
    ("News / World news / China", "http://www.guardian.co.uk/world/china/rss"),
    ("News / World news / Israel and the Palestinian territories", "http://www.guardian.co.uk/world/israelandthepalestinians/rss"),
    ("News / Education", "http://www.guardian.co.uk/education/rss"),
    ("Environment / Tread lightly", "http://www.guardian.co.uk/environment/pledges/rss"),
    ("Environment / Endangered habitats", "http://www.guardian.co.uk/environment/endangeredhabitats/rss"),
    ("Business / David Gow on Europe", "http://www.guardian.co.uk/business/series/davidgowoneurope/rss"),
    ("Sport / Olympic Games 2008", "http://www.guardian.co.uk/sport/olympicgames2008/rss"),
    ("News / World news / China", "http://www.guardian.co.uk/world/china+japan/rss"),
    ("News / Technology / Social networking", "http://www.guardian.co.uk/technology/socialnetworking/rss"),
    ("Travel / Short breaks", "http://www.guardian.co.uk/travel/shortbreaks/rss"),
    ("Environment / Wildlife", "http://www.guardian.co.uk/environment/wildlife/rss"),
    ("Sport / US sport", "http://www.guardian.co.uk/sport/ussport/rss"),
    ("News / World news / Fair trade", "http://www.guardian.co.uk/world/fairtrade/rss"),
    ("Business / Viewpoint column", "http://www.guardian.co.uk/business/series/viewpointcolumn/rss"),
    ("Environment / Desertification", "http://www.guardian.co.uk/environment/desertification/rss"),
    ("Environment / Water", "http://www.guardian.co.uk/environment/water/rss"),
    ("Sport / Motor sport", "http://www.guardian.co.uk/sport/motorsports/rss"),
    ("News / Education / School tables", "http://www.guardian.co.uk/education/schooltables/rss"),
    ("Business / Economics", "http://www.guardian.co.uk/business/economics/rss"),
    ("Sport / Tour de France", "http://www.guardian.co.uk/sport/tourdefrance/rss"),
    ("News / World news / Egypt", "http://www.guardian.co.uk/world/egypt/rss"),
    ("News / Science / Medical research", "http://www.guardian.co.uk/science/medicalresearch/rss"),
    ("News / Education / GCSEs", "http://www.guardian.co.uk/education/gcses/rss"),
    ("News / Technology / MySpace", "http://www.guardian.co.uk/technology/myspace/rss"),
    ("News / Technology / Facebook", "http://www.guardian.co.uk/technology/facebook/rss"),
    ("News / Media / Marketing & PR", "http://www.guardian.co.uk/media/marketingandpr/rss"),
    ("News / Education / A-levels", "http://www.guardian.co.uk/education/alevels/rss"),
    ("Sport / Football", "http://www.guardian.co.uk/football/rss"),
    ("News / Politics", "http://www.guardian.co.uk/politics/page/2007/dec/17/1/rss"),
    ("Environment / Carbon emissions", "http://www.guardian.co.uk/environment/carbonemissions/rss"),
    ("News / Science / Controversies in science", "http://www.guardian.co.uk/science/controversiesinscience/rss"),
    ("Environment / Ethical living", "http://www.guardian.co.uk/environment/page/2007/jun/25/1/rss"),
    ("News / Science", "http://www.guardian.co.uk/science/page/sciencecourse/rss"),
    ("News / World news / China", "http://www.guardian.co.uk/world/china+content/audio/rss"),
    ("News / Politics / Glasgow East byelection", "http://www.guardian.co.uk/politics/glasgoweast/rss"),
    ("News / Media / Advertising", "http://www.guardian.co.uk/media/advertising/rss"),
    ("Travel / Top 10 city guides", "http://www.guardian.co.uk/travel/series/top10cityguides/rss"),
    ("Environment / Forests", "http://www.guardian.co.uk/environment/forests/rss"),
    ("News / World news / China", "http://www.guardian.co.uk/world/china+korea/rss"),
    ("News / World news / Iraq", "http://www.guardian.co.uk/world/iraq/rss"),
    ("News / Media / Radio", "http://www.guardian.co.uk/media/radio/rss"),
    ("News", "http://www.guardian.co.uk/america/rss"),
    ("Business / US economy", "http://www.guardian.co.uk/business/useconomy/rss"),
    ("News / Technology / Tech Weekly", "http://www.guardian.co.uk/technology/series/techweekly/rss"),
    ("Environment / Wave, tidal and hydropower", "http://www.guardian.co.uk/environment/waveandtidalpower/rss"),
    ("Sport / Horse racing", "http://www.guardian.co.uk/sport/horseracing/rss"),
    ("News / Technology / iPod", "http://www.guardian.co.uk/technology/ipod/rss"),
    ("News / World news / Lebanon", "http://www.guardian.co.uk/world/lebanon/rss"),
    ("Environment / Fishing", "http://www.guardian.co.uk/environment/fishing/rss"),
    ("Environment / Flooding", "http://www.guardian.co.uk/environment/flooding/rss"),
    ("News / World news / Syria", "http://www.guardian.co.uk/world/syria/rss"),
    ("Money", "http://www.guardian.co.uk/money/rss"),
    ("News / Society", "http://www.guardian.co.uk/society/rss"),
    ("Business / Credit crunch", "http://www.guardian.co.uk/business/creditcrunch/rss"),
    ("Money / Savings", "http://www.guardian.co.uk/money/savings/rss"),
    ("News / Science / Particle physics", "http://www.guardian.co.uk/science/particlephysics/rss"),
    ("Sport / A1GP", "http://www.guardian.co.uk/sport/a1gp/rss"),
    ("News / Technology / Internet", "http://www.guardian.co.uk/technology/internet/rss"),
    ("Money / Property", "http://www.guardian.co.uk/money/property/rss"),
    ("Environment / Pollution", "http://www.guardian.co.uk/environment/pollution/rss"),
    ("News / World news / Afghanistan", "http://www.guardian.co.uk/world/afghanistan/rss"),
    ("Environment / Food", "http://www.guardian.co.uk/environment/food/rss"),
    ("News / Science / Genetics", "http://www.guardian.co.uk/science/genetics/rss"),
    ("Business", "http://www.guardian.co.uk/business/markets/rss"),
    ("News / Media / Web 2.0", "http://www.guardian.co.uk/media/web20/rss"),
    ("Sport / Rugby union", "http://www.guardian.co.uk/sport/rugbyunion/rss"),
    ("News / Science / Science Weekly", "http://www.guardian.co.uk/science/series/science/rss"),
    ("News / Technology / Robots", "http://www.guardian.co.uk/technology/robots/rss"),
    ("Culture", "http://www.guardian.co.uk/culture/rss"),
    ("News / Technology / Computing", "http://www.guardian.co.uk/technology/computing/rss"),
    ("Environment / Solar power", "http://www.guardian.co.uk/environment/solarpower/rss"),
    ("Environment / Wind power", "http://www.guardian.co.uk/environment/windpower/rss"),
    ("News / World news / China", "http://www.guardian.co.uk/world/china+tibet/rss"),
    ("News / Society / Health", "http://www.guardian.co.uk/society/health/rss"),
    ("News / Science / Climate change", "http://www.guardian.co.uk/science/scienceofclimatechange/rss"),
    ("Sport / Superbikes", "http://www.guardian.co.uk/sport/superbikes/rss"),
    ("Environment / Energy", "http://www.guardian.co.uk/environment/energy/rss"),
    ("Sport / Rugby league", "http://www.guardian.co.uk/sport/rugbyleague/rss"),
    ("Environment / Organics", "http://www.guardian.co.uk/environment/organics/rss"),
    ("Business / Private equity", "http://www.guardian.co.uk/business/privateequity/rss"),
    ("News / Science / Chemistry", "http://www.guardian.co.uk/science/chemistry/rss"),
    ("News / World news / Burma", "http://www.guardian.co.uk/world/burma/rss"),
    ("Travel / Rail travel", "http://www.guardian.co.uk/travel/railtravel/rss"),
    ("News / Science / Physics", "http://www.guardian.co.uk/science/physics/rss"),
    ("Sport / England rugby league team", "http://www.guardian.co.uk/sport/englandrugbyleagueteam/rss"),
    ("Travel / Restaurants", "http://www.guardian.co.uk/travel/restaurants/rss"),
    ("Sport / GP2", "http://www.guardian.co.uk/sport/gp2/rss"),
    ("News / Technology / Games", "http://www.guardian.co.uk/technology/games/rss"),
    ("News / Politics", "http://www.guardian.co.uk/politics/all/rss"),
    ("Business / US housing and sub-prime crisis", "http://www.guardian.co.uk/business/subprimecrisis/rss"),
    ("Money / Interest rates", "http://www.guardian.co.uk/money/interestrates/rss"),
    ("News / Media / Digital media", "http://www.guardian.co.uk/media/digitalmedia/rss"),
    ("Money / Snooping around", "http://www.guardian.co.uk/money/series/snoopingaround/rss"),
    ("Sport / Golf", "http://www.guardian.co.uk/sport/golf/rss"),
    ("News / Society / NHS", "http://www.guardian.co.uk/society/nhs/rss"),
    ("News / World news / Iraq", "http://www.guardian.co.uk/world/iraq+content/video/rss"),
    ("Sport / Football / Rumour Mill", "http://www.guardian.co.uk/football/series/rumourmill/rss"),
    ("Comment is free", "http://www.guardian.co.uk/commentisfree/rss"),
    ("News / Technology / Mobile phones", "http://www.guardian.co.uk/technology/mobilephones/rss"),
    ("Life & style", "http://www.guardian.co.uk/lifeandstyle/rss"),
    ("News / World news / Zimbabwe", "http://www.guardian.co.uk/world/zimbabwe/rss"),
    ("News / Science / Cern", "http://www.guardian.co.uk/science/cern/rss"),
    ("News / Science / Evolution", "http://www.guardian.co.uk/science/evolution/rss"),
    ("News / Technology / Wi-Fi", "http://www.guardian.co.uk/technology/wifi/rss"),
    ("Environment / Ask Leo", "http://www.guardian.co.uk/environment/series/askleo/rss"),
    ("Environment / Carbon footprints", "http://www.guardian.co.uk/environment/carbonfootprints/rss"),
    ("Sport / Heineken Cup", "http://www.guardian.co.uk/sport/heinekencup/rss"),
    ("News / World news / United Nations", "http://www.guardian.co.uk/world/unitednations/rss"),
    ("News / Technology / Apple", "http://www.guardian.co.uk/technology/apple/rss"),
    ("Travel / United Kingdom", "http://www.guardian.co.uk/travel/uk+hotels/rss"),
    ("News / World news / China", "http://www.guardian.co.uk/world/china+content/video/rss"),
    ("Environment / Alternative energy", "http://www.guardian.co.uk/environment/alternativeenergy/rss"),
    ("Travel / United States", "http://www.guardian.co.uk/travel/usa+hotels/rss"),
    ("Money / Buying to let", "http://www.guardian.co.uk/money/buyingtolet/rss"),
    ("Comment is free / Environment", "http://www.guardian.co.uk/commentisfree/green/rss"),
    ("News / Technology / Bebo", "http://www.guardian.co.uk/technology/bebo/rss"),
    ("News / World news / John D McHugh's diary", "http://www.guardian.co.uk/world/series/johndmchughdiary/rss"),
    ("News / Media / Media business", "http://www.guardian.co.uk/media/mediabusiness/rss"),
    ("Culture / Stage", "http://www.guardian.co.uk/stage/rss"),
    ("Environment / Glaciers", "http://www.guardian.co.uk/environment/glaciers/rss"),
    ("Environment / Practical advice", "http://www.guardian.co.uk/environment/practicaladvice/rss"),
    ("News / World news / Saudi Arabia", "http://www.guardian.co.uk/world/saudiarabia/rss"),
    ("Sport / Formula one", "http://www.guardian.co.uk/sport/formulaone/rss"),
    ("News / World news / India", "http://www.guardian.co.uk/world/india/rss"),
    ("Culture / Stage / Comedy", "http://www.guardian.co.uk/stage/comedy/rss"),
    ("News / Science / Darwin", "http://www.guardian.co.uk/science/darwinbicentenary/rss"),
    ("News / World news / US elections 2008", "http://www.guardian.co.uk/world/uselections2008/rss"),
    ("Sport / MotoGP", "http://www.guardian.co.uk/sport/motogp/rss"),
    ("Culture / Film", "http://www.guardian.co.uk/film/rss"),
    ("Environment / Fossil fuels", "http://www.guardian.co.uk/environment/fossilfuels/rss"),
    ("Business / European banks", "http://www.guardian.co.uk/business/europeanbanks/rss"),
    ("News / Technology / Amazon.com", "http://www.guardian.co.uk/technology/amazon/rss"),
    ("News / Education / Sats", "http://www.guardian.co.uk/education/sats/rss"),
    ("News / Science / Energy", "http://www.guardian.co.uk/science/energy/rss"),
    ("News / World news / Bird flu", "http://www.guardian.co.uk/world/birdflu/rss"),
    ("Sport / Super League", "http://www.guardian.co.uk/sport/superleague/rss"),
    ("News / Society / Communities", "http://www.guardian.co.uk/society/communities/rss"),
    ("Comment is free / Radio Cif", "http://www.guardian.co.uk/commentisfree/radiocif/rss"),
    ("News / Education / Higher education", "http://www.guardian.co.uk/education/highereducation/rss"),
    ("News / Politics / The Backbencher", "http://www.guardian.co.uk/politics/thebackbencher/rss"),
    ("News / Technology / E-commerce", "http://www.guardian.co.uk/technology/efinance/rss"),
    ("Sport / Cricket", "http://www.guardian.co.uk/sport/cricket/rss"),
    ("Culture / Stage / Dance", "http://www.guardian.co.uk/stage/dance/rss"),
    ("Culture / Stage / Dance", "http://www.guardian.co.uk/stage/dance+content/gallery/rss"),
    ("Comment is free / World news", "http://www.guardian.co.uk/commentisfree/world/rss"),
    ("News / Science / Infectious diseases", "http://www.guardian.co.uk/science/infectiousdiseases/rss"),
    ("News / Education / Tefl", "http://www.guardian.co.uk/education/tefl/rss"),
    ("News / Technology / YouTube", "http://www.guardian.co.uk/technology/youtube/rss"),
    ("News / Science / Cancer", "http://www.guardian.co.uk/science/cancer/rss"),
    ("News / Technology / Computer security", "http://www.guardian.co.uk/technology/security/rss"),
    ("Comment is free / Zimbabwe", "http://www.guardian.co.uk/commentisfree+world/zimbabwe/rss"),
    ("News / Technology / Artificial intelligence (AI)", "http://www.guardian.co.uk/technology/artificialintelligenceai/rss"),
    ("Money / Isas", "http://www.guardian.co.uk/money/isas/rss"),
    ("News / World news / Zimbabwe", "http://www.guardian.co.uk/world/zimbabwe+content/gallery/rss"),
    ("Culture / Film", "http://www.guardian.co.uk/film/film+content/quiz/rss"),
    ("News / Education / School admissions", "http://www.guardian.co.uk/education/schooladmissions/rss"),
    ("Business / Andrew Clark on America", "http://www.guardian.co.uk/business/series/andrewclarkonamerica/rss"),
    ("News / Technology / Research and development", "http://www.guardian.co.uk/technology/research/rss"),
    ("Environment / Carbon offset projects", "http://www.guardian.co.uk/environment/carbonoffsetprojects/rss"),
    ("News / Society / Children", "http://www.guardian.co.uk/society/children/rss"),
    ("News / Society / Long-term care", "http://www.guardian.co.uk/society/longtermcare/rss"),
    ("News / Education / 14 - 19 education", "http://www.guardian.co.uk/education/1419education/rss"),
    ("News / Science / Drugs", "http://www.guardian.co.uk/science/drugs/rss"),
    ("News / World news / Pakistan", "http://www.guardian.co.uk/world/pakistan/rss"),
    ("News / Technology / Second Life", "http://www.guardian.co.uk/technology/secondlife/rss"),
    ("News / Technology / Microsoft", "http://www.guardian.co.uk/technology/microsoft/rss"),
    ("News / World news / Turkey", "http://www.guardian.co.uk/world/turkey/rss"),
    ("Money / Child trust funds", "http://www.guardian.co.uk/money/childtrustfunds/rss"),
    ("Environment / Drought", "http://www.guardian.co.uk/environment/drought/rss"),
    ("News / Science / Agriculture", "http://www.guardian.co.uk/science/agriculture/rss"),
    ("Culture / Film", "http://www.guardian.co.uk/film/film+tone/reviews/rss"),
    ("News / Comment & debate", "http://www.guardian.co.uk/theguardian/mainsection/commentanddebate/rss"),
    ("Sport / Football", "http://www.guardian.co.uk/football/clubs/rss"),
    ("Sport / Speedway", "http://www.guardian.co.uk/sport/speedway/rss"),
    ("Business / Interest rates", "http://www.guardian.co.uk/business/interestrates/rss"),
    ("News / Politics", "http://www.guardian.co.uk/politics/page/2008/jan/03/1/rss"),
    ("News / World news / China", "http://www.guardian.co.uk/world/china+usa/rss"),
    ("Sport / Lewis Hamilton", "http://www.guardian.co.uk/sport/lewishamilton/rss"),
    ("Money / Banks and building societies", "http://www.guardian.co.uk/money/banks/rss"),
    ("Travel / United Kingdom", "http://www.guardian.co.uk/travel/uk/rss"),
    ("Culture / Stage", "http://www.guardian.co.uk/stage/stage+tone/reviews/rss"),
    ("News / World news / Kurds", "http://www.guardian.co.uk/world/kurds/rss"),
    ("News / World news / China", "http://www.guardian.co.uk/world/china+content/gallery/rss"),
    ("Life & style / Family & relationships", "http://www.guardian.co.uk/lifeandstyle/familyandrelationships/rss"),
    ("Culture / Film", "http://www.guardian.co.uk/film/film+content/audio/rss"),
    ("News / World news / Iran", "http://www.guardian.co.uk/world/iran/rss"),
    ("Business / Credit crunch", "http://www.guardian.co.uk/business/creditcrunch+content/audio/rss"),
    ("News / World news / United States", "http://www.guardian.co.uk/world/usa/rss"),
    ("News / World news / Hillary Clinton", "http://www.guardian.co.uk/world/hillaryclinton/rss"),
    ("News / World news / Benazir Bhutto", "http://www.guardian.co.uk/world/benazirbhutto/rss"),
    ("News / Society / Mental health", "http://www.guardian.co.uk/society/mentalhealth/rss"),
    ("Life & style / Gardens", "http://www.guardian.co.uk/lifeandstyle/gardens/rss"),
    ("Sport / Tiger Woods", "http://www.guardian.co.uk/sport/tigerwoods/rss"),
    ("News / Society / MRSA and superbugs", "http://www.guardian.co.uk/society/mrsa/rss"),
    ("UNKNOWN", "http://www.guardian.co.uk/profile/michaeltomasky/rss"),
    ("Environment / GM crops", "http://www.guardian.co.uk/environment/gmcrops/rss"),
    ("Culture / Film", "http://www.guardian.co.uk/film/film+tone/features/rss"),
    ("Money / Consumer affairs", "http://www.guardian.co.uk/money/consumeraffairs/rss"),
    ("Business / Europe", "http://www.guardian.co.uk/business/europe/rss"),
    ("News / Technology / Internet censorship", "http://www.guardian.co.uk/technology/censorship/rss"),
    ("News / World news / US elections 2008", "http://www.guardian.co.uk/world/uselections2008+content/video/rss"),
    ("News / Education / Further education", "http://www.guardian.co.uk/education/furthereducation/rss"),
    ("Money / Investments", "http://www.guardian.co.uk/money/moneyinvestments/rss"),
    ("Life & style / Private lives", "http://www.guardian.co.uk/lifeandstyle/series/privatelives/rss"),
    ("Comment is free / Flooding", "http://www.guardian.co.uk/commentisfree+environment/flooding/rss"),
    ("Comment is free / United States", "http://www.guardian.co.uk/commentisfree/america/rss"),
    ("News / Politics / Politics and Iraq", "http://www.guardian.co.uk/politics/iraq/rss"),
    ("Life & style / Dear Mariella", "http://www.guardian.co.uk/lifeandstyle/series/dearmariella/rss"),
    ("Sport / County Championship Division One", "http://www.guardian.co.uk/sport/countychampionship1stdivisioncricket/rss"),
    ("Money / Mortgages", "http://www.guardian.co.uk/money/mortgages/rss"),
    ("Culture / Film", "http://www.guardian.co.uk/film/film+tone/news/rss"),
    ("News / Technology / Hacking", "http://www.guardian.co.uk/technology/hacking/rss"),
    ("Money / Work & careers", "http://www.guardian.co.uk/money/workandcareers/rss"),
    ("News / Politics / Opinion polls", "http://www.guardian.co.uk/politics/polls/rss"),
    ("Culture / Edinburgh festival", "http://www.guardian.co.uk/culture/edinburghfestival/rss"),
    ("Life & style / Fitness", "http://www.guardian.co.uk/lifeandstyle/fitness/rss"),
    ("News / Society / Public sector careers", "http://www.guardian.co.uk/society/publicsectorcareers/rss"),
    ("Money / House prices", "http://www.guardian.co.uk/money/houseprices/rss"),
    ("Environment / Renewable energy", "http://www.guardian.co.uk/environment/renewableenergy/rss"),
    ("News / World news / Afghanistan timeline", "http://www.guardian.co.uk/world/afghanistantimeline/rss"),
    ("Money / Work-life balance", "http://www.guardian.co.uk/money/worklifebalance/rss"),
    ("News / Society / Drugs and alcohol", "http://www.guardian.co.uk/society/drugsandalcohol/rss"),
    ("Life & style / Food & drink", "http://www.guardian.co.uk/lifeandstyle/foodanddrink/rss"),
    ("News / Society / Voluntary sector", "http://www.guardian.co.uk/society/voluntarysector/rss"),
    ("Sport / Indian Premier League", "http://www.guardian.co.uk/sport/indianpremierleague/rss"),
    ("News / Science / Astronomy", "http://www.guardian.co.uk/science/astronomy/rss"),
    ("News / Education / New schools", "http://www.guardian.co.uk/education/newschools/rss"),
    ("Environment / Flooding", "http://www.guardian.co.uk/environment/flooding+content/gallery/rss"),
    ("News / Education / Students", "http://www.guardian.co.uk/education/students/rss"),
    ("News / World news / Al-Qaida", "http://www.guardian.co.uk/world/alqaida/rss"),
    ("News / World news / Osama bin Laden", "http://www.guardian.co.uk/world/osamabinladen/rss"),
    ("News / World news / Iran", "http://www.guardian.co.uk/world/iran+content/video/rss"),
    ("Culture / Film", "http://www.guardian.co.uk/film/film+content/video/rss"),
    ("Money / With-profits funds", "http://www.guardian.co.uk/money/withprofitsfunds/rss"),
    ("Culture / Television", "http://www.guardian.co.uk/culture/television/rss"),
    ("Culture / Stage / Comedy", "http://www.guardian.co.uk/stage/comedy+tone/reviews/rss"),
    ("News / Technology / Dell", "http://www.guardian.co.uk/technology/dell/rss"),
    ("Life & style / Health & wellbeing", "http://www.guardian.co.uk/lifeandstyle/healthandwellbeing/rss"),
    ("UNKNOWN", "http://www.guardian.co.uk/profile/annapickard/rss"),
    ("Culture / Music / Classical music and opera", "http://www.guardian.co.uk/music/classicalmusicandopera/rss"),
    ("Money / Shares", "http://www.guardian.co.uk/money/shares/rss"),
    ("Culture / Stage", "http://www.guardian.co.uk/stage/stage+tone/news/rss"),
    ("Money / Ask the experts: Homebuying", "http://www.guardian.co.uk/money/series/expertsproperty/rss"),
    ("Life & style / Sexual healing", "http://www.guardian.co.uk/lifeandstyle/series/sexualhealing/rss"),
    ("News / Society / Social exclusion", "http://www.guardian.co.uk/society/socialexclusion/rss"),
    ("Life & style / Celebrity", "http://www.guardian.co.uk/lifeandstyle/celebrity/rss"),
    ("News / World news / Tibet", "http://www.guardian.co.uk/world/tibet/rss"),
    ("Life & style / My body and soul", "http://www.guardian.co.uk/lifeandstyle/series/mybodyandsoul/rss"),
    ("Sport / Football / Champions League", "http://www.guardian.co.uk/football/championsleague/rss"),
    ("Culture / Art and design", "http://www.guardian.co.uk/artanddesign/rss"),
    ("Comment is free / Religion", "http://www.guardian.co.uk/commentisfree/religion/rss"),
    ("News / World news / CIA rendition", "http://www.guardian.co.uk/world/ciarendition/rss"),
    ("News / World news / Sri Lanka", "http://www.guardian.co.uk/world/srilanka/rss"),
    ("Environment / Activists", "http://www.guardian.co.uk/environment/activists/rss"),
    ("News / World news / Barack Obama", "http://www.guardian.co.uk/world/barackobama/rss"),
    ("News / Education / Student health", "http://www.guardian.co.uk/education/studenthealth/rss"),
    ("Culture / Stage / Theatre", "http://www.guardian.co.uk/stage/theatre/rss"),
    ("Sport / Rallying", "http://www.guardian.co.uk/sport/rallying/rss"),
    ("News / Education / Tuition fees", "http://www.guardian.co.uk/education/tuitionfees/rss"),
    ("News / Society / Local government", "http://www.guardian.co.uk/society/localgovernment/rss"),
    ("Money / Household bills", "http://www.guardian.co.uk/money/householdbills/rss"),
    ("News / Education / International education news", "http://www.guardian.co.uk/education/internationaleducationnews/rss"),
    ("News / Work", "http://www.guardian.co.uk/theguardian/2008/jul/26/work/rss"),
    ("News / Society / International aid and development", "http://www.guardian.co.uk/society/internationalaidanddevelopment/rss"),
    ("Money / Borrowing & debt", "http://www.guardian.co.uk/money/debt/rss"),
    ("News / Education / Faith schools", "http://www.guardian.co.uk/education/faithschools/rss"),
    ("News / Society / Learning disability", "http://www.guardian.co.uk/society/learningdisability/rss"),
    ("News / World news / Tibet", "http://www.guardian.co.uk/world/tibet+content/audio/rss"),
    ("News / World news / Guantanamo Bay", "http://www.guardian.co.uk/world/guantanamo/rss"),
    ("News / Technology / Nintendo", "http://www.guardian.co.uk/technology/nintendo/rss"),
    ("Culture / Art and design / Architecture", "http://www.guardian.co.uk/artanddesign/architecture/rss"),
    ("News / Society / Equality", "http://www.guardian.co.uk/society/equality/rss"),
    ("Life & style / Shopping", "http://www.guardian.co.uk/lifeandstyle/shopping/rss"),
    ("Life & style / Women", "http://www.guardian.co.uk/lifeandstyle/women/rss"),
    ("Culture / Art and design / Architecture", "http://www.guardian.co.uk/artanddesign/architecture+content/gallery/rss"),
    ("Life & style / Clippings", "http://www.guardian.co.uk/lifeandstyle/series/clippings/rss"),
    ("News / World news / Barack Obama", "http://www.guardian.co.uk/world/barackobama+content/video/rss"),
    ("Life & style / Stumped?", "http://www.guardian.co.uk/lifeandstyle/series/stumped/rss"),
    ("News / Education / Access to university", "http://www.guardian.co.uk/education/accesstouniversity/rss"),
    ("Life & style / Observer Food Monthly", "http://www.guardian.co.uk/theobserver/2008/jul/20/foodmonthly/rss"),
    ("News / Society / Disability", "http://www.guardian.co.uk/society/disability/rss"),
    ("News / World news / Aids and HIV", "http://www.guardian.co.uk/world/aids/rss"),
    ("Culture / Books", "http://www.guardian.co.uk/books/rss"),
    ("News / World news / September 11 2001", "http://www.guardian.co.uk/world/september11/rss"),
    ("Life & style / Fashion", "http://www.guardian.co.uk/lifeandstyle/fashion/rss"),
    ("News / Education / Schools", "http://www.guardian.co.uk/education/schools/rss"),
    ("News / Education / University funding", "http://www.guardian.co.uk/education/universityfunding/rss"),
    ("Culture / Art and design / Design", "http://www.guardian.co.uk/artanddesign/design/rss"),
    ("Life & style / The reluctant dieter", "http://www.guardian.co.uk/lifeandstyle/series/thereluctantdieter/rss"),
    ("Sport / England rugby union team", "http://www.guardian.co.uk/sport/englandrugbyteam/rss"),
    ("Environment / Polar regions", "http://www.guardian.co.uk/environment/poles/rss"),
    ("Environment / Flooding", "http://www.guardian.co.uk/environment/flooding+content/audio/rss"),
    ("News / Science / Universe", "http://www.guardian.co.uk/science/universe/rss"),
    ("News / Education / Student housing", "http://www.guardian.co.uk/education/studenthousing/rss"),
    ("Environment / Energy efficiency", "http://www.guardian.co.uk/environment/energyefficiency/rss"),
    ("News / Society / Housing", "http://www.guardian.co.uk/society/housing/rss"),
    ("News / World news / Hillary Clinton", "http://www.guardian.co.uk/world/hillaryclinton+content/video/rss"),
    ("Sport / England Cricket Team", "http://www.guardian.co.uk/sport/englandcricketteam/rss"),
    ("News / World news / John McCain", "http://www.guardian.co.uk/world/johnmccain/rss"),
    ("Sport / Touring cars", "http://www.guardian.co.uk/sport/touringcars/rss"),
    ("News / Society / Youth justice", "http://www.guardian.co.uk/society/youthjustice/rss"),
    ("News / Society / Volunteering", "http://www.guardian.co.uk/society/volunteering/rss"),
    ("Money / Insurance", "http://www.guardian.co.uk/money/insurance/rss"),
    ("News / Technology / Software", "http://www.guardian.co.uk/technology/software/rss"),
    ("Sport / Guinness Premiership", "http://www.guardian.co.uk/sport/premiership/rss"),
    ("News / UK news / Military", "http://www.guardian.co.uk/uk/military/rss"),
    ("Money / Investment funds", "http://www.guardian.co.uk/money/investmentfunds/rss"),
    ("News / World news / Tibet", "http://www.guardian.co.uk/world/tibet+content/video/rss"),
    ("News / Society / Social enterprises", "http://www.guardian.co.uk/society/socialenterprises/rss"),
    ("News / Society / Young people", "http://www.guardian.co.uk/society/youngpeople/rss"),
    ("Money / Motor insurance", "http://www.guardian.co.uk/money/motorinsurance/rss"),
    ("News / World news / Six months in Afghanistan", "http://www.guardian.co.uk/world/sixmonthsinafghanistan/rss"),
    ("News / World news / Zimbabwe", "http://www.guardian.co.uk/world/zimbabwe+content/audio/rss"),
    ("Money / First-time buyers", "http://www.guardian.co.uk/money/firsttimebuyers/rss"),
    ("Money / Discrimination at work", "http://www.guardian.co.uk/money/discriminationatwork/rss"),
    ("Business / Market turmoil", "http://www.guardian.co.uk/business/marketturmoil/rss"),
    ("Environment / Biofuels", "http://www.guardian.co.uk/environment/biofuels/rss"),
    ("Sport / Football / Fantasy Football", "http://www.guardian.co.uk/fantasyfootball/rss"),
    ("News / Technology / Web 2.0", "http://www.guardian.co.uk/technology/web20/rss"),
    ("Life & style / Homes", "http://www.guardian.co.uk/lifeandstyle/homes/rss"),
    ("News / Technology / PlayStation", "http://www.guardian.co.uk/technology/playstation/rss"),
    ("News / Science / Biodiversity", "http://www.guardian.co.uk/science/biodiversity/rss"),
    ("Sport / County Championship Division Two", "http://www.guardian.co.uk/sport/countychampionship2nddivisioncricket/rss"),
    ("News / World news / Iraq", "http://www.guardian.co.uk/world/iraq/rss"),
    ("Comment is free / Middle East", "http://www.guardian.co.uk/commentisfree/middleeast/rss"),
    ("News / Education / Public schools", "http://www.guardian.co.uk/education/publicschools/rss"),
    ("Culture / Film", "http://www.guardian.co.uk/film/film+content/gallery/rss"),
    ("News / Shortcuts", "http://www.guardian.co.uk/theguardian/series/shortcuts/rss"),
    ("News / Education / University teaching", "http://www.guardian.co.uk/education/universityteaching/rss"),
    ("Comment is free / UK news", "http://www.guardian.co.uk/commentisfree/uk/rss"),
    ("Life & style / Ethical fashion directory", "http://www.guardian.co.uk/lifeandstyle/page/ethicalfashiondirectory/rss"),
    ("News / World news / First world war", "http://www.guardian.co.uk/world/firstworldwar/rss"),
    ("Money / Ask the expert: Work", "http://www.guardian.co.uk/money/series/asktheexpertwork/rss"),
    ("News / Society / Regeneration", "http://www.guardian.co.uk/society/regeneration/rss"),
    ("News / World news / Kashmir", "http://www.guardian.co.uk/world/kashmir/rss"),
    ("Money / Maternity & paternity rights", "http://www.guardian.co.uk/money/maternitypaternityrights/rss"),
    ("Money / Internet, phones & broadband", "http://www.guardian.co.uk/money/internetphonesbroadband/rss"),
    ("Money / Ethical money", "http://www.guardian.co.uk/money/ethicalmoney/rss"),
    ("Business / Banking sector", "http://www.guardian.co.uk/business/banking/rss"),
    ("Culture / Stage / Dance", "http://www.guardian.co.uk/stage/dance+tone/reviews/rss"),
    ("Culture / Books", "http://www.guardian.co.uk/books/books+tone/features/rss"),
    ("News / World news / South Africa", "http://www.guardian.co.uk/world/southafrica/rss"),
    ("Sport / GB rugby league team", "http://www.guardian.co.uk/sport/gbrugbyleagueteam/rss"),
    ("Money / Capital letters", "http://www.guardian.co.uk/money/series/capitalletters/rss"),
    ("Life & style / Love by numbers", "http://www.guardian.co.uk/lifeandstyle/series/lovebynumbers/rss"),
    ("Culture / Music", "http://www.guardian.co.uk/music/rss"),
    ("Life & style / Catwalk", "http://www.guardian.co.uk/lifeandstyle/catwalk/rss"),
    ("Life & style / The sport trial", "http://www.guardian.co.uk/lifeandstyle/series/thesporttrial/rss"),
    ("News / Education / Ofsted", "http://www.guardian.co.uk/education/ofsted/rss"),
    ("News / Education / International students", "http://www.guardian.co.uk/education/internationalstudents/rss"),
    ("News / Society / Child protection", "http://www.guardian.co.uk/society/childprotection/rss"),
    ("Environment / Flooding", "http://www.guardian.co.uk/environment/flooding+content/video/rss"),
    ("News / World news / Zimbabwe", "http://www.guardian.co.uk/world/zimbabwe+content/video/rss"),
    ("News / Science / Fossils", "http://www.guardian.co.uk/science/fossils/rss"),
    ("Culture / Art and design", "http://www.guardian.co.uk/artanddesign/artanddesign+tone/news/rss"),
    ("Life & style / Five ways to ...", "http://www.guardian.co.uk/lifeandstyle/series/fivewaysto/rss"),
    ("Comment is free", "http://www.guardian.co.uk/commentisfree/commentisfree+world/tibet/rss"),
    ("News / World news / Cyclone Nargis", "http://www.guardian.co.uk/world/cyclonenargis/rss"),
    ("Life & style / Health question", "http://www.guardian.co.uk/lifeandstyle/series/healthquestion/rss"),
    ("Life & style / Paris fashion week", "http://www.guardian.co.uk/lifeandstyle/parisfashionweek/rss"),
    ("News / Education / University administration", "http://www.guardian.co.uk/education/administration/rss"),
    ("Environment / Nuclear power", "http://www.guardian.co.uk/environment/nuclearpower/rss"),
    ("News / Education / Research", "http://www.guardian.co.uk/education/research/rss"),
    ("Culture / Music", "http://www.guardian.co.uk/music/music+tone/albumreview/rss"),
    ("News / From the Observer", "http://observer.guardian.co.uk/rss"),
    ("Sport / Challenge Cup", "http://www.guardian.co.uk/sport/challengecup/rss"),
    ("Travel / United Kingdom", "http://www.guardian.co.uk/travel/uk+restaurants/rss"),
    ("Life & style / Space solves", "http://www.guardian.co.uk/lifeandstyle/series/spacesoles/rss"),
    ("Life & style / Milan fashion week", "http://www.guardian.co.uk/lifeandstyle/milanfashionweek/rss"),
    ("Life & style / Ethical fashion", "http://www.guardian.co.uk/lifeandstyle/ethicalfashion/rss"),
    ("Life & style / London fashion week", "http://www.guardian.co.uk/lifeandstyle/londonfashionweek/rss"),
    ("Life & style / Doctor, doctor", "http://www.guardian.co.uk/lifeandstyle/series/doctordoctor/rss"),
    ("Life & style / This Muslim life", "http://www.guardian.co.uk/lifeandstyle/series/thismuslimlife/rss"),
    ("News / Society / Social care", "http://www.guardian.co.uk/society/socialcare/rss"),
    ("News / World news / Second world war", "http://www.guardian.co.uk/world/secondworldwar/rss"),
    ("News / Society / Emergency planning", "http://www.guardian.co.uk/society/emergencyplanning/rss"),
    ("Culture / Art and design / Art", "http://www.guardian.co.uk/artanddesign/art/rss"),
    ("Life & style / Ethical food", "http://www.guardian.co.uk/lifeandstyle/ethicalfood/rss"),
    ("News / World news / Barack Obama", "http://www.guardian.co.uk/world/barackobama+content/audio/rss"),
    ("Money / Health insurance", "http://www.guardian.co.uk/money/healthinsurance/rss"),
    ("News / From the Observer / Pendennis", "http://www.guardian.co.uk/theobserver/series/pendennis/rss"),
    ("Life & style / Restaurants", "http://www.guardian.co.uk/lifeandstyle/restaurants/rss"),
    ("Culture / Music / Proms diary", "http://www.guardian.co.uk/music/series/promsdiary/rss"),
    ("Culture / Art and design / Private view podcast", "http://www.guardian.co.uk/artanddesign/series/privateview/rss"),
    ("Money / Scams and fraud", "http://www.guardian.co.uk/money/scamsandfraud/rss"),
    ("News / Education / Commonwealth universities", "http://www.guardian.co.uk/education/commonwealthuniversities/rss"),
    ("News / Politics / Women in politics", "http://www.guardian.co.uk/politics/women/rss"),
    ("Culture / Music / Classical music and opera", "http://www.guardian.co.uk/music/classicalmusicandopera+tone/albumreview/rss"),
    ("Life & style / Beauty", "http://www.guardian.co.uk/lifeandstyle/beauty/rss"),
    ("News / Education / Gap years", "http://www.guardian.co.uk/education/gapyears/rss"),
    ("Environment / Green building", "http://www.guardian.co.uk/environment/greenbuilding/rss"),
    ("UNKNOWN", "http://www.guardian.co.uk/profile/michaeltomasky/rss"),
    ("Culture / Music", "http://www.guardian.co.uk/music/music+tone/features/rss"),
    ("Money / Pay", "http://www.guardian.co.uk/money/pay/rss"),
    ("News / Education / Early years education", "http://www.guardian.co.uk/education/earlyyearseducation/rss"),
    ("News / World news / Tibet", "http://www.guardian.co.uk/world/tibet+content/gallery/rss"),
    ("Culture / Radio", "http://www.guardian.co.uk/culture/radio/rss"),
    ("News / World news / Cyclone Nargis", "http://www.guardian.co.uk/world/cyclonenargis+content/gallery/rss"),
    ("News / World news / Cyclone Nargis", "http://www.guardian.co.uk/world/cyclonenargis+tone/comment/rss"),
    ("Comment is free", "http://www.guardian.co.uk/commentisfree/commentisfree+world/barackobama/rss"),
    ("News / From the Observer / World news", "http://www.guardian.co.uk/theobserver/news/worldnews/rss"),
    ("UNKNOWN", "http://www.guardian.co.uk/profile/kiracochrane/rss"),
    ("News / Education / School funding", "http://www.guardian.co.uk/education/schoolfunding/rss"),
    ("Money / Credit cards", "http://www.guardian.co.uk/money/creditcards/rss"),
    ("News / World news / Cyclone Nargis", "http://www.guardian.co.uk/world/cyclonenargis+content/audio/rss"),
    ("Life & style / Two Wheels", "http://www.guardian.co.uk/lifeandstyle/series/twowheels/rss"),
    ("Culture / Music", "http://www.guardian.co.uk/music/music+tone/news/rss"),
    ("News / World news / John McCain", "http://www.guardian.co.uk/world/johnmccain+content/video/rss"),
    ("News / World news / Gender", "http://www.guardian.co.uk/world/gender/rss"),
    ("Culture / Books", "http://www.guardian.co.uk/books/books+tone/news/rss"),
    ("Life & style / Homes", "http://www.guardian.co.uk/lifeandstyle/homes/rss"),
    ("Culture / Music / Classical music and opera", "http://www.guardian.co.uk/music/classicalmusicandopera+tone/livemusicreview/rss"),
    ("Culture / Art and design / Photography", "http://www.guardian.co.uk/artanddesign/photography/rss"),
    ("Life & style / New York fashion week", "http://www.guardian.co.uk/lifeandstyle/newyorkfashionweek/rss"),
    ("Culture / Books / Original writing", "http://www.guardian.co.uk/books/originalwriting/rss"),
    ("Culture / Observer Review", "http://www.guardian.co.uk/theobserver/2008/jul/27/review/rss"),
    ("News / World news / Global terrorism", "http://www.guardian.co.uk/world/terrorism/rss"),
    ("Culture / Art and design / Photography", "http://www.guardian.co.uk/artanddesign/photography+content/gallery/rss"),
    ("Culture / Stage / Theatre", "http://www.guardian.co.uk/stage/theatre+content/gallery/rss"),
    ("News / World news / Global terrorism", "http://www.guardian.co.uk/world/terrorism+content/video/rss"),
    ("News / Politics / Defence policy", "http://www.guardian.co.uk/politics/defence/rss"),
    ("Business / Observer Business, Media & Cash", "http://www.guardian.co.uk/theobserver/2008/jul/27/businessandmedia/rss"),
    ("News / Education / Research Assessment Exercise", "http://www.guardian.co.uk/education/researchassessmentexerciseeducation/rss"),
    ("Life & style / What would Beth Ditto do?", "http://www.guardian.co.uk/lifeandstyle/series/whatwouldbethdittodo/rss"),
    ("Life & style / Sidelines", "http://www.guardian.co.uk/lifeandstyle/series/sidelines/rss"),
    ("Culture / Stage / Theatre", "http://www.guardian.co.uk/stage/theatre+tone/reviews/rss"),
    ("UNKNOWN", "http://www.guardian.co.uk/profile/charliebrooker/rss"),
#    ("Life & style / Pick of the week", "http://www.guardian.co.uk/lifeandhealth/series/pickoftheweek/rss"),
    ("News / Society / Prisons and probation", "http://www.guardian.co.uk/society/prisonsandprobation/rss"),
    ("Culture / Books", "http://www.guardian.co.uk/books/books+content/audio/rss"),
    ("Culture / Books", "http://www.guardian.co.uk/books/books+tone/reviews/rss"),
    ("Travel / Observer Escape", "http://www.guardian.co.uk/theobserver/2008/jul/27/escape/rss"),
    ("Culture / Art and design / Art", "http://www.guardian.co.uk/artanddesign/art+content/gallery/rss"),
    ("Comment is free / Current TV on Cif", "http://www.guardian.co.uk/commentisfree/series/currenttvoncif/rss"),
    ("Culture / Art and design / Design", "http://www.guardian.co.uk/artanddesign/design+content/gallery/rss"),
    ("Culture / Music", "http://www.guardian.co.uk/music/music+content/gallery/rss"),
    ("News / From the Observer / Comment", "http://www.guardian.co.uk/theobserver/news/comment/rss"),
    ("Life & style / Health & wellbeing", "http://www.guardian.co.uk/lifeandstyle/healthandwellbeing/rss"),
    ("Money / Travel insurance", "http://www.guardian.co.uk/money/travelinsurance/rss"),
    ("Life & style / Fashion diary", "http://www.guardian.co.uk/lifeandstyle/series/fashiondiary/rss"),
    ("Culture / Music / Music Weekly", "http://www.guardian.co.uk/music/series/musicweekly/rss"),
    ("News / World news / Natural disasters", "http://www.guardian.co.uk/world/naturaldisasters/rss"),
    ("News / Society / Public services policy", "http://www.guardian.co.uk/society/policy/rss"),
    ("News / Society / Climbie inquiry", "http://www.guardian.co.uk/society/climbie/rss"),
    ("News / World news / Cyclone Nargis", "http://www.guardian.co.uk/world/cyclonenargis+content/video/rss"),
    ("Life & style / Ask Hadley", "http://www.guardian.co.uk/lifeandstyle/series/askhadley/rss"),
    ("Life & style / Emma Cook on beauty", "http://www.guardian.co.uk/lifeandstyle/series/emmacookonbeauty/rss"),
    ("Life & style / My space", "http://www.guardian.co.uk/lifeandstyle/series/myspace/rss"),
    ("Money / Cash", "http://www.guardian.co.uk/theobserver/businessandmedia/cash/rss"),
    ("Money / Life insurance", "http://www.guardian.co.uk/money/lifeinsurance/rss"),
    ("Life & style", "http://www.guardian.co.uk/lifeandstyle/rss"),
    ("Money / Let's move to ...", "http://www.guardian.co.uk/money/series/letsmoveto/rss"),
    ("Money / Home insurance", "http://www.guardian.co.uk/money/homeinsurance/rss"),
    ("Culture / Music", "http://www.guardian.co.uk/music/music+culture/festivals/rss"),
    ("Comment is free / Radio Cif", "http://www.guardian.co.uk/commentisfree/radiocif/rss"),
    ("Sport / Observer Sport Monthly", "http://www.guardian.co.uk/theobserver/2008/jul/27/sportmonthly/rss"),
    ("News / From the Observer / News", "http://www.guardian.co.uk/theobserver/news/uknews/rss"),
    ("Culture / Music", "http://www.guardian.co.uk/music/music+tone/livemusicreview/rss"),
    ("News / UK news / UK security and terrorism", "http://www.guardian.co.uk/uk/uksecurity/rss"),
    ("Sport / Observer Sport", "http://www.guardian.co.uk/theobserver/2008/jul/27/sport/rss"),
    ("Life & style / Haute couture", "http://www.guardian.co.uk/lifeandstyle/hautecoutureshows/rss"),
    ("Culture / Art and design / My best shot", "http://www.guardian.co.uk/artanddesign/series/mybestshot/rss"),
    ("Life & style / Observer Magazine", "http://www.guardian.co.uk/theobserver/2008/jul/27/magazine/rss"),
    ("News / Education / The business of research", "http://www.guardian.co.uk/education/businessofresearch/rss"),
    ("Life & style / Food & drink", "http://www.guardian.co.uk/lifeandstyle/foodanddrink/rss"),
    ("News / From the Observer / Beauty Queen", "http://www.guardian.co.uk/theobserver/series/beautyqueen/rss"),
    ("Life & style / Best beauty buys", "http://www.guardian.co.uk/lifeandstyle/series/bestbeautybuys/rss"),
    ("News / Politics / Terrorism policy", "http://www.guardian.co.uk/politics/terrorism/rss"),
    ("Life & style / Ethical beauty", "http://www.guardian.co.uk/lifeandstyle/ethicalbeauty/rss"),
    ("News / From the Observer / Main section", "http://www.guardian.co.uk/theobserver/2008/jul/27/news/rss"),
    ("Life & style / Fashion", "http://www.guardian.co.uk/lifeandstyle/fashion/rss"),
    ("News / UK news / Crime", "http://www.guardian.co.uk/uk/ukcrime/rss"),
    ("Culture / Art and design / Sebastiao Salgado: Genesis", "http://www.guardian.co.uk/artanddesign/series/sebastiaosalgadogenesis/rss"),
    ("News / World news / International crime", "http://www.guardian.co.uk/world/internationalcrime/rss"),
    ("News / UK news / UK gun violence", "http://www.guardian.co.uk/uk/ukguns/rss"),
    ("News / UK news / Knife crime", "http://www.guardian.co.uk/uk/knifecrime/rss"),
    ("News / UK news / Jean Charles de Menezes", "http://www.guardian.co.uk/uk/menezes/rss"),
    ("News / UK news / July 7 London attacks", "http://www.guardian.co.uk/uk/july7/rss"),
    ("News / Politics / Police", "http://www.guardian.co.uk/politics/police/rss"),
    ("News / World news / Brazil", "http://www.guardian.co.uk/world/brazil/rss"),
    ("News / UK news / July 7: one year on", "http://www.guardian.co.uk/uk/july72006/rss"),
    ("News / World news / Bolivia", "http://www.guardian.co.uk/world/bolivia/rss"),
    ("News / World news / Venezuela", "http://www.guardian.co.uk/world/venezuela/rss"),
    ("News / World news / Colombia", "http://www.guardian.co.uk/world/colombia/rss"),
]




def FindBlogFeeds():
    feeds = []

    # overall feed
#    feeds.append( ('All guardian.co.uk blog posts', 'http://blogs.guardian.co.uk/atom.xml') )

    html = ukmedia.FetchURL( 'http://blogs.guardian.co.uk/index.html' )
    soup = BeautifulSoup( html )

    for d in soup.findAll( 'div', {'class':"bloglatest"} ):
        a = d.find( 'img', {'alt':'Webfeed'} ).parent
        url = a['href']
        title = ukmedia.FromHTML( d.h3.renderContents( None ) )
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

        # date
        pubdate = attrsdiv.find( 'li', { 'class':'date' } ).renderContents(None).strip()
        art['pubdate'] = ukmedia.ParseDateTime( pubdate )

        # guardian or observer?
        # (guardian is the catchall - we use it for web-only content too)
        publication = attrsdiv.find( 'li', { 'class':'publication' } ).a.string
        if publication == u'The Observer':
            art['srcorgname'] = u'observer'
        else:
            art['srcorgname'] = u'guardian'
    
        # now strip out all non-text bits of content div
        attrsdiv.extract()
    elif blogbylinediv:
        # might be a blog...
        raw = ukmedia.FromHTMLOneLine( blogbylinediv.renderContents(None) )

        # "Posted by Lee Glendinning Tuesday August 19 2008 1:07 pm"
        bylinepat = re.compile( ur'Posted\s+(.*?)\s+(\w+\s+\w+\s+\d{1,2}\s+\d{4}\s+\d{1,2}:\d{2}\s+\w{2})', re.UNICODE );
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

    # images
    for cruft in contentdiv.findAll( 'div', {'class':re.compile("""\\bimage\\b""") } ):
        cruft.extract()

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

    if not desc:  # long first para
        # use <meta name="description" content="XXXXX">
        meta_desc = soup.head.find('meta', {'name': 'description'})
        if meta_desc and 'content' in dict(meta_desc.attrs):
            desc = meta_desc['content']

    if not desc:
        descpara = textpart.p  # no? just use first para of text instead.
        desc = ukmedia.FromHTML( descpara.prettify(None) )

    art['description'] = ukmedia.DescapeHTML(desc)

    # that's it!
    art['content'] = ukmedia.SanitiseHTML( textpart.prettify(None) )

    if not art['description']:
        art['description'] = ukmedia.FirstPara( art['content'] );
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
    re.compile( r'.*[.]guardian[.]co[.]uk/(.*?\d{4}/.*?/\d+/.*(?![.]html))$' ),

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
    context['srcid'] = CalcSrcID( url )

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
        ukmedia.DBUG2( "IGNORE travel section link '%s' (%s)\n" % (context['title'], url) );
        return None

    for bad in ( 'gallery', 'audio', 'flash', 'interactive', 'video', 'quiz', 'slideshow', 'poll', 'cartoon' ):
        s = "/%s/" % (bad)
        if s in url:
            ukmedia.DBUG2( "IGNORE %s page '%s' (%s)\n" % ( bad, context['title'], url) );
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
def DupeCheckFunc( artid, art ):
    if WhichFormat( art['srcurl'] ) != 'storyserver':
        return

    srcorg = orgmap[ art['srcorgname'] ]
    pubdatestr = '%s' % (art['pubdate'])

    c = myconn.cursor()
    # find any articles with the same title published a day either
    # side of this one
    s = art['pubdate'] - timedelta(days=1)
    e = art['pubdate'] + timedelta(days=1)
    c.execute( "SELECT id,srcid FROM article WHERE status='a' AND "
        "srcorg=%s AND title=%s AND pubdate > %s AND pubdate < %s "
        "ORDER BY srcid DESC",
        srcorg,
        art['title'].encode('utf-8'),
        str(s), str(e) )

    rows = c.fetchall()
    if len(rows) > 1:
        # there are dupes!
        for dupe in rows[1:]:
            c.execute( "UPDATE article SET status='d' WHERE id=%s",
                dupe['id'] )
            myconn.commit()
            ukmedia.DBUG2( " hide dupe id=%s (srcid='%s')\n" % (dupe['id'],dupe['srcid']) )




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
    return ScraperUtils.FindArticlesFromRSS( feeds, u'guardian', ScrubFunc )
    #return ScraperUtils.FindArticlesFromRSS( rssfeeds, u'guardian', ScrubFunc )




# connection and orgmap used by DupeCheckFunc()
myconn = DB.Connect()

orgmap = {}
c = myconn.cursor()
c.execute( "SELECT id,shortname FROM organisation" )
while 1:
    row=c.fetchone()
    if not row:
        break
    orgmap[ row[1] ] = row[0]
c.close()
c=None



if __name__ == "__main__":
    ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract, DupeCheckFunc )


