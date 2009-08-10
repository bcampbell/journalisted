<?php

/* 
 * Page to show information about why we might be missing articles for
 * a journo and to submit links.
*/

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../phplib/scrapeutils.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';

$state = strtolower( get_http_var('state','initial' ) );
$ref = strtolower( get_http_var( 'j' ) );   /* eg 'fred-bloggs' */
$journo = NULL;
if( $ref )
    $journo = db_getRow( "SELECT id,ref,prettyname,lastname,firstname FROM journo WHERE ref=? AND status='a'", $ref );

if( $journo )
    $title = "Tell us about missing articles for " . $journo['prettyname'];
else
{
    page_header('');
?>
<p>No journalist specified</p>
<?php
    page_footer();
    return;
}

page_header($title);

?>
<div id="maincolumn">

<div class="box">
<h2>Tell us about missing articles for <a href="/<?php echo $journo['ref'];?>"><?php echo $journo['prettyname']; ?></a></h2>
<div class="box-content">

<?php

do_it();


?>
</div>
</div>
</div> <!-- end maincolumn -->
<div id="smallcolumn">
  <div class="box">
    <h3>Why might an article be missing?</h3>
    <div class="box-content">
      <p>An article may not appear on a journalist's page if:</p>
      <ul>
        <li>It was not published in one of the <a href="/about#whichoutlets">news outlets</a> we cover (but that's OK - tell us anyway so we can list it)</li>
        <li>It was not bylined</li>
        <li>The byline was mis-spelt in the original publication</li>
        <li>The byline was contained within the text of the article so our system could not find it</li>
        <li>It was published before we started</li>
        <li>It was published in a 'registration required' area of the news outlet's website</li>
      </ul>
    </div>
  </div>
</div>  <!-- end smallcolumn -->
<?php

page_footer();




function emit_rawform() {
    global $journo;
?>
      <form action="/missing" method="POST">
        <input type="hidden" name="j" value="<?php echo $journo['ref']; ?>" />
        <p>Please enter the urls of the article(s) you want to submit, one per line:</p>
        <textarea name="rawurls" style="width: 100%;" rows="8"></textarea><br/>
        <button type="submit" name="action" value="go">Submit</button>
      </form>
<?php
}



function get_items() {

    $items = array();
    // fetch unprocessed items (just a bunch of urls)
    $rawurls = get_http_var( 'rawurls', null );
    if( $rawurls ) {
        $urls = array_unique( explode("\n",$rawurls) );
        foreach( $urls as $u ) {
            $u = trim($u);
            if( $u ) {
                $items[] = array(
                    'url'=>$u,
                    'state'=>'pending',
                    'title'=>null,
                    'pubdate'=>null,
                    'publication'=>null,
                    'errs'=>array() );
            }
        }
    }
//    print "<pre>" . sizeof($items) . " from raw</pre>\n";

    // fetch processed items
    $cnt = get_http_var( 'cnt', 0 );
    for( $i=0; $i<$cnt; ++$i ) {
        $url = get_http_var( "url{$i}" );
        if(!$url )
            continue;

        $items[] = array(
            'url'=>$url,
            'state'=>get_http_var( "state{$i}" ),
            'title'=>get_http_var( "title{$i}", null ),
            'pubdate'=>get_http_var( "pubdate{$i}", null ),
            'publication'=>get_http_var( "publication{$i}", null ),
            'errs'=>array()
        );
    }
//    print "<pre>" . $cnt . " cnt</pre>\n";
//    print "<pre>" . sizeof($items) . " total</pre>\n";

    // add "http://" prefix if missing
/*    foreach( $items as &$item ) {
        if( preg_match( "%^[a-zA-Z]+://%", $item['url'] ) == 0 ) {
            $item['url'] = "http://" . $item['url'];
        }
    }
*/
    return $items;
}


function do_it() {
    $items = get_items();
    if( $items ) {
        $idx = 0;
        foreach( $items as &$item ) {
            process_item( $item );
        }
        emit_form($items);
    } else {
        emit_rawform();
    }
}


// differs from built-in parse_url() in these ways:
// - doesn't abort with errors if it gets confused
// - if it looks like a valid url, _all_ fields will be returned (missing ones will be empty)
function crack_url( $url )
{
    $fields = array( 'scheme','user','pass','host','port','path','query','fragment' );
    $m = array();
    if( preg_match( "/^((?P<scheme>\w+):\/\/)?((?P<user>.+?)(:(?P<pass>.+?))?@)?(?P<host>[^\/:?]+)?(:(?P<port>\d+))?(?P<path>.+?)?([?](?P<query>.*?))?([#](?P<fragment>.*?))?$/",$url,&$m ) > 0 ) {


        $ret = array();
        foreach( $fields as $f ) {
            $ret[$f] = array_key_exists( $f, $m ) ? $m[$f] : '';
        }
        return $ret;
    } else {
        /* actually, I'm not sure the preg_match can ever fail... every part is optional... */
        return FALSE;
    }

}

//
function is_sane_article_url( $url )
{
    $bits = crack_url( $url );
    if( $bits === FALSE )
        return "Please enter the full url of the article";
    // default to http://
    if( $bits['scheme'] == '' ) {
        $bits['scheme'] = 'http';
    }

    $host = trim( $bits['host'] );
    $scheme = trim( strtolower( $bits['scheme'] ) );
    $path = trim( $bits['path'] );
    $query = trim( $bits['query'] );

    if( $host == '' ) {
        return "Please enter the full url of the article";
    }

    // no ftp: or internal file: links please!
    if( $scheme != 'http' && $scheme != 'https' ) {
        return "Sorry, \"{$scheme}://\" urls are not supported";
    }

    // hostnames probably shouldn't have spaces in them...
    // (proably user entering  a headline... sigh...)
    if( strpos( $host, ' ' ) !== False ) {
        return "Please enter a valid url";
    }

    // make sure we've got at least a non-blank path (or a non-blank query)
    if( ($path=='' || $path=='/') && $query=='' ) {
        return "Please enter the FULL url of the article";
    }

    return null;
}

function is_url_scrapable( $url )
{
    $srcid = scrape_CalcSrcID( $url );
    if( $srcid )
        return TRUE;
    else
        return FALSE;
}

function check_details( $item )
{
    $errs = array();
    if( $item['pubdate'] == '' ) {
        $errs['pubdate'] = "Please enter the date the article was published";
    } else {
        $dt = strtotime( $item['pubdate'] );
        if( !$dt ) {
            $errs['pubdate'] = "Please enter a valid date";
        }
    }

    if( $item['title'] == '' ) {
        $errs['title'] = "Please enter the title of the article";
    }

    return $errs;
}



// process a single item, and set it's state/error messages appropriately.
function process_item( &$item )
{
    global $journo;
    if( $item['state'] == 'ok' || $item['state'] =='ok_extra' ) {
        return; // no further processing needed
    }

    $err = is_sane_article_url($item['url']);
    if( $err ) {
        $item['state'] = 'badurl';
        $item['errs'] = array( 'url'=>$err );
        return;
    }

    if( is_url_scrapable( $item['url'] ) ) {
        db_do( "INSERT INTO missing_articles (journo_id,url) VALUES (?,?)",
            $journo['id'], $item['url'] );
        db_commit();
        $item['state'] = 'ok';
    } else {
        // not scrapable
        if( $item['state'] == 'need_extra' ) {
            $item['errs'] = check_details( $item );
            if( !$item['errs'] ) {
                $sql = <<<EOT
INSERT INTO journo_other_articles ( journo_id, url, title, pubdate, publication, status )
    VALUES ( ?,?,?,?,?,'u' )
EOT;
                $dt = new DateTime( $item['pubdate'] );
                db_do( $sql, $journo['id'], $item['url'], $item['title'], $dt->format(DateTime::ISO8601), $item['publication'] );
                db_commit();

                $item['state'] = 'ok_extra';
            }
        } else {
            $item['state'] = 'need_extra';
            // TODO: could use url here to look up publication!
        }
    }
}





function emit_form( &$items )
{
    global $journo;
    $accepted = 0;
    $pending = 0;

    foreach( $items as &$item ) {
        if( $item['state'] == 'ok' || $item['state'] == 'ok_extra' ) {
            ++$accepted;
        } else {
            ++$pending;
        }
        // add htmlentities()-encoded strings to items
        $item = h_array($item);
    }
    unset($item);

    // show the ones that have already been accepted and processed
    // (they'll be repeated later as hidden form elements too)
    if( $accepted > 0 ) {
        if( sizeof($items)==1 ) {
?><p>Thank you! This article has been submitted:</p><?php
        } else {
?><p>Thank you! These articles have been submitted:</p><?php
        }
?>
<ul class="art-list" >
<?php
        foreach( $items as &$item ) {
            if( $item['state'] == 'ok' ) {
                /* it's a url we should be able to scrape */
?>
<li><a href="<?php echo $item['h_url']; ?>"><?php echo $item['h_url']; ?></a></li>
<?php
            } else if( $item['state'] == 'ok_extra' ) {
                /* it's a url we don't scrape, with title,date etc */
                $dt = new DateTime( $item['pubdate'] );
?>
<li>
<a href="<?php echo $item['h_url']; ?>"><?php echo $item['h_title']; ?></a><?php if( $item['h_publication'] ) { ?>, <span class="publication"><?php echo $item['h_publication']; ?></span><?php } ?>, <span class="published"><?php echo pretty_date($dt); ?></span>
</li>
<?php
            }
        }

?>
</ul>
<?php

    }

    // if they've all been done, we can bail out now.
    if( $pending == 0 ) {
?>
        <p><a href="/missing?j=<?php echo $journo['ref']; ?>">Submit more missing articles for <?php echo $journo['prettyname']; ?></a></p>

<?php
        return;
    }


    // show the ones still being sorted out
?>
</ul>

<form method="POST" action="/missing" id="missing">
<input type="hidden" name="j" value="<?php echo $journo['ref']; ?>" />
<input type="hidden" name="cnt" value="<?php echo sizeof($items); ?>" />

<?php
    $idx = 0;
    foreach( $items as &$item ) {
        emit_item( $item, $idx );
        ++$idx;
    }
?>
<button name="action" value="go">Submit</button>
</form>
<?php

}



// output an individual item on the form. May be editable, may be hidden, depending on its state
function emit_item( $item, $idx )
{
    $state = $item['state'];
    $errs = $item['errs'];
    if( $state == 'ok' ) {
?>
<input type="hidden" name="state<?php echo $idx;?>" value="<?php echo $item['state']; ?>" />
<input type="hidden" name="url<?php echo $idx;?>" value="<?php echo $item['h_url']; ?>" />
<?php
    } elseif( $state == 'ok_extra' ) {
?>
<input type="hidden" name="state<?php echo $idx;?>" value="<?php echo $item['state']; ?>" />
<input type="hidden" name="url<?php echo $idx;?>" value="<?php echo $item['h_url']; ?>" />
<input type="hidden" name="title<?php echo $idx;?>" value="<?php echo $item['h_title']; ?>" />
<input type="hidden" name="pubdate<?php echo $idx;?>" value="<?php echo $item['h_pubdate']; ?>" />
<input type="hidden" name="publication<?php echo $idx;?>" value="<?php echo $item['h_publication']; ?>" />
<?php
    } elseif( $state == 'need_extra' ) {
?>
<fieldset>

<p>Please tell us a little more about this article:</p>

<input type="hidden" name="state<?php echo $idx;?>" value="<?php echo $item['state']; ?>" />

<div class="field">
<?php if( array_key_exists('url',$errs) ) { ?> <span class="errhint"><?php echo $errs['url']; ?></span><br/> <?php } ?>
<label for="url<?php echo $idx;?>">article url</label>
<input type="text" class="wide" id="url<?php echo $idx;?>" name="url<?php echo $idx;?>" value="<?php echo $item['h_url']; ?>" />
</div>

<div class="field">
<?php if( array_key_exists('title',$errs) ) { ?> <span class="errhint"><?php echo $errs['title']; ?></span> <?php } ?>
<label for="title<?php echo $idx;?>">article title</label>
<input type="text" class="wide" id="title<?php echo $idx;?>" name="title<?php echo $idx;?>" value="<?php echo $item['h_title']; ?>" />
</div>

<div class="field">
<?php if( array_key_exists('pubdate',$errs) ) { ?> <span class="errhint"><?php echo $errs['pubdate']; ?></span> <?php } ?>
<label for="pubdate<?php echo $idx;?>">publication date<br/><small>(yyyy-mm-dd)</small></label>
<input type="text" id="pubdate<?php echo $idx;?>" name="pubdate<?php echo $idx;?>" value="<?php echo $item['h_pubdate']; ?>" />
</div>

<div class="field">
<label for="publication<?php echo $idx;?>">publication<br/><small>(optional)</small></label>
<input type="text" id="publication<?php echo $idx;?>" name="publication<?php echo $idx;?>" value="<?php echo $item['h_publication']; ?>" />
</div>

</fieldset>
<?php

    } else {

?>
<fieldset>
<input type="hidden" name="<?php echo "state{$idx}";?>" value="<?php echo $item['state']; ?>" />
<?php if( array_key_exists('url',$errs) ) { ?> <span class="errhint"><?php echo $errs['url']; ?></span> <?php } ?>
<label for="url<?php echo $idx;?>">article url</label>
<input type="text" class="wide" id="url<?php echo $idx;?>" name="url<?php echo $idx;?>" value="<?php echo $item['h_url']; ?>" />
</fieldset>
<?php
    }
}

