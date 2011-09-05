#!/bin/bash
ionice -c3 -p $$

good="www.dailyrecord.co.uk www.liverpoolecho.co.uk www.northwalesweeklynews.co.uk www.birminghammail.net"
bad="www.eveningtimes.co.uk www.thecourier.co.uk"

for domain in $good
do
    feedsfile=feeds/$domain.feeds
    if [ ! -f $feedsfile ]
    then
        echo "GENERATING $feedsfile"
        ./findfeeds -jv http://$domain >$feedsfile
    else
        echo "already got $feedsfile"
    fi
done

