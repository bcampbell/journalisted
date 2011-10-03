<?php
// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../phplib/adm.php';
require_once '../phplib/article.php';
require_once '../phplib/tabulator.php';
require_once '../phplib/paginator.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';

require_once '../phplib/drongo-forms/forms.php';

xdebug_enable();

// validator to ensure a list of journos are all valid
class CommaSeparatedJournoValidator {
    public $msg = 'Invalid journos (%1$s)';
    public $code = 'invalid_journos';
    function execute($value) {

        $authors = explode(',',$value);
        $bad = array();
        foreach($authors as $ref) {
            $journo_id = db_getOne("SELECT id FROM journo WHERE ref=?",trim($ref));
            if(is_null($journo_id)) {
                $bad[] = $ref;
            }
        }
        if($bad) {
           $params = array(join(',',$bad));
           throw new ValidationError(vsprintf($this->msg,$params), $this->code, $params );
        }
    }
}


// form for editing/adding articles
class ArticleForm extends Form
{

    function __construct($data=array(),$files=array(),$opts=array()) {
        $status_choices = array('a'=>'a (active)','h'=>'h (hidden)', 'd'=>'d (duplicate)');

        $publication_choices = array();
        $publication_choices[''] = "-- automatic, from permalink --";
        $r = db_query("SELECT id,shortname FROM organisation ORDER BY shortname");
        while($row = db_fetch_array($r)) {
            $publication_choices[$row['id']] = $row['shortname'];
        }

        parent::__construct($data,$files,$opts);
        $this->error_css_class = 'errors';
        $this->fields['id'] = new IntegerField(array('widget'=>new HiddenInput(), 'required'=>FALSE));
        $this->fields['status'] = new ChoiceField(array('choices'=>$status_choices,));
        $this->fields['title'] = new CharField(array('max_length'=>200,));
        $this->fields['permalink'] = new URLField(array( 'max_length'=>400, ));
        $this->fields['srcorg'] = new ChoiceField(array('choices'=>$publication_choices, 'required'=>FALSE));
        $this->fields['authors'] = new CharField(array(
            'max_length'=>400,
            'required'=>FALSE,
            'validators'=>array(array(new CommaSeparatedJournoValidator(),"execute")),
        ));
        $this->fields['byline'] = new CharField(array(
            'required'=>FALSE,
            'max_length'=>200,
            'label'=>'Raw Byline'));
        $this->fields['description'] = new CharField(array(
            'widget'=>new Textarea(array('cols'=>'80')),
            'required'=>FALSE));
        $this->fields['pubdate'] = new DateField(array());
//        $this->fields['firstseen'] = new DateField(array('required'=>FALSE));
//        $this->fields['lastseen'] = new DateField(array('required'=>FALSE));
//        $this->fields['srcurl'] = new URLField(array('required'=>FALSE, 'max_length'=>400, ));
//        $this->fields['srcid'] = new CharField(array('required'=>FALSE));
//        $this->fields['lastscraped'] = new DateField(array('required'=>FALSE));
//        $this->fields['wordcount'] = new IntegerField(array('required'=>FALSE));

//        $this->fields['total_bloglinks'] = new IntegerField(array('required'=>FALSE));
//        $this->fields['total_comments'] = new IntegerField(array('required'=>FALSE));
//        $this->fields['last_comment_check'] = new DateField(array('required'=>FALSE));
//        $this->fields['last_similar'] = new DateField(array('required'=>FALSE));

        // others:
        // journo_attr
        // article_content
        // article_url
    }
}



// article form, with added load/save functioning
class ArticleModelForm extends ArticleForm
{

