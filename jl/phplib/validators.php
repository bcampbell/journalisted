<?php

require_once 'drongo-forms/forms.php';
require_once '../../phplib/db.php';

// validator to ensure a journo is in the DB
class JournoValidator {
    public $msg = 'Please enter a valid journo ref';
    public $code = 'journo';
    function execute($value) {
        $journo_id = db_getOne("SELECT id FROM journo WHERE ref=?",$value);
        if(is_null($journo_id)) {
            $params = array();
            throw new ValidationError(vsprintf($this->msg,$params), $this->code, $params );
        }
    }
}




?>
