package jl

import (
	"regexp"
	"strings"
)

var tagPats = struct {
	tag, prefixStrip, compressSpace *regexp.Regexp
}{
	regexp.MustCompile(`(?s)[^.\s]\s*((\p{Lu}\p{L}+)(\s+(\p{Lu}\p{L}+))*)`),
	regexp.MustCompile(`(?i)^(mr|dr|ms|mrs)\s+`),
	regexp.MustCompile(`\s+`),
}

type Tag struct {
	Name string
	Freq int
}

type TagsByName []Tag

func (a TagsByName) Len() int           { return len(a) }
func (a TagsByName) Swap(i, j int)      { a[i], a[j] = a[j], a[i] }
func (a TagsByName) Less(i, j int) bool { return a[i].Name < a[j].Name }

// extract a list of terms (tags) from text
func ExtractTagsFromText(rawTxt string) []Tag {

	tags := map[string]int{}

	// noddy Crapitalisation algorithm
	for _, foo := range tagPats.tag.FindAllStringSubmatch(rawTxt, -1) {
		m := foo[1]
		m = tagPats.compressSpace.ReplaceAllString(m, " ")

		// ignore short tags unless they look like acronymns
		if len(m) <= 3 && m != strings.ToUpper(m) {
			continue
		}

		// discard tags with more than 4 words
		if len(strings.Split(m, " ")) > 4 {
			continue
		}

		m = strings.ToLower(m)
		// TODO: check against blacklist
		// apply synonyms?
		// classify?

		// remove any leading "mr" "mrs" etc...
		m = tagPats.prefixStrip.ReplaceAllLiteralString(m, "")

		tags[m] += 1
	}

	out := []Tag{}
	for name, freq := range tags {
		out = append(out, Tag{name, freq})
	}

	return out
	/*
	   blacklist = GetBlacklist()
	   countries = GetCountryList()
	   synonyms = {}

	   # extract phrases with the first letter of each word capitalised,
	   # but not at the beginning of a sentence.
	   tagpat = re.compile( u'[^.\\s]\\s*(([A-Z]\\w+)(\\s+([A-Z]\\w+))*)', re.UNICODE|re.DOTALL )

	   # prefixes we'll trim off
	   prefixpat = re.compile( u'^(mr|dr|ms|mrs)\\s+',re.UNICODE|re.IGNORECASE )

	   # calculate tags using noddy Crapitisation algorithm
	   tags = {}
	   for m in tagpat.findall(txt):
	       # compress whitespace
	       words = m[0].split()
	       if len( words ) > 4:
	           continue    # discard tags with more than 4 words
	       t = ' '.join( words )

	       # ignore short tags unless they look like acronymns
	       if len(t)<=3 and t != t.upper():
	           continue

	       t = t.lower()
	       if t in blacklist:
	           continue

	       # trim off any title prefixes (mr, mrs, ms, dr)
	       t = prefixpat.sub( u'', t )

	       # is there a synonym to remap this key?
	       if synonyms.has_key(t):
	           t=synonyms[t]


	       # which kind of term is it?
	       kind = ' '  # unknown
	       if t in countries:
	           kind = 'c'


	       # TODO: kind field in DB should be part of primary key too!

	       # key is tag name _and_ kind of tag!
	       k = (t,kind)
	       tags[k] = tags.get(k,0) + 1     # ++freq
	*/
}
