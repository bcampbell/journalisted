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
<script type="text/javascript" src="/js/jquery-dynamic-form.js"></script>

<script type="text/javascript">
    $(document).ready(
        function() {
            $(".admired-journo").autocomplete("ajax_journo_lookup.php", {
//                width: 300,
//        		multiple: true,
//        		matchContains: true,
                formatItem: function(row) { return row[0] + " (<em>" + row[1] + "</em>)"; },

//        		formatItem: function( formatItem,
//        		formatResult: formatResult
            });

            $("#admired-journo").dynamicForm( '#admired-journo-plus', '#admired-journo-minus', {limit:10} );
    });
</script>
<?php
    }




    function displayMain()
    {
        // submitting new entries?
        $action = get_http_var( "action" );
        if( $action == "submit" ) {
            $added = $this->handleSubmit();
        }

        // show list journos already listed as admired

        $sql = <<<EOT
SELECT a.admired_id, a.admired_name, j.prettyname, j.ref, j.oneliner
    FROM (journo_admired a LEFT JOIN journo j ON a.admired_id=j.id)
    WHERE a.journo_id=?
EOT;

        $admired = db_getAll( $sql, $this->journo['id'] );

?>
<?php if( $admired ) { ?>
<h2>Thanks for telling us which journalists do you most admire</h2>
<?php } else { ?>
<h2>Which journalists do you most admire?</h2>
<?php } ?>

<ul>
<?php foreach( $admired as $a ) { ?>
<?php if( $a['admired_id'] ) { ?>
 <li><?=journo_link($a)?></li>
<?php } else { ?>
 <li><?=$a['admired_name']?></li>
<?php } ?>
<?php } ?>
</ul>
<?php

        if($admired ) {
?><p>You can add more if you'd like:</p><?php
        }


        // form for adding new ones:
        $this->showForm();


        // if they've entered any journos already, encourage them to add more to their profile */
        if( $admired ) {
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




    function showForm()
    {

?>

<form method="POST" action="/profile_admired">
 <table border="0">
 <tbody>
 <tr id="admired-journo">
  <td>
   <label for="admired_name">Journalist's name</label>
  </td>
  <td>
   <input class="admired-journo" type="text" name="admired_name[]" id="admired_name" value="" />
   <a id="admired-journo-minus" href="">[-]</a>
   <a id="admired-journo-plus" href="">[+]</a>
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
        $cnt = 0;
        $names = get_http_var( 'admired_name' );
        foreach( $names as $n ) {
            if( db_getOne( "SELECT id FROM journo_admired WHERE journo_id=? AND admired_name=?", $this->journo['id'], $n ) ) {
                continue;
            }

            db_do( "INSERT INTO journo_admired (journo_id,admired_name,admired_id) VALUES (?,?,?)",
                $this->journo['id'], $n, NULL );
            $cnt += 1;
        }
        db_commit();

        return $cnt;
    }
}



$page = new AdmiredJournosPage();
$page->display();

