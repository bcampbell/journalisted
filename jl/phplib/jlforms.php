<?php
// base class for widgets
// a widget is pretty general, and might be expressed as a single html form
// element (eg an input box), or some collection of html elements (eg for
// a fancy date range thingy), or an html link (eg a "delete" button) or
// an entire form.
abstract class jlWidget
{
    public $opts = array('label'=>'', 'explain'=>'');
    public $attrs = array();
    public $name = null;
    public $value = null;

    public function __construct( $name, $value=null, $opts = array(), $attrs = array() )
    {
        $this->name = $name;
        $this->value = $value;
        $this->opts['label'] = $name;
        $this->opts = array_merge($this->opts, $opts);
        $this->attrs = array_merge($this->attrs, $attrs);
    }

    public function label() { return $this->opts['label']; }
    public function explain() { return $this->opts['explain']; }
    // returns true if this part of normal widget grid arrangement.
    // (eg if you're using a <table> to arrange your form, you don't want
    // empty cells for hidden input elements, so if inGrid() returns false,
    // it indicates that the widget should be shoved down below the </table>)
    public function inGrid() { return true; }

    // helper fn for outputting html tag
    public function buildTag( $tag, $attrs, $content=null )
    {
        $attrs = array_merge( $this->attrs, $attrs );
        $frags = array();
        foreach( $attrs as $name=>$value ) {
            if( is_null( $value ) ) {
                $frags[] = $name;
            } else {
                $frags[] = sprintf( '%s="%s"', $name, htmlentities( $value ) );
            }
        }
        if( is_null( $content ) ) {
            return sprintf( "<%s %s />", $tag, implode( ' ', $frags ) );
        } else {
            return sprintf( "<%s %s>%s</%s>", $tag, implode( ' ', $frags ), $content, $tag );
        }
    }

    // generate html to express the widget
    abstract public function render( $errs=array() );

    // set the values for the widget
    // $data is array of _all_ the values available (eg $_POST, or an array
    // pulled out of a database), and the widget picks out the one(s) it
    // wants.
    public function populate( $data ) {
        if( isset( $data[$this->name] ) ) {
            $this->value = $data[$this->name];
        }
    }
}


class jlWidgetHidden extends jlWidget
{
    public function inGrid() { return false; }
    public function render( $errs=array() )
    {
        $id = $this->name;
        $out = $this->buildTag('input',
            array('type'=>'hidden', 'id'=>$id, 'name'=>$this->name, 'value'=>$this->value) );
        $out .= "\n";
        return $out;
    }
}


// submit button
class jlWidgetSubmit extends jlWidget
{
    public function inGrid() { return false; }
    public function render( $errs=array() )
    {
        $id = $this->name;
        $input = $this->buildTag('input',
            array('type'=>'submit', 'id'=>$id, 'name'=>$this->name, 'value'=>$this->name ) );

//        $out = sprintf( "<tr><td colspan=\"2\">%s</td></tr>\n", $input );
        return $input;
    }
}


class jlWidgetInput extends jlWidget
{
    public function render( $errs=array() )
    {
        $id = $this->name;


        $input = $this->buildTag('input',
            array('type'=>'text', 'id'=>$id, 'name'=>$this->name, 'value'=>$this->value) );

        return $input;

    }
}


class jlWidgetCheckbox extends jlWidget
{
    public function render( $errs=array() )
    {
        $id = $this->name;

        $attrs = array('type'=>'checkbox', 'id'=>$id, 'name'=>$this->name, 'value'=>1 );
        if( $this->value ) {
            $attrs['checked'] = null;
        }
        $input = $this->buildTag('input', $attrs );

        return $input;
    }
}

class jlWidgetSelect extends jlWidget
{
    public function render( $errs=array() )
    {
        $id = $this->name;

        $options = '';
        foreach( $this->opts['choices'] as $v=>$txt ) {
            $sel = $this->value==$v ? ' selected': '';
            $options .= "<option value=$v$sel>$txt</option>\n";
        }

        $input = $this->buildTag('select',
            array( 'id'=>$id, 'name'=>$this->name ), $options );

        return $input;
    }
}





// form contains child widgets
class jlForm extends jlWidget
{
    protected $children = array();

    public function __construct( $name, $value=null, $opts = array(), $attrs = array() )
    {
        $this->attrs['action'] = '';
        $this->attrs['method'] = 'GET';
        parent::__construct( $name, $value, $opts, $attrs );
    }

    public function addWidget( $w ) {
        $this->children[$w->name] = $w;
    }

    public function addWidgets( $widgets ) {
        foreach( $widgets as $w ) {
            $this->addWidget( $w );
        }
    }

    public function populate( $data )
    {
        foreach( $this->children as $name=>$w ) {
            $w->populate( $data );
        }
    }

    public function render( $errs=array() )
    {
        $out = '<table>';
        foreach( $this->children as $name=>$w ) {
            if( $w->inGrid() ) {
                $id = $name;

                $label = $w->label();
                if( $label ) {
                    $label = sprintf( '<label for="%s">%s</label>', $id, $w->label() );
                }
                $explain = $w->explain();
                if( $explain) {
                    $explain = sprintf( '<span class="explain">%s</span>', $w->explain() );
                }

                $widget_html = $w->render();

                $out .= sprintf("<tr><td>%s</td><td>%s<br/>%s</td>\n",
                    $label, $widget_html, $explain );
            }
        }
        $out .= "</table>\n";
        foreach( $this->children as $name=>$w ) {
            if( !$w->inGrid() ) {
                $out .= $w->render();
            }
        }

        return $out;
    }
}
?>
