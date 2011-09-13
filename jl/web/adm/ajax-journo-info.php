<?php
// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/adm.php';
require_once '../phplib/journo.php';




function view() {
    if( !admCheckAccess() )
        exit;   // should return error code?
    $j = get_http_var('j');
    $j = strtolower($j);
    $journo = db_getRow("SELECT id,ref,prettyname,oneliner,status FROM journo WHERE ref=?",$j);

    if(is_null($journo))
        // TODO: 404
        return;

    $sql = <<<EOT
    SELECT p.id,p.email,p.name,perm.permission
        FROM person p INNER JOIN person_permission perm ON perm.person_id=p.id
        WHERE perm.permission='edit' AND perm.journo_id=?
EOT;
    $users = db_getAll($sql, $journo['id']);


    $journo['arts'] = journo_collectArticles($journo,5);
    $journo['num_arts'] = db_getOne("SELECT COUNT(*) FROM journo_attr WHERE journo_id=?",$journo['id']);
    $journo['linked_users'] = $users;

    template($journo);
}


function template($vars)
{
    header("Cache-Control: no-cache");
    extract($vars);

    switch($status) {
    case 'a': $pretty_status = 'Active'; break;
    case 'i': $pretty_status = 'Inactive'; break;
    case 'h': $pretty_status = 'Hidden'; break;
    default: $pretty_status = $status; break;
    }

?>
<h4><?= admJournoLink($ref,$prettyname); ?></h4>
<?php if($oneliner) { ?><em><?= $oneliner ?></em><br/><?php } ?>
<?= $pretty_status ?>, <?= $num_arts ?> articles<br/>
<?php foreach($linked_users as $user) { ?>
Linked to <a href="/adm/useraccounts?person_id=<?= $user['id'] ?>"><?= $user['email'] ?></a>: '<?= $user['permission'] ?>'<br/>
<?php } ?>
recent articles:
<ul>
<?php foreach($arts as $art) { ?>
<li>
<a class="extlink" href="<?= $art['permalink']; ?>"><?= $art['title']; ?></a>
<?= $art['srcorgname']; ?>, <?= $art['pretty_pubdate']; ?>
</li>
<?php } ?>
</ul>
<?php
}


view();

?>
