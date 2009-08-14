<?php
// journo.php
// admin page for managing journos

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/adm.php';

require_once 'weblink_widget.php';

//require_once "HTML/QuickForm.php";

function ExtraHead()
{
    WeblinkWidget::emit_head_js();
}

$statusnames = array('i'=>'i - Inactive', 'a'=>'a - Active', 'h'=>'h - Hidden' );

$ref = strtolower( get_http_var( 'ref', '' ) );
$journo_id = get_http_var( 'journo_id' );
if( $ref )
    $journo_id = db_getOne( "SELECT id FROM journo WHERE ref=?", $ref );
 
$action = get_http_var( 'action' );

$journo_name = 'Journos';
if( $journo_id )
    $journo_name = db_getOne( "SELECT prettyname FROM journo WHERE id=?",$journo_id);


admPageHeader( $journo_name, "ExtraHead" );

switch( $action )
{
	case 'list':
		/* List journos */
		print "<h2>Journalists</h2>\n";
		EmitJournoFilterForm();
		EmitJournoList();
		break;
	case 'change_status':
		ChangeJournoStatus( $journo_id, get_http_var('status') );
		EmitJourno( $journo_id );
		break;
	case "add_link":
		AddWeblink( $journo_id, get_http_var('url'), get_http_var('desc') );
		EmitJourno( $journo_id );
		break;
	case "remove_link":
		ConfirmRemoveWeblink( $journo_id, get_http_var('link_id') );
		break;
	case "remove_link_confirmed":
		RemoveWeblink( $journo_id, get_http_var('link_id') );
		EmitJourno( $journo_id );
		break;
	case "approve_link":
		ApproveWeblink( $journo_id, get_http_var('link_id') );
		EmitJourno( $journo_id );
		break;
	case "disapprove_link":
		DisapproveWeblink( $journo_id, get_http_var('link_id') );
		EmitJourno( $journo_id );
		break;
	case "approve_bio":
		ApproveBio( $journo_id, get_http_var('bio_id') );
		EmitJourno( $journo_id );
		break;
	case "disapprove_bio":
		DisapproveBio( $journo_id, get_http_var('bio_id') );
		EmitJourno( $journo_id );
		break;

	case "add_email":
		AddEmail( $journo_id, get_http_var('email') );
		EmitJourno( $journo_id );
		break;
	case "remove_email":
		ConfirmRemoveEmail( $journo_id, get_http_var('email_id') );
		break;
	case "remove_email_confirmed":
		RemoveEmail( $journo_id, get_http_var('email_id') );
		EmitJourno( $journo_id );
		break;
	case "approve_email":
		ApproveEmail( $journo_id, get_http_var('email_id') );
		EmitJourno( $journo_id );
		break;
	case "disapprove_email":
		DisapproveEmail( $journo_id, get_http_var('email_id') );
		EmitJourno( $journo_id );
		break;
	case "add_otherarticle":
		AddOtherArticle( $journo_id, get_http_var('url'), get_http_var('title'), get_http_var('pubdate'), get_http_var('publication') );
		EmitJourno( $journo_id );
		break;
	case "remove_otherarticle_confirmed":
		RemoveOtherArticle( $journo_id, get_http_var('otherarticle_id') );
		EmitJourno( $journo_id );
		break;
	case "approve_otherarticle":
		ApproveOtherArticle( $journo_id, get_http_var('otherarticle_id') );
		EmitJourno( $journo_id );
		break;
	case "disapprove_otherarticle":
		DisapproveOtherArticle( $journo_id, get_http_var('otherarticle_id') );
		EmitJourno( $journo_id );
		break;

	default:
		if( $journo_id )
			EmitJourno( $journo_id );
		else
		{
			print "<h2>Journalists</h2>\n";
			EmitJournoFilterForm();
		}
		break;
}

admPageFooter();

/********************************/

function EmitActionMsg( $msg )
{
    print( "<div class=\"action_summary\">{$msg}</div>\n" );
}

