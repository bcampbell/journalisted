<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/editprofilepage.php';
require_once '../phplib/eventlog.php';
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
<link type="text/css" rel="stylesheet" href="/css/jquery.autocomplete.css" />
<script type="text/javascript" src="/js/jquery-1.3.2.min.js"></script>
<script type="text/javascript" src="/js/jquery.autocomplete.js"></script>
<script type="text/javascript" src="/js/jquery.form.js"></script>
<script type="text/javascript" src="/js/jl-util.js"></script>
<script type="text/javascript">
    $(document).ready( function() {

        function fancify() {
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
                formatItem: function(row) { return row[0] + "<br/>&nbsp&nbsp<em>(" + row[1] + ")</em>"; },

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
        }

        /* set up ajax lookup on name field */
        $('.admired dl').each( fancify );

        /* set up 'add' link - clone template to create a new entry */
        $('.admired .template' ).hide();
        $('.admired .add').click( function() {
            var c = $('.admired .template').clone();
            jl.normalizeElement( c );
            c.removeClass('template');
            c.insertBefore( '.admired .template' )
            c.fadeIn();
            c.each(fancify );
            return false;
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
            $this->Redirect( "/{$this->journo['ref']}" );
        }
        if( $action =='remove' ) {
            $this->handleRemove();
            $this->Redirect( "/{$this->journo['ref']}" );
        }
    }


    function display()
    {

        $sql = <<<EOT
SELECT a.id, a.admired_id, a.admired_name, j.prettyname, j.ref as admired_ref, j.oneliner
    FROM (journo_admired a LEFT JOIN journo j ON a.admired_id=j.id)
    WHERE a.journo_id=?
EOT;
        $admired = db_getAll( $sql, $this->journo['id'] );

?>
<h2>Which journalists do you most admire?</h2>
<form class="admired" method="POST" action="<?= $this->pagePath; ?>">
<?php
        foreach( $admired as $a ) {
            $this->emitEntry( $a );
        }
        $this->emitEntry( null );   // the template entry
?>
  <input type="hidden" name="ref" value="<?=$this->journo['ref'];?>" />
  <input type="hidden" name="action" value="submit" />
  <div class="button-area">
    <a class="add" href="#">Add a journalist</a><br/>
    <button class="submit" type="submit">Save changes</button> or <a href="/<?= $this->journo['ref'] ?>">cancel</a>
  </div>
</form>
<?php

    }



    function emitEntry( $e=null ) {
        $uniq = 0;

        $is_template = false;
        if( is_null( $e ) ) {
            $is_template = true;
            $e = array('id'=>'', 'admired_name'=>'','admired_ref'=>'', 'oneliner'=>null );
        }
?>
  <dl class="<?= $is_template?'template':'' ?>">
    <dt><label for="admired_name_<?= $uniq; ?>">Journalist</label></dt>
    <dd>
      <input type="text" name="admired_name[]" class="admired_name" id="admired_name_<?= $uniq; ?>" value="<?= h($e['admired_name']); ?>" />
      <span class="admired_oneliner"><?= h(is_null($e['oneliner']) ? '':"({$e['oneliner']})" ); ?></span>
        <input type="hidden" name="admired_ref[]" class="admired_ref" value="<?= h($e['admired_ref']); ?>" />
    </dd>
  </dl>

<?php
        ++$uniq;
    }


    function ajax()
    {
        return NULL;
    }


    function entriesFromHTTPVars() {
        $admired_names = get_http_var( 'admired_name' );
        $admired_refs = get_http_var( 'admired_ref' );

        $entries = array();
        while( !empty($admired_names) ) {
            $e = array(
                'admired_name'=>array_shift($admired_names),
                'admired_ref'=>array_shift($admired_refs) );

            // only use non-blank ones
            if( $e['admired_name'] )
                $entries[] = $e;
        }
        return $entries;
    }


    function handleSubmit()
    {
        $admired = $this->entriesFromHTTPVars();

        // add ids of journos in the database
        foreach( $admired as &$a ) {
            $a['admired_id'] = null;
            if( $a['admired_ref'] ) {
                $foo = db_getRow( "SELECT id,prettyname FROM journo WHERE ref=?", $a['admired_ref'] );
                if( $foo ) {
                    $a['admired_id'] = $foo['id'];
                    $a['admired_name'] = $foo['prettyname'];
                }
            }
        }
        unset( $a );

        db_do( "DELETE FROM journo_admired WHERE journo_id=?", $this->journo['id'] );
        foreach( $admired as &$a ) {
            db_do( "INSERT INTO journo_admired (journo_id,admired_name,admired_id) VALUES (?,?,?)",
                $this->journo['id'],
                $a['admired_name'],
                $a['admired_id'] );
            // $id = db_getOne( "SELECT lastval()" );
        }
        db_commit();
        eventlog_Add( "modify-admired", $this->journo['id'] );
    }


    function handleRemove() {
        $id = get_http_var("id");

        // include journo id to stop people zapping other journos entries!
        db_do( "DELETE FROM journo_admired WHERE id=? AND journo_id=?", $id, $this->journo['id'] );
        db_commit();

        eventlog_Add( "remove-admired", $this->journo['id'] );
    }
}



$page = new AdmiredJournosPage();
$page->run();

