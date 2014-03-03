<?php

    // template for site front page
    //
    // params:
    //
    //


?>
<div class="main front-page">

    <div class="l-fullwidth">
        <div class="box">
            <div class="body">

                <div class="fudge1" >Find journalists and see what they're writing about</div>
               
                <div class="row">
                    <div class="col-6">
                        <div class="box box-recently-updated">
                            <div class="head"><h3>Recently updated profiles</h3></div>
                            <div class="body">

                                <ul>
                                    <?php foreach( $recently_updated as $j ) { ?>
                                    <li><?= journo_link($j) ?></li>
                                    <?php } ?>
                                </ul>

                            </div>
                            <div class="foot"></div>
                        </div>  <!-- end .box-recently-updated -->
                    </div>
                    <div class="col-6">
                        <div class="exhort">
                            <h3>Are you a journalist? If so...<br/>
                                Sign up or claim a profile - it's free</h3>
                            <p class="fudge3">Creating a profile takes less than 3 minutes</p>
                            <form method="GET" action="/profile">
                                <input type="hidden" name="action" value="lookup"/>
                                <input type="text" id="fullname" name="fullname" value="" placeholder="your name" />
                                <input class="btn btn-lg" type="submit" value="Create/claim profile" />
                            </form>

                        </div>


                    </div>
                </div> <!-- end row -->
                <br/>
                <br/>
            </div>
        </div>
    </div>

</div>  <!-- end main -->
<?php

