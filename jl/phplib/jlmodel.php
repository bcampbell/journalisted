<?php
// model class for describing things for building admin interfaces
// Based (very very roughly) on django model, but only implementing
// enough to build a working admin interface from.

class jlModel
{
    // these should all be done using overridden static vars, but
    // php late static binding blah blah blah big pile of poo.
    // So we use non-static member vars via configure() instead.
    protected $pk = null;
    protected $fields = null;
    protected $table = null;

    function __construct() {
    }

    protected function configure( $table, $fields ) {
        $this->table = $table;
        $this->fields = array();
        $default_def = array( 'type'=>'string', 'pk'=>FALSE, 'notnull'=>FALSE );
        foreach( $fields as $f=>$def ) {
            $def = array_merge( $default_def, $def );
            $this->fields[ $f ] =  $def;
            if( $def['pk'] ) {
                // TODO: assert is_null( $this->pk ); (only support one pk)
                $this->pk = $f;
            }

            // set the default value
            switch( $def['type'] ) {
                case 'int': $this->$f = ($def['notnull'] ? 0 : null); break;
                case 'bool': $this->$f = false; break;
                case 'string': $this->$f = ''; break;
                case 'datetime': $this->$f = null; break;
                case 'fk': $this->$f = new $def['othermodel'](); break;
            }

        }
        // TODO: assert pk not null
    }

    // return the members of the model as an array
    function data() {
        $data = array();
        foreach( $this->fields as $f=>&$def ) {
            $data[$f] = $this->$f;
        }
        return $data;
    }

    // return the value of the primary key
    // (null if not assigned yet)
    function pk() {
        return $this->{$this->pk};
    }


    function isBlank() {
        foreach( $this->fields as $f=>$def ) {
            if( $this->$f ) {
                return FALSE;
            }
        }
        return TRUE;
    }

    function fromHTTPVars( $data ) {
        if( is_null($data) ) {
            $data=array();
        }

        // for each field in the model...
        foreach( $this->fields as $f=>$def ) {
            $v =  isset($data[$f]) ? $data[$f]:null;
            switch( $def['type'] ) {
                case 'string':
                case 'datetime':
                    $this->$f = $v;
                    break;
                case 'int':
                    if( $v==='' or is_null( $v) ) {
                        $this->$f = ($def['notnull'] ? 0 : null); break;
                    } else {
                        $this->$f = intval($v);
                    }
                    break;
                case 'bool':
                    $this->$f = $v ? TRUE:FALSE;
                    break;
                case 'fk':
                    $this->$f = new $def['othermodel']();
                    $this->$f->fromHTTPVars( $v );
                    break;
                default:
                    $this->$f=null;
                    break;
            }
        }
    }

    function fromDBRow( $row, $containingField=null ) {
        foreach( $this->fields as $f=>$def ) {
            $idx = $f;
            if( !is_null( $containingField ) ) {
                $idx = "{$containingField}__{$f}";
            }
            switch( $def['type'] ) {
                case 'string':
                case 'datetime':
                    $v = $row[$idx];
                    $this->$f = $v;
                    break;
                case 'int':
                    $v = $row[$idx];
                    if( is_null($v) ) {
                        $this->$f = null;
                    } else {
                        $this->$f = intval( $v );
                    }
                    break;
                case 'bool':
                    $v = $row[$idx];
                    if( $v == 't' ) {
                        $this->$f = TRUE;
                    } else {
                        $this->$f = FALSE;
                    }
                    break;
                case 'fk':
                    // contained models are assumed to have been LEFT JOINed
                    // into the query, so if missing, all the fields will be
                    // null (which is fine - we'll have a blank obj).
                    $this->$f = new $def['othermodel']();
                    $this->$f->fromDBRow( $row, $idx );
                    break;
                default:
                    $this->$f=null;
                    break;
            }
        }
    }

    function save() {
        // NOTE: expects member fk objects to already have been saved
        if( $this->pk() ) {
            // update existing entry
            $frags = array();
            $params = array();
            foreach( $this->fields as $f=>$def ) {
                if( !$def['pk'] ) {

                    switch( $def['type'] ) {
                    case 'fk':
                        $frags[] = "$f=?";
                        $params[] = is_null($this->$f)?null:$this->$f->pk();
                        break;
                    case 'datetime':
                        $frags[] = "$f=?";
                        $params[] = $this->$f ? $this->$f: null;
                        break;
                    default:
                        $frags[] = "$f=?";
                        $params[] = $this->$f;
                        break;
                    }
                }
            }

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
                    switch( $def['type'] ) {
                    case 'fk':
                        $insert_fields[] = $f;
                        $frags[] = "?";
                        $params[] = is_null($this->$f)?null:$this->$f->pk();
                        break;
                    case 'datetime':
                        $insert_fields[] = $f;
                        $frags[] = "?";
                        $params[] = $this->$f ? $this->$f: null;
                        break;
                    default:
                        $insert_fields[] = $f;
                        $frags[] = "?";
                        $params[] = $this->$f;
                        break;
                    }
                }
            }
            $sql = "INSERT INTO {$this->table} (" . implode( ",", $insert_fields ) . ") ".
                "VALUES (" . implode(',',$frags) . ")";
            //print $sql;
            db_do( $sql, $params );
            $this->{$this->pk} = db_getOne( "SELECT lastval()" );
//            eventlog_Add( "add-{$this->pageName}", $this->journo['id'], $item );
        }
        db_commit();
    }

/*
    function buildForm($name=null) {
        $form = new jlForm( $name );

        $widgets = array();
        foreach( $this->fields as $f=>$def ) {
            switch($def['type']) {
                case "string":
                    $w = new jlWidgetInput($f);
                    $w->populate( array( $f => $this->$f ));
                    $form->addWidget($w);
                    break;
                case "int":
                    $w = new jlWidgetInput($f);
                    $w->populate( array( $f => $this->$f ));
                    $form->addWidget($w);
                    break;
                case "bool":
                    $w = new jlWidgetCheckbox($f);
                    $w->populate( array( $f => $this->$f ));
                    $form->addWidget($w);
                    break;
                case 'fk':
                    $w = $this->$f->buildForm($f);
                    $form->addWidget( $w );
                    break;
                default:
                    break;
            }
        }

        return $form;
    }
*/
}

?>
