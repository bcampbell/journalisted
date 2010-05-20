<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/person.php';

require_once '../phplib/passwordbox.php';




account_page();


/*

- add articles
- upload picure
- edit profile
- add admired journos
- sign up to newsletter
- set up alerts
- show pending claim

*/



function account_page()
{

    $r = array( 'reason_web' => "Log in",
        'reason_email' => "Log in to Journalisted",
        'reason_email_subject' => 'Log in to Journalisted' );
    $P = person_signon($r);

    $passwordbox = new PasswordBox();

    // linked to a journo for editing (or claim pending)?
    $sql = <<<EOT
SELECT j.*, perm.permission
    FROM journo j INNER JOIN person_permission perm
        ON perm.journo_id=j.id
    WHERE perm.permission in ('edit','claimed') AND perm.person_id=?
    LIMIT 1
EOT;
    $journo = db_getRow( $sql, $P->id() );


    // signed up for newsletters?
    $newsletter = db_getOne( "SELECT person_id FROM person_receives_newsletter WHERE person_id=?", $P->id() ) ? TRUE:FALSE;

    // how many alerts set up?
    $alert_cnt = db_getOne( "SELECT count(*) FROM alert WHERE person_id=?", $P->id() );

    // what bits of profile have been filled in?
    $photo_cnt = 0;
    $edu_cnt = 0;
    $emp_cnt = 0;
    $book_cnt = 0;
    $award_cnt = 0;
    $admired_cnt = 0;
    if( !is_null($journo) ) {
        $photo_cnt = db_getOne( "SELECT count(*) FROM journo_photo WHERE journo_id=?", $journo['id'] );
        $edu_cnt = db_getOne( "SELECT count(*) FROM journo_education WHERE journo_id=?", $journo['id'] );
        $emp_cnt = db_getOne( "SELECT count(*) FROM journo_employment WHERE journo_id=?", $journo['id'] );
        $book_cnt = db_getOne( "SELECT count(*) FROM journo_books WHERE journo_id=?", $journo['id'] );
        $award_cnt = db_getOne( "SELECT count(*) FROM journo_awards WHERE journo_id=?", $journo['id'] );
        $admired_cnt = db_getOne( "SELECT count(*) FROM journo_admired WHERE journo_id=?", $journo['id'] );

        // combined article count (ugh)
        $sql = <<<EOT
SELECT (
    SELECT COUNT(*)
        FROM (article a INNER JOIN journo_attr attr ON attr.journo_id=a.id)
        WHERE a.status='a' AND attr.journo_id=?
    ) + (
    SELECT COUNT(*)
        FROM journo_other_articles
        WHERE status='a' AND journo_id=?
    )
EOT;
        $article_cnt = db_getOne( $sql, $journo['id'], $journo['id'] );
    }

    $name_or_email = $P->name_or_blank() ? $P->name : $P->email;

    $title = "Your account";

    page_header( $title );


    $n=0;   // track the number of items we're displaying


    if( !is_null($journo) && $journo['permission']=='edit' && $journo['status'] =='i' ) {
        emit_inactive_note($journo);
    }


?>
<div class="main account">

<h2>Welcome to journa<i>listed</i>, <?= $name_or_email ?></h2>
<?php
    /* show a bunch of things user could/should do now... */
 
    if( !is_null( $journo ) ) {
        if( $journo['permission'] == 'claimed' ) {
            emit_claim_pending($journo);
        }
        if( $journo['permission'] == 'edit' ) {
//            print"<pre>You've got $article_cnt articles</pre>";
?>
<a class="dostuff" href="/<?= $journo['ref'] ?>">View your profile</a>
<?php
            if( $article_cnt<5 ) {
                emit_add_articles( $journo ); ++$n;
            }
            if( $photo_cnt==0 ) {
                emit_add_photo($journo); ++$n;
            }
            if( $edu_cnt==0 && $emp_cnt==0 ) {
                emit_add_information( $journo ); ++$n;
            }
            if( $admired_cnt==0 ) {
                emit_add_admired( $journo ); ++$n;
            }

        }
    }

    if( ( $alert_cnt==0 && $n<4) || $n<2 ) {
        emit_add_alerts(); ++$n;
    }

    if( ( !$newsletter && $n<4) || $n<2 ) {
        emit_subscribe_to_newsletter(); ++$n;
    }

?>

</div>  <!-- end main -->

<div class="sidebar">
<div class="box">
  <div class="head">
    <h3><?= $passwordbox->title() ?></h3>
  </div>
  <div class="body">
  <?php $passwordbox->emit(); ?>
  <p>If you need to change your email address, please <?= SafeMailto( OPTION_TEAM_EMAIL, "let us know") ?></p>
  </div>
  <div class="foot"></div>
</div>
</div> <!-- end sidebar -->
<?php

    page_footer();
}





function emit_claim_pending( &$journo ) {

?>
<div class="infomessage">
<p>
You have claimed to be <a href="/<?= $journo['ref'] ?>"><?= $journo['prettyname'] ?></a>.
</p>
<p>
To avoid mix ups - deliberate or otherwise - registrations are manually examined before being activated.
</p>
<p>
We will email you when your profile is available for editing
</p>
</div>
<?php

}


function emit_add_articles( &$journo )
{

?>
<div class="accountaction">
<h3>Add articles</h3>
<p>Add articles you have written that are published on
the web</p>

<a class="dostuff" href="/missing?j=<?= $journo['ref'] ?>">Add articles</a>
</div>
<?php

}


function emit_inactive_note( $journo ) {
?>
<div class="not-public">
  <p><strong>Please Note:</strong>
  Your <a href="/<?= $journo['ref'] ?>">public profile</a> is not yet active.
  It will be switched on once you have <a href="/missing?j=<?= $journo['ref'] ?>">added</a> five articles.
  </p>
</div>
<?php
}

function emit_add_photo( &$journo ) {

?>
<div class="accountaction">
<h3>Upload a profile picture</h3>
<p>
<img width="64" height="64" src="/img/rupe.png" alt="no photo" />
</p>
<a class="dostuff" href="/profile_photo?ref=<?= $journo['ref'] ?>">Add photo</a>
</div>
<?php

}

function emit_add_information( &$journo )
{

?>
<div class="accountaction">
<h3>Fill out your profile information</h3>

<p>
Add your experience, education, awards and links to other
profile information on the web
</p>

<a class="dostuff" href="/<?= $journo['ref'] ?>#bio-tab">Edit profile</a>
</div>
<?php

}


function emit_add_admired( &$journo ) {

?>
<div class="accountaction">
<h3>Which journalists do you admire?</h3>
<p>Point people to other journalists you admire –
recommendations make the web go round</p>

<a class="dostuff" href="/profile_admired?ref=<?= $journo['ref'] ?>">Add journalists</a>
</div>
<?php

}


function emit_add_alerts() {

?>
<div class="accountaction">
<h3>Set up email alerts</h3>
<p>Follow your favourite journalist(s).</p>
<a class="dostuff" href="/weeklydigest">Set up alerts</a>
</div>
<?php

}


function emit_subscribe_to_newsletter()
{

?>
<div>
<h3>Subscribe to the weekly digest</h3>
<p>
You are not subscribed to the weekly digest.
</p>
<a class="dostuff" href="/weeklydigest">Subscribe</a>
</div>
<?php

}

?>

