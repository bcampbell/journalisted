package jl

import (
	"code.google.com/p/go.text/unicode/norm"
	"strings"
)

func toASCII(txt string) string {
	// convert to NFKD form
	// eg, from wikipedia:
	// "U+00C5" (the Swedish letter "Å") is expanded into "U+0041 U+030A" (Latin letter "A" and combining ring above "°")
	n := norm.NFKD.String(txt)

	// strip out non-ascii chars (eg combining ring above "°", leaving just "A")
	n = strings.Map(
		func(r rune) rune {
			if r > 128 {
				r = -1
			}
			return r
		}, n)
	return n
}