function EmitJournoFilterForm()
{
	global $statusnames;

	$s = array('any'=>'Any') + $statusnames;
?>

<form method="get" action="/adm/journo">
 <input type="hidden" name="action" value="list" /><br />
 with status:
 <?= form_element_select( "status", $s, get_http_var( 'status' ) ); ?><br />
 name containing: <input type="text" name="name" size="40" /><br />
 <input type="submit" name="submit" value="Find" />
</form>
<?php

}

function EmitJournoList()
{
?>
<table>
<thead>
 <tr>
  <th>id</th>
  <th>status</th>
  <th>prettyname</th>
  <th>ref</th>
 </tr>
</thead>
<tbody>
<?php

	$status = get_http_var( 'status', 'any' );
	$name = get_http_var( 'name' );

	$conds = array();
	$params = array();

	if( $status != 'any' ) {
		$conds[] = "status = ?";
		$params[] = $status;
	}

	if( $name ) {
		$conds[] = "prettyname ilike ?";
		$params[] = '%' . $name . '%';
	}

	$sql = "SELECT * FROM journo";
    if( $conds ) {
        $sql .= ' WHERE ' . implode( ' AND ', $conds );
    }

	$q = db_query( $sql, $params );

	while( $r = db_fetch_array($q) ) {
		$link = sprintf( "?journo_id=%s", $r['id'] );
		printf( " <tr>\n" );
		printf( "  <td>%s</td>\n", $r['id'] );
		printf( "  <td>%s</td>\n", $r['status'] );
		printf( "  <td><a href=\"%s\">%s</a></td>\n", $link, $r['prettyname'] );
		printf( "  <td>%s</td>\n", $r['ref'] );
		printf( " </tr>\n" );
	}

?>
</tbody>
</table>
<?php
}



function EmitJourno( $journo_id )
{
	global $statusnames;

	$j = db_getRow( "SELECT * FROM journo WHERE id=?", $journo_id );

?>
<h2><?php echo $j['prettyname']; ?></h2>
<a href="/<?php echo $j['ref'];?>?full=yes">Jump to their page</a>
(<a href="/<?php echo $j['ref'];?>?full=yes">Force a page rebuild</a>)
<h3>General</h3>
<?php

	printf("<form method='post'>\n");
	printf("<strong>status:</strong> %s\n", $statusnames[ $j['status'] ] );
	print form_element_hidden( 'action', 'change_status' );
	print form_element_hidden( 'journo_id', $journo_id );
	/* only allow setting to active or hidden, not inactive */
	if( $j['status'] == 'a' ) {
		printf("<input type=\"submit\" name=\"submit\" value=\"Change to 'Hidden'\" />\n" );
		print form_element_hidden( 'status', 'h' );
	}
	else
	{
		printf("<input type=\"submit\" name=\"submit\" value=\"Change to 'Active'\" />\n" );
		print form_element_hidden( 'status', 'a' );
	}

	printf("</form>\n");
	printf("<strong>id:</strong> %s<br />\n", $j['id'] );
	printf("<strong>ref:</strong> %s<br />\n", $j['ref'] );
	printf("<strong>prettyname:</strong> %s<br />\n", $j['prettyname'] );
	printf("<strong>firstname:</strong> %s<br />\n", $j['firstname'] );
	printf("<strong>lastname:</strong> %s<br />\n", $j['lastname'] );
	printf("<strong>created:</strong> %s<br />\n", $j['created'] );

	EmitEmailAddresses( $journo_id );
	EmitWebLinks( $journo_id );
	EmitBios( $journo_id );
	EmitArticles( $journo_id );
	EmitOtherArticles( $journo_id );
}


