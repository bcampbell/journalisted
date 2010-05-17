<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/editprofilepage.php';
require_once '../phplib/eventlog.php';
require_once '../phplib/hresume.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';


/* page to manage importing data from other places (eg LinkedIn) */


// case-insensitive, alpha-only string compare
function cmp_alpha( $s1, $s2 ) {
    $s1 = preg_replace( '/[^a-z]/', '', strtolower($s1) );
    $s2 = preg_replace( '/[^a-z]/', '', strtolower($s2) );
    return $s1 == $s2; 
}


// return TRUE if the two education entries are similar enough
// to be considered identical
function cmp_edu( $e1, $e2 ) {
    if( $e1['year_from'] == $e2['year_from'] &&
        $e1['year_to'] == $e2['year_to'] &&
        cmp_alpha($e1['school'],$e2['school']) ) {
        return TRUE;
    } else {
        return FALSE;
    }
}

// return TRUE if the two experience entries are similar enough
// to be considered identical
function cmp_exp( $e1, $e2 ) {
    if( $e1['year_from'] == $e2['year_from'] &&
        $e1['year_to'] == $e2['year_to'] &&
        cmp_alpha($e1['employer'],$e2['employer']) &&
        cmp_alpha($e1['job_title'],$e2['job_title']) ) {
        return TRUE;
    } else {
        return FALSE;
    }
}


function find_in_array( $needle, $haystack, $compare_fn ) {
    foreach( $haystack as $key => $value) {
        if( $compare_fn( $value, $needle) ) {
            return $key;
        }
    }
    return FALSE;
}  


class ImportProfilePage extends EditProfilePage
{

    function __construct() {
        $this->pageName = "import";
        $this->pageTitle = "Import Profile";
        $this->pagePath = "/profile_import";
        $this->pageParams = array( 'head_extra_fn'=>array( &$this, 'extra_head' ) );
        parent::__construct();


        $this->imported = null;
    }


    function extra_head()
    {
?>
<?php
    }


    function handleActions()
    {
        // submitting new entries?
        $action = get_http_var( "action" );
        if( $action == "import_profile" ) {
            $this->doImportProfile();
        }
    }

    function doImportProfile()
    {
        $url = get_http_var( 'url' );
        $data = hresume_import( $url );

        $this->raw = $data;
        $this->imported = array( 'education'=>array(), 'experience'=>array() );
        if( $data['education'] ) {
            $this->importEducation( $data['education'] );
        }
        if( $data['experience'] ) {
            $this->importExperience( $data['experience'] );
        }
    }


    function importEducation( $new_edus ) {
        $fields = array( 'year_from','year_to','school','field','qualification' );
        // only want education entries we don't already have
        $existing_edus = db_getAll( "SELECT * FROM journo_education WHERE journo_id=?", $this->journo['id'] );
        foreach( $new_edus as $edu ) {
            if( find_in_array( $edu, $existing_edus, 'cmp_edu' ) === FALSE ) {
                $edu['id'] = null;
                $this->genericStoreItem( 'journo_education', $fields, $edu );
                $this->imported['education'][] = $edu;
            }
        }
    }


    function importExperience( $new_exps ) {
        $fields = array( 'year_from','year_to','current','employer','job_title' );
        // only want experience entries we don't already have
        $existing_exps = db_getAll( "SELECT * FROM journo_employment WHERE journo_id=?", $this->journo['id'] );
        foreach( $new_exps as $exp ) {
            if( find_in_array( $exp, $existing_exps, 'cmp_exp' ) === FALSE ) {
                $exp['id'] = null;
                $this->genericStoreItem( 'journo_employment', $fields, $exp );
                $this->imported['experience'][] = $exp;
            }
        }
    }


    function display()
    {

?>
<h2>Import information from LinkedIn</h2>
<?php

        if( !is_null( $this->imported ) ) {
            $this->displayResults();
        } else {
            $this->displayImportForm();
        }
    }


    function displayImportForm()
    {
        $url = get_http_var('url');

?>

<p>
If you've got a public profile on <a href="http://www.linkedin.com">LinkedIn.com</a>,
you can import it to journa<i>listed</i> profile.
</p>
<form action="<?= $this->pagePath ?>" method="POST">
  <dl>
  <dt><label for="url">Your LinkedIn URL:</label></dt>
  <dd><input type="text" size="80" name="url" id="url" value="<?= h($url) ?>" /><br/>
  <span class="explain">e.g. "http://www.linkedin.com/in/<?= preg_replace('/-/','',$this->journo['ref'] ) ?>"</span>
  </dd>
  <input type="hidden" name="ref" value="<?=$this->journo['ref'];?>" />
  <input type="hidden" name="action" value="import_profile" />
  <button class="submit" type="submit">Import</button> or <a href="/<?= $this->journo['ref'] ?>">cancel</a>
</form>
<?php
    }


    function displayResults()
    {
        if( !$this->imported['education'] && !$this->imported['experience'] ) {
?>
<p>Sorry, no new information was able to be imported.</p>

<a href="/<?= $this->journo['ref'] ?>#tab-bio">Go back to your profile page</a>
<?php
        } else {
?>

<p>Thanks - the following information has been added to your profile:</p>

<ul>
<?php foreach( $this->imported['education'] as $e ) { ?>
  <li><?= $e['school'] ?> (<?= $e['year_from'] ?>-<?= $e['year_to'] ?>)</li>
<?php } ?>
<?php foreach( $this->imported['experience'] as $e ) { ?>
  <li><?= $e['job_title']?> at <?= $e['employer'] ?> (<?= $e['year_from'] ?>-<?= $e['year_to'] ?>)</li>
<?php } ?>
</ul>

<a href="/<?= $this->journo['ref'] ?>#tab-bio">View your profile</a>
<?php
        }

    }




}


$page = new ImportProfilePage();
$page->run();

?>

