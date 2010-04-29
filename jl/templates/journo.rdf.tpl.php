<?php
// template for outputing journo data as RDF
?>
<rdf:RDF
    xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
    xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
    xmlns:foaf="http://xmlns.com/foaf/0.1/"
    xmlns:admin="http://webns.net/mvcb/">

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
?>

  </foaf:Person>
</rdf:RDF>

