<?php

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../phplib/article.php';
require_once '../../phplib/db.php';

// TODO: rename article_error table (and 'reason_code' and 'submitted' fields)


// class for modeling article_error entries
class SubmittedArticle
{
    public $id;
    public $url;
    public $status;         // journo_mismatch, scrape_failed, rejected, resolved
    public $when_submitted;
    public $expected_journo;
    public $submitted_by;
    public $article;


    function __construct()
    {
    }

/*
    public static function create($url,$expected_journo, $submitted_by) {
        $instance = new self();
        $instance->url = $url;
        $instance->expected_journo = $expected_journo;
        return $instance;
    }
*/
/*
    public function refresh() {
        assert(!is_null($this->url));
        assert(!is_null($this->expected_journo));

        if($this->status=='rejected')
            return;
        if($this->status=='resolved')
            return;

        // article in db?
        $art_id = article_find($this->url);
        if(!is_null($art_id)) {
            $this->set_article($art_id);
        }
    }
*/

    // fill out the article
    protected function set_article($art_id) {
        if(is_null($art_id)) {
            $this->article = null;
            return;
        }

        $art = db_getRow("SELECT id,title,byline,permalink,pubdate,srcorg FROM article WHERE id=?",$art_id);
        $this->article = array_to_object($art, array('id', 'title', 'byline'));
        $this->article->authors = array();

        $sql = <<<EOT
            SELECT j.ref
                FROM (journo j INNER JOIN journo_attr attr ON attr.journo_id=j.id)
                WHERE attr.article_id=?
EOT;
        foreach(db_getAll($sql, $art_id) as $journo) {
            $this->article->authors[] = array_to_object($journo,array('ref'));
        }
    }


    // updates the status and returns scraper output text
    public function scrape() {
        $expected_ref = null;
        if(!is_null($this->expected_journo)) {
            $expected_ref = $this->expected_journo->ref;
        }

        list($ret,$txt) = scrape_ScrapeURL($this->url, $expected_ref);
        $art_id = null;
        if($ret == 0) {
            // scraped ran

            $arts = scrape_ParseOutput($txt);
            if(sizeof($arts)>0) {
                // scraped at least one article
                $this->set_article($arts[0]);
            }
        }

        $this->update_status();
        return $txt;
    }


    // 
    public function update_status() {

        if($this->status=='rejected') {
            return;
        }

        if(!$this->article) {
            // check to see if article now exists...
            $art_id = article_find($this->url);
            if(!is_null($art_id)) {
                $this->set_article($art_id);
            }
        }


        if(!$this->article) {
            $this->status = 'scrape_failed';
        } else {
            $this->status = 'journo_mismatch';
            foreach($this->article->authors as $attributed) {
                if($attributed->ref == $this->expected_journo->ref) {
                    $this->status='resolved';
                    break;
                }
            }
        }
    }


    protected function from_db_row($row) {
        set_fields($this, $row, array('id'=>'id','url'=>'url','reason_code'=>'status','submitted'=>'when_submitted'));
        if(!is_null($row['expected_journo'])) {
            $this->expected_journo = array_to_object($row, array(
                'expected_journo'=>'id',
                'expected_ref'=>'ref',
                'expected_prettyname'=>'prettyname' ));
        } else {
            $this->expected_journo = null;
        }
        if(!is_null($row['submitted_by'])) {
            $this->submitted_by = array_to_object($row, array('submitted_by'=>'id', 'submitted_by_name'=>'name', 'submitted_by_email'=>'email'));
        } else {
            $this->submitted_by = NULL;
        }
        if(!is_null($row['article_id'])) {
            $this->article = array_to_object($row, array('article_id'=>'id', 'article_title'=>'title', 'article_byline'=>'byline'));
            //
            $this->article->authors = array();
            $author_refs = preg_split("/[{},]/",$row['attributed'],-1,PREG_SPLIT_NO_EMPTY);
            foreach($author_refs as $ref) {
                $author = new stdClass();
                $author->ref = $ref;
                $this->article->authors[] = $author;
            }
        } else {
            $this->article = NULL;
        }

    }


    private static $sql_select = <<<EOT
e.id, e.url, e.reason_code, e.submitted, e.submitted_by, e.article_id, e.expected_journo,
                j.ref as expected_ref,
                j.prettyname as expected_prettyname,
                a.title as article_title, a.permalink as article_permalink, a.byline as article_byline,
                p.name as submitted_by_name,
                p.email as submitted_by_email,
                array(SELECT ref FROM journo j2 WHERE j2.id IN (SELECT journo_id FROM journo_attr attr WHERE attr.article_id=e.article_id)) as attributed
EOT;

