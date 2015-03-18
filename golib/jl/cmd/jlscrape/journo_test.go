package main

import (
	"fmt"
	//	"testing"
)

func ExampleBaseRef() {
	names := []string{
		"Fred Bloggs",
		"  Bill Smith  ",
		"Peter O'Toole",
		"Tim Nice-But-Dim",
		"José Ricardo Álvarez",
		"Dr. Nick Riviera",
	}

	for _, n := range names {
		fmt.Println(baseRef(n))
	}

	// Output:
	// fred-bloggs
	// bill-smith
	// peter-o-toole
	// tim-nice-but-dim
	// jose-ricardo-alvarez
	// dr-nick-riviera
}
