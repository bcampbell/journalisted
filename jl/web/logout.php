<?php
/*
 * logout.php:
 * Log user out.
 * 
 * Copyright (c) 2005 UK Citizens Online Democracy. All rights reserved.
 * Email: chris@mysociety.org; WWW: http://www.mysociety.org/
 *
 * $Id: logout.php,v 1.1 2005/07/15 23:20:52 matthew Exp $
 * 
 */

require_once '../phplib/page.php';
require_once '../../phplib/person.php';

if (person_if_signed_on(true)) {
    person_signoff();
    header("Location: /logout");
    exit;
}

page_header(_('Logged out'));
?>
<div class="main">
  <div class="head"></div>
  <div class="body">
    <p>You are now logged out.</p>
    <p>Thanks for using the site!</p>

    <a href="/">Home page</a>
  </div>
  <div class="foot"></div>
</div> <!-- end main -->
<?php
page_footer();

?>
