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

<script type="text/javascript">
    $(document).ready(
        function() {
            
            var el = $("#admired-name");
            el.keypress( function (e) {
                var ignore = new Array( 9,10,13, 37,38,39,40, 33,34, 27 )
                if($.inArray(e.keyCode, ignore) == -1 ) {
                    $("#admired-oneliner").text("");
                    $("#admired-ref").val("");
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
                $("#admired-oneliner").text( '(' + oneliner + ')' );
                $("#admired-ref").val(ref);
            });


//            $("#admired-journo").dynamicForm( '#admired-journo-plus', '#admired-journo-minus', {limit:10} );

    });
</script>
<?php
    }




    function displayMain()
    {
        // submitting new entries?
        $action = get_http_var( "action" );
        if( $action == "submit" ) {
            $this->handleSubmit();
        }
        if( get_http_var('remove_id') ) {
            $this->handleRemove();
        }

        $sql = <<<EOT
SELECT a.id, a.admired_id, a.admired_name, j.prettyname, j.ref, j.oneliner
    FROM (journo_admired a LEFT JOIN journo j ON a.admired_id=j.id)
    WHERE a.journo_id=?
EOT;
        $admired = db_getAll( $sql, $this->journo['id'] );

//        if( sizeof( $admired ) ) {
/* ?> <h2>Thanks for telling us which journalists do you most admire</h2> <?php */
//        } else {
?> <h2>Which journalists do you most admire?</h2> <?php
//        }

        if( sizeof( $admired ) > 0 ) {
            $this->showAdmired( $admired );
?><p>You can add another if you'd like:</p><?php
        } else {
?><p>add one now...</p><?php
        }

        // form for adding new ones:
        $this->showForm();

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




    function showAdmired( &$admired )
    {
        // show list journos already listed as admired
?>
<ul>
<?php foreach( $admired as $a ) { ?>
<li>
<?php if( $a['admired_id'] ) { ?>
<?=journo_link($a)?>
<?php } else { ?>
<?=$a['admired_name']?>
<?php } ?>
 [<a href="/profile_admired?ref=<?=$this->journo['ref'];?>&remove_id=<?=$a['id'];?>">remove</a>]
</li>
<?php } ?>
</ul>
<?php

        return sizeof( $admired );
    }



    function showForm()
    {

?>

<form method="POST" action="/profile_admired">
 <table border="0">
 <tbody>
 <tr id="admired-journo">
  <td>
   <label for="admired-name">Journalist's name</label>
  </td>
  <td>
   <input type="text" name="admired-name" id="admired-name" value="" />
<?php
//   <a id="admired-journo-minus" href="">[-]</a>
//   <a id="admired-journo-plus" href="">[+]</a>
?>
   <span id="admired-oneliner"></span>
   <input type="hidden" name="admired-ref" id="admired-ref" value="" />
  </td>
 </tr>
 </tbody>
 </table>
 <input type="hidden" name="ref" value="<?php echo $this->journo['ref']; ?>" />
 <button name="action" value="submit">Submit</button>
</form>
<?php

    }


    function handleSubmit()
    {
        $admired_name = get_http_var( 'admired-name' );
        $admired_ref = get_http_var( 'admired-ref' );

        if( $admired_ref ) {
            // it's a journo in the database
            $admired_id = db_getOne( "SELECT id FROM journo WHERE ref=?",$admired_ref );
            db_do( "DELETE FROM journo_admired WHERE journo_id=? and admired_id=?",
                $this->journo['id'], $admired_id );
            db_do( "INSERT INTO journo_admired (journo_id,admired_name,admired_id) VALUES (?,?,?)",
                 $this->journo['id'], $admired_name, $admired_id );
        } else {
            // a journo not in the DB - leave admired_id NULL
            db_do( "DELETE FROM journo_admired WHERE journo_id=? and admired_name=?",
                $this->journo['id'], $admired_name );
            db_do( "INSERT INTO journo_admired (journo_id,admired_name,admired_id) VALUES (?,?,NULL)",
                 $this->journo['id'], $admired_name );
        }
        db_commit();
    }


    function handleRemove() {
        $id = get_http_var("remove_id");

        // include journo id, to stop people zapping other journos entries!
        db_do( "DELETE FROM journo_admired WHERE id=? AND journo_id=?", $id, $this->journo['id'] );
        db_commit();
    }
}



$page = new AdmiredJournosPage();
$page->display();