    function save() {

        $data = $this->cleaned_data;

        $fields = array('title','byline','description', 'pubdate', /*'firstseen','lastseen',*/ 'permalink',
            /*'srcurl',*/ 'srcorg', /*'srcid', 'lastscraped', 'wordcount', */ 'status');



        // if srcorg left blank, fill it out using domainname
        if(!$data['srcorg']) {
            $parts = crack_url($data['permalink']);
            $domain = strtolower($parts['host']);
            $data['srcorg'] = $this->find_or_create_publication($domain);
        }



        // all set - time to upsert!

        $params = array();
        foreach($fields as $f) {
            $values[] = $data[$f];
            $placeholders[] = '?';
        }
        if($data['id']) {
            // update
            $sql = "UPDATE article SET (" . join(',',$fields) . ") = (" . join(',',$placeholders) . ") WHERE id=?";
            $values[] = $data['id'];
            db_do($sql, $values);

            // make sure article_url has permalink (srcurl might be different, but we'll assume it's there already)
            db_do("DELETE FROM article_url WHERE url=? AND article_id=?", $data['permalink'],$data['id']);
            db_do("INSERT INTO article_url (url,article_id) VALUES (?,?)", $data['permalink'],$data['id']);
        } else {
            //create new
            $sql = "INSERT INTO article (id," . join(',',$fields) . ") VALUES (DEFAULT," . join(',',$placeholders) . ") RETURNING id";
            $data['id'] = db_getOne($sql, $values);
    
            // set up article_url
            db_do("INSERT INTO article_url (url,article_id) VALUES (?,?)", $data['permalink'],$data['id']);
        }

        // set attributed journos
        db_do("DELETE FROM journo_attr WHERE article_id=?",$data['id']);
        $authors = explode(',',$data['authors']);
        $params = array();
        $params[] = $data['id'];
        $placeholders = array();
        foreach($authors as $a) {
            $params[] = trim($a);
            $placeholders[] = '?';
        }
        print_r($params);
        db_do("INSERT INTO journo_attr (journo_id,article_id) SELECT id,? FROM journo WHERE ref IN (" . join(',',$placeholders) . ")", $params);

        # TODO:
        #  log the action
        #  resolve any relevant submitted articles

        return $data;
    }


    // helper for saving
    function find_or_create_publication($domain) {
        $foo = preg_replace("/^www[.]/","",$domain);
        $pub_id = db_getOne("SELECT pub_id FROM pub_domain WHERE domain in (?,?) LIMIT 1", $foo, "www.$foo");

        if(!is_null($pub_id))
            return $pub_id;

        // not found, so create a new publication:
        $shortname = $foo;
        $prettyname = $foo;
        $shortname = $foo;
        $sortname = $foo;
        $home_url = "http://{$domain}";

        $pub_id = db_getOne( "INSERT INTO organisation (id,shortname,prettyname,sortname,home_url) VALUES (DEFAULT, ?,?,?,?) RETURNING id",
            $shortname,$prettyname,$sortname, $home_url );
        db_do( "INSERT INTO pub_domain (pub_id,domain) VALUES (?,?)", $pub_id, $domain);
        db_do( "INSERT INTO pub_alias (pub_id,alias) VALUES (?,?)", $pub_id, $prettyname);

        return $pub_id;
    }


    static function from_db($art_id) {
        $art = db_getRow("SELECT * FROM article WHERE id=?", $art_id);
        $date_fields = array('pubdate','lastscraped','firstseen','lastseen');
        foreach($date_fields as $f) {
            $art[$f] = new DrongoDateTime($art[$f]);
        }

        // 
        $foo = db_getAll("SELECT j.ref FROM (journo_attr attr INNER JOIN journo j ON j.id=attr.journo_id) WHERE attr.article_id=?", $art_id);
        $authors = array();
        foreach($foo as $row) {
            $authors[] = $row['ref'];
        }
        $art['authors'] = join(',',$authors);
        return new ArticleModelForm($art);
    }
}


// pull together everything we need to display the page, then invoke the template
function view()
{
    $art_id = null;
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        $f= new ArticleModelForm($_POST,array(),array());
        if($f->is_valid()) {
            $art = $f->save();
            db_commit();
            $url = sprintf("http://%s/adm/editarticle?id36=%s",$_SERVER['HTTP_HOST'],article_id_to_id36($art['id']));
            header("HTTP/1.1 303 See Other");
            header("Location: {$url}");
            return;
        }
    } else {
        // handle either base-10 or base-36 article ids
        $art_id = get_http_var('id36');
        if( $art_id ) {
            $art_id = article_id36_to_id($art_id);
        } else {
            $art_id = get_http_var('id');
        }
        if($art_id) {
            $f = ArticleModelForm::from_db($art_id);
        } else {
            $f = new ArticleModelForm();
            $art_id=null;
        }
    }

    $v = array('form'=>$f, 'art_id'=>$art_id); 
    template($v);
}


function template($vars)
{
    extract($vars);
    admPageHeader();

?>
<?php if(is_null($art_id)) { ?>
<h2>Create Article</h2>
<?php } else { ?>
<h2>Edit Article</h2>
go to <a href="<?= article_url($art_id); ?>">public page</a>, <a href="<?= article_adm_url($art_id); ?>">admin page</a>
<?php }?>
<form action="/adm/editarticle" method="POST">
<table>
<?= $form->as_table(); ?>
</table>
<input type="submit" name="submit" value="Submit" />
</form>
<?php
    admPageFooter();
}


view();

?>
