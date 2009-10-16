<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/editprofilepage.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



class EmploymentPage extends EditProfilePage
{

    function __construct() {
        $this->pageName = "employment";
        $this->pageTitle = "Employment";
        $this->pagePath = "/profile_employment";
        $this->pageParams = array( 'head_extra_fn'=>array( &$this, 'extra_head' ) );
        parent::__construct();
    }


    function extra_head()
    {
        // TODO: use compressed jquery.autocomplete

?>
<link type="text/css" rel="stylesheet" href="/profile.css" /> 
<link type="text/css" rel="stylesheet" href="/css/jquery.autocomplete.css" />
<script type="text/javascript" src="/js/jquery-1.3.2.min.js"></script>
<script type="text/javascript" src="/js/jquery.form.js"></script>
<script type="text/javascript" src="/js/jquery.autocomplete.js"></script>
<script type="text/javascript" src="/js/jl-fancyforms.js"></script>

<script type="text/javascript">
    $(document).ready( function() {
        fancyForms( '.employer', function() {
            var f = $(this);

            f.find("input[name=employer]").autocomplete( "/ajax_employer_lookup" );

            var current = f.find("input[name=current]")
            var year_to = f.find("input[name=year_to]").closest('tr')
            year_to.toggle( ! current.attr('checked') );
            current.click( function() {
                year_to.toggle( ! current.attr('checked') );
            });
        });
    });
</script>
<?php
    }




    function displayMain()
    {
        // submitting new entries?
        $action = get_http_var( "action" );
        if( $action == "submit" ) {
            $this->handleSubmit();
        }
        if( get_http_var('remove_id') ) {
            $this->handleRemove();
        }

?>
<h2>Add Employment Information</h2>
<?php
        $employers = db_getAll( "SELECT * FROM journo_employment WHERE journo_id=? ORDER BY year_from DESC", $this->journo['id'] );
        foreach( $employers as $e ) {
            $this->showForm( $e );
        }

        $this->showForm( NULL );
    }



    function ajax()
    {
        header( "Cache-Control: no-cache" );
        $action = get_http_var( "action" );
        if( $action == "submit" ) {
            $entry_id = $this->handleSubmit();

            $result = array( 'status'=>'success',
                'id'=>$entry_id,
                'remove_link_html'=>$this->genRemoveLink( $entry_id ),
            );
            print json_encode( $result );
        }
    }


    /* return a "remove" link for the given item */
    function genRemoveLink( $entry_id ) {
        return <<<EOT
<a class="remove" href="{$this->pagePath}?ref={$this->journo['ref']}&remove_id={$entry_id}">remove</a>
EOT;
    }

    /* if $emp is null, then display a fresh form for entering a new entry */
    function showForm( $emp )
    {
        static $uniqID=0;

        /* the way the template form is used depends on if javascript is in use:
         * javascript on: template is hidden, and cloned to add new entries
         * javascript off: template is used to submit a new entry
         */

        $is_template = is_null( $emp );

        $uniq = "_{$uniqID}";
        $uniqID++;

        $classes = 'employer';
        if( $is_template ) {
            /* a dummy, blank entry */
            $emp = array( 'employer'=>'', 'job_title'=>'', 'year_from'=>'', 'year_to'=>'' );
            $classes .= " template";
        }


?>

<form class="<?= $classes; ?>" method="POST" action="<?= $this->pagePath; ?>">
<table border="0">
 <tr><th><label for="employer<?= $uniq; ?>">Employer</label></td><td><input type="text" size="60" name="employer" id="employer<?= $uniq; ?>" value="<?= h($emp['employer']); ?>"/></td></tr>
 <tr><th><label for="job_title<?= $uniq; ?>">Job Title</label></td><td><input type="text" size="60" name="job_title" id="job_title<?= $uniq; ?>" value="<?= h($emp['job_title']); ?>"/></td></tr>
 <tr><th><label for="year_from<?= $uniq; ?>">Year from</label></td><td><input type="text" size="4" name="year_from" id="year_from<?= $uniq; ?>" value="<?= h($emp['year_from']); ?>"/></td></tr>
 <tr><th><label for="year_to<?= $uniq; ?>">Year to</label></td><td><input type="text" size="4" name="year_to" id="year_to<?= $uniq; ?>" value="<?= h($emp['year_to']); ?>"/></td></tr>
 <tr><th></th><td><input type="checkbox" <?php if( !$emp['year_to'] ) { ?>checked <?php } ?>name="current" id="current<?= $uniq; ?>"/><label for="current<?= $uniq; ?>">I currently work here</label></td></tr>
</table>
<input type="hidden" name="ref" value="<?= $this->journo['ref']; ?>" />
<button class="submit" type="submit" name="action" value="submit">Save</button>
<button class="cancel" type="reset">Cancel</button>
<?php if( !$is_template ) { ?>
<input type="hidden" name="id" value="<?= $emp['id']; ?>" />
<?= $this->genRemoveLink($emp['id']); ?>
<?php } ?>
</form>
<?php

    }



    function handleSubmit()
    {
        $fieldnames = array( 'employer', 'job_title', 'year_from', 'year_to' );
        $item = $this->genericFetchItemFromHTTPVars( $fieldnames );
        if( get_http_var( 'current' ) )
            $item['year_to'] = NULL;
        $this->genericStoreItem( "journo_employment", $fieldnames, $item );
        return $item['id'];
    }

    function handleRemove() {
        $id = get_http_var("remove_id");

        // include journo id, to stop people zapping other journos entries!
        db_do( "DELETE FROM journo_employment WHERE id=? AND journo_id=?", $id, $this->journo['id'] );
        db_commit();
    }
}





$page = new EmploymentPage();
$page->run();


