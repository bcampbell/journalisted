<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/person.php';



$r = array(
    'reason_web' => "To edit custom newspapers on Journalisted, we need your email address.",
    'reason_email' => "You can then set up custom newspapers",
    'reason_email_subject' => "Set up a Journalisted custom newspapers"
);

/* this will redirect to login page if not signed on */
$P = person_signon($r);

page_header( "Edit Custom newspaper", array() );


$action = get_http_var( "action" );
$paper_id = get_http_var( 'id' );

/* make sure that if we're editing or viewing a paper, that it belongs to current user! */
if( $paper_id )
{
    if( $P->id() != db_getOne( "SELECT owner FROM custompaper WHERE id=?",$paper_id ) )
    {
        /* not our paper to view or perform actions upon */
        $action = '';
        $paper_id = null;
    }
}

if( $action == "alterdetails" )
{
    $paper = paper_from_httpvars();
    if( $P->id() == db_getOne( "SELECT owner FROM custompaper WHERE id=?",$paper['id'] ) )
    {
        db_do( "UPDATE custompaper SET name=?, description=?, is_public=? WHERE id=?",
            $paper['name'],
            $paper['description'],
            $paper['is_public']?'t':'f',
            $paper['id'] );
        db_commit();

        action_msg( "UPDATED DETAILS" );
    }
}
else if( $action == 'newpaper' )
{
    db_do( "INSERT INTO custompaper (name,owner,is_public) VALUES (?,?,?)",
        "my custom paper", $P->id(), 'f' );
    db_commit();
}
else if( $action == 'deletepaper' )
{
    db_do( "DELETE FROM custompaper WHERE id=?",$paper_id );
    db_commit();
    $paper_id = null;
    action_msg( "DELETED paper" );
}
else if( $action == 'addq' )    /* add a query to the paper */
{
    $q = get_http_var('q');
    if( $paper_id && $q )
    {
        db_do( "INSERT INTO custompaper_criteria_text (paper_id,query) VALUES (?,?)", $paper_id, $q );
        db_commit();
        action_msg( "Added a query to paper" );
    }
}
else if( $action == 'delq' )    /* delete a query on the paper */
{
    $qid = get_http_var('qid');
    if( $paper_id && $qid )
    {
        /* include $paper_id in the WHERE, to stop people deleting queries from other people's papers! */
        db_do( "DELETE FROM custompaper_criteria_text WHERE id=? AND paper_id=?", $qid, $paper_id );
        db_commit();
        action_msg( "Removed query from paper" );
    }
}


/* now display paper, or list ones we can edit */
if( $paper_id )
{
    $paper = paper_from_db( $paper_id );
    paper_emitform( $paper );
}
else
{
    emit_owned_paper_list( $P );

?>
<a href="/custompaper_edit?action=newpaper">Create a new custom paper</a><br/>
<a href="/custompaper">back to custom newspaper index</a>
<?php

}



page_footer();


function action_msg( $msg )
{
    print "<p><strong>$msg</strong></p>\n";
}



function emit_owned_paper_list( $P )
{
    $person_id = $P->id();
    $papers = db_getAll( "SELECT * FROM custompaper WHERE owner=?", $person_id );

?>
<h2>Your Custom Newspapers</h2>
<ul>
<?php

    foreach( $papers as $paper )
    {

        $name = $paper['name'];
        $paper_id = $paper['id'];
        $link = "/custompaper?id={$paper_id}";
        $editlink = "/custompaper_edit?id={$paper_id}";
        $deletelink = "/custompaper_edit?id={$paper_id}&action=deletepaper";
        $access = $paper['is_public']=='t' ? 'public':'private';
        print("<li><a href=\"{$link}\">{$name}</a> ({$access}) [<a href=\"{$editlink}\">edit</a>] <small>[<a href=\"{$deletelink}\">delete</a>]</small></li>" );
    }
?>
</ul>
<?php

}


function paper_from_db( $paper_id )
{
    $paper = db_getRow( "SELECT * FROM custompaper WHERE id=?", $paper_id );

    $paper['is_public'] = $paper['is_public'] == 't' ? true:false;
    return $paper;
}

function paper_from_httpvars()
{
    $paper = array(
        'name'=>get_http_var('name'),
        'description'=>get_http_var('description'),
        'is_public'=>get_http_var('is_public'),
    );

    $paper_id = get_http_var('id');
    if( $paper_id )
        $paper['id'] = $paper_id;

    return $paper;
}


function paper_emitform( $paper )
{
    $paper_id = $paper['id'];
    $paperlink = "/custompaper?id={$paper_id}";
    print "<h2><a href=\"{$paperlink}\">{$paper['name']}</a></h2>\n";

?>
<h3>details</h3>
<form method="post">
title: <input type="text" size="40" name="name" value="<?php echo $paper['name'];?>" /><br/>
description: <input type="text" size="80" name="description" value="<?php echo $paper['description'];?>" /><br/>

public? <input type="checkbox" name="is_public"<?php echo $paper['is_public']=='t' ? ' checked':'' ?> /><br/>
<input type="hidden" name="id" value="<?php echo $paper_id; ?>" />
<input type="hidden" name="action" value="alterdetails" />

<input type="submit" value="Apply changes" /><br/>
</form>

<h3>criteria</h3>
<?php

    /* the list of criteria */
    $criteria = db_getAll( "SELECT * FROM custompaper_criteria_text WHERE paper_id=?", $paper['id'] );

    if( $criteria )
    {
        print "<ol>\n";
        foreach( $criteria as $c )
        {
            $removelink = "/custompaper_edit?id={$paper['id']}&action=delq&qid={$c['id']}";
            print "<li><code>{$c['query']}</code> <small>[<a href=\"{$removelink}\">delete</a>]</small></li>\n";
        }
        print "</ol>\n";
    }
    else
    {
        print"<p><em>No queries defined</em></p>\n";
    }

?>
<form action=/custompaper_edit method="post">
<input type="text" name="q" value="" />
<input type="hidden" name="id" value="<?php echo $paper_id;?>" />
<input type="hidden" name="action" value="addq" />
<input type="submit" value="Add a query" />
</form>

<a href="/custompaper_edit">back to my papers</a>
<?php
}


