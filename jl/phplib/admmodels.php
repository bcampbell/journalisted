<?php

require_once 'jlmodel.php';
require_once 'jlforms.php';

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



class Education extends jlModel
{
    static public $kinds = array( 'u'=>'university', 's'=>'school' );

    function __construct() {
        $this->configure( 'journo_education', array(
            'id'=>array('type'=>'int', 'pk'=>true),
            'journo_id'=>array('type'=>'int'),
            'school'=>array('type'=>'string'),
            'field'=>array('type'=>'string'),
            'qualification'=>array('type'=>'string'),
            'year_from'=>array('type'=>'int'),
            'year_to'=>array('type'=>'int'),
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
            new jlWidgetHidden( 'journo_id', $this->journo_id ),
            new jlWidgetInput('school', $this->school ),
            new jlWidgetInput('field', $this->field),
            new jlWidgetInput('qualification', $this->qualification ),
            new jlWidgetInput('year_from', $this->year_from ),
            new jlWidgetInput('year_to', $this->year_to),
            new jlWidgetSelect('kind', $this->kind, array('choices'=>self::$kinds ) ),
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



class Award extends jlModel
{
    function __construct() {
        $this->configure( 'journo_awards', array(
            'id'=>array('type'=>'int', 'pk'=>true),
            'journo_id'=>array('type'=>'int'),
            'award'=>array('type'=>'string'),
            'year'=>array('type'=>'int'),
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
            new jlWidgetHidden( 'journo_id', $this->journo_id ),
            new jlWidgetInput('award', $this->award ),
            new jlWidgetInput('year', $this->year ),
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

?>
