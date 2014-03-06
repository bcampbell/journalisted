<?php
    // template for lookup phase of profile create/claim
?>
<div class="main">
    <div class="l-fullwidth">
    <div class="head"></div>
    <div class="body">
        <div class="exhort">
        <h3>Setting up profile...</h3>
        <?php if( sizeof($matching_journos)==1 ) { ?>
        <?php $j=$matching_journos[0]; ?>
        <p class="fudge3">There is already a <?= journo_link($j); ?> on journa<i>listed</i>. Is this you?</p>
        <form method="get" action="/create_profile">
            <input type="hidden" name="action" value="claim" />
            <input type="hidden" name="ref" value="<?= $j['ref'] ?>" />
            <input class="btn" type="submit" value="Claim existing profile" />
            <input type="checkbox" name="iamwhoisay" id="iamwhoisay" value="yes" />
            <label for="iamwhoisay">I confirm that I <strong>am</strong> really this person</label>
        </form>
        <?php } elseif(sizeof($matching_journos)>1) { ?>
        <?php $uniq=0; ?>
        <form method="get" action="/create_profile">
            <p class="fudge3">Are you one of these people?</p>

            <?php foreach( $matching_journos as $j ) { ?>
            <input type="radio" id="ref_<?= $uniq ?>" name="ref" value="<?= $j['ref'] ?>" />
            <label for="ref_<?= $uniq ?>"><?= journo_link($j) ?></label>
            <br/>
            <?php ++$uniq; } ?>
            <input type="hidden" name="action" value="claim" />
            <br/>
            <input class="btn" type="submit" value="Claim existing profile" />
            <input type="checkbox" name="iamwhoisay" id="iamwhoisay" value="yes" />
            <label for="iamwhoisay">I confirm that I <strong>am</strong> really this person</label>
        </form>

        <?php } ?>

        <p class="fudge3">or...</p>
        <br/>
        <a class="btn" href="/create_profile?action=create&fullname=<?=h($fullname)?>">Create a new profile</a>

        </div>
    </div>
    <div class="foot"></div>
</div>
</div> <!-- end main -->
<?php

