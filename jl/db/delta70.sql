BEGIN;

CREATE TABLE person_receives_newsletter (
   person_id integer references person(id) ON DELETE CASCADE PRIMARY KEY
);


END;


