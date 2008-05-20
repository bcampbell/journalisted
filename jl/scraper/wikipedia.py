#!/usr/bin/env python2.4
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#

'''
Scrapes wikipedia articles (headings and paragraphs).
'''

###
# Doubtless I'll find that there's a pre-XML-ified version of wikipedia
# somewhere, but until then...
#
# ...Yes there is, see <http://en.wikipedia.org/wiki/Help:Export>.
# However,
#    "In the current version the export format does not contain
#     an XML replacement of wiki markup",
# so it's nearly useless. It might be worth trying http://pywikipediabot.sf.net
# but it's not in either Debian or the Cheeseshop, so it's a nasty dependency.
###

import sys
import re
import urllib2

import site
site.addsitedir('../pylib')
from BeautifulSoup import BeautifulSoup, NavigableString, Tag
from JL import DB, ukmedia, ScraperUtils
from datetime import datetime


class NoSuchArticle(Exception):
    pass

class DisambiguationNeeded(Exception):
    '''Reached a disambiguation page'''

class UnsureException(Exception):
    '''May not be the right person'''

class DeadPersonException(Exception):
    '''This is an ex-person! Bereft of life, they write no more.'''


# Not needed here (provided by ScraperUtils with --url), but used by updatewikipedialinks
def ScrapeArticle(url):
    '''Wrapper for testing on a single URL.'''
    context = ContextFromURL(url)
    req = urllib2.Request(url, headers={'User-Agent': 'JournalistedBot'})
    html = urllib2.urlopen(req).read()
    return Extract(html, context)