/* show a list of all the articles attributed to this journo */
function EmitArticles( $journo_id )
{
	$orgs = get_org_names();

	print "<h3>Articles</h3>\n";

	$sql = <<<EOT
SELECT id,title,permalink,status,srcorg,pubdate
	FROM (article a INNER JOIN journo_attr attr ON a.id=attr.article_id)
		WHERE attr.journo_id=? ORDER BY pubdate DESC
EOT;

	$rows = db_getAll( $sql, $journo_id );

	printf( "<p>%d Articles:</p>\n", sizeof( $rows ) );
?>
<ul>
<?php

	foreach( $rows as $row )
	{
		$id  = $row['id'];
		$permalink = $row['permalink'];
		$title = $row['title'];
		$status = $row['status'];
		$org = $orgs[ $row['srcorg'] ];
		$pubdate = pretty_date(strtotime($row['pubdate']));

		// TODO: correct the class usage!
		$divclass = $status=='a' ? 'bio_approved':'bio_unapproved';

		print " <li>\n";
		print(" <div class=\"$divclass\"><a href=\"/adm/article?id=$id\">$title</a>" );
		print("  <small>{$pubdate}, <em>{$org}</em> [<a href=\"$permalink\">original article</a>]</small></div>\n" );
		print " </li>\n";
	}
?>
</ul>
<?php

}





function ChangeJournoStatus( $journo_id, $status )
{
	global $statusnames;

	db_query("UPDATE journo SET status=? WHERE id=?", $status, $journo_id );
	/* TODO: LOG THIS ACTION! */
	db_commit();

	EmitActionMsg( sprintf( "Journo status changed to '%s'", $statusnames[$status] ) );
}


function EmitWebLinks( $journo_id )
{
	print "<h3>Web links</h3>\n";

    $links = WeblinkWidget::fetch_lots( $journo_id, '' );
    foreach( $links as $l ) {
        $w = new WeblinkWidget( $l );
        $w->emit_full();
    }

/* cheesy form to add a link */
/* replace with widget-based version */
?>
<form method="post">
url: <input type="text" name="url" size="40" />
description: <input type="text" name="desc" size="40" />
<?php
print form_element_hidden( 'action', 'add_link' );
print form_element_hidden( 'journo_id', $journo_id );
?>
<input type="submit" name="submit" value="Add Link" />
</form>
<?php
    return;
}

function OLD_EmitWebLinks( $journo_id )
{
	//$links = db_getAll( "SELECT * FROM journo_weblink WHERE journo_id=? AND type!='cif:blog:feed'", $journo_id );
	$links = db_getAll( "SELECT * FROM journo_weblink WHERE journo_id=?", $journo_id );

	if( $links )
	{
?>
	<ul>
<?php
		foreach( $links as $l )
		{
			$id = $l['id'];
			$url = $l['url'];
			$desc = $l['description'];
			$approved = ($l['approved']=='t');

			$anchor = "<a href=\"$url\">$url</a>";
			$removelink = (
				"<a href=\"?action=remove_link&journo_id=$journo_id&link_id=$id\">remove</a>");
			
			if ( $approved )
			{
				$divclass = 'bio_approved';
				$approvelink = sprintf(
					"<a href=\"?action=disapprove_link&journo_id=%s&link_id=%s\">disapprove</a>",
					$journo_id, $id );
			}
			else
			{
				$divclass = 'bio_unapproved';
				$approvelink = sprintf(
					"<a href=\"?action=approve_link&journo_id=%s&link_id=%s\">approve</a>",
					$journo_id, $id );
			}
			
			print " <li>\n";
			print (" <div class=\"$divclass\">[$id] $anchor - '$desc' " .
			       "<small>[$removelink] [$approvelink]</small></div>");
			print " </li>\n";
		}
?>
	</ul>
<?php

	}
	else
	{
		print( "<p>-- no links --</p>\n" );
	}

?>
<form method="post">
url: <input type="text" name="url" size="40" />
description: <input type="text" name="desc" size="40" />
<?php
print form_element_hidden( 'action', 'add_link' );
print form_element_hidden( 'journo_id', $journo_id );
?>
<input type="submit" name="submit" value="Add Link" />
</form>
<?php

}


