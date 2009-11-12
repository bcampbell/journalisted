<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/editprofilepage.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';




class AdmiredJournosPage extends EditProfilePage
{

    function __construct() {
        $this->pageName = "admired";
        $this->pagePath = "/profile_admired";
        $this->pageTitle = "Journalists you admire";
        $this->pageParams = array( 'head_extra_fn'=>array( &$this, 'extra_head' ) );
        parent::__construct();
    }


    function extra_head()
    {
        // TODO: use compressed jquery.autocompete

?>
<link type="text/css" rel="stylesheet" href="/profile.css" /> 
<link type="text/css" rel="stylesheet" href="/css/jquery.autocomplete.css" />
<script type="text/javascript" src="/js/jquery-1.3.2.min.js"></script>
<script type="text/javascript" src="/js/jquery.autocomplete.js"></script>
<script type="text/javascript" src="/js/jquery.form.js"></script>
<script type="text/javascript" src="/js/jl-fancyforms.js"></script>
<script type="text/javascript">
    $(document).ready( function() {
        fancyForms( '.admired', function() {
            var f = $(this);

            var el = f.find(".admired_name");
            el.keypress( function (e) {
                var ignore = new Array( 9,10,13, 37,38,39,40, 33,34, 27 )
                if($.inArray(e.keyCode, ignore) == -1 ) {
                    f.find(".admired_oneliner").text("");
                    f.find(".admired_ref").val("");
                }
            });
            el.autocomplete("ajax_journo_lookup.php", {
//                width: 300,
//              multiple: true,
//              matchContains: true,
                formatItem: function(row) { return row[0] + "<br/>&nbsp&nbsp<em>(" + row[1] + ")</em>"; },

//              formatItem: function( formatItem,
//              formatResult: formatResult
            });
            el.result(function(event, data, formatted) {
                var ref = '';
                var oneliner =''
                if (data) {
                    oneliner = data[1];
                    ref = data[2];
                }
                f.find(".admired_oneliner").text( '(' + oneliner + ')' );
                f.find(".admired_ref").val(ref);
            });
        });
    });
</script>
<?php
    }



    function handleActions()
    {
        $action = get_http_var( "action" );
        if( $action == "submit" ) {
            $this->handleSubmit();
        }
        if( get_http_var('remove_id') ) {
            $this->handleRemove();
        }

        return TRUE;
    }


    function displayMain()
    {

        $sql = <<<EOT
SELECT a.id, a.admired_id, a.admired_name, j.prettyname, j.ref as admired_ref, j.oneliner
    FROM (journo_admired a LEFT JOIN journo j ON a.admired_id=j.id)
    WHERE a.journo_id=?
EOT;
        $admired = db_getAll( $sql, $this->journo['id'] );

//        if( sizeof( $admired ) ) {
/* ?> <h2>Thanks for telling us which journalists do you most admire</h2> <?php */
//        } else {
?> <h2>Which journalists do you most admire?</h2> <?php
//        }


        foreach( $admired as $a ) {
            $this->showForm('edit',$a);
        }
        if( !$admired )
            $this->showForm('creator',null);
        $this->showForm('template', null);

        // if they've entered any journos already, encourage them to add more to their profile */
        if( sizeof( $admired) > 0 ) {
    ?>
    <div class="donate-box">
     <div class="donate-box_top"><div></div></div>
      <div class="donate-box_content exhort">

        Would you like to add to your profile page on Journalisted?<br/>
    <a href="/profile_employment?ref=<?=$this->journo['ref'];?>">Add to my profile</a><br/>
    <a href="/<?=$this->journo['ref'];?>">View my Journalisted page</a><br/>

      </div>
     <div class="donate-box_bottom"><div></div></div>
    </div>
    <?php
        }
    }


    function ajax()
    {
        header( "Cache-Control: no-cache" );
        $action = get_http_var( "action" );
        if( $action == "submit" ) {
            $entry_id = $this->handleSubmit();
            $result = array( 'status'=>'success',
                'id'=>$entry_id,
                'editlinks_html'=>$this->genEditLinks($entry_id),
            );
            print json_encode( $result );
        }
        if( get_http_var('remove_id') ) {
            $this->handleRemove();
        }
    }



    function showForm( $formtype, $admired )
    {

        static $uniq=0;
        ++$uniq;
        if( is_null( $admired ) )
            $award = array( 'admired_name'=>'', 'admired_ref' );

        $formclasses = 'admired';
        if( $formtype == 'template' )
            $formclasses .= " template";
        if( $formtype == 'creator' )
            $formclasses .= " creator";
?>

<form class="<?= $formclasses; ?>" method="POST" action="<?= $this->pagePath; ?>">
<table border="0">
 <tr>
  <th><label for="admired_name_<?= $uniq; ?>">Journalist:</label></th>
  <td>
   <input type="text" name="admired_name" class="admired_name" id="admired_name_<?= $uniq; ?>" value="<?= h($admired['admired_name']); ?>" />
   <span class="admired_oneliner"><?= h(is_null($admired['oneliner']) ? '':"({$admired['oneliner']})" ); ?></span>
  </td>
 </tr>
</table>
<input type="hidden" name="admired_ref" class="admired_ref" value="<?= h($admired['admired_ref']); ?>" />
<input type="hidden" name="ref" value="<?php echo $this->journo['ref']; ?>" />
<input type="hidden" name="action" value="submit" />
<button class="submit" type="submit">Save</button>
<?php if( $formtype=='edit' ) { ?>
<input type="hidden" name="id" value="<?= $admired['id']; ?>" />
<?= $this->genEditLinks($admired['id']); ?>
<?php } ?>
</form>
<?php

    }


    function handleSubmit()
    {
        $id = get_http_var( 'id' );
        $admired_name = get_http_var( 'admired_name' );
        $admired_ref = get_http_var( 'admired_ref' );
        $admired_id = NULL;
        if( $admired_ref ) {
            $foo = db_getRow( "SELECT id,prettyname FROM journo WHERE ref=?", $admired_ref );
            if( $foo ) {
                $admired_id = $foo['id'];
                $admired_name = $foo['prettyname'];
            }
        }

        if( $id ) {
            // it's already in the DB - update it
            db_do( "UPDATE journo_admired SET admired_name=?, admired_id=? WHERE id=? and journo_id=?",
                $admired_name, $admired_id, $id, $this->journo['id'] );
        } else {
            // it's new
            db_do( "INSERT INTO journo_admired (journo_id,admired_name,admired_id) VALUES (?,?,?)",
                $this->journo['id'], $admired_name, $admired_id  );
            $id = db_getOne( "SELECT lastval()" );
        }
        db_commit();
        return $id;
    }


    function handleRemove() {
        $id = get_http_var("remove_id");

        // include journo id, to stop people zapping other journos entries!
        db_do( "DELETE FROM journo_admired WHERE id=? AND journo_id=?", $id, $this->journo['id'] );
        db_commit();
        print"REMOVED $id {$this->journo['id']}";
    }
}



$page = new AdmiredJournosPage();
$page->run();

