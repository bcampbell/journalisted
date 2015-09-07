package main

import (
	"bufio"
	"fmt"
	"os"
	"strconv"
	"strings"
)

func getSinceID(filename string) (int, error) {
	f, err := os.Open(filename)
	if err != nil {
		if os.IsNotExist(err) {
			return 0, nil // ok if file doesn't exist
		}
		return 0, err
	}
	defer f.Close()

	scanner := bufio.NewScanner(f)
	for scanner.Scan() {
		line := scanner.Text()
		line = strings.TrimSpace(line)
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}

		id, err := strconv.Atoi(line)
		if err != nil {
			return 0, err
		}
		return id, nil
	}
	return 0, scanner.Err()
}

func putSinceID(filename string, id int) error {

	f, err := os.Create(filename)
	if err != nil {
		return err
	}
	defer f.Close()

	_, err = fmt.Fprintln(f, id)
	return err
}