function EmitBios( $journo_id )
{
	print "<h3>Bios</h3>\n";
	$rows = db_getAll( "SELECT * FROM journo_bio WHERE journo_id=?", $journo_id );

	if( $rows )
	{
?>
	<ul>
<?php
		foreach( $rows as $row )
		{
			$id  = $row['id'];
			$srcurl = $row['srcurl'];
			$bio = $row['bio'];
			$bio_type = $row['kind'];
			$approved = ($row['approved']=='t');
			
			if ( $approved )
			{
				$divclass = 'bio_approved';
				$approvebio = sprintf(
					"<a href=\"?action=disapprove_bio&journo_id=%s&bio_id=%s\">disapprove</a>",
					$journo_id, $id );
			}
			else
			{
				$divclass = 'bio_unapproved';
				$approvebio = sprintf(
					"<a href=\"?action=approve_bio&journo_id=%s&bio_id=%s\">approve</a>",
					$journo_id, $id );
			}
			
			$source = "<a href=\"$srcurl\">$bio_type</a>";
			
			print " <li>\n";
			print(" <div class=\"$divclass\">[$id] $bio <small>(source: $source)</small><br />" .
			      "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" .
			      "<small>[$approvebio]</small></div>");
			print " </li>\n";
		}
?>
	</ul>
<?php

	}
	else
	{
		print( "<p>-- no bios --</p>\n" );
	}

	// TODO: Add bios.
}


/* Form to confirm that weblink _should_ be removed from this journo */
function ConfirmRemoveWeblink( $journo_id, $link_id )
{
	$l = db_getRow( "SELECT * FROM journo_weblink WHERE id=?", $link_id );
	$journo = db_getRow( "SELECT * FROM journo WHERE id=?", $journo_id );

?>
<form method="post" action="/adm/journo">
<p>Are you sure you want to remove
<a href="<?=$l['url']?>"><?=$l['description']; ?></a>
from <?=$journo['prettyname'];?>?<br />
<input type="hidden" name="link_id" value="<?=$link_id;?>" />
<input type="hidden" name="journo_id" value="<?=$journo_id;?>" />
<input type="hidden" name="action" value="remove_link_confirmed" />
<input type="submit" name="submit" value="Yes!" />
<a href="?journo_id=<?=$journo_id;?>">No, I've changed my mind</a>
</form>
<?php

}

function AddWeblink( $journo_id, $url, $desc )
{
	db_query( "INSERT INTO journo_weblink (journo_id,url,description,approved) VALUES (?,?,?,?)",
		$journo_id, $url, $desc, 't' );
	db_query( "DELETE FROM htmlcache WHERE name='j$journo_id'" );
	/* TODO: LOG THIS ACTION! */
	db_commit();
	EmitActionMsg( "Added Weblink ({$url})" );
}

function RemoveWeblink( $journo_id, $link_id )
{
	db_query( "DELETE FROM journo_weblink WHERE id=?", $link_id );
	db_query( "DELETE FROM htmlcache WHERE name='j$journo_id'" );
	EmitActionMsg( "Removed Weblink" );
	/* TODO: LOG THIS ACTION! */
	db_commit();
}

function ApproveWeblink( $journo_id, $link_id )
{
	db_query( "UPDATE journo_weblink SET approved=true WHERE id=?", $link_id );
	db_query( "DELETE FROM htmlcache WHERE name='j$journo_id'" );
	EmitActionMsg( "Approved Weblink $link_id" );
	/* TODO: LOG THIS ACTION! */
	db_commit();
}

function DisapproveWeblink( $journo_id, $link_id )
{
	db_query( "UPDATE journo_weblink SET approved=false WHERE id=?", $link_id );
	db_query( "DELETE FROM htmlcache WHERE name='j$journo_id'" );
	EmitActionMsg( "Disapproved Weblink $link_id" );
	/* TODO: LOG THIS ACTION! */
	db_commit();
}

function ApproveBio( $journo_id, $bio_id )
{
	db_query( "UPDATE journo_bio SET approved=true WHERE id=?", $bio_id );
	db_query( "DELETE FROM htmlcache WHERE name='j$journo_id'" );
	EmitActionMsg( "Approved Bio $bio_id" );
	/* TODO: LOG THIS ACTION! */
	db_commit();
}

function DisapproveBio( $journo_id, $bio_id )
{
	db_query( "UPDATE journo_bio SET approved=false WHERE id=?", $bio_id );
	db_query( "DELETE FROM htmlcache WHERE name='j$journo_id'" );
	EmitActionMsg( "Disapproved Bio $bio_id" );
	/* TODO: LOG THIS ACTION! */
	db_commit();
}




