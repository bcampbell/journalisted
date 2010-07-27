--
-- PostgreSQL database dump
--

SET client_encoding = 'SQL_ASCII';
SET standard_conforming_strings = off;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET escape_string_warning = off;

--
-- Name: plpgsql; Type: PROCEDURAL LANGUAGE; Schema: -; Owner: -
--

CREATE PROCEDURAL LANGUAGE plpgsql;


SET search_path = public, pg_catalog;

--
-- Name: article_setjournomodified_onupdate(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION article_setjournomodified_onupdate() RETURNS trigger
    AS $$
BEGIN
    -- whenever article is modified, set the modified flag on any attributed jounos
    UPDATE journo SET modified=true WHERE id IN (SELECT journo_id FROM journo_attr WHERE article_id=NEW.id);
    return NULL;
END;
$$
    LANGUAGE plpgsql;


--
-- Name: article_update_total_bloglinks(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION article_update_total_bloglinks() RETURNS trigger
    AS $$
    BEGIN
        IF TG_OP = 'INSERT' THEN
            UPDATE article SET total_bloglinks=total_bloglinks+1 WHERE id=new.article_id;
        END IF;

        IF TG_OP = 'DELETE' THEN
            UPDATE article SET total_bloglinks=total_bloglinks-1 WHERE id=old.article_id;
        END IF;

        RETURN new;
    END
$$
    LANGUAGE plpgsql;


--
-- Name: article_update_total_comments(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION article_update_total_comments() RETURNS trigger
    AS $$
    BEGIN
        IF TG_OP = 'INSERT' THEN
            UPDATE article SET total_comments=total_comments+new.num_comments WHERE id=new.article_id;
        END IF;

        IF TG_OP = 'DELETE' THEN
            UPDATE article SET total_comments=total_comments-old.num_comments WHERE id=old.article_id;
        END IF;

        IF TG_OP = 'UPDATE' THEN
            UPDATE article SET total_comments=total_comments+new.num_comments-old.num_comments WHERE id=old.article_id;
        END IF;

        RETURN new;
    END
$$
    LANGUAGE plpgsql;


--
-- Name: journo_setmodified_ondelete(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION journo_setmodified_ondelete() RETURNS trigger
    AS $$
BEGIN
    UPDATE journo SET modified=true WHERE id=OLD.journo_id;
    return NULL;
END;
$$
    LANGUAGE plpgsql;


--
-- Name: journo_setmodified_oninsert(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION journo_setmodified_oninsert() RETURNS trigger
    AS $$
BEGIN
    UPDATE journo SET modified=true WHERE id=NEW.journo_id;
    return NULL;
END;
$$
    LANGUAGE plpgsql;


--
-- Name: journo_setmodified_onupdate(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION journo_setmodified_onupdate() RETURNS trigger
    AS $$
BEGIN
    UPDATE journo SET modified=true WHERE id=NEW.journo_id;
    UPDATE journo SET modified=true WHERE id=OLD.journo_id;
    return NULL;
END;
$$
    LANGUAGE plpgsql;


--
-- Name: ms_current_timestamp(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION ms_current_timestamp() RETURNS timestamp without time zone
    AS $$
    begin
        return current_timestamp;
    end;
$$
    LANGUAGE plpgsql;


SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: alert; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE alert (
    id integer NOT NULL,
    person_id integer NOT NULL,
    journo_id integer
);


--
-- Name: alert_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE alert_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: alert_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE alert_id_seq OWNED BY alert.id;


--
-- Name: article; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE article (
    id integer DEFAULT nextval(('article_id_seq'::text)::regclass) NOT NULL,
    title text NOT NULL,
    byline text NOT NULL,
    description text NOT NULL,
    pubdate timestamp without time zone,
    firstseen timestamp without time zone NOT NULL,
    lastseen timestamp without time zone NOT NULL,
    content text,
    permalink text NOT NULL,
    srcurl text NOT NULL,
    srcorg integer NOT NULL,
    srcid text NOT NULL,
    lastscraped timestamp without time zone,
    wordcount integer,
    status character(1) DEFAULT 'a'::bpchar,
    total_bloglinks integer DEFAULT 0 NOT NULL,
    total_comments integer DEFAULT 0 NOT NULL,
    needs_indexing boolean DEFAULT true NOT NULL,
    last_comment_check timestamp without time zone,
    last_similar timestamp without time zone,
    CONSTRAINT article_status_check CHECK ((((status = 'a'::bpchar) OR (status = 'h'::bpchar)) OR (status = 'd'::bpchar)))
);


--
-- Name: article_bloglink; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE article_bloglink (
    id integer NOT NULL,
    nearestpermalink text DEFAULT ''::text NOT NULL,
    title text DEFAULT ''::text NOT NULL,
    blogname text NOT NULL,
    blogurl text NOT NULL,
    linkcreated timestamp without time zone NOT NULL,
    excerpt text DEFAULT ''::text NOT NULL,
    source text DEFAULT ''::text NOT NULL,
    article_id integer NOT NULL
);


--
-- Name: article_bloglink_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE article_bloglink_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: article_bloglink_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE article_bloglink_id_seq OWNED BY article_bloglink.id;


--
-- Name: article_commentlink; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE article_commentlink (
    article_id integer NOT NULL,
    source text DEFAULT ''::text NOT NULL,
    comment_url text DEFAULT ''::text NOT NULL,
    num_comments integer,
    score integer
);


--
-- Name: article_dupe; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE article_dupe (
    article_id integer NOT NULL,
    dupeof_id integer NOT NULL
);


--
-- Name: article_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE article_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: article_image; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE article_image (
    id integer NOT NULL,
    article_id integer NOT NULL,
    url text NOT NULL,
    caption text DEFAULT ''::text NOT NULL,
    credit text DEFAULT ''::text NOT NULL
);


--
-- Name: article_image_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE article_image_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: article_image_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE article_image_id_seq OWNED BY article_image.id;


--
-- Name: article_needs_indexing; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE article_needs_indexing (
    article_id integer NOT NULL
);


--
-- Name: article_similar; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE article_similar (
    article_id integer NOT NULL,
    other_id integer NOT NULL,
    score real NOT NULL
);


--
-- Name: article_tag; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE article_tag (
    article_id integer NOT NULL,
    tag text NOT NULL,
    freq integer NOT NULL,
    kind character(1) DEFAULT ' '::bpchar NOT NULL
);


--
-- Name: custompaper; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE custompaper (
    id integer NOT NULL,
    owner integer NOT NULL,
    name text DEFAULT ''::text NOT NULL,
    description text DEFAULT ''::text NOT NULL,
    is_public boolean DEFAULT false NOT NULL
);


--
-- Name: custompaper_criteria_journo; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE custompaper_criteria_journo (
    id integer NOT NULL,
    paper_id integer NOT NULL,
    journo_id integer NOT NULL
);


--
-- Name: custompaper_criteria_journo_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE custompaper_criteria_journo_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: custompaper_criteria_journo_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE custompaper_criteria_journo_id_seq OWNED BY custompaper_criteria_journo.id;


--
-- Name: custompaper_criteria_text; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE custompaper_criteria_text (
    id integer NOT NULL,
    paper_id integer NOT NULL,
    query text NOT NULL
);


--
-- Name: custompaper_criteria_text_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE custompaper_criteria_text_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: custompaper_criteria_text_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE custompaper_criteria_text_id_seq OWNED BY custompaper_criteria_text.id;


--
-- Name: custompaper_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE custompaper_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: custompaper_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE custompaper_id_seq OWNED BY custompaper.id;


--
-- Name: error_articlescrape; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE error_articlescrape (
    srcid text NOT NULL,
    scraper text NOT NULL,
    title text,
    srcurl text NOT NULL,
    attempts integer DEFAULT 1 NOT NULL,
    report text NOT NULL,
    action character(1) DEFAULT ' '::bpchar NOT NULL,
    firstattempt timestamp without time zone DEFAULT now() NOT NULL,
    lastattempt timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: event_log; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE event_log (
    id integer NOT NULL,
    event_type text NOT NULL,
    event_time timestamp without time zone DEFAULT now() NOT NULL,
    journo_id integer,
    context_json text DEFAULT ''::text NOT NULL
);


--
-- Name: event_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE event_log_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: event_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE event_log_id_seq OWNED BY event_log.id;


--
-- Name: htmlcache; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE htmlcache (
    name character varying(10) NOT NULL,
    content text,
    gentime timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: image; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE image (
    id integer NOT NULL,
    filename text NOT NULL,
    width integer,
    height integer,
    created timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: image_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE image_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: image_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE image_id_seq OWNED BY image.id;


--
-- Name: journo; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE journo (
    id integer DEFAULT nextval(('journo_id_seq'::text)::regclass) NOT NULL,
    ref text NOT NULL,
    prettyname text NOT NULL,
    lastname text NOT NULL,
    firstname text NOT NULL,
    created timestamp without time zone NOT NULL,
    status character(1) DEFAULT 'i'::bpchar,
    oneliner text DEFAULT ''::text NOT NULL,
    last_similar timestamp without time zone,
    modified boolean DEFAULT true NOT NULL,
    firstname_metaphone text DEFAULT ''::text NOT NULL,
    lastname_metaphone text DEFAULT ''::text NOT NULL,
    admin_notes text DEFAULT ''::text NOT NULL,
    admin_tags text DEFAULT ''::text NOT NULL,
    CONSTRAINT journo_status_check CHECK ((((status = 'a'::bpchar) OR (status = 'h'::bpchar)) OR (status = 'i'::bpchar)))
);


--
-- Name: journo_address; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE journo_address (
    id integer NOT NULL,
    journo_id integer NOT NULL,
    address text DEFAULT ''::text NOT NULL
);


--
-- Name: journo_address_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE journo_address_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: journo_address_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE journo_address_id_seq OWNED BY journo_address.id;


--
-- Name: journo_admired; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE journo_admired (
    id integer NOT NULL,
    journo_id integer NOT NULL,
    admired_name text,
    admired_id integer
);


--
-- Name: journo_admired_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE journo_admired_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: journo_admired_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE journo_admired_id_seq OWNED BY journo_admired.id;


--
-- Name: journo_alias; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE journo_alias (
    journo_id integer NOT NULL,
    alias text NOT NULL
);


--
-- Name: journo_attr; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE journo_attr (
    journo_id integer NOT NULL,
    article_id integer NOT NULL
);


--
-- Name: journo_average_cache; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE journo_average_cache (
    last_updated timestamp without time zone,
    wc_total real,
    wc_avg real,
    wc_min real,
    wc_max real,
    num_articles real
);


--
-- Name: journo_awards; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE journo_awards (
    id integer NOT NULL,
    journo_id integer NOT NULL,
    award text DEFAULT ''::text NOT NULL,
    year smallint
);


--
-- Name: journo_awards_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE journo_awards_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: journo_awards_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE journo_awards_id_seq OWNED BY journo_awards.id;


--
-- Name: journo_bio; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE journo_bio (
    bio text NOT NULL,
    approved boolean DEFAULT false,
    id integer NOT NULL,
    journo_id integer NOT NULL,
    srcurl text NOT NULL,
    kind text DEFAULT ''::text NOT NULL
);


--
-- Name: journo_bio_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE journo_bio_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: journo_bio_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE journo_bio_id_seq OWNED BY journo_bio.id;


--
-- Name: journo_books; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE journo_books (
    id integer NOT NULL,
    journo_id integer NOT NULL,
    title text DEFAULT ''::text NOT NULL,
    publisher text DEFAULT ''::text NOT NULL,
    year_published smallint
);


--
-- Name: journo_books_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE journo_books_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: journo_books_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE journo_books_id_seq OWNED BY journo_books.id;


--
-- Name: journo_education; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE journo_education (
    id integer NOT NULL,
    journo_id integer NOT NULL,
    school text DEFAULT ''::text NOT NULL,
    field text DEFAULT ''::text NOT NULL,
    qualification text DEFAULT ''::text NOT NULL,
    year_from smallint,
    year_to smallint,
    kind character(1) DEFAULT 'u'::bpchar NOT NULL
);


--
-- Name: journo_education_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE journo_education_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: journo_education_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE journo_education_id_seq OWNED BY journo_education.id;


--
-- Name: journo_email; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE journo_email (
    email text NOT NULL,
    srcurl text NOT NULL,
    srctype text NOT NULL,
    journo_id integer NOT NULL,
    approved boolean DEFAULT false NOT NULL,
    id integer NOT NULL
);


--
-- Name: journo_email_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE journo_email_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: journo_email_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE journo_email_id_seq OWNED BY journo_email.id;


--
-- Name: journo_employment; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE journo_employment (
    id integer NOT NULL,
    journo_id integer NOT NULL,
    employer text DEFAULT ''::text NOT NULL,
    job_title text DEFAULT ''::text NOT NULL,
    year_from smallint,
    year_to smallint,
    kind character(1) DEFAULT 'e'::bpchar NOT NULL,
    current boolean DEFAULT false NOT NULL
);


--
-- Name: journo_employment_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE journo_employment_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: journo_employment_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE journo_employment_id_seq OWNED BY journo_employment.id;


--
-- Name: journo_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE journo_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: journo_jobtitle; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE journo_jobtitle (
    journo_id integer NOT NULL,
    jobtitle text NOT NULL,
    firstseen timestamp without time zone NOT NULL,
    lastseen timestamp without time zone NOT NULL,
    org_id integer,
    id integer NOT NULL
);


--
-- Name: journo_jobtitle_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE journo_jobtitle_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: journo_jobtitle_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE journo_jobtitle_id_seq OWNED BY journo_jobtitle.id;


--
-- Name: journo_other_articles; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE journo_other_articles (
    id integer NOT NULL,
    journo_id integer NOT NULL,
    url text NOT NULL,
    title text NOT NULL,
    pubdate timestamp without time zone NOT NULL,
    publication text,
    status character(1) DEFAULT 'u'::bpchar NOT NULL
);


--
-- Name: journo_other_articles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE journo_other_articles_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: journo_other_articles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE journo_other_articles_id_seq OWNED BY journo_other_articles.id;


--
-- Name: journo_phone; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE journo_phone (
    id integer NOT NULL,
    journo_id integer NOT NULL,
    phone_number text DEFAULT ''::text NOT NULL
);


--
-- Name: journo_phone_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE journo_phone_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: journo_phone_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE journo_phone_id_seq OWNED BY journo_phone.id;


--
-- Name: journo_photo; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE journo_photo (
    id integer NOT NULL,
    journo_id integer NOT NULL,
    image_id integer NOT NULL,
    is_thumbnail boolean NOT NULL
);


--
-- Name: journo_photo_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE journo_photo_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: journo_photo_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE journo_photo_id_seq OWNED BY journo_photo.id;


--
-- Name: journo_similar; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE journo_similar (
    journo_id integer NOT NULL,
    other_id integer NOT NULL,
    score real NOT NULL
);


--
-- Name: journo_weblink; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE journo_weblink (
    id integer DEFAULT nextval(('journo_weblink_id_seq'::text)::regclass) NOT NULL,
    journo_id integer NOT NULL,
    url text NOT NULL,
    description text NOT NULL,
    approved boolean DEFAULT false NOT NULL,
    kind text DEFAULT ''::text NOT NULL,
    rank integer DEFAULT 100 NOT NULL
);


--
-- Name: journo_weblink_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE journo_weblink_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: missing_articles; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE missing_articles (
    id integer NOT NULL,
    journo_id integer,
    url text NOT NULL,
    submitted timestamp without time zone DEFAULT now() NOT NULL,
    reason text DEFAULT ''::text NOT NULL
);


--
-- Name: missing_articles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE missing_articles_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: missing_articles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE missing_articles_id_seq OWNED BY missing_articles.id;


--
-- Name: news; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE news (
    id integer NOT NULL,
    status character(1) DEFAULT 'u'::bpchar NOT NULL,
    title text DEFAULT ''::text NOT NULL,
    author text DEFAULT ''::text NOT NULL,
    slug text DEFAULT ''::text NOT NULL,
    posted timestamp without time zone DEFAULT now() NOT NULL,
    content text DEFAULT ''::text NOT NULL,
    date_from date,
    date_to date,
    kind text DEFAULT ''::text NOT NULL
);


--
-- Name: news_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE news_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: news_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE news_id_seq OWNED BY news.id;


--
-- Name: organisation; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE organisation (
    id integer DEFAULT nextval(('organisation_id_seq'::text)::regclass) NOT NULL,
    shortname text NOT NULL,
    prettyname text NOT NULL,
    phone text DEFAULT ''::text NOT NULL,
    email_format text DEFAULT ''::text NOT NULL,
    home_url text DEFAULT ''::text NOT NULL,
    sop_name text DEFAULT ''::text NOT NULL,
    sop_url text DEFAULT ''::text NOT NULL
);


--
-- Name: organisation_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE organisation_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: person; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE person (
    id integer NOT NULL,
    name text,
    email text NOT NULL,
    password text,
    website text,
    numlogins integer DEFAULT 0 NOT NULL
);


--
-- Name: person_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE person_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: person_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE person_id_seq OWNED BY person.id;


--
-- Name: person_permission; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE person_permission (
    id integer NOT NULL,
    person_id integer NOT NULL,
    journo_id integer,
    permission text,
    created timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: person_permission_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE person_permission_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: person_permission_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE person_permission_id_seq OWNED BY person_permission.id;


--
-- Name: person_receives_newsletter; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE person_receives_newsletter (
    person_id integer NOT NULL
);


--
-- Name: recently_viewed; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE recently_viewed (
    id integer NOT NULL,
    journo_id integer,
    view_time timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: recently_viewed_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE recently_viewed_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: recently_viewed_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE recently_viewed_id_seq OWNED BY recently_viewed.id;


--
-- Name: requeststash; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE requeststash (
    key character(16) NOT NULL,
    whensaved timestamp without time zone DEFAULT now() NOT NULL,
    method text DEFAULT 'GET'::text NOT NULL,
    url text NOT NULL,
    post_data bytea,
    extra text,
    email text DEFAULT ''::text,
    CONSTRAINT requeststash_check CHECK ((((post_data IS NULL) AND (method = 'GET'::text)) OR ((post_data IS NOT NULL) AND (method = 'POST'::text)))),
    CONSTRAINT requeststash_method_check CHECK (((method = 'GET'::text) OR (method = 'POST'::text)))
);


--
-- Name: secret; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE secret (
    secret text NOT NULL
);


--
-- Name: tag_blacklist; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE tag_blacklist (
    bannedtag text NOT NULL
);


--
-- Name: tag_synonym; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE tag_synonym (
    alternate text NOT NULL,
    tag text NOT NULL
);


--
-- Name: token; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE token (
    scope text NOT NULL,
    token text NOT NULL,
    data bytea NOT NULL,
    created timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE alert ALTER COLUMN id SET DEFAULT nextval('alert_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE article_bloglink ALTER COLUMN id SET DEFAULT nextval('article_bloglink_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE article_image ALTER COLUMN id SET DEFAULT nextval('article_image_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE custompaper ALTER COLUMN id SET DEFAULT nextval('custompaper_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE custompaper_criteria_journo ALTER COLUMN id SET DEFAULT nextval('custompaper_criteria_journo_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE custompaper_criteria_text ALTER COLUMN id SET DEFAULT nextval('custompaper_criteria_text_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE event_log ALTER COLUMN id SET DEFAULT nextval('event_log_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE image ALTER COLUMN id SET DEFAULT nextval('image_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE journo_address ALTER COLUMN id SET DEFAULT nextval('journo_address_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE journo_admired ALTER COLUMN id SET DEFAULT nextval('journo_admired_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE journo_awards ALTER COLUMN id SET DEFAULT nextval('journo_awards_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE journo_bio ALTER COLUMN id SET DEFAULT nextval('journo_bio_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE journo_books ALTER COLUMN id SET DEFAULT nextval('journo_books_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE journo_education ALTER COLUMN id SET DEFAULT nextval('journo_education_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE journo_email ALTER COLUMN id SET DEFAULT nextval('journo_email_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE journo_employment ALTER COLUMN id SET DEFAULT nextval('journo_employment_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE journo_jobtitle ALTER COLUMN id SET DEFAULT nextval('journo_jobtitle_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE journo_other_articles ALTER COLUMN id SET DEFAULT nextval('journo_other_articles_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE journo_phone ALTER COLUMN id SET DEFAULT nextval('journo_phone_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE journo_photo ALTER COLUMN id SET DEFAULT nextval('journo_photo_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE missing_articles ALTER COLUMN id SET DEFAULT nextval('missing_articles_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE news ALTER COLUMN id SET DEFAULT nextval('news_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE person ALTER COLUMN id SET DEFAULT nextval('person_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE person_permission ALTER COLUMN id SET DEFAULT nextval('person_permission_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE recently_viewed ALTER COLUMN id SET DEFAULT nextval('recently_viewed_id_seq'::regclass);


--
-- Name: alert_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY alert
    ADD CONSTRAINT alert_pkey PRIMARY KEY (id);


--
-- Name: article_bloglink_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY article_bloglink
    ADD CONSTRAINT article_bloglink_pkey PRIMARY KEY (id);


--
-- Name: article_commentlink_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY article_commentlink
    ADD CONSTRAINT article_commentlink_pkey PRIMARY KEY (article_id, source);


--
-- Name: article_dupe_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY article_dupe
    ADD CONSTRAINT article_dupe_pkey PRIMARY KEY (article_id);


--
-- Name: article_image_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY article_image
    ADD CONSTRAINT article_image_pkey PRIMARY KEY (id);


--
-- Name: article_needs_indexing_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY article_needs_indexing
    ADD CONSTRAINT article_needs_indexing_pkey PRIMARY KEY (article_id);


--
-- Name: article_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY article
    ADD CONSTRAINT article_pkey PRIMARY KEY (id);


--
-- Name: article_similar_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY article_similar
    ADD CONSTRAINT article_similar_pkey PRIMARY KEY (article_id, other_id);


--
-- Name: article_srcid_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY article
    ADD CONSTRAINT article_srcid_key UNIQUE (srcid);


--
-- Name: article_tag_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY article_tag
    ADD CONSTRAINT article_tag_pkey PRIMARY KEY (article_id, tag);


--
-- Name: custompaper_criteria_journo_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY custompaper_criteria_journo
    ADD CONSTRAINT custompaper_criteria_journo_pkey PRIMARY KEY (id);


--
-- Name: custompaper_criteria_text_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY custompaper_criteria_text
    ADD CONSTRAINT custompaper_criteria_text_pkey PRIMARY KEY (id);


--
-- Name: custompaper_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY custompaper
    ADD CONSTRAINT custompaper_pkey PRIMARY KEY (id);


--
-- Name: error_articlescrape_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY error_articlescrape
    ADD CONSTRAINT error_articlescrape_pkey PRIMARY KEY (srcid);


--
-- Name: event_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY event_log
    ADD CONSTRAINT event_log_pkey PRIMARY KEY (id);


--
-- Name: htmlcache_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY htmlcache
    ADD CONSTRAINT htmlcache_pkey PRIMARY KEY (name);


--
-- Name: image_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY image
    ADD CONSTRAINT image_pkey PRIMARY KEY (id);


--
-- Name: journo_address_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY journo_address
    ADD CONSTRAINT journo_address_pkey PRIMARY KEY (id);


--
-- Name: journo_admired_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY journo_admired
    ADD CONSTRAINT journo_admired_pkey PRIMARY KEY (id);


--
-- Name: journo_alias_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY journo_alias
    ADD CONSTRAINT journo_alias_pkey PRIMARY KEY (journo_id, alias);


--
-- Name: journo_attr_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY journo_attr
    ADD CONSTRAINT journo_attr_pkey PRIMARY KEY (journo_id, article_id);


--
-- Name: journo_awards_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY journo_awards
    ADD CONSTRAINT journo_awards_pkey PRIMARY KEY (id);


--
-- Name: journo_books_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY journo_books
    ADD CONSTRAINT journo_books_pkey PRIMARY KEY (id);


--
-- Name: journo_education_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY journo_education
    ADD CONSTRAINT journo_education_pkey PRIMARY KEY (id);


--
-- Name: journo_employment_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY journo_employment
    ADD CONSTRAINT journo_employment_pkey PRIMARY KEY (id);


--
-- Name: journo_jobtitle_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY journo_jobtitle
    ADD CONSTRAINT journo_jobtitle_pkey PRIMARY KEY (id);


--
-- Name: journo_other_articles_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY journo_other_articles
    ADD CONSTRAINT journo_other_articles_pkey PRIMARY KEY (id);


--
-- Name: journo_phone_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY journo_phone
    ADD CONSTRAINT journo_phone_pkey PRIMARY KEY (id);


--
-- Name: journo_photo_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY journo_photo
    ADD CONSTRAINT journo_photo_pkey PRIMARY KEY (id);


--
-- Name: journo_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY journo
    ADD CONSTRAINT journo_pkey PRIMARY KEY (id);


--
-- Name: journo_similar_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY journo_similar
    ADD CONSTRAINT journo_similar_pkey PRIMARY KEY (journo_id, other_id);


--
-- Name: journo_uniquename_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY journo
    ADD CONSTRAINT journo_uniquename_key UNIQUE (ref);


--
-- Name: journo_weblink_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY journo_weblink
    ADD CONSTRAINT journo_weblink_pkey PRIMARY KEY (id);


--
-- Name: missing_articles_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY missing_articles
    ADD CONSTRAINT missing_articles_pkey PRIMARY KEY (id);


--
-- Name: news_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY news
    ADD CONSTRAINT news_pkey PRIMARY KEY (id);


--
-- Name: organisation_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY organisation
    ADD CONSTRAINT organisation_pkey PRIMARY KEY (id);


--
-- Name: person_permission_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY person_permission
    ADD CONSTRAINT person_permission_pkey PRIMARY KEY (id);


--
-- Name: person_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY person
    ADD CONSTRAINT person_pkey PRIMARY KEY (id);


--
-- Name: person_receives_newsletter_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY person_receives_newsletter
    ADD CONSTRAINT person_receives_newsletter_pkey PRIMARY KEY (person_id);


--
-- Name: recently_viewed_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY recently_viewed
    ADD CONSTRAINT recently_viewed_pkey PRIMARY KEY (id);


--
-- Name: requeststash_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY requeststash
    ADD CONSTRAINT requeststash_pkey PRIMARY KEY (key);


--
-- Name: tag_blacklist_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY tag_blacklist
    ADD CONSTRAINT tag_blacklist_pkey PRIMARY KEY (bannedtag);


--
-- Name: tag_synonym_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY tag_synonym
    ADD CONSTRAINT tag_synonym_pkey PRIMARY KEY (alternate);


--
-- Name: token_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY token
    ADD CONSTRAINT token_pkey PRIMARY KEY (scope, token);


--
-- Name: alert_person_id_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX alert_person_id_idx ON alert USING btree (person_id);


--
-- Name: article_bloglink_article_id_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX article_bloglink_article_id_idx ON article_bloglink USING btree (article_id);


--
-- Name: article_image_article_id_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX article_image_article_id_idx ON article_image USING btree (article_id);


--
-- Name: article_lastscraped_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX article_lastscraped_idx ON article USING btree (lastscraped);


--
-- Name: article_pubdate_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX article_pubdate_idx ON article USING btree (pubdate);


--
-- Name: article_similar_article_id_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX article_similar_article_id_idx ON article_similar USING btree (article_id);


--
-- Name: article_similar_other_id_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX article_similar_other_id_idx ON article_similar USING btree (other_id);


--
-- Name: article_srcid_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX article_srcid_idx ON article USING btree (srcid);


--
-- Name: article_tag_article_id_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX article_tag_article_id_idx ON article_tag USING btree (article_id);


--
-- Name: article_tag_tag_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX article_tag_tag_idx ON article_tag USING btree (tag);


--
-- Name: article_title_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX article_title_idx ON article USING btree (title);


--
-- Name: articles_needs_indexing_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX articles_needs_indexing_idx ON article USING btree (needs_indexing);


--
-- Name: journo_attr_article_id_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX journo_attr_article_id_idx ON journo_attr USING btree (article_id);


--
-- Name: journo_attr_journo_id_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX journo_attr_journo_id_idx ON journo_attr USING btree (journo_id);


--
-- Name: journo_bio_idkey; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE UNIQUE INDEX journo_bio_idkey ON journo_bio USING btree (id);


--
-- Name: journo_bio_idx_journo_id; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX journo_bio_idx_journo_id ON journo_bio USING btree (journo_id);


--
-- Name: journo_email_idkey; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE UNIQUE INDEX journo_email_idkey ON journo_email USING btree (id);


--
-- Name: journo_firstname_metaphone_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX journo_firstname_metaphone_idx ON journo USING btree (firstname_metaphone);


--
-- Name: journo_lastname_metaphone_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX journo_lastname_metaphone_idx ON journo USING btree (lastname_metaphone);


--
-- Name: journo_other_articles_journo_id_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX journo_other_articles_journo_id_idx ON journo_other_articles USING btree (journo_id);


--
-- Name: journo_similar_journo_id_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX journo_similar_journo_id_idx ON journo_similar USING btree (journo_id);


--
-- Name: journo_weblink_idx_journo_id; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX journo_weblink_idx_journo_id ON journo_weblink USING btree (journo_id);


--
-- Name: person_email_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE UNIQUE INDEX person_email_idx ON person USING btree (email);


--
-- Name: requeststash_whensaved_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX requeststash_whensaved_idx ON requeststash USING btree (whensaved);


--
-- Name: article_update_total_bloglinks_on_delete; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER article_update_total_bloglinks_on_delete
    AFTER DELETE ON article_bloglink
    FOR EACH ROW
    EXECUTE PROCEDURE article_update_total_bloglinks();


--
-- Name: article_update_total_bloglinks_on_insert; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER article_update_total_bloglinks_on_insert
    AFTER INSERT ON article_bloglink
    FOR EACH ROW
    EXECUTE PROCEDURE article_update_total_bloglinks();


--
-- Name: article_update_total_comments_on_delete; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER article_update_total_comments_on_delete
    AFTER DELETE ON article_commentlink
    FOR EACH ROW
    EXECUTE PROCEDURE article_update_total_comments();


--
-- Name: article_update_total_comments_on_insert; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER article_update_total_comments_on_insert
    AFTER INSERT ON article_commentlink
    FOR EACH ROW
    EXECUTE PROCEDURE article_update_total_comments();


--
-- Name: article_update_total_comments_on_update; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER article_update_total_comments_on_update
    AFTER UPDATE ON article_commentlink
    FOR EACH ROW
    EXECUTE PROCEDURE article_update_total_comments();


--
-- Name: journo_address_delete; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_address_delete
    AFTER DELETE ON journo_address
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_ondelete();


--
-- Name: journo_address_insert; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_address_insert
    AFTER INSERT ON journo_address
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_oninsert();


--
-- Name: journo_address_update; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_address_update
    AFTER UPDATE ON journo_address
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_onupdate();


--
-- Name: journo_admired_delete; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_admired_delete
    AFTER DELETE ON journo_admired
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_ondelete();


--
-- Name: journo_admired_insert; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_admired_insert
    AFTER INSERT ON journo_admired
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_oninsert();


--
-- Name: journo_admired_update; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_admired_update
    AFTER UPDATE ON journo_admired
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_onupdate();


--
-- Name: journo_attr_delete; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_attr_delete
    AFTER DELETE ON journo_attr
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_ondelete();


--
-- Name: journo_attr_insert; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_attr_insert
    AFTER INSERT ON journo_attr
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_oninsert();


--
-- Name: journo_attr_update; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_attr_update
    AFTER UPDATE ON journo_attr
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_onupdate();


--
-- Name: journo_awards_delete; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_awards_delete
    AFTER DELETE ON journo_awards
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_ondelete();


--
-- Name: journo_awards_insert; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_awards_insert
    AFTER INSERT ON journo_awards
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_oninsert();


--
-- Name: journo_awards_update; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_awards_update
    AFTER UPDATE ON journo_awards
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_onupdate();


--
-- Name: journo_bio_delete; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_bio_delete
    AFTER DELETE ON journo_bio
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_ondelete();


--
-- Name: journo_bio_insert; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_bio_insert
    AFTER INSERT ON journo_bio
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_oninsert();


--
-- Name: journo_bio_update; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_bio_update
    AFTER UPDATE ON journo_bio
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_onupdate();


--
-- Name: journo_books_delete; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_books_delete
    AFTER DELETE ON journo_books
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_ondelete();


--
-- Name: journo_books_insert; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_books_insert
    AFTER INSERT ON journo_books
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_oninsert();


--
-- Name: journo_books_update; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_books_update
    AFTER UPDATE ON journo_books
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_onupdate();


--
-- Name: journo_education_delete; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_education_delete
    AFTER DELETE ON journo_education
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_ondelete();


--
-- Name: journo_education_insert; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_education_insert
    AFTER INSERT ON journo_education
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_oninsert();


--
-- Name: journo_education_update; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_education_update
    AFTER UPDATE ON journo_education
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_onupdate();


--
-- Name: journo_email_delete; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_email_delete
    AFTER DELETE ON journo_email
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_ondelete();


--
-- Name: journo_email_insert; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_email_insert
    AFTER INSERT ON journo_email
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_oninsert();


--
-- Name: journo_email_update; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_email_update
    AFTER UPDATE ON journo_email
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_onupdate();


--
-- Name: journo_employment_delete; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_employment_delete
    AFTER DELETE ON journo_employment
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_ondelete();


--
-- Name: journo_employment_insert; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_employment_insert
    AFTER INSERT ON journo_employment
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_oninsert();


--
-- Name: journo_employment_update; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_employment_update
    AFTER UPDATE ON journo_employment
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_onupdate();


--
-- Name: journo_other_articles_delete; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_other_articles_delete
    AFTER DELETE ON journo_other_articles
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_ondelete();


--
-- Name: journo_other_articles_insert; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_other_articles_insert
    AFTER INSERT ON journo_other_articles
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_oninsert();


--
-- Name: journo_other_articles_update; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_other_articles_update
    AFTER UPDATE ON journo_other_articles
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_onupdate();


--
-- Name: journo_phone_delete; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_phone_delete
    AFTER DELETE ON journo_phone
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_ondelete();


--
-- Name: journo_phone_insert; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_phone_insert
    AFTER INSERT ON journo_phone
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_oninsert();


--
-- Name: journo_phone_update; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_phone_update
    AFTER UPDATE ON journo_phone
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_onupdate();


--
-- Name: journo_photo_delete; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_photo_delete
    AFTER DELETE ON journo_photo
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_ondelete();


--
-- Name: journo_photo_insert; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_photo_insert
    AFTER INSERT ON journo_photo
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_oninsert();


--
-- Name: journo_photo_update; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_photo_update
    AFTER UPDATE ON journo_photo
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_onupdate();


--
-- Name: journo_similar_delete; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_similar_delete
    AFTER DELETE ON journo_similar
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_ondelete();


--
-- Name: journo_similar_insert; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_similar_insert
    AFTER INSERT ON journo_similar
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_oninsert();


--
-- Name: journo_similar_update; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_similar_update
    AFTER UPDATE ON journo_similar
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_onupdate();


--
-- Name: journo_weblink_delete; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_weblink_delete
    AFTER DELETE ON journo_weblink
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_ondelete();


--
-- Name: journo_weblink_insert; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_weblink_insert
    AFTER INSERT ON journo_weblink
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_oninsert();


--
-- Name: journo_weblink_update; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER journo_weblink_update
    AFTER UPDATE ON journo_weblink
    FOR EACH ROW
    EXECUTE PROCEDURE journo_setmodified_onupdate();


--
-- Name: $1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY article
    ADD CONSTRAINT "$1" FOREIGN KEY (srcorg) REFERENCES organisation(id);


--
-- Name: alert_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY alert
    ADD CONSTRAINT alert_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: alert_person_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY alert
    ADD CONSTRAINT alert_person_id_fkey FOREIGN KEY (person_id) REFERENCES person(id) ON DELETE CASCADE;


--
-- Name: article_bloglink_article_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY article_bloglink
    ADD CONSTRAINT article_bloglink_article_id_fkey FOREIGN KEY (article_id) REFERENCES article(id) ON DELETE CASCADE;


--
-- Name: article_commentlink_article_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY article_commentlink
    ADD CONSTRAINT article_commentlink_article_id_fkey FOREIGN KEY (article_id) REFERENCES article(id) ON DELETE CASCADE;


--
-- Name: article_dupe_article_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY article_dupe
    ADD CONSTRAINT article_dupe_article_id_fkey FOREIGN KEY (article_id) REFERENCES article(id) ON DELETE CASCADE;


--
-- Name: article_dupe_dupeof_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY article_dupe
    ADD CONSTRAINT article_dupe_dupeof_id_fkey FOREIGN KEY (dupeof_id) REFERENCES article(id) ON DELETE CASCADE;


--
-- Name: article_image_article_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY article_image
    ADD CONSTRAINT article_image_article_id_fkey FOREIGN KEY (article_id) REFERENCES article(id) ON DELETE CASCADE;


--
-- Name: article_needs_indexing_article_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY article_needs_indexing
    ADD CONSTRAINT article_needs_indexing_article_id_fkey FOREIGN KEY (article_id) REFERENCES article(id) ON DELETE CASCADE;


--
-- Name: article_similar_article_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY article_similar
    ADD CONSTRAINT article_similar_article_id_fkey FOREIGN KEY (article_id) REFERENCES article(id) ON DELETE CASCADE;


--
-- Name: article_similar_other_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY article_similar
    ADD CONSTRAINT article_similar_other_id_fkey FOREIGN KEY (other_id) REFERENCES article(id) ON DELETE CASCADE;


--
-- Name: article_tag_article_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY article_tag
    ADD CONSTRAINT article_tag_article_id_fkey FOREIGN KEY (article_id) REFERENCES article(id) ON DELETE CASCADE;


--
-- Name: custompaper_criteria_journo_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY custompaper_criteria_journo
    ADD CONSTRAINT custompaper_criteria_journo_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: custompaper_criteria_journo_paper_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY custompaper_criteria_journo
    ADD CONSTRAINT custompaper_criteria_journo_paper_id_fkey FOREIGN KEY (paper_id) REFERENCES custompaper(id) ON DELETE CASCADE;


--
-- Name: custompaper_criteria_text_paper_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY custompaper_criteria_text
    ADD CONSTRAINT custompaper_criteria_text_paper_id_fkey FOREIGN KEY (paper_id) REFERENCES custompaper(id) ON DELETE CASCADE;


--
-- Name: custompaper_owner_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY custompaper
    ADD CONSTRAINT custompaper_owner_fkey FOREIGN KEY (owner) REFERENCES person(id);


--
-- Name: event_log_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY event_log
    ADD CONSTRAINT event_log_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: journo_address_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY journo_address
    ADD CONSTRAINT journo_address_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: journo_admired_admired_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY journo_admired
    ADD CONSTRAINT journo_admired_admired_id_fkey FOREIGN KEY (admired_id) REFERENCES journo(id);


--
-- Name: journo_admired_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY journo_admired
    ADD CONSTRAINT journo_admired_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: journo_alias_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY journo_alias
    ADD CONSTRAINT journo_alias_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: journo_attr_article_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY journo_attr
    ADD CONSTRAINT journo_attr_article_id_fkey FOREIGN KEY (article_id) REFERENCES article(id) ON DELETE CASCADE;


--
-- Name: journo_attr_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY journo_attr
    ADD CONSTRAINT journo_attr_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: journo_awards_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY journo_awards
    ADD CONSTRAINT journo_awards_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: journo_bio_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY journo_bio
    ADD CONSTRAINT journo_bio_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id);


--
-- Name: journo_books_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY journo_books
    ADD CONSTRAINT journo_books_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: journo_education_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY journo_education
    ADD CONSTRAINT journo_education_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: journo_email_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY journo_email
    ADD CONSTRAINT journo_email_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id);


--
-- Name: journo_employment_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY journo_employment
    ADD CONSTRAINT journo_employment_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: journo_jobtitle_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY journo_jobtitle
    ADD CONSTRAINT journo_jobtitle_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: journo_jobtitle_org_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY journo_jobtitle
    ADD CONSTRAINT journo_jobtitle_org_id_fkey FOREIGN KEY (org_id) REFERENCES organisation(id);


--
-- Name: journo_other_articles_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY journo_other_articles
    ADD CONSTRAINT journo_other_articles_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: journo_phone_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY journo_phone
    ADD CONSTRAINT journo_phone_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: journo_photo_image_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY journo_photo
    ADD CONSTRAINT journo_photo_image_id_fkey FOREIGN KEY (image_id) REFERENCES image(id) ON DELETE CASCADE;


--
-- Name: journo_photo_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY journo_photo
    ADD CONSTRAINT journo_photo_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: journo_similar_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY journo_similar
    ADD CONSTRAINT journo_similar_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: journo_similar_other_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY journo_similar
    ADD CONSTRAINT journo_similar_other_id_fkey FOREIGN KEY (other_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: journo_weblink_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY journo_weblink
    ADD CONSTRAINT journo_weblink_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: missing_articles_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY missing_articles
    ADD CONSTRAINT missing_articles_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: person_permission_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY person_permission
    ADD CONSTRAINT person_permission_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id);


--
-- Name: person_permission_person_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY person_permission
    ADD CONSTRAINT person_permission_person_id_fkey FOREIGN KEY (person_id) REFERENCES person(id);


--
-- Name: person_receives_newsletter_person_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY person_receives_newsletter
    ADD CONSTRAINT person_receives_newsletter_person_id_fkey FOREIGN KEY (person_id) REFERENCES person(id) ON DELETE CASCADE;


--
-- Name: recently_viewed_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY recently_viewed
    ADD CONSTRAINT recently_viewed_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: public; Type: ACL; Schema: -; Owner: -
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

