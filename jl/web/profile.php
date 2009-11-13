<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/misc.php';
require_once '../phplib/gatso.php';
require_once '../phplib/cache.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



/* get journo identifier (eg 'fred-bloggs') */

$ref = strtolower( get_http_var( 'ref' ) );
$journo = NULL;

if( $ref ) {
    $journo = db_getRow( "SELECT * FROM journo WHERE status='a' AND ref=?", $ref );
    if( !$journo ) {
        header("HTTP/1.0 404 Not Found");
        exit(1);
    }
}

$login_reasons = array(
    'reason_web' => "Edit your page on Journalisted",
    'reason_email' => "Edit your page on Journalisted",
    'reason_email_subject' => "Edit your page on Journalisted"
    );

$P = person_if_signed_on();

if( is_null($journo) ) {
    // no journo given - if person is logged on, see if they are associated with a journo (or journos)
    if( $P ) {
        $editables = db_getAll( "SELECT j.* FROM ( journo j INNER JOIN person_permission p ON p.journo_id=j.id) WHERE p.person_id=? AND p.permission='edit' AND j.status='a'", $P->id() );

        if( sizeof( $editables) > 1 ) {
            /* let user pick which one... */
            page_header("");

?>
<p>Which one do you want to edit?</p>
<ul>
<?php foreach( $editables as $j ) { ?>
<li><a href="/profile?ref=<?= $j['ref'] ?>"><?= $j['prettyname'] ?></a></li>
<?php } ?>
</ul>
<?php

            page_footer();
            exit;
        }

        if( sizeof( $editables ) == 1 )
            $journo = $editables[0];        // just one journo.
    }
}



// if logged in and have access to the journo, redirect.
if( $journo && $P ) {
    // is this person allowed to edit this journo?
    if( db_getOne( "SELECT id FROM person_permission WHERE person_id=? AND journo_id=? AND permission='edit'",
        $P->id(), $journo['id'] ) ) {

        showPage( $journo );
        // yes - just redirect to the first profile page
//        header( "Location: /profile_admired?ref={$journo['ref']}" );
            exit();
    }
}

showRegistration( $journo );



function showRegistration( $journo )
{

$title = "Edit profile";
if( $journo )
    $title = "Edit profile for " . $journo['prettyname'];
page_header( $title );

?>
<?php if( $journo ) { ?>
<h2>Edit profile for <a href="/<?=$journo['ref'];?>"><?=$journo['prettyname'];?></a></h2>
<?php } else { ?>
<h2>Edit profile</h2>
<?php } ?>

<p>already got an account? <a href="/login">log in now!</a>

<p><strong>or...</strong></p>

<p>It's easy to get set up to edit your profile! But we need to know you are who you say you are...</p>
Here's how:
<ol>
<li>blah blah</li>
<li>references</li>
<li>blah blah</li>
<li>email us</li>
<li>blah blah we'll send you a login link blah blah</li>
</ol>

<?php

page_footer();
}


function collectProfileData( $journo )
{
    $data = $journo;
    $data['employers'] = db_getAll( "SELECT * FROM journo_employment WHERE journo_id=? ORDER BY year_to DESC", $journo['id'] );
    $data['education'] = db_getAll( "SELECT * FROM journo_education WHERE journo_id=? ORDER BY year_to DESC", $journo['id'] );
    $data['awards'] = db_getAll( "SELECT * FROM journo_awards WHERE journo_id=? ORDER BY year DESC", $journo['id'] );
    $data['books'] = db_getAll( "SELECT * FROM journo_books WHERE journo_id=? ORDER BY year_published DESC", $journo['id'] );

    $sql = <<<EOT
SELECT j.prettyname, j.ref, j.oneliner
    FROM (journo_admired a INNER JOIN journo j ON j.id=a.admired_id)
    WHERE a.journo_id=?
EOT;
    $data['admired'] = db_getAll( $sql, $journo['id'] );

    $data['picture'] = journo_getPicture( $journo['id'] );

    return $data;
}


function showPage( $journo )
{
    $title = "Edit profile for " . $journo['prettyname'];
    page_header( $title, array( 'head_extra_fn'=>'extra_head' )  );

    $data = collectProfileData( $journo );
    extract( $data );

?>

<h2>Welcome, <a href="/<?=$journo['ref'];?>"><em><?=$journo['prettyname'];?></em></a></h2>
<h2>Edit Profile</h2>
<div class="profile-box">
 <h3>Photo:</h3>
  <div class="picture">
<?php if( $picture ) { ?>
    <img src="<?= $picture['url']; ?>" />
<?php } else { ?>
    <img src="/images/rupe.gif" />
<?php } ?>
    <a class="edit" href="/profile_picture?ref=<?= $ref ?>">edit</a>
  </div>
</div>


<div class="profile-box">
 <h3>Admired Journalists:</h3>
  <ul>
<?php foreach( $admired as $a ) { ?>
   <li><?=journo_link($a) ?></li>
<?php } ?>
  </ul>
  <a class="edit" href="/profile_admired?ref=<?= $ref ?>">edit</a>
</div>

<div class="profile-box">
  <h3>Experience</h3>
    <ul>
<?php foreach( $employers as $e ) { ?>
 <?php if( $e['year_to'] ) { ?>
      <li><span class="jobtitle"><?= $e['job_title'] ?></span>, <span class="publication"><?= $e['employer'] ?></span><br/>
        <span class="daterange"><?= $e['year_from'] ?>-<?= $e['year_to'] ?></span></li>
 <?php } else { ?>
      <li class="current-employer" ><span class="jobtitle"><?= $e['job_title'] ?></span>, <span class="publication"><?= $e['employer'] ?></span><br/>
        <span class="daterange"><?= $e['year_from'] ?>-Present</span></li>
 <?php } ?>
<?php } ?>
    </ul>
    <a class="edit" href="/profile_employment?ref=<?= $ref ?>">edit</a>
</div>


<div class="profile-box">
  <h3>Education</h3>
    <ul>
<?php foreach( $education as $edu ) { ?>
      <li>
        <?= $edu['school']; ?><br/>
        <?= $edu['qualification']; ?>, <?=$edu['field']; ?><br/>
        <span class="daterange"><?= $edu['year_from']; ?>-<?= $edu['year_to']; ?></span><br/>
      </li>
<?php } ?>
    </ul>
    <a class="edit" href="/profile_education?ref=<?= $ref ?>">edit</a>
</div>


<div class="profile-box">
  <h3>Books</h3>
    <ul>
<?php foreach( $books as $b ) { ?>
    <li><?= $b['title']; ?> (<?= $b['publisher']; ?>, <?= $b['year_published']; ?>)</li>
<?php } ?>
    </ul>
    <a class="edit" href="/profile_books?ref=<?= $ref ?>">edit</a>
</div>


<div class="profile-box">
  <h3>Awards Won</h3>
    <ul>
<?php foreach( $awards as $a ) { ?>
    <li><?php if( $a['year'] ) { ?><?= $a['year'] ?>: <?php } ?><?= $a['award']; ?></li>
<?php } ?>
    </ul>
    <a class="edit" href="/profile_awards?ref=<?= $ref ?>">edit</a>
</div>


<?php

     page_footer();
}

function extra_head()
{
?>
<link type="text/css" rel="stylesheet" href="/profile.css" /> 
<?php
}

?>
