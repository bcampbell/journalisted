<?php
// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../phplib/cache.php';
require_once '../../phplib/db.php';
require_once '../phplib/adm.php';
require_once '../phplib/validators.php';
require_once '../phplib/wizard.php';

require_once '../phplib/drongo-forms/forms.php';



class MergeJournoForm extends Form
{

    function __construct($data,$files,$opts) {
        parent::__construct($data,$files,$opts);
        $this->error_css_class = 'errors';
        $this->fields['from_ref'] = new CharField(array(
            'widget'=> new TextInput(array('class'=>'journo-lookup')),
            'max_length'=>200,
            'required'=>TRUE,
            'label'=>'Source journo (from)',
            'help_text'=>'The journo that will be merged (and deleted) eg freddy-bloggs-75',
            'validators'=>array(array(new JournoValidator(),"execute")),
        ));

        $this->fields['into_ref'] = new CharField(array(
            'widget'=> new TextInput(array('class'=>'journo-lookup')),
            'max_length'=>200,
            'required'=>TRUE,
            'label'=>'Destination journo (into)',
            'help_text'=>'The journo that will receive the merged data, eg fred-bloggs',
            'validators'=>array(array(new JournoValidator(),"execute")),
        ));
    }

    function clean()
    {
        $d = $this->cleaned_data;
        if(isset($d['from_ref']) && isset($d['into_ref'])) {
            if($this->cleaned_data['from_ref'] == $this->cleaned_data['into_ref']) {
                throw new ValidationError("source and destination journos must be different");
            }
        }
        return parent::clean();
    }

}
// TODO: extra validation required:
#	if( $params['from_ref'] == $params['into_ref'] )
#		$errs[] = "FROM and INTO journos can't be the same";




// dummy empty form, just to provide a confirmation page.
class ConfirmForm extends Form
{
}


class MergeJournoWizard extends Wizard
{
    function __construct() {
        parent::__construct(array('MergeJournoForm', 'ConfirmForm'));
    }
}



function view()
{
    $wiz = new MergeJournoWizard();

    if($wiz->is_complete()) {
        $params = $wiz->get_cleaned_data();
        
        $actions = merge_journos($params['from_ref'], $params['into_ref']);
        template_completed(array('wiz'=>$wiz,'actions'=>$actions));
        return;
    } else {
        if($wiz->step == 1) {
            // preview
        } else {
        }

    }

    $vars = array('wiz'=>$wiz);
    template($vars);
}



function template($vars)
{
    extract($vars);
    admPageHeader("Merge Journos");

    $button = "preview";

?>
<h2>Merge Journos</h2>

<?php if($wiz->step==1) {
    $params = $wiz->get_cleaned_data();
    $button = "MERGE";
?>
<p>OK, so you want to merge:</p>
<?php JournoOverview( $params['from_ref'] ); ?>
<p>into:</p>
<?php JournoOverview( $params['into_ref'] ); ?>
<p>is that right?</p>

<?php } ?>

<form action="" method="POST">
<?= $wiz->management(); ?>
<table>
<?= $wiz->form->as_table(); ?>
</table>
<input type="submit" value="<?= $button ?>" />
</form>
<?php

    admPageFooter();
}



function template_completed($vars)
{
    extract($vars);
    admPageHeader("Merge Journos");
?>
    <h2>Journo merged</h2>
    <div class="action_summary">
    <ul>
<?php foreach($actions as $action) { ?>
        <li><?= $action ?></li>
<?php } ?>
    </ul>
<?php
    admPageFooter();
}






function EmitPreview( $params )
{
?>
<form method="POST">

<p>OK, so you want to merge:</p>
<?php JournoOverview( $params['from_ref'] ); ?>
<p>into:</p>
<?php JournoOverview( $params['into_ref'] ); ?>
<p>is that right?</p>

<input type="hidden" name="from_ref" value="<?=$params['from_ref'];?>" />
<input type="hidden" name="into_ref" value="<?=$params['into_ref'];?>" />
<input type="hidden" name="action" value="commit" />
<input type="submit" value="MERGE THEM" /><br />
</form>
<?php
}



function merge_journos($from_ref, $into_ref)
{
	$fromj = db_getRow("SELECT id,ref,prettyname,lastname,firstname FROM journo WHERE ref=?", $from_ref);
	$intoj = db_getRow("SELECT id,ref,prettyname,lastname,firstname FROM journo WHERE ref=?", $into_ref);

	$from_id = $fromj['id'];
	$into_id = $intoj['id'];

	db_do( "UPDATE journo_attr SET journo_id=? WHERE journo_id=? AND article_id NOT IN (SELECT article_id FROM journo_attr WHERE journo_id=?)", $into_id, $from_id, $into_id );
// alias deprecated
//	db_do( "UPDATE journo_alias SET journo_id=? WHERE journo_id=?", $into_id, $from_id );
	db_do( "UPDATE journo_jobtitle SET journo_id=? WHERE journo_id=?", $into_id, $from_id );
	db_do( "UPDATE journo_weblink SET journo_id=? WHERE journo_id=?", $into_id, $from_id );
	db_do( "UPDATE journo_email SET journo_id=? WHERE journo_id=?", $into_id, $from_id );
	db_do( "UPDATE journo_bio SET journo_id=? WHERE journo_id=?", $into_id, $from_id );
	db_do( "UPDATE journo_other_articles SET journo_id=? WHERE journo_id=?", $into_id, $from_id );
    // (and why not:)
    db_do( "UPDATE missing_articles SET journo_id=? WHERE journo_id=?", $into_id, $from_id );
	db_do( "DELETE FROM journo WHERE id=?", $from_id );

    // try to force similar-article recalculation
    db_do( "UPDATE journo SET last_similar=NULL WHERE id=?", $into_id );

	db_do( "DELETE FROM htmlcache WHERE name=?", 'j'.$into_id);
	db_do( "DELETE FROM htmlcache WHERE name=?", 'j'.$from_id);
	db_commit();

    // TODO: LOG THIS ACTION!

    $actions[] = sprintf("Merge '%s' into '%s'", $from_ref, admJournoLink($into_ref));
    $actions[] = sprintf("Delete '%s'", $from_ref);
    return $actions;
}



function JournoOverview( $ref )
{
	$journo = db_getRow( "SELECT id,ref,prettyname FROM journo WHERE ref=?", $ref );

	$r = db_query( "SELECT a.srcorg as orgid, COUNT(*) as numarticles ".
		"FROM (article a INNER JOIN journo_attr attr ON a.id=attr.article_id) ".
		"WHERE attr.journo_id=? ".
		"GROUP BY a.srcorg",
		$journo['id'] );
	$orgs = get_org_names();
?>

<?= admJournoLink($journo['ref']) ?>
<table border=1>
<tr><th>publication</th><th>num articles</th></tr>
<?php while( $row = db_fetch_array( $r ) ) { $orgid = $row['orgid']; ?>
<tr><td><?= $orgs[$orgid] ?></td><td><?= $row['numarticles'] ?></td></tr>
<?php } ?>
</table>
<?php

}


view();