function EmitEmailAddresses( $journo_id )
{

?>
<h3>Email Addresses</h3>
<?php

	$rows = db_getAll( "SELECT * FROM journo_email WHERE journo_id=?", $journo_id );

	if( $rows )
	{

?>
	<ul>
<?php

		foreach( $rows as $r )
		{
			$id = $r['id'];
			$email = $r['email'];
			$srcurl = $r['srcurl'];
			$srctype = $r['srctype'];
			$approved = ($r['approved']=='t');

			$removelink = (
				"<a href=\"?action=remove_email&journo_id=$journo_id&email_id=$id\">remove</a>");
			
			if ( $approved )
			{
				$divclass = 'approved';
				$approvelink = sprintf(
					"<a href=\"?action=disapprove_email&journo_id=%s&email_id=%s\">disapprove</a>",
					$journo_id, $id );
			}
			else
			{
				$divclass = 'unapproved';
				$approvelink = sprintf(
					"<a href=\"?action=approve_email&journo_id=%s&email_id=%s\">approve</a>",
					$journo_id, $id );
			}
			
            $desc = '';
            if( $srcurl || $srctype )
                $desc = "(srctype: '$srctype' srcurl: '$srcurl') ";

            if( !$email )
                $email = "<em>- Blank Address -</em>\n";

			print " <li>\n";
			print (" <div class=\"$divclass\">[$id] $email $desc" .
			       "<small>[$removelink] [$approvelink]</small></div>");
			print " </li>\n";
		}
?>
	</ul>
<?php

	}
	else
	{
		print( "<p>-- no email addresses --</p>\n" );
	}

?>
<form method="post">
email: <input type="text" name="email" size="80" />
<?php

print form_element_hidden( 'action', 'add_email' );
print form_element_hidden( 'journo_id', $journo_id );

?>
<input type="submit" name="submit" value="Add Email Address" />
</form>
<small><p>
Note: add a blank address to suppress all email display on the journos page.
This will also suppress any guessing of addresses based on previously-published
articles.
</p></small>
<?php

}

/* Form to confirm that email address _should_ be removed from this journo */
function ConfirmRemoveEmail( $journo_id, $email_id )
{
	$l = db_getRow( "SELECT * FROM journo_email WHERE id=?", $email_id );
	$journo = db_getRow( "SELECT * FROM journo WHERE id=?", $journo_id );

?>
<form method="post" action="/adm/journo">
<p>Are you sure you want to remove
<code><?=$l['email']?></code>
from <?=$journo['prettyname'];?>?<br />
<input type="hidden" name="email_id" value="<?=$email_id;?>" />
<input type="hidden" name="journo_id" value="<?=$journo_id;?>" />
<input type="hidden" name="action" value="remove_email_confirmed" />
<input type="submit" name="submit" value="Yes!" />
<a href="?journo_id=<?=$journo_id;?>">No, I've changed my mind</a>
</form>
<?php

}


function AddEmail( $journo_id, $email )
{
	db_query( "INSERT INTO journo_email (journo_id,email,srctype,srcurl,approved) VALUES (?,?,?,?,?)",
		$journo_id, $email, '', '', 't' );
	db_query( "DELETE FROM htmlcache WHERE name='j$journo_id'" );
	/* TODO: LOG THIS ACTION! */
	db_commit();
	EmitActionMsg( "Added Email address ($email)" );
}

function RemoveEmail( $journo_id, $email_id )
{
	db_query( "DELETE FROM journo_email WHERE id=?", $email_id );
	db_query( "DELETE FROM htmlcache WHERE name='j$journo_id'" );
	EmitActionMsg( "Removed Email\n" );
	/* TODO: LOG THIS ACTION! */
	db_commit();
}

function ApproveEmail( $journo_id, $email_id )
{
	db_query( "UPDATE journo_email SET approved=true WHERE id=?", $email_id );
	db_query( "DELETE FROM htmlcache WHERE name='j$journo_id'" );
	EmitActionMsg( "Approved email (id $email_id)" );
	/* TODO: LOG THIS ACTION! */
	db_commit();
}