def Extract(html, context):
    ukmedia.DBUG2( "*** wikipedia ***: scraping %s\n" % context['srcurl'])

    if 'Wikipedia does not have an article with this exact name' in html:
        raise NoSuchArticle("No article with this exact name.")

    if 'Wikipedia:Disambiguation' in html:
        raise DisambiguationNeeded("Disambiguation page")

    OCCUPATIONS = [
        'Journalist', 'Journalism', 'Science_journalist', 'Author', 'Writer',
        'Novelist',
        'Member_of_Parliament',
        'Broadcasting', 'Broadcaster'  # a bit chancy, but excusable
        
        # should we assume sports are sufficient indicators too?
        # there is, after all, only one wikipedia page for all of these names
    ]
    # Check for a definition list at the start
    tables_re = re.compile(r'<table(?=\W).*?</table>', flags=re.DOTALL)
    html2 = '\n'.join(tables_re.split(html))
    tag = re.search(r'<(dd|p)(?=\W)', html2)
    if tag.group(1)=='dd':
        # Check for "For ..., see ...".
        dd = re.search(r'<dd.*?>(.*?)</dd', html2, flags=re.DOTALL).group(1)
        raise UnsureException("Unsure if this is the right person")
        # This test isn't picky enough:
        #
        #for job in OCCUPATIONS:
        #    if job.lower() in dd.lower():
        #        raise Exception("Probably the wrong page")
        #print "WARNING: possibly the wrong page:"
        #print "    %s" % dd.lstrip().encode('utf-8')

    # Any page which uses the word 'journalist' is assumed to be the right one.
    # People related to journalists may fall foul of this, but they're usually
    # journos too :-).  We're careful to miss "see Martin Arnold (journalist)".
    
    if ' journalist' not in html and 'Journalist ' not in html:
        # FIXME: This catches problem pages (inc. disambiguation pages) fine,
        # but we should have a way to force scraping the page.
        
        ok = False
        for word in OCCUPATIONS:
            if ('href="/wiki/%s"' % word) in html:
                ok = True
        if not ok:
            raise UnsureException("No link to writing-occupation page found, may be wrong person.")

    m = re.search(r'<li id="lastmod"> This page was last modified on (.*?, at \d\d:\d\d).</li>', html)
    if m is None:
        raise Exception("No modification date.")  # probably no article
    context['pubdate'] = ukmedia.ParseDateTime(m.group(1))

    # Extract infobox. Unfortunately BeautifulSoup isn't up to it.
    image = ''
    infobox = None
    m = re.compile('<table class="[^"]*infobox[^"]*?".*?>.*?</table>', re.DOTALL).search(html)
    if m:
        html = html[:m.start()] + html[m.end():]
        infobox = m.group()
        img_re = re.compile(r'<img .*?>', re.DOTALL)
        try:
            image = '<p>%s</p>' % img_re.findall(infobox)[0]
            if 'replace' in image.lower():
                image = ''
        except IndexError:
            image = ''
    
    soup = BeautifulSoup(html)
    
    keywords = []
    for meta in soup.findAll('meta'):
        attrs = dict(meta.attrs)
        if attrs.get('name')=='keywords' and attrs.get('content'):
            keywords.append(attrs.get('content'))
    context['keywords'] = ','.join(keywords)
    
    # Remove relative links and [<number>] links.
    for a in soup.findAll('a'):
        contents = a.renderContents(None)
        if contents and re.match(r'\[\d+\]', contents):
            extract_li = False
            if a.parent and a.parent.name == 'li':
                for sub in a.parent.contents:
                    if isinstance(sub, Tag):
                        if sub is not a: break
                        extract_li = True  # a is the first subtags
                    else:
                        if sub.strip() not in ('', '^'): break
            if extract_li:
                a.parent.extract()
            else:
                a.extract()
        elif not a.get('href', '').startswith('http://'):
            replace_with_contents(a)

    # Remove [edit] spans in headings
    for span in soup.findAll('span', {'class': ['editsection', 'mw-headline']}):
        if span['class'] == 'editsection':
            span.extract()
        else:
            span.replaceWith(span.string or '')
    
    # Remove references in the text
    for sup in soup.findAll('sup', attrs={'class': 'reference'}):
        sup.extract()

    # Remove references at the bottom (not reliable)
    for sup in soup.findAll('li', attrs={'id': re.compile('_note.*')}):
        sup.extract()
    
    # Replace their ridiculous use of tables for pull-quotes with blockquotes
    for tag in soup.findAll('table'):
        if dict(tag.attrs).get('class')=='cquote':
            celltext = tag.findAll('td', limit=2)[1].renderContents(None)
            quote = Tag(soup, u'blockquote')
            quote.append(celltext)
            tag.replaceWith(quote)
    
    tags = soup.body.findAll(['h2', 'p', 'ul', 'ol', 'blockquote'])
    
    # blockquotes cause problems - they contain other tags.
    # Remove all tags with a blockquote parent from the list of tags
    # so that we don't break the parent relationships when assigning
    # tags to headings.
    
    for tag in list(tags):
        for parent in get_parents(tag):
            if parent.name=='blockquote':
                tags.remove(tag)

    # Drop the junk after the article (not always present)
    for i, tag in enumerate(tags):
        if tag.name=='ul' and tag.findAll('li', id='ca-nstab-main'):
            break
    tags = tags[:i]

    # Build a list of headings with their sub-tags.
    headings = [(None, [])]
    for tag in tags:
        if tag.name=='h2':
            headings.append((tag, []))
        else:
            headings[-1][1].append(tag)

    def drop_heading(heading):
        print 'DROPPING:', tagtext(heading)
        return None
    
    def fix_heading(heading, tags):
        '''
        Remove paragraphs with no strings.
        Remove non-special headings that don't start with a paragraph.
        Remove lists from non-special headings.
        '''
        SPECIAL = ['Books', 'Works', 'Bibliography', 'External links',
                   'Further reading']
        tags = [tag for tag in tags if tag is not None and
                    tag.name!='p' or tag.renderContents(None).strip()]
        special = False
        heading_text = tagtext(heading)
        for word in SPECIAL:
            if word.lower() in heading_text.lower():
                special = True
        if heading_text == 'External links and references':
            set_heading_text(heading, 'External links')
        # Shorten lists (even special ones).
        # The motivation is that some pages like to list reams of crap,
        # usually in bibliographies, but in other things too.
        found_lis = 0
        MAX_ITEMS = 10  # per headed section
        for tag in list(tags):
            if tag.name in ('ul', 'ol'):
                for li in tag.findAll('li', recursive=False):
                    found_lis += 1
                    if found_lis > MAX_ITEMS:
                        li.extract()
        # Remove any now-empty uls and ols.
        # Harder than it sounds, due to nested lists.
        temp = Tag(soup, 'p')
        for tag in tags:
            temp.append(tag)
        for tag in temp.findAll(('ul', 'ol')):
            if not tag.findAll(('ul', 'ol', 'li')):
                tag.extract()
        tags = temp.contents
        if found_lis > MAX_ITEMS:
            # Add " (some omitted)" to heading
            if heading_text:
                heading_text = heading_text.replace(' (incomplete)', '')
                if context['srcurl'].startswith('http:'):
                    heading_text += ' (some <a href="%s">omitted</a>)' % context['srcurl']
                else:
                    heading_text += ' (some omitted)'  # no "relative" links
                set_heading_text(heading, heading_text)
        if not special:
            if heading is not None:
                # Remove non-special headings which don't start with a paragraph.
                if not tags or tags[0].name != 'p':
                    return drop_heading(heading)
                # Remove non-special headings containing lists.
                for tag in tags:
                    if tag.name in ('ul', 'ol'):
                        return drop_heading(heading)
            else:
                # Remove lists from None (before first heading, if any).
                tags = [tag for tag in tags if tag.name not in ('ul', 'ol')]
        return (heading, tags)
    
    headings = [fix_heading(*ht) for ht in headings]
    headings = [ht for ht in headings if ht is not None]
    
    # Flatten.
    tags = []
    for heading, htags in headings:
        if heading is not None:
            tags.append(heading)
        if tagtext(heading)=='Death':
            for tag in htags:
                for year in re.findall(r'19[0-9]\d(?!\d)', tag.renderContents(None)):
                    raise DeadPersonException("ex-person! Died in %s!" % year)
        tags += htags
    
    # Replace contents heading with an empty paragraph tag.
    # Since it's now the only one, we can use it to detect the
    # end of the introductory paragraphs.
    #
    # Also strip tag contents and stringify.
    
    for i, tag in enumerate(tags):
        if tag.name=='h2' and tagtext(tag)=='Contents':
            tags[i] = u'<p></p>'
        else:
            tagname = unicode(tag.name)
            tags[i] = u'<%s>%s</%s>' % \
                (tagname, strip(tag.renderContents(None)), tagname)
    
    text = '\n'.join(tags)
    
    # Replace ", ." with ".". Arises from our removing cross-references.
    text = re.sub(r',\s*\.', '.', text)
    
    context['content'] = image + text

    if image:
        bio = unicode(BeautifulSoup(text).p)  # include <p> tag
    else:
        bio = BeautifulSoup(text).p.renderContents(None)
    bio = re.sub('<b>(.*?)</b>', r'\1', bio)
    context['bio'] = image + bio
    return context


