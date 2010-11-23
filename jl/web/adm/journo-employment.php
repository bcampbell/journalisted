<?php
// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../../phplib/db.php';
require_once '../phplib/adm.php';
require_once '../phplib/jlforms.php';

class EmpEditor extends jlForm
{
    static public $kinds = array( 'e'=>'employment', 'f'=>'freelance' );

    public function __construct( $data=array() )
    {
        $this->addWidgets( array(
            new jlWidgetInput('employer'),
            new jlWidgetInput('job_title'),
            new jlWidgetInput('year_from'),
            new jlWidgetInput('year_to'),
            new jlWidgetCheckbox('current'),
            //'current' => new sfWidgetFormInputCheckbox(),
            new jlWidgetInput('rank'),
            new jlWidgetSelect('kind', array('choices'=>self::$kinds ) ),
            new jlWidgetHidden( 'journo_id' ),
            new jlWidgetHidden( '_action' ),
        ) );

        // are we editing, or creating?
        if( isset( $data['id'] ) && $data['id'] ) {
            // editing
            $data['_action'] = 'update';
            $this->addWidget( new jlWidgetHidden( 'id' ) );
            $this->addWidget( new jlWidgetSubmit( 'update' ) );
        } else {
            // creating new
            $data['_action'] = 'create';
            $this->addWidget( new jlWidgetSubmit( 'add' ) );
        }

        $this->populate( $data );

        parent::__construct( 'emp' );
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
    $emp->fromHTTPVars();
    $emp->save();

?>
<div class="info">Saved.</div>
<?php

} else {
    $emp = new Employment();
    if( is_null( $id ) ) {
        // it's new.
        $emp->journo_id = $journo_id;
    } else {
        // fetch from db
        $row =  db_getRow( "SELECT * FROM journo_employment WHERE id=?", $id );
        $emp->fromDBRow( $row );
    }
    // show form
    $f = new EmpEditor( $emp->data() );

?>
    <h2><?= $id ? "Edit Employment" : "Create New" ?></h2>
    <?= $f->render(); ?>
<?php

}

?>
    <a href="/adm/<?= $journo['ref'] ?>">Back to journo</a>
<?php

admPageFooter();




class Employment
{
    public $table = 'journo_employment';
    public $pk = 'id';
    public $fields = array(
        'id'=>array('type'=>'int', 'pk'=>true),
        'journo_id'=>array('type'=>'int'),
        'employer'=>array('type'=>'string'),
        'job_title'=>array('type'=>'string'),
        'year_from'=>array('type'=>'int'),
        'year_to'=>array('type'=>'int'),
        'current'=>array('type'=>'bool'),
        'rank'=>array('type'=>'int'),
        'kind'=>array('type'=>'string'),
    );

    function __construct() {
        foreach( $this->fields as $f=>&$def ) {
            if( !isset($def['pk'] ) ) {
                $def['pk'] = false;
            }
        }
    }

    function data() {
        $data = array();
        foreach( $this->fields as $f=>&$def ) {
            $data[$f] = $this->$f;
        }
        return $data;
    }

    function fromHTTPVars() {
        foreach( $this->fields as $f=>$def ) {
            $v = get_http_var( $f, null );
            switch( $def['type'] ) {
                case 'string': $this->$f = $v; break;
                case 'int': $this->$f = intval($v); break;
                case 'bool': $this->$f = $v ? TRUE:FALSE; break;
                default: $this->$f=null; break;
            }
        }
    }

    function fromDBRow( $row ) {
        foreach( $this->fields as $f=>$def ) {
            $v = $row[$f];
            switch( $def['type'] ) {
                case 'string': $this->$f = $v; break;
                case 'int': $this->$f = intval($v); break;
                case 'bool': $this->$f = ($v=='t') ? TRUE:FALSE; break;
                default: $this->$f=null; break;
            }
        }
    }

    function save() {
        if( $this->{$this->pk} ) {
            // update existing entry
            $frags = array();
            $params = array();
            foreach( $this->fields as $f=>$def ) {
                if( !$def['pk'] ) {
                    $frags[] = "$f=?";
                    $params[] = $this->$f;
                }
            }

            /* TODO: restrict by journo id to stop people hijacking others entries! */
            $sql = "UPDATE {$this->table} SET " . implode( ',', $frags ) . " WHERE id=?";
            $params[] = $this->{$this->pk};

            db_do( $sql, $params );

//           eventlog_Add( "modify-{$this->pageName}", $this->journo['id'], $item );

        } else {
            /* insert new entry */
            $frags = array();
            $params = array();
            $insert_fields = array();
            foreach( $this->fields as $f=>$def ) {
                if( !$def['pk'] ) {
                    $insert_fields[] = $f;
                    $frags[] = "?";
                    $params[] = $this->$f;
                }
            }
            $sql = "INSERT INTO {$this->table} (" . implode( ",", $insert_fields ) . ") ".
                "VALUES (" . implode(',',$frags) . ")";
            print $sql;
            db_do( $sql, $params );
            $this->{$this->pk} = db_getOne( "SELECT lastval()" );
//            eventlog_Add( "add-{$this->pageName}", $this->journo['id'], $item );
        }
        db_commit();
    }
}


?>