function DisapproveEmail( $journo_id, $email_id )
{
	db_query( "UPDATE journo_email SET approved=false WHERE id=?", $email_id );
	db_query( "DELETE FROM htmlcache WHERE name='j$journo_id'" );
	EmitActionMsg( "Disapproved Email (id $email_id)" );
	/* TODO: LOG THIS ACTION! */
	db_commit();
}



/* show a list of other articles attributed to this journo */
function EmitOtherArticles( $journo_id )
{

	print "<h3>Other Articles (not covered by scrapers)</h3>\n";

	$sql = <<<EOT
SELECT * FROM journo_other_articles WHERE journo_id=? ORDER BY pubdate DESC
EOT;

	$rows = db_getAll( $sql, $journo_id );

?>
<p><?php echo sizeof($rows); ?> other articles:</p>
<table>
<thead><tr><th>url</th><th>title</th><th>pubdate</th><th>publication</th><th>status</th><th></th></tr></thead>
<tbody>
<?php foreach( $rows as $row ) { ?>

    <tr class="<?php echo $row['status']=='a' ? 'bio_approved':'bio_unapproved'; ?>">
        <td><a href="<?php echo $row['url'];?>"><?php echo $row['url'];?></a></td>
        <td><?php echo $row['title'];?></td>
        <td><?php $d=new DateTime( $row['pubdate'] ); echo $d->format('Y-m-d H:i:s');?></td>
        <td><?php echo $row['publication'];?></td>
        <td><?php echo $row['status'];?></td>
        <td>
			<a href="?action=remove_otherarticle_confirmed&journo_id=<?php echo $journo_id;?>&otherarticle_id=<?php echo $row['id']; ?>">remove</a>
<?php if( $row['status'] == 'h' ) { ?>
			<a href="?action=approve_otherarticle&journo_id=<?php echo $journo_id;?>&otherarticle_id=<?php echo $row['id']; ?>">approve</a>
<?php } else { ?>
			<a href="?action=disapprove_otherarticle&journo_id=<?php echo $journo_id;?>&otherarticle_id=<?php echo $row['id']; ?>">disapprove</a>
<?php } ?>
        <td>
    </tr>
<?php } ?>
</tbody>
</table>

<form method="post">
<input type="hidden" name="journo_id" value="<?php echo $journo_id; ?>" />
url: <input type="text" name="url" size="40" />
title: <input type="text" name="title" size="40" /><br/>
pubdate (yyyy-mm-dd): <input type="text" name="pubdate" />
publication: <input type="text" name="publication" /><br/>
<button type="submit" name="action" value="add_otherarticle">Add Other Article</button>
</form>

<?php

}


function AddOtherArticle( $journo_id, $url,$title,$pubdate,$publication )
{
	db_query( "INSERT INTO journo_other_articles (journo_id,url,title,pubdate,publication,status) VALUES (?,?,?,?,?,?)",
        $journo_id, $url, $title, $pubdate, $publication, 'a' );
	/* TODO: LOG THIS ACTION! */
	db_commit();
	EmitActionMsg( "Added other article ({$title} - {$url})" );
}

function RemoveOtherArticle( $journo_id, $otherarticle_id )
{
	db_query( "DELETE FROM journo_other_articles WHERE id=?", $otherarticle_id );
	EmitActionMsg( "Removed Other Article\n" );
	/* TODO: LOG THIS ACTION! */
	db_commit();
}

function ApproveOtherArticle( $journo_id, $otherarticle_id )
{
	db_query( "UPDATE journo_other_articles SET status='a' WHERE id=?", $otherarticle_id );
	EmitActionMsg( "Approved Other Article" );
	/* TODO: LOG THIS ACTION! */
	db_commit();
}

function DisapproveOtherArticle( $journo_id, $otherarticle_id )
{
	db_query( "UPDATE journo_other_articles SET status='h' WHERE id=?", $otherarticle_id );
	EmitActionMsg( "Disapproved Other Article" );
	/* TODO: LOG THIS ACTION! */
	db_commit();
}