## Helpers

def get_parents(tag):
    parents = []
    while tag:
        tag = tag.parent
        if tag: parents.append(tag)
    return parents

def set_heading_text(heading, text):
    while heading.contents:
        heading.contents[-1].extract()
    heading.append(NavigableString(text))

def strip(s):
    '''Strip outer whitespace from s, including <br /> tags.'''
    s = re.search(r'(?s)\s*(?:<br\s*/>\s*)*(.*)', s).group(1)
    return re.search(r'(?s)(.*?)\s*(?:<br\s*/>\s*)*\Z', s).group(1)

def tagtext(tag, strip=True):  # much safer than tag.string
    if tag is None:
        return u''
    text = tag.renderContents(None)
    if strip:
        text = text.strip()
    return text

def replace_with_contents(tag):
    '''Extracts a tag but keeps its contents.'''
    parent, index = tag.parent, tag.parent.contents.index(tag)
    children = tag.contents or []
    tag.extract()
    for child in children:
        parent.insert(index, child)
        index += 1


def FindArticles():
    ukmedia.DBUG2( "*** wikipedia ***: generating URLs for journalists...\n" )
    conn = DB.Connect()
    c = conn.cursor()
    c.execute("SELECT prettyname FROM journo")
    found = []
    for row in c.fetchall():
        prettyname = row[0].replace(' ', '_').encode('utf-8')
        ctx = ContextFromURL('http://en.wikipedia.org/wiki/%s'
                             % urllib.quote(prettyname))
        found.append(ctx)
    return found


def ContextFromURL(url):
    """Build up an article scrape context from a bare url."""
    context = {}
    prettyname = url.split('/')[-1].replace('_', ' ')
    prettyname = prettyname.decode('utf-8') # just in case
    context['title'] = u'wikipedia: ' + prettyname
    context['description'] = u'Wikipedia article on ' + prettyname
    context['srcurl'] = url
    context['permalink'] = url
    context['srcid'] = url
    context['srcorgname'] = u'wikipedia:journo'
    context['lastseen'] = datetime.now()
    context['byline'] = u''
    return context


if __name__=='__main__':
    if 1:
        for i, arg in enumerate(sys.argv):
            if not arg.startswith('-') and not arg.startswith('http'):
                sys.argv[i] = 'file:' + arg
        ScraperUtils.RunMain(FindArticles, ContextFromURL, Extract)
    else:
        # TEST CODE
        import os, sys
        for fname in os.listdir('wikip2'):
            if fname.startswith('_'): continue  # "no article with this" etc.
            import StringIO
            sys.argv[1:] = ['-d', '-u', 'file:wikip2/' + fname]
            print '%s: ' % fname,
            sys.stderr = StringIO.StringIO()
            import pdb
            try:
                x = ScraperUtils.RunMain(FindArticles, ContextFromURL, Extract)
            except Exception, e:
                pdb.set_trace()
            bad = False
            for line in StringIO.StringIO(sys.stderr.getvalue()):
                if line.startswith('Exception:'):
                    if 'No article with this exact name' in line: bad = True
                    sys.stdout.write(line)
            if bad:
                print '(Renaming as _%s)' % fname
                os.rename('wikip2/'+fname, 'wikip2/_'+fname)
                assert os.path.isfile('wikip2/_'+fname)
            sys.stderr = sys.__stderr__
    if 0:
        # This might be useful for building a non-ScraperUtils alternative,
        # so I'm keeping it here for the time being.
        import pprint
        args = sys.argv[1:]
        if '--help' in args or '-h' in args:
            sys.exit('usage: wikipedia.py [URL]\n'
                     '"file:" URLs are supported for local files. '
                     'With no URL, runs scraper framework.')
        if len(args) == 1:
            url = sys.argv[1]
            if re.match('[a-zA-Z_\ ]+$', url):
                url = 'http://en.wikipedia.org/wiki/' + url.replace(' ', '_')
            pprint.pprint(ScrapeArticle(url))
        elif len(args)==0:
            ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract )
        else:
            sys.exit('usage: wikipedia.py URL  (may be "file:..." for local files)')
