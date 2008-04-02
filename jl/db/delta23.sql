/* Stores the context object scraped by wikipedia.py as JSON (in UTF-8). */
CREATE TABLE journo_bio (
    journo_ref  TEXT  PRIMARY KEY,  /* journo.ref */
    context     TEXT  NOT NULL,  /* JSON-encoded, dates as YYYY-MM-DDThh:mm:ss. */
    bio         TEXT  NOT NULL   /* context['bio'] (first paragraph), for easy access from PHP. */
);
