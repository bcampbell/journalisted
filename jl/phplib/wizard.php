<?php
//require_once '../conf/general';
//require_once '../phplib/page.php';
//require_once '../phplib/misc.php';

require_once '../phplib/drongo-forms/forms.php';


// class to handle multi page forms.
// completed forms are carried along as hidden fields
class Wizard
{
    public $step_field = 'step';

    // $steps is a list of form class names eg:
    //   array('Form1','Form2')
    function __construct($steps) {
        $this->steps = $steps;

        $step = 0;
        if($_SERVER['REQUEST_METHOD']=='POST') {
            if(array_key_exists($this->step_field, $_POST)) {
                $step = intval($_POST[$this->step_field]);
            }
        }

        assert($step>=0 && $step<$this->num_steps());

        // collate data for any completed steps, validating each one
        $this->completed = array();
        for($i=0; $i<$step; ++$i) {
            $f = $this->get_form($i,$_POST);
            if($f->is_valid()) {
                $this->completed[] = $f;
            } else {
                // uh-oh...
                // drop back to offending step
                // TODO: error message
                $step = $i;
                break;
            }
        }

        // now do current step
        if($_SERVER['REQUEST_METHOD']=='POST') {
            $f = $this->get_form($step, $_POST);
        } else {
            $f = $this->get_form($step);
        }

        if($f->is_valid()) {
            $this->completed[] = $f;
            ++$step;
            if($step < $this->num_steps()) {
                $prev_form = $f;
                // next step! (unbound)
                $opts = array();
                $f = $this->get_form($step,array(),array(),$opts);
            } else {
                // done!
                $f = null;
            }
        }

        $this->step = $step;
        $this->form = $f;
    }

    // returns true if all forms are complete and valid
    function is_complete() {
        return $this->step >= $this->num_steps();
    }


    // access data for completed steps
    // (can access any completed step, but obviosuly not uncompleted steps :-)
    function get_cleaned_data_for_step($step)
    {
        return $this->completed[$step]->cleaned_data;
    }


    // returns array of merged data from all completed steps
    function get_cleaned_data()
    {
        $data = array();
        foreach($this->completed as $f) {
            $data = array_merge($data, $f->cleaned_data);
        }
        return $data;
    }

    function get_form($step, $data=array(), $files=array(), $opts=array())
    {
        $opts['prefix'] = $this->prefix_for_step($step);
        $opts = array_merge($opts, $this->get_form_opts($step));

        $f = new $this->steps[$step]($data,$files,$opts);
        return $f;
    }




    function num_steps() { return sizeof($this->steps); }

    // call this as part of your html output, as part of the form
    // it renders hidden elements for steps and completed fields.
    function management()
    {
        $out = array();

        // carry along all the completed forms as hidden fields
        foreach($this->completed as $prev_form) {
            foreach($prev_form->fields as $name=>$field) {
                $bf = new BoundField($prev_form, $field, $name);
                $out[] = $bf->as_hidden();
            }
        }

        // output step
        $hidden = new HiddenInput();
        $out[] = $hidden->render($this->step_field, $this->step);
        return join("\n",$out);
    }



    // stuff that you might want to override:

    function prefix_for_step($step) {
        return strval($step);
    }

    function get_form_opts($step) {
        return array();
    }
}


