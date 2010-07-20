<?php

/*
 template for header at the top of every page

 $title - the page title (ie, for <title>)
 $rss_feeds - array of rss feeds ( as name=>url pairs )
 $logged_in_user - email of logged in user, or null
 $js_files - list of extra javascript files to include
 $head_extra - extra stuff to plonk in the <head> block
 $mnpage - name of active menu page (for showing active menu tab)
 $search - search parameters
*/



/*
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
*/
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
  <title><?=$title ?></title>
  <style type="text/css" media="all">@import "/style.css";</style>
  <!--[if IE]>
    <style type="text/css" media="all">@import "/ie/ie.css";</style>
  <![endif]-->
  <meta name="Content-Type" content="text/html; charset=UTF-8" />
  <link rel="shortcut icon" type="image/x-icon" href="/favicon.ico" />
<?php foreach( $rss_feeds as $rss_title => $rss_url) { ?>
  <link rel="alternate" type="application/rss+xml" title="<?= $rss_title ?>" href="<?= $rss_url ?>" />
<?php } ?>
<?php if( $canonical_url ) { ?>
  <link rel="canonical" href="<?= $canonical_url ?>" />
<?php } ?>
  <script type="text/javascript" src="/js/jquery-1.3.2.min.js"></script>
  <script type="text/javascript" src="/js/jquery.stylish-select.min.js"></script>
  <script type="text/javascript" src="/js/jl-util.js"></script>
<?php foreach( $js_files as $f ) { ?>
  <script type="text/javascript" src="<?= $f ?>"></script>
<?php } ?>
<?= $head_extra; ?>
  <script type="text/javascript" language="JavaScript">
        addLoadEvent( activatePlaceholders );

    $(document).ready( function() {
        $('#header .search select').sSelect();
        });
  </script>
</head>


<body>
  <div id="header">
    <div class="inner">
      <h1><a href="/"><img src="/img/journalisted_logo.png" alt="Journalisted - read all about them!" /></a></h1>
      <a class="mst-logo" href="http://www.mediastandardstrust.org"><img src="/img/mst_logo.png" alt="Media Standards Trust" /></a>
      <div class="nav">
<?php if( $logged_in_user ) { ?>
          <span class="hellouser">Hello, <a href="/account"><em><?= $logged_in_user ?></em></a></span>
<?php } ?>
        <ul>
          <li><a href="/">Home</a></li>
<?php if( $logged_in_user && $can_edit_profile ) { ?>
          <li><a href="/profile">My profile</a></li>
<?php } else { ?>
          <li><a href="/profile">Edit profile</a></li>
<?php } ?>
<?php if( $logged_in_user ) { ?>
          <li class="my<?= $mnpage=='my' ? ' active' :''; ?>"><a href="/alert">Alerts</a></li>
<?php } ?>
<!--    <li class="all<?= $mnpage=='all' ? ' active' :''; ?>"><a href="/list">Journalists A-Z</a></li>
          <li class="subject<?= $mnpage=='subject' ? ' active' :''; ?>"><a href="/tags">Subject Index</a></li>
-->
<?php if( $logged_in_user ) { ?>
          <li class="about<?= $mnpage=='about' ? ' active' :''; ?>"><a href="/about">About</a></li>
          <li><a href="/logout">Log out</a></li>
<?php } else { ?>
          <li class="alert<?= $mnpage=='my' ? ' active' :''; ?>"><a href="/alert">Alerts</a></li>
          <li class="about<?= $mnpage=='about' ? ' active' :''; ?>"><a href="/about">About</a></li>
          <li><a href="/login">Log in</a></li>
<?php } ?>
        </ul>
      </div>
      <div class="search">
        <form action="/search" method="get">
<!--        <label for="q">Search articles</label> -->
          <select id="win-xp" name="type">
            <option value="journo"<?= ($search['type']=='journo')?' selected':'' ?>>Search journalists</option>
            <option value="article"<?= ($search['type']=='article')?' selected':'' ?>>Search articles</option>
          </select>
          <input type="text" value="<?= h($search['q']) ?>" id="q" name="q" />
          <input type="submit" alt="search" value="Search" />
        </form>
      </div>
      <div style="clear:both;"></div>
    </div>
  </div>




<div id="content">


