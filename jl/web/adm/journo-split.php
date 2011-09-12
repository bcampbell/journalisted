<?php
// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../phplib/cache.php';
require_once '../../phplib/db.php';
require_once '../phplib/adm.php';
require_once '../phplib/wizard.php';
require_once '../phplib/validators.php';

require_once '../phplib/drongo-forms/forms.php';



class PickJournoForm extends Form
{

    function __construct($data,$files,$opts) {
        parent::__construct($data,$files,$opts);
        $this->error_css_class = 'errors';
        $this->fields['from_ref'] = new CharField(array(
            'widget'=> new TextInput(array('class'=>'journo-lookup')),
            'max_length'=>200,
            'required'=>TRUE,
            'label'=>'Journo to split',
            'help_text'=>'eg fred-bloggs',
            'validators'=>array(array(new JournoValidator(),"execute")),
        ));
    }
}





class SplitJournoForm extends Form
{

    function __construct($data,$files,$opts) {

        // get all publications this journo has written for, with article counts
        assert(array_key_exists('from_ref',$opts));
        $from_ref = $opts['from_ref'];

        $pub_choices = array();
        $sql = <<<EOT
SELECT o.id, o.shortname, count(*)
    FROM (((organisation o
        INNER JOIN article a on o.id=a.srcorg)
        INNER JOIN journo_attr attr ON attr.article_id=a.id)
        INNER JOIN journo j ON j.id=attr.journo_id)
        WHERE j.ref=?
        GROUP BY o.id,o.shortname
        ORDER BY count DESC
EOT;
        foreach(db_getAll($sql, $from_ref) as $row) {
            $pub_choices[$row['id']] = sprintf("%s (%d articles)", $row['shortname'], $row['count']);
        }

        $extra_opts = array(
            'initial'=>array('from_ref'=>$from_ref),
        );
        $opts = array_merge($opts,$extra_opts);
        parent::__construct($data,array(),$opts);
        $this->error_css_class = 'errors';
        $this->fields['from_ref'] = new CharField(array(
            'max_length'=>200,
            'required'=>TRUE,
            'label'=>'Journo to split',
            'help_text'=>'eg fred-bloggs',
            'readonly'=>TRUE,
            'validators'=>array(array(new JournoValidator(),"execute")))
        );
        $this->fields['from_ref']->widget->attrs['readonly'] = TRUE;

        $this->fields['split_pubs'] = new MultipleChoiceField(array(
            'label'=>'Publications to split off',
            'help_text'=>'Articles from the ticked publications will be assigned over to the new journo',
            'choices'=>$pub_choices,
            'widget'=>'CheckboxSelectMultiple'));
        $this->fields['to_ref'] = new CharField(array(
            'widget'=> new TextInput(array('class'=>'journo-lookup')),
            'max_length'=>200,
            'required'=>FALSE,
            'label'=>'Destination journo',
            'help_text'=>'eg fred-bloggs or leave blank to create a new journo',
            'validators'=>array(array(new JournoValidator(),"execute")),
        ));
    }
}

// dummy empty form, just to provide a confirmation page.
class ConfirmSplitForm extends Form
{
}


class SplitJournoWizard extends Wizard
{
    function __construct() {
        parent::__construct(array('PickJournoForm','SplitJournoForm','ConfirmSplitForm'));
    }

    function get_form_opts($step) {
        $opts = array();
        if($step == 1) {
            $step0_data = $this->get_cleaned_data_for_step(0);
            $opts['from_ref'] = $step0_data['from_ref'];
        }
        return $opts;
    }
}

function view()
{
    $wiz = new SplitJournoWizard();

    if($wiz->is_complete()) {
        // perform the split...
        $params = $wiz->get_cleaned_data();

        $split_pubs = $params['split_pubs'];
        $from_ref= $params['from_ref'];
        $to_ref= $params['to_ref'];

        if(!$to_ref) {
            // make sure source journo has a numeric postfix, and create a new dest journo
            $baseref = RefBase($from_ref);
            $num = RefNum($from_ref);
            if(is_null($num)) {
                $new_from_ref = NextFreeRef($baseref, 1);
                $num = RefNum($new_from_ref);
            }
            $to_ref = NextFreeRef($baseref, $num+1);
        } else {
            // source journo retains their existing ref
            $new_from_ref = $from_ref;
        }

        $actions = do_split($from_ref, $new_from_ref, $split_pubs, $to_ref);
        template_completed(array('wiz'=>$wiz,'actions'=>$actions));

    } else {
        $art_summary = null;
        if($wiz->step == 2) {
            // preview/confirmation
            $params = $wiz->get_cleaned_data();
            $art_summary = preview_articles($params);
        }
        template_step(array('wiz'=>$wiz,'art_summary'=>$art_summary));
    }
}

// figure out summary of which articles are moving over to new journo and
// which are staying, by publication.
function preview_articles($params)
{
$sql = <<<EOT
    SELECT a.srcorg,pub.shortname, COUNT(*) as num_articles
        FROM article a
            INNER JOIN journo_attr attr ON a.id=attr.article_id
            INNER JOIN organisation pub ON pub.id=a.srcorg
            INNER JOIN journo j on j.id=attr.journo_id
        WHERE j.ref=?
        GROUP BY a.srcorg,pub.shortname
EOT;
    $r = db_getAll($sql, $params['from_ref']);

    $articles = array();

    foreach($r as $row) {
        $moving = in_array(intval($row['srcorg']), $params['split_pubs']);
        $articles[] = array('publication'=>$row['shortname'], 'num_articles'=>$row['num_articles'], 'moving'=>$moving);
    }
    return $articles;
}




