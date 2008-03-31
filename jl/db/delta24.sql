-- journalist email addresses harvested from various places
CREATE TABLE journo_email (
    journo_ref TEXT PRIMARY KEY,
    email TEXT NOT NULL,
    srcurl TEXT NOT NULL,  -- but may be empty
    srctype TEXT NOT NULL  -- short identifier of type, e.g. 'article'
);
