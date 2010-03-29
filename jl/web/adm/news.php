<?php

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../phplib/markdown.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/adm.php';

$action = get_http_var( 'action' );

// handy default
if( $action == '' && get_http_var( 'id' ) )
    $action = 'edit';

admPageHeader();

switch( $action ) {
    case 'create':
        ?><h2>Post New</h2><?php
        $post = newsBlankPost();
        newsEdit( $post );
        break;
    case 'edit':
        $id = get_http_var( 'id' );
        $post = db_getRow( "SELECT * FROM news WHERE id=?", $id );
        newsEdit( $post );
        break;
    case 'Preview':
        $post = newsFromHTTPVars();
        newsPreview( $post );
        newsEdit( $post );
        break;
    case 'Save':
        $post = newsFromHTTPVars();
        newsSave( $post );
        newsList();
        break;
    case 'delete':
        $id = get_http_var( 'id' );
        $post = db_getRow( "SELECT * FROM news WHERE id=?", $id );
?>
<p>
<strong>Do You really want to kill this post?</strong><br/>
</p>
<p>
<a href="/adm/news" />No, I've changed my mind</a>
&nbsp;&nbsp; 
<small><a href="/adm/news?action=reallydelete&id=<?=$id?>" />Yes, delete it!</a></small>
</p>
<?php
        newsPreview( $post );
        break;
    case 'reallydelete':
        $id = get_http_var( 'id' );
        $post = db_getRow( "SELECT * FROM news WHERE id=?", $id );
        newsDelete( $post );
        newsList();
        break;
    default:
        newsList();
        break;
}

admPageFooter();

function newsList() {

    $posts = db_getAll( "SELECT id,status,title,slug,posted,author FROM news ORDER BY posted DESC" );

?>
<h2>News Posts</h2>
 <a href="/adm/news?action=create">Create a new post</a>
 <ul>
<?php foreach( $posts as $p ) { ?>
  <li class="<?= ($p['status']=='a') ? 'approved':'unapproved' ?>" >
    <?php if( $p['status'] == 'a' ) { ?>
    <strong><a href="/news/<?= $p['slug']; ?>"><?= $p['title'] ?></a></strong>
    <?php } else { ?>
    <strong><?= $p['title']?></strong> (unpublished)
    <?php } ?>
    <small>posted by <em><?=$p['author'] ?></em>, <?= $p['posted'] ?>.</small>
    <br/>
      <a href="/adm/news?action=edit&id=<?= $p['id'] ?>">[edit]</a>
      <small> <a href="/adm/news?action=delete&id=<?= $p['id'] ?>">[delete]</a> </small>
    <br/>
  </li>
<?php } ?>
 </ul>

<?php




}
        

function newsFromHTTPVars() {
    $post = array(
        'title'=>get_http_var('title'),
        'status'=>get_http_var('status','u'),
        'author'=>get_http_var('author'),
        'slug'=>get_http_var('slug'),
        'content'=>get_http_var('content'),
    );

    if( $post['slug'] == '' ) {
        $slug = strtolower( $post['title'] );
        $slug = preg_replace("/[^a-zA-Z0-9 ]/", "", $slug );
        $slug = str_replace(" ", "-", $slug);
        $post['slug'] = $slug;
    }

    $id = get_http_var('id' );
    if( $id )
        $post['id'] = $id;
    return $post;
}


function newsBlankPost() {
    return array(
        'status'=>'u',  // unpublished
        'title'=>'',
        'author'=>'',
        'slug'=>'',
        'content'=>'' );
}


function newsEdit($post) {


?>
<form method="POST" action="/adm/news">

<label for="status">Publish?</label>
<input type="checkbox" name="status" value='a' <?= $post['status']=='a'?'checked':'' ?> />
<br />

<label for="title">Title:</label>
<input type="text" size="80" name="title" id="title" value="<?= $post['title'] ?>" /><br />
<label for="author">Author:</label>
<input type="text" size="80" name="author" id="author" value="<?= $post['author'] ?>" /><br />
<label for="slug">Slug:</label>
<input type="text" size="80" name="slug" id="slug" value="<?= $post['slug'] ?>" /><br /><br />
<label for="content">Content:</label><br/>
<textarea rows="120" cols="100" name="content" id="content">
<?= $post['content'] ?>
</textarea>
<br />

<?php if( array_key_exists( 'id', $post ) ) { ?>
<input type="hidden" name="id" value="<?= $post['id'] ?>" />
<?php } ?>
<input type="submit" name="action" value="Preview" />
<input type="submit" name="action" value="Save" />
</form>
<?php

}


// saves post to database. if it's a new post, its new id will be added to $post
function newsSave( &$post )
{

    if( array_key_exists( 'id', $post ) ) {
        // update existing post
        db_do( "UPDATE news SET status=?, title=?, author=?, slug=?, content=? WHERE id=?",
            $post['status'],$post['title'],$post['author'],$post['slug'],$post['content'],$post['id'] );

    } else {
        db_do( "INSERT INTO news (status, title, author, posted, slug, content) VALUES (?,?,?,NOW(),?,?)",
            $post['status'],$post['title'],$post['author'],$post['slug'],$post['content'] );
        $post['id'] = db_getOne( "SELECT lastval()" );
    }
    db_commit();
?>
<div class="action_summary">
Saved <a href="/news/<?= $post['slug']?>"><?= $post['title'] ?></a>
</div>
<?php
}

function newsPreview( $post )
{

    $html = Markdown( $post['content'] );
?>
<p>preview:</p>
<div class="news-preview" style="border: 1px solid black; padding: 1em; margin: 2em;">
<?= $html ?>
</div>
<?php
}

function newsDelete( $post )
{
    db_do( "DELETE FROM news WHERE id=?", $post['id'] );
    db_commit();
?>
<div class="action_summary">Deleted '<?= $post['title']; ?>'</div>
<?php
}

?>

