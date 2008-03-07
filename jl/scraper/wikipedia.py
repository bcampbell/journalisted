'''
Scrapes wikipedia articles (headings and paragraphs).
'''

# Doubtless I'll find that there's a pre-XML-ified version of wikipedia
# somewhere, but until then...

import sys
import re
import urllib2

from BeautifulSoup import BeautifulSoup


def ScrapeArticle(url):
    info = {}
    req = urllib2.Request(url, headers={'User-Agent': 'JournalistedBot'})
    html = urllib2.urlopen(req).read()
    soup = BeautifulSoup(html)
    
    keywords = []
    for meta in soup.findAll('meta'):
        attrs = dict(meta.attrs)
        if attrs.get('name')=='keywords' and attrs.get('content'):
            keywords.append(attrs.get('content'))
    info['keywords'] = ','.join(keywords)
    
    # Remove relative links and [<number>] links.
    for a in soup.findAll('a'):
        if a.string and re.match(r'\[\d+\]', a.string):
            a.extract()
        elif not a.get('href', '').startswith('http://'):
            a.replaceWith(a.string or '')

    # Remove [edit] spans in headings
    for span in soup.findAll('span', {'class': ['editsection', 'mw-headline']}):
        if span['class'] == 'editsection':
            span.extract()
        else:
            span.replaceWith(span.string or '')
    
    # Remove references
    for sup in soup.findAll('sup', attrs={'class': 'reference'}):
        sup.extract()
    
    tags = soup.body.findAll(['h2', 'p'])

    # Remove paragraphs with no strings and headings with no paragraphs.
    i = 1
    while i < len(tags):
        if (tags[i].name=='h2' and tags[i-1].name=='h2') or \
           (tags[i].name=='p' and not tags[i].renderContents()):
            tags.pop(i)
        else:
            i += 1
    
    while tags and tags[-1].name=='h2':
        tags.pop()
    
    # Replace contents heading with an empty paragraph tag.
    # Since it's now the only one, we can use it to detect the
    # end of the introductory paragraphs.
    #
    # Also strip tag contents and stringify.
    
    for i, tag in enumerate(tags):
        if tag.name=='h2' and tag.string=='Contents':
            tags[i] = u'<p></p>'
        else:
            tagname = unicode(tag.name)
            tags[i] = u'<%s>%s</%s>' % \
                (tagname, tag.renderContents(None).strip(), tagname)
    
    text = '\n'.join(tags)
    
    info['contents'] = text
    return info


# TODO: integrate the rest of the scraping framework code.

if __name__=='__main__':
    import pprint
    if len(sys.argv[1:]) == 1:
        url = sys.argv[1]
        if re.match('[a-zA-Z_\ ]+$', url):
            url = 'http://en.wikipedia.org/wiki/' + url.replace(' ', '_')
        pprint.pprint(ScrapeArticle(url))
    else:
        sys.exit('usage: wikipedia.py URL  (may be "file:..." for local files)')