    private static $sql_from = <<<EOT
            (((article_error e LEFT JOIN article a ON a.id=e.article_id)
                LEFT JOIN journo j ON j.id=e.expected_journo)
                LEFT JOIN person p ON p.id=e.submitted_by)
EOT;

    public static function fetch_single($id) {

        $sel_part = self::$sql_select;
        $from_part = self::$sql_from;
        $sql = <<<EOT
        SELECT {$sel_part}
            FROM {$from_part}
            WHERE e.id=?
            ORDER BY e.submitted DESC
EOT;
        $sub = new SubmittedArticle();
        $sub->from_db_row(db_getRow($sql,$id));
        return $sub;
    }


    // return array of submittedarticle objects matching $url
    public static function fetch_by_url($url) {
        $sel_part = self::$sql_select;
        $from_part = self::$sql_from;
        $sql = <<<EOT
        SELECT {$sel_part}
            FROM {$from_part}
            WHERE e.url=?
            ORDER BY e.submitted DESC
EOT;
        $rows = db_getAll($sql,$url);
        $art_errs = array();

        foreach($rows as $row) {
            $sub = new SubmittedArticle();
            $sub->from_db_row($row);
            $art_errs[] = $sub;
        }

        return $art_errs;
    }

    public static function count($filter=null) {
        $params = array();
        $from_part = self::$sql_from;
        $where_part = "reason_code NOT IN ('rejected','resolved')";
        if($filter['expected_ref']) {
            $where_part .= " AND j.ref=?";
            $params[] = $filter['expected_ref'];
        }
        $sql = "SELECT COUNT(*) FROM {$from_part} WHERE {$where_part}";
        $n=db_getOne($sql,$params);
        return $n;
    }


    // fetch article_errors, returning an array of widgets
    public static function fetch($filter=null,$offset=null,$limit=null) {

        $params = array();
        $sel_part = self::$sql_select;
        $from_part = self::$sql_from;
        $where_part = "reason_code NOT IN ('rejected','resolved')";
        if($filter['expected_ref']) {
            $where_part .= " AND j.ref=?";
            $params[] = $filter['expected_ref'];
        }

        $sql = <<<EOT
        SELECT {$sel_part}
            FROM {$from_part}
            WHERE {$where_part}
            ORDER BY e.submitted DESC
EOT;

        if(!is_null($offset)) {
            $sql .= " OFFSET ?\n";
            $params[] = $offset;
        }
        if(!is_null($limit)) {
            $sql .= " LIMIT ?\n";
            $params[] = $limit;
        }
        $rows = db_getAll($sql,$params);
        $art_errs = array();

        foreach($rows as $row) {
            $sub = new SubmittedArticle();
            $sub->from_db_row($row);
            $art_errs[] = $sub;
        }

        return $art_errs;
    }


    // attribute article to expected journo (zapping any previous
    // attribution for the article)
    function replace_journo() {
        $this->add_journo(TRUE);
    }

    // add expected journo to the article
    function add_journo($replace=FALSE) {
        assert(!is_null($this->article));
        assert(!is_null($this->expected_journo));

        if( $replace===TRUE ) {
            db_do("DELETE FROM journo_attr WHERE article_id=?", $this->article->id);
        } else {
            db_do("DELETE FROM journo_attr WHERE article_id=? AND journo_id=?", $this->article->id, $this->expected_journo->id);
        }
        
        db_do("INSERT INTO journo_attr (journo_id,article_id) VALUES (?,?)",
            $this->expected_journo->id, $this->article->id);

        // update article->authors
        if( $replace===TRUE ) {
            $this->article->authors = array();
        }
        $this->article->authors[] = $this->expected_journo;

        // if the journo's name isn't immediately obvious in the byline, zap the byline too.
        // (implication is that byline is wrong)
        if(stripos($this->article->byline, $this->expected_journo->prettyname) === FALSE )
        {
            db_do("UPDATE article SET byline='' WHERE id=?", $this->article->id);
            $this->article->byline = '';
        }

        // TODO: could also 1) clear htmlcache for this journo 2) activate them if required
        $this->status = "resolved";
    }


    function save() {
        db_do("UPDATE article_error SET url=?, reason_code=?, submitted=?, submitted_by=?, article_id=?, expected_journo=? WHERE id=?",
            $this->url,
            $this->status,
            $this->when_submitted,
            is_null($this->submitted_by) ? null : $this->submitted_by->id,
            is_null($this->article) ? null : $this->article->id,
            is_null($this->expected_journo) ? null : $this->expected_journo->id,
            $this->id );
    }

    function zap() {
//        db_do("DELETE FROM article_error WHERE id=?", $this->id);
//        $this->id = null;
    }


};

?>
