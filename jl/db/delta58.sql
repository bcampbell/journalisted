CREATE TABLE image (
    id serial PRIMARY KEY,
    filename text NOT NULL,
    width integer,
    height integer
);

CREATE TABLE journo_picture (
    id serial PRIMARY KEY,
    journo_id integer NOT NULL REFERENCES journo(id) ON DELETE CASCADE,
    image_id integer NOT NULL REFERENCES image(id) ON DELETE CASCADE
);


