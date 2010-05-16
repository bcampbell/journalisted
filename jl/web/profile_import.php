<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/editprofilepage.php';
require_once '../phplib/eventlog.php';
require_once '../phplib/hresume.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';


/* page to manage importing data from other places (eg linkedin) */


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
            $url = get_http_var( 'url' );
            $data = hresume_import( $url );

            $this->imported = array( 'education'=>array(), 'experience'=>array() );

            $this->importEducation( $data['education'] );
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
        $this->showImportForm();
    }

    function showImportForm()
    {
        $url = get_http_var('url');

?>
<h2>Import your linkedin profile</h2>
<p>Instructions go here</p>
<form action="<?= $this->pagePath ?>" method="POST">
    <input type="text" size="60" name="url" id="url" value="<?= $url ?>" />
    <input type="hidden" name="ref" value="<?=$this->journo['ref'];?>" />
    <input type="hidden" name="action" value="import_profile" />
    <button class="submit" type="submit">Import</button>
</form>


<?php if( $this->imported ) { ?>
<h3>Imported:</h3>
<pre>
Education:

<?php print_r( $this->imported['education'] ); ?>


Experience:
<?php print_r( $this->imported['experience'] ); ?>

</pre>
<?php } ?>

<?php

    }




}


$page = new ImportProfilePage();
$page->run();

?>