function template_step($vars)
{
    extract($vars);
    $params = $wiz->get_cleaned_data();

    switch($wiz->step) {
    case 1: $button='preview...'; break;
    case 2: $button='PERFORM SPLIT'; break;
    default:
    case 0: $button='next...'; break;
    }
    admPageHeader("Split Journo");
?>
<h2>Split journo</h2>
<p>
This lets you move articles from one journo to another.<br/>
<em>Any other information (email addresses, user accounts, links, education, experience etc) will _not_ be reassigned.</em>
</p>
<?php if($wiz->step==2) {
    // the preview step! show a summary.
    $from_ref = $params['from_ref'];
?>
Here's a preview:
<h3>Splitting <?= admJournoLink($from_ref) ?></h3>
<table border="1">
<thead><tr><th>publication</th><th>num_articles</th><th>moving?</th></tr></thead>
<tbody>
<?php foreach($art_summary as $a) { ?>
<tr><td><?= $a['publication'] ?></td><td><?= $a['num_articles'] ?></td><td><?= $a['moving'] ? "YES":"no"; ?></td></tr>
<?php } ?>
</tbody>
</table>
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
    admPageHeader("Split Journo");
?>
    <h2>Split completed</h2>
    <div class="action_summary">
    <ul>
<?php foreach($actions as $action) { ?>
        <li><?= $action ?></li>
<?php } ?>
    </ul>
<?php
    admPageFooter();
}





/* return a ref, stripped of it's number postfix (if any) */
function RefBase( $ref )
{
    $m = array();
    if( preg_match( '/^(.*?)(-\d+)?$/', $ref, &$m ) > 0 ) {
        return $m[1];
    }
    return null;
}

/* return the numeric postfix of a ref, or null if none */
function RefNum( $ref )
{
    $m = array();
    if( preg_match( '/^(.*)-(\d+)$/', $ref, &$m ) > 0 ) {
        return (int)$m[2];
    }
    return null;
}

/* search for an unused ref based on $baseref */
function NextFreeRef( $baseref, $startnum )
{
    $n = $startnum;
    while(1)
    {
        $ref = sprintf("%s-%d", $baseref,$n );
        if( db_getRow( "SELECT id FROM journo WHERE ref=?",$ref ) )
            ++$n;           /* it's used */
        else
            return $ref;    /* it's free! */
    }
    /* never gets here... */
}




/*
 * adds an 'id' field to $j
 */
function journoCreate( &$j )
{
    db_do( "INSERT INTO journo (ref,prettyname,firstname,lastname,firstname_metaphone,lastname_metaphone,status,created) VALUES (?,?,?,?,?,?,?,NOW())",
        $j['ref'],
        $j['prettyname'],
        $j['firstname'],
        $j['lastname'],
        metaphone($j['firstname'],4),
        metaphone($j['lastname'],4),
        $j['status'] );
    $j['id'] = db_getOne( "SELECT currval( 'journo_id_seq' )" );

// deprecated
    // TODO: should handle multiple aliases
//  $alias = $j['alias'];

//  db_do( "INSERT INTO journo_alias (journo_id,alias) VALUES (?,?)",
//      $j['id'], $alias );
}




// perform the split!
function do_split($from_ref, $new_from_ref, $split_pubs, $to_ref)
{
    $actions = array();

    if($new_from_ref != $from_ref) {
        // rename the source journo
        db_do( "UPDATE journo SET ref=? WHERE ref=?", $new_from_ref, $from_ref);
        $actions[] = sprintf("Renamed journo %s -> %s", $from_ref, admJournoLink($new_from_ref) );
        $from_ref = $new_from_ref;
    }

    $fromj = db_getRow( "SELECT id,ref,prettyname,lastname,firstname,status FROM journo WHERE ref=?", $from_ref );
    $toj = db_getRow( "SELECT id,ref,prettyname,lastname,firstname,status FROM journo WHERE ref=?", $to_ref );
    if(!$toj) {
        // need to create new journo (just take a copy of 'from' journo)
        $toj = $fromj;
        unset( $toj['id'] );
        $toj['ref'] = $to_ref;
        journoCreate( $toj );
        // TODO: copy journo_alias entries too...
        $actions[] = sprintf("Created new journo: %s", admJournoLink($to_ref));
    }


    // move articles
    $orglist = implode( ',', $split_pubs );
    if( $orglist )
    {
        $sql = <<<EOD
UPDATE journo_attr SET journo_id=?
    WHERE journo_id=? AND article_id IN
        (
        SELECT a.id
            FROM (article a INNER JOIN journo_attr attr ON a.id=attr.article_id)
            WHERE journo_id=? AND a.srcorg IN ({$orglist})
        )
EOD;

        $rows_affected = db_do( $sql, $toj['id'], $fromj['id'], $fromj['id'] );
        $actions[] = sprintf( "reassigned %d articles from %s to %s", $rows_affected, $from_ref, $to_ref);
    }

    // leave all other data attached to from_ journo (links, email etc)


    // Clear the htmlcache for the to and from journos
	db_do( "DELETE FROM htmlcache WHERE name=?", 'j'.$fromj['id']);
	db_do( "DELETE FROM htmlcache WHERE name=?", 'j'.$toj['id']);

    db_commit();

    return $actions;
}



view();

