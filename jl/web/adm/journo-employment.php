<?php
// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../../phplib/db.php';
require_once '../phplib/adm.php';
require_once '../phplib/jlforms.php';
require_once '../phplib/jlmodel.php';


class Link extends jlModel
{
    function __construct() {
        $this->configure( 'link', array(
            'id'=>array('type'=>'int', 'pk'=>true),
            'url'=>array('type'=>'string'),
            'title'=>array('type'=>'string'),
            'pubdate'=>array('type'=>'datetime'),
            'publication'=>array('type'=>'string'),
        ) );

        parent::__construct();
    }
}

class Employment extends jlModel
{
    static public $kinds = array( 'e'=>'employment', 'f'=>'freelance' );

    function __construct() {
        $this->configure( 'journo_employment', array(
            'id'=>array('type'=>'int', 'pk'=>true),
            'journo_id'=>array('type'=>'int'),
            'employer'=>array('type'=>'string'),
            'job_title'=>array('type'=>'string'),
            'year_from'=>array('type'=>'int'),
            'year_to'=>array('type'=>'int'),
            'current'=>array('type'=>'bool'),
            'rank'=>array('type'=>'int','notnull'=>TRUE ),
            'kind'=>array('type'=>'string'),
            'src'=>array( 'type'=>'fk', 'othermodel'=>'Link' ),
        ) );

        parent::__construct();
    }

    function save() {
        if( !$this->src->isBlank()) {
            $this->src->save();
        }
        parent::save();
    }

    function buildForm() {
        $form = new jlForm('');
        $src_form = new jlForm('src');
        $src = $this->src;
        $src_form->addWidgets( array(
            new jlWidgetHidden('src[id]', $src->id),
            new jlWidgetInput('src[url]', $src->url),
            new jlWidgetInput('src[title]', $src->title),
            new jlWidgetInput('src[pubdate]', $src->pubdate),
            new jlWidgetInput('src[publication]', $src->publication),
        ) );

        $form->addWidgets( array(
            new jlWidgetHidden('id', $this->id ),
            new jlWidgetInput('employer', $this->employer ),
            new jlWidgetInput('job_title', $this->job_title),
            new jlWidgetInput('year_from', $this->year_from ),
            new jlWidgetInput('year_to', $this->year_to),
            new jlWidgetCheckbox('current', $this->current),
            new jlWidgetInput('rank', $this->rank),
            new jlWidgetSelect('kind', $this->kind, array('choices'=>self::$kinds ) ),
            new jlWidgetHidden( 'journo_id', $this->journo_id ),
            $src_form,
        ) );

        if( is_null( $this->pk() ) ) {
            $form->addWidget( new jlWidgetHidden( '_action','create' ) );
            $form->addWidget( new jlWidgetSubmit( 'create' ) );
        } else {
            $form->addWidget( new jlWidgetHidden( '_action','update' ) );
            $form->addWidget( new jlWidgetSubmit( 'update' ) );
        }

        return $form;
    }
}



$id = get_http_var( "id",null );
$journo_id = get_http_var( "journo_id",null );
if( is_null( $journo_id ) ) {
    $journo_id = db_getOne( "SELECT journo_id FROM journo_employment WHERE id=?", $id );
}
$journo = db_getRow( "SELECT * FROM journo WHERE id=?", $journo_id );

admPageHeader( $journo['ref'] . " Employment Info" );

$action = get_http_var( '_action' );
if($action == 'update' || $action=='create' ) {
    // form has been submitted
    $emp = new Employment();
    $emp->fromHTTPVars( $_POST );
/*
    print"<hr/><pre><code>\n";
    print_r( $_POST );
    print "--------\n";
    print_r( $emp );
    print"</code></pre><hr/>\n";
*/
    $emp->save();

?>
<div class="info">Saved.</div>
<?php

} else {
    $emp = new Employment();

    if( !$id ) {
        // it's new.
        $emp->journo_id = $journo_id;
    } else {
        // fetch from db
        $sql = <<<EOT
SELECT e.*,
        l.id as src__id,
        l.url as src__url,
        l.title as src__title,
        l.pubdate as src__pubdate,
        l.publication as src__publication
    FROM (journo_employment e LEFT JOIN link l ON e.src=l.id )
    WHERE e.id=?
EOT;
        $row = db_getRow( $sql, $id );
        $emp->fromDBRow( $row );
    }
/*    print"<pre>\n";
    print_r( $emp );
    print"</pre>\n";
 */
    $form = $emp->buildForm();

?>
    <h2><?= $id ? "Edit" : "Create New" ?> employment entry for <?= $journo['ref'] ?></h2>
<form action="" method="POST">
    <?= $form->render(); ?>
</form>
<?php
}

?>
    <a href="/adm/<?= $journo['ref'] ?>">Back to <?= $journo['ref'] ?></a>
<?php

admPageFooter();

?>
