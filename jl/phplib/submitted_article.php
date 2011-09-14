<?php

require_once '../conf/general';
require_once '../phplib/misc.php';
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


    public static function create($url,$expected_journo, $submitted_by) {
        $instance = new self();
        $instance->url = $url;
        $instance->expected_journo = $expected_journo;
        $instance->refresh();
        return $instance;
    }
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


    protected function set_article($art_id) {
        if(is_null($art_id)) {
            $this->article = null;
            return;
        }

        $art = db_getRow("SELECT id,title,permalink,pubdate,srcorg FROM article WHERE id=?",$art_id);
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


    // returns scraper output text
    public function scrape() {
        list($ret,$txt) = scrape_ScrapeURL($this->url);
        $art_id = null;
        if($ret != 0) {
            // scraped failed
            $this->status = 'scrape_failed';
            return $txt;
        }

        $arts = scrape_ParseOutput($txt);
        if(sizeof($arts)<1) {
            // scraped, but yielded no article
            $this->status = 'scrape_failed';
            return $txt;
        }

        // got article
        $this->set_article($art[0]);

        // see if expected journo is attributed to article
        if($this->article) {
            foreach($this->article->authors as $attributed) {
                if($attributed->ref == $this->expected_journo->ref) {
                    // YAY
                    $this->status='resolved';
                    return $txt;
                }
            }
        }
        $this->status = 'journo_mismatch';
        return $txt;
    }

    protected function from_db_row($row) {
        set_fields($this, $row, array('id'=>'id','url'=>'url','reason_code'=>'status','submitted'=>'when_submitted'));
        if(!is_null($row['expected_journo'])) {
            $this->expected_journo = array_to_object($row, array('expected_journo'=>'id', 'expected_ref'=>'ref'));
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



    public static function fetch_single($id) {
        $sql = <<<EOT
SELECT e.id, e.url, e.reason_code, e.submitted, e.submitted_by, e.article_id, e.expected_journo,
                j.ref as expected_ref,
                a.title as article_title, a.permalink as article_permalink, a.byline as article_byline,
                p.name as submitted_by_name,
                p.email as submitted_by_email,
                array(SELECT ref FROM journo WHERE id IN (SELECT journo_id FROM journo_attr attr WHERE attr.article_id=e.article_id)) as attributed
            FROM (((article_error e LEFT JOIN article a ON a.id=e.article_id)
                LEFT JOIN journo j ON j.id=e.expected_journo)
                LEFT JOIN person p ON p.id=e.submitted_by)
            WHERE e.id=?
            ORDER BY e.submitted DESC
EOT;
        $sub = new SubmittedArticle();
        $sub->from_db_row(db_getRow($sql,$id));
        return $sub;
    }


    // fetch all article_errors, returning an array of widgets
    public static function fetch_all() {
        $sql = <<<EOT
SELECT e.id, e.url, e.reason_code, e.submitted, e.submitted_by, e.article_id, e.expected_journo,
                j.ref as expected_ref,
                a.title as article_title, a.permalink as article_permalink, a.byline as article_byline,
                p.name as submitted_by_name,
                p.email as submitted_by_email,
                array(SELECT ref FROM journo j2 WHERE j2.id IN (SELECT attr.journo_id FROM journo_attr attr WHERE attr.article_id=e.article_id)) as attributed
            FROM (((article_error e LEFT JOIN article a ON a.id=e.article_id)
                LEFT JOIN journo j ON j.id=e.expected_journo)
                LEFT JOIN person p ON p.id=e.submitted_by)
            ORDER BY e.submitted DESC
EOT;
        $rows = db_getAll($sql);
        $art_errs = array();

        foreach($rows as $row) {
            $sub = new SubmittedArticle();
            $sub->from_db_row($row);
            $art_errs[] = $sub;
        }

        return $art_errs;
    }


    function attribute_journo() {
        assert(!is_null($this->article));
        assert(!is_null($this->expected_journo));

        db_do("DELETE FROM journo_attr WHERE journo_id=? AND article_id=?",
            $this->expected_journo->id, $this->article->id);
        db_do("INSERT INTO journo_attr (journo_id,article_id) VALUES (?,?)",
            $this->expected_journo->id, $this->article->id);

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
