#!/usr/bin/python

#### UNFINISHED (but probably pretty close to working).
#### I don't really trust the pockbot approach anyway.

'''
This script is a drastically cut-down version of PockBot, a wikipedia bot
by Dan Adams (User:PocklingtonDan), ported to Python.

What the script does
====================

It acts as a web spider. Given a wikipedia category, it finds all articles
listed in that category as well as all subcategories of that category.
For every subcategory it pulls a list of articles.
It then prints the article list to stdout.

Intended use
============

To grab a list of articles under a given category on wikipedia. e.g.:

   ./pockbot.py "British journalists"
'''

import sys
import re
import time
import urllib


def getArticlesInCategory(html):
    
    if '<div id="mw-pages">' not in html:
        # not a wikipedia category page
        return []
    
    if 'There are 0 pages in this section of this category' in html:
        return []

    repls = [
        r'.*<div id="mw-pages">',
        (r'</div>.*', '</div>'),
        (r'.*?<ul>', '<ul>'),
        r'<h3>.*?</h3>',
        r'<ul>',
        r'</ul>',
        r'<td>',
        r'</td>',
        r'</div>',
        r'</tr>',
        r'</table>',
        (r'</li>', '|'),
        (r'<li>', '|'),
        r'\n',
        (re.escape('||'), '|'),
        r'<a.*?>',
        r'</a>',
        r'\|$',
        r'^\|',
        (r'_', ' '),
        (r'\s\|', '\|'),
    ]
    for repl in repls:
        if isinstance(repl, basestring):
            pattern, replacement = repl, ''
        else:
            pattern, replacement = repl
        html = re.compile(pattern, flags=re.DOTALL).sub(replacement, html)
    
    return html.split('|')


def getSubCatsInCategory(html):
    
    if '<div id="mw-subcategories">' not in html:
        # not a wikipedia category page
        return []

    if 'There are 0 subcategories to this category' in html:
        return []
    
    repls = [
        r'.*<div id="mw-subcategories">',
        r'<div id="mw-pages">.*',
        r'<h3>.*?</h3>',
        r'<div.*?>',
        r'</div>',
        r'<span.*?</span>',
        r'.*?<ul>/<ul>',
        r'<ul>',
        r'</ul>',
        (r'</li>', '|'),
        (r'<li>', '|'),
        r'<a.*?>',
        r'</a>',
        r'\n',
        (re.escape('||'), '|'),
        r'<td>',
        r'</td>',
        r'</tr>',
        r'</table>',
        (r'\s*?\|', '\|'),
        r'\|$',
        r'^\|',
        (re.escape('||'), '|'),
    ]
    for repl in repls:
        if isinstance(repl, basestring):
            pattern, replacement = repl, ''
        else:
            pattern, replacement = repl
        html = re.compile(pattern, flags=re.DOTALL).sub(replacement, html)
    
    return html.split('|')

def processContents(category, contents, userRunningBot='', userIPAddress=''):
    category = category.replace('_', ' ')

    # Check to make sure category is valid
    testcategory, testcontents = fetchContents(category)
    if 'noarticletext' in testcontents:
        error("You specified an invalid category. "
              "Please check your spelling and capitalization and try again.")
    else:
        #Separate the page generation from spider work
    
        # Set spider to work on requested category, in separate thread
        args = (category, contents, userRunningBot, userIPAddress)
        thread.start_new_thread(workthread, args)

def removeDuplicates(articles):
    seen = {}
    no_dupes = []
    for article in articles:
        if article not in seen:
            seen[article] = 1
            no_dupes.append(article)
    return no_dupes

def getAllArticlesIn(subcats):
    articles = []
    for subcat in subcats:
        subcategory, subcategorycontents = fetchContents(subcat)
        for article in getArticlesInCategory(subcategorycontents):
            articles.append(found_article)
    return articles

def removeImages(articles):
    return [article for article in articles
                if ('Image:' not in article and 'Category' not in article and
                    'Template' not in article)]

def getTimeStamp():
    return time.strftime('%H:%M:%S, %a %b %d, %Y')
        #see http://docs.python.org/lib/module-time.html

def workthread(category, contents, userRunningBot, userIPAddress):    
    subcats = getSubCatsInCategory(contents)
    articles = getArticlesInCategory(contents)
    new_subcats_found_this_round = 1
    subcats_done = {}
    subcatLimit = 500

    # Keep searching until no new subcats are found.in any categories searched
    while new_subcats_found_this_round>0 and len(subcats) < subcatLimit:
     
        new_subcats_found_this_round = 0
        proposed_extra_subcats = []
        
        # Perform a search of every category we currently know of
        for subcat in subcats:
            if subcat not in subcats_done:
                subcategory, subcategorycontents = fetchContents(subcat)
                additional_subcats = getSubCatsInCategory(subcategorycontents)
                for proposed in additional_subcats:
                    proposed_extra_subcats.append(proposed)
                subcats_done[subcat] = 1
    
        # If this new found subcat isn't a duplicate of one we already know about...
        for proposed in proposed_extra_subcats:
            if proposed not in subcats:
                subcats.append(proposed)
                new_subcats_found_this_round += 1
    
    # And now get a list of every article in every subcat
    articles.extend(getAllArticlesIn(subcats))

    # Remove duplicates and images from article list.
    articles = removeImages(removeDuplicates(articles))

    # Print the article titles found.
    for article_title in articles:
        print article_title


def fetchContents(category):
    # TODO: FIXME: add timeout
    category = re.sub(r'\s', '_', category)
    url = "http://en.wikipedia.org/wiki/Category:" + category
    contents = urllib.urlopen(url).read()
    time.sleep(1) # don't hammer the server! One read request every 1 second.
    return (category, contents)

def fetchTalkContents(article):
    # TODO: FIXME: add timeout
    article = re.sub(r'\s', '_', article)
    url = "http://en.wikipedia.org/wiki/Talk:" + article
    contents = urllib.urlopen(url).read()
    time.sleep(1) # don't hammer the server! One read request every 1 second.
    return (article, contents)

def finishedRunning(category):
    pass

def getMainCategory(category):
    userRunningBot, userIPAddress = '', ''
    if category=='BLANK':
        error("Error receiving category name")
    else:
        category, contents = fetchContents(category)
        processContents(category, contents, userRunningBot, userIPAddress)
        finishedRunning(category)

def error(msg):
    sys.exit("ERROR: " + msg)


if __name__=='__main__':
    args = sys.argv[1:]
    if len(args) != 1:
    	sys.exit("usage: pockbot.hacked <categoryname>\n")
    else:
        getMainCategory(args[0])
