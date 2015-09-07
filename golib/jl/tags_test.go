package jl

import (
	"fmt"
	//	"testing"
)

func ExampleExtractTagsFromText() {
	txts := []string{
		"blah blah Fred Bloggs blah Mr Smith blah blah",
		"according to the IMF.",
		"blah FooBar blah FooBar blah FooBar blah",
		"...said José Ricardo Álvarez today",
		`Bernstein’s response, first posted on the Institute’s website last October, was released by the Union of Concerned Scientists on Wednesday as part of a report on climate disinformation promoted by companies such as ExxonMobil, BP, Shell and Peabody Energy, called the Climate Deception Dossiers.`,
		`Mr Osborne echoed Margaret Thatcher by warning there was no alternative and that backsliding could leave Britain like Greece: ‘Having come this far, there can be no turning back.’`,
	}

	for _, txt := range txts {
		// TODO: need to sort results for comparison...
		fmt.Println(ExtractTagsFromText(txt))
	}

	// Output:
	// [{fred bloggs 1} {smith 1}]
	// [{imf 1]]
	// [{foobar 3]]
	// [{josé ricardo álvarez 1}]
	// [{institute 1} {october 1} {union 1} {concerned scientists 1} {wednesday 1} {exxonmobile 1} {bp 1} {shell 1} {peabody energy 1} {climate deception dossiers 1}]
	// [{osborne 1} {margaret thatcher 1} {britain 1} {greece 1} {having 1}]
}
