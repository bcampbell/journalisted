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
  <meta http-equiv="X-UA-Compatible" content="IE=EmulateIE7" />
<?php foreach( $rss_feeds as $rss_title => $rss_url) { ?>
  <link rel="alternate" type="application/rss+xml" title="<?= $rss_title ?>" href="<?= $rss_url ?>" />
<?php } ?>
<?php if( $canonical_url ) { ?>
  <link rel="canonical" href="<?= $canonical_url ?>" />
<?php } ?>
  <script type="text/javascript" src="/js/jquery-1.3.2.min.js"></script>
  <script type="text/javascript" src="/js/jl-util.js"></script>
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
      <a class="mst-logo" href="http://www.mediastandardstrust.org"><img src="/img/mst_logo.png" alt="Media Standards Trust" /></a>
      <div class="nav">
<?php if( $logged_in_user ) { ?>
<?php if( $can_edit_profile ) { ?>
          <span class="hellouser">Hello, <a href="/profile" title="Click to go to your public profile page"><em><?= $logged_in_user ?></em></a></span>
<?php } else { ?>
          <span class="hellouser">Hello, <a href="/account" title="See user your account details"><em><?= $logged_in_user ?></em></a></span>
<?php } ?>
<?php } ?>
        <ul>
          <li><a href="/" title="Go to the front page of journalisted">Home</a></li>
<?php if( $logged_in_user ) { ?>
          <li><a href="/account" title="See your user account details">My details</a></li>
<?php } ?>
<?php /*
<?php if( $logged_in_user && $can_edit_profile ) { ?>
          <li><a href="/profile">My profile</a></li>
<?php } else { ?>
          <li><a href="/profile">Edit profile</a></li>
<?php } ?>
<?php */ ?>
          <li class="alert<?= $mnpage=='my' ? ' active' :''; ?>"><a href="/alert" title="Follow your favourite journalist(s) via email">Alerts</a></li>
          <li class="about<?= $mnpage=='about' ? ' active' :''; ?>"><a href="/about" title="More information about journalisted">About</a></li>
<?php if( $logged_in_user ) { ?>
          <li><a href="/logout" title="Log out - make sure you use this if you're using a public computer">Log out</a></li>
<?php } else { ?>
          <li><a href="/login" title="Log in/register">Log in</a></li>
<?php } ?>
        </ul>
      </div>
      <div class="search">
        <form action="/search" method="GET">
          <input type="text" value="" id="q" name="q" />
          <input type="submit" alt="search" value="Search" />
          <input type="hidden" name="type" value="" />
        </form>
      </div>
      <div style="clear:both;"></div>
    </div>
  </div>



<div id="content">

  <div class="">
      <a class="crosspromo" href="http://churnalism.com">New <img src="/img/promo/churnalism_icon.png"/> Churnalism web site and browser extensions.&nbsp;&nbsp;&nbsp; <strong>Check articles for press release churn while you browse!</strong></a>
  </div>

