<?php
// template for forward-profile-to-a-friend emails


/* build up a list of _current_ employers */
$current_employment = array();
foreach( $employers as $emp ) {
    if( $emp['current'] )
        $current_employment[] = $emp;
}

/* list of previous employers (just employer name, nothing else) */
$previous_employers = array();
foreach( $employers as $emp ) {
    if( !$emp['current'] )
        $previous_employers[] = $emp['employer'];
}
$previous_employers = array_unique( $previous_employers );


// helper to format headings
function heading( $t, $n ) {
    $u = '?';
    switch( $n ) {
        case 1:
            return $t . "\n" . str_pad( '', strlen( $t ), '=' ) . "\n\n";
        case 2:
        default:
            return $t . "\n" . str_pad( '', strlen( $t ), '-' ) . "\n\n";
    }
}

// helper to format employment info
function fmt_employer( $e ) {
   if( $e['kind'] == 'e' ) {
       if($e['job_title'])
           return "{$e['job_title']} at {$e['employer']}";
        else
            return "{$e['employer']}";
    } else {
        /* freelance */
        if( $e['employer'] )
           return "Freelance ({$e['employer']})";
        else
           return "Freelance";
    }
}


// like journo_link() but for plain text instead of html
function fmt_journo( $j ) {
    $out = $j['prettyname'];
    if( $j['oneliner'] ) {
        $out .= " ({$j['oneliner']})";
    }
    $url = OPTION_BASE_URL . "/" . $j['ref'];
    $out .= "\n  " . $url;
    return $out;
}




// -------------- start ------------------


echo heading( "journalisted profile: $prettyname",1 );

$profile_url = OPTION_BASE_URL . "/$ref";
echo "$profile_url\n\n";

/*
echo heading( "Overview", 2 );

if( $current_employment ) {
    echo "current employment:\n";
    foreach( $current_employment as $e ) {
        echo " " . fmt_employer($e) . "\n";
    }
    echo "\n";
}
*/

echo heading( "Most recent articles", 2 );

$MAX_ARTICLES = 5;
$n=0;
foreach( $articles as $art ) {
    echo "{$art['title']}\n";
    echo "  {$art['srcorgname']}, {$art['pretty_pubdate']}\n";
    echo "  {$art['permalink']}\n";
    ++$n;
    if( $n>=$MAX_ARTICLES )
        break;
}
echo "\n";

echo heading( "Experience",2 );
if( $employers ) {
    foreach( $employers as $e ) {
        echo fmt_employer( $e ) . "\n";
        $year_from = $e['year_from'] ? $e['year_from'] : '';
        $year_to = $e['current']?'present':$e['year_to'];
        if( $e['year_from'] || $e['year_to'] || $e['current'] ) {
            echo "  $year_from - $year_to\n";
        }
    }
} else {
    echo "No experience entered\n";
}
echo "\n";

echo heading( "Education",2 );
if( $education ) {
    foreach( $education as $edu ) {
        echo "{$edu['school']}\n";
        if( $edu['qualification'] && $edu['field'] ) {
            echo "  {$edu['qualification']}, {$edu['field']}\n";
        }
        if( $edu['year_from'] || $edu['year_to'] ) {
            echo "  {$edu['year_from']}-{$edu['year_to']}\n";
        }
    }
} else {
    echo "No education entered\n";
}
echo "\n";


echo heading( "Books by {$prettyname}", 2 );
if( $books ) {
    foreach( $books as $b ) {
        echo "{$b['title']}\n";

        if( $b['publisher'] || $b['year_published'] ) {
            echo "  {$b['publisher']}, {$b['year_published']}\n";
        }
    }
} else {
    echo "No books entered\n";
}
echo "\n";


echo heading( "Awards won", 2 );
if( $awards ) {
    foreach( $awards as $a ) {
        if( $a['year'] ) {
            echo "{$a['award']}, {$a['year']}\n";
        } else {
            echo "{$a['award']}\n";
        }
    }
} else {
    echo "No books entered\n";
}
echo "\n";


echo heading( "Contact details", 2 );

if( $known_email ) {
    echo "Email:   {$known_email['email']}\n";
    if( $known_email['srcurl'] ) {
        echo "         [from {$known_email['srcurl']} ]\n";
    }
} else {
    echo "Email:   unknown\n";

    if( $guessed ) {
        echo "         You could try contacting {$guessed['orgname']}";
        if( $guessed['orgphone'] ) {
            echo "(Telephone: {$guessed['orgphone']})";
        }
        echo "\n";

        if( $guessed['emails'] ) {
            echo "\n";
            echo "         Based on the standard email format for {$guessed['orgname']}, the email\n";
            echo "         address /might/ be " . implode( ' or ', $guessed['emails'] ) . "\n";
        }
    }
}

if( $twitter_id ) {
    echo "Twitter: @{$twitter_id} [{$twitter_url}]\n";
}

if( $phone_number ) {
    echo "Phone:   {$phone_number}\n";
}

if( $address ) {
    echo "Address: ";
    echo str_replace( "\n", "\n         ", $address ) . "\n";
}
echo "\n\n";


echo heading( "Web links", 2 );
if( $links ) {
    foreach( $links as $l ) {
        echo"{$l['description']}\n";
        echo "  {$l['url']}\n";
    }
} else {
    echo "No links known\n";
}
echo "\n\n";





echo heading( "Journalists who write similar articles",2);
$n=0;
foreach( $similar_journos as $j ) {
    echo fmt_journo( $j ) . "\n";
    if(++$n>=5) break;
}
echo "\n\n";


echo heading( "Journalists admired by {$prettyname}",2 );
if( $admired ) {
    foreach( $admired as $a ) {
        echo fmt_journo( $a ) . "\n";
    }
} else {
    echo "{$prettyname} has not added any journalists\n";
}
echo "\n\n";



if( !$quick_n_nasty ) {
    echo heading( "10 topics mentioned most by $prettyname",2);
    $out = '';
    foreach($tags as $tag=>$freq ) {
        echo "{$tag}\n";
    }
}
echo "\n\n";



