<?php

/*
 template for header at the top of every page

 $title - the page title (ie, for <title>)
 $rss_feeds - array of rss feeds ( as name=>url pairs )
 $logged_in_user - email of logged in user, or null
 $js_files - list of extra javascript files to include
 $head_extra - extra stuff to plonk in the <head> block
 $mnpage - name of active menu page (for showing active menu tab)

*/

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
  <title><?=$title ?></title>
  <style type="text/css" media="all">@import "/style.css";</style>
  <meta name="Content-Type" content="text/html; charset=UTF-8" />

<?php foreach( $rss_feeds as $rss_title => $rss_url) { ?>
  <link rel="alternate" type="application/rss+xml" title="<?= $rss_title ?>" href="<?= $rss_url ?>" />
<?php } ?>

<?php foreach( $js_files as $f ) { ?>
  <script type="text/javascript" src="<?= $f ?>"></script>
<?php } ?>

<?= $head_extra; ?>

  <script type="text/javascript" language="JavaScript">
        addLoadEvent( activatePlaceholders );
  </script>
</head>


<body>
  <div id="header">
    <div class="inner">
      <h1><a href="/"><img src="/img/journalisted_logo.png" alt="Journalisted - read all about them!" /></a></h1>

      <div class="nav">
        <ul>
          <li class="all<?= $mnpage=='all' ? ' active' :''; ?>"> <a href="/list">Journalists A-Z</a> </li>
          <li class="subject<?= $mnpage=='subject' ? ' active' :''; ?>"> <a href="/tags">Subject Index</a> </li>
          <li class="my<?= $mnpage=='my' ? ' active' :''; ?>"> <a href="/alert">Alerts</a> </li>
          <li class="about<?= $mnpage=='about' ? ' active' :''; ?>"> <a href="/about">About</a> </li>
      </div>
      <div class="search">
        <form action="/journo_search" method="get">
<!--        <label for="q">Search articles</label> -->
          <input type="text" value="" title="find journalist" id="q" name="q" class="text" placeholder="find journalist"/>
          <input type="submit" alt="find" value="Find" />
        </form>
      </div>
      <div style="clear:both;"></div>
    </div>
  </div>

<div id="dateline">
<?php if( $logged_in_user ) { ?>
  <span id="hellouser">
    Hello, <?php echo $logged_in_user; ?>
<?php if( $can_edit_profile ) { ?> [<a href="/profile">edit my profile</a>] <?php } ?>
[<a href="/logout">log out</a>] <br/>
  </span>
<?php } ?>
</div>



<div id="content">


