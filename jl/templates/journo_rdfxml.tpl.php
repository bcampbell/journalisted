<?php
// template for outputing journo data as RDF
?>
<rdf:RDF
    xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
    xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
    xmlns:foaf="http://xmlns.com/foaf/0.1/"
    xmlns:doac="http://ramonantonio.net/content/xml/doac01"
<?php /*
    xmlns:admin="http://webns.net/mvcb/"
    xmlns:rss="http://purl.org/rss/1.0/"
*/ ?>
>

  <foaf:Person>

    <foaf:name><?= x($prettyname) ?></foaf:name>
    <foaf:givenname><?= x($firstname) ?></foaf:givenname>
    <foaf:family_name><?= x($lastname) ?></foaf:family_name>
<?php if( $known_email ) { ?>
    <foaf:mbox rdf:resource="mailto:<?= x($known_email['email']) ?>"/>
<?php } ?>
<?php if( $phone_number ) { ?>
    <foaf:phone rdf:resource="tel:<?= x($phone_number) ?>"/>
<?php } ?>
<?php
    foreach( $links as $l ) {
        switch( $l['kind'] ) {
            case 'webpage':
?>    <foaf:homepage rdf:resource="<?= x($l['url']) ?>"/><?php
                break;
            case 'blog':
?>    <foaf:weblog rdf:resource="<?= x( $l['url'] ) ?>"/><?php
                break;
        }
    }


    $i=0; foreach( $employers as $e ) {
?>
    <doac:has_experience>
      <doac:Experience rdf:about="#exp<?= $i ?>">
<?php if($e['kind']=='f') { /* freelance */?>
        <doac:position>Freelance</doac:position>
<?php } else { /* kind=='e' */ ?>
    <?php if( $e['employer'] ) { ?>
        <foaf:organization><?= $e['employer'] ?></foaf:organization>
    <?php } ?>
    <?php if( $e['job_title'] ) { ?>
        <doac:title><?= $e['job_title'] ?></doac:title>
    <?php } ?>
<?php } ?>
<?php if( $e['year_from'] ) { ?>
        <doac:date_starts><?= $e['year_from']; ?></doac:date_starts>
<?php } ?>
<?php if( $e['current']==FALSE && $e['year_to'] ) { ?>
        <doac:date_ends><?= $e['year_to']; ?></doac:date_ends>
<?php } ?>
      </doac:Experience>
    </doac:has_experience>
<?php ++$i; } ?>


<?php
    $i=0; foreach( $education as $e ) {
?>
    <doac:education>
      <doac:Education rdf:about="#edu<?= $i ?>">
<?php if( $e['school'] ) { ?>
        <foaf:organization><?= $e['school'] ?></foaf:organization>
<?php } ?>
<?php if( $e['year_from'] ) { ?>
        <doac:date_starts><?= $e['year_from']; ?></doac:date_starts>
<?php } ?>
<?php if( $e['year_to'] ) { ?>
        <doac:date_ends><?= $e['year_to']; ?></doac:date_ends>
<?php } ?>
      </doac:Education>
    </doac:education>
<?php ++$i; } ?>

  </foaf:Person>


<rdf:Description rdf:about="">
<foaf:primaryTopic rdf:resource="http://webscience.org/person/2"/>
</rdf:Description>s

</rdf:RDF>

