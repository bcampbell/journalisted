--
-- PostgreSQL database dump
--

SET client_encoding = 'SQL_ASCII';
SET standard_conforming_strings = off;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET escape_string_warning = off;

--
-- Name: plpgsql; Type: PROCEDURAL LANGUAGE; Schema: -; Owner: postgres
--

CREATE PROCEDURAL LANGUAGE plpgsql;


ALTER PROCEDURAL LANGUAGE plpgsql OWNER TO postgres;

SET search_path = public, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: alert; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE alert (
    id integer NOT NULL,
    person_id integer NOT NULL,
    journo_id integer
);


ALTER TABLE public.alert OWNER TO mst;

--
-- Name: article; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
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
    CONSTRAINT article_status_check CHECK ((((status = 'a'::bpchar) OR (status = 'h'::bpchar)) OR (status = 'd'::bpchar)))
);


ALTER TABLE public.article OWNER TO mst;

--
-- Name: article_bloglink; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
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


ALTER TABLE public.article_bloglink OWNER TO mst;

--
-- Name: article_commentlink; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE article_commentlink (
    article_id integer NOT NULL,
    source text DEFAULT ''::text NOT NULL,
    comment_url text DEFAULT ''::text NOT NULL,
    num_comments integer,
    score integer
);


ALTER TABLE public.article_commentlink OWNER TO mst;

--
-- Name: article_dupe; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE article_dupe (
    article_id integer NOT NULL,
    dupeof_id integer NOT NULL
);


ALTER TABLE public.article_dupe OWNER TO mst;

--
-- Name: article_tag; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE article_tag (
    article_id integer NOT NULL,
    tag text NOT NULL,
    freq integer NOT NULL,
    kind character(1) DEFAULT ' '::bpchar NOT NULL
);


ALTER TABLE public.article_tag OWNER TO mst;

--
-- Name: custompaper; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE custompaper (
    id integer NOT NULL,
    owner integer NOT NULL,
    name text DEFAULT ''::text NOT NULL,
    is_public boolean DEFAULT false NOT NULL,
    description text DEFAULT ''::text NOT NULL
);


ALTER TABLE public.custompaper OWNER TO mst;

--
-- Name: custompaper_criteria_journo; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE custompaper_criteria_journo (
    id integer NOT NULL,
    paper_id integer NOT NULL,
    journo_id integer NOT NULL
);


ALTER TABLE public.custompaper_criteria_journo OWNER TO mst;

--
-- Name: custompaper_criteria_text; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE custompaper_criteria_text (
    id integer NOT NULL,
    paper_id integer NOT NULL,
    query text NOT NULL
);


ALTER TABLE public.custompaper_criteria_text OWNER TO mst;

--
-- Name: error_articlescrape; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
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


ALTER TABLE public.error_articlescrape OWNER TO mst;

--
-- Name: htmlcache; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE htmlcache (
    name character varying(10) NOT NULL,
    content text,
    gentime timestamp without time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.htmlcache OWNER TO mst;

--
-- Name: journo; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
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
    CONSTRAINT journo_status_check CHECK ((((status = 'a'::bpchar) OR (status = 'h'::bpchar)) OR (status = 'i'::bpchar)))
);


ALTER TABLE public.journo OWNER TO mst;

--
-- Name: journo_alias; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE journo_alias (
    journo_id integer NOT NULL,
    alias text NOT NULL
);


ALTER TABLE public.journo_alias OWNER TO mst;

--
-- Name: journo_attr; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE journo_attr (
    journo_id integer NOT NULL,
    article_id integer NOT NULL
);


ALTER TABLE public.journo_attr OWNER TO mst;

--
-- Name: journo_average_cache; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE journo_average_cache (
    last_updated timestamp without time zone,
    wc_total real,
    wc_avg real,
    wc_min real,
    wc_max real,
    num_articles real
);


ALTER TABLE public.journo_average_cache OWNER TO mst;

--
-- Name: journo_bio; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE journo_bio (
    context text NOT NULL,
    bio text NOT NULL,
    approved boolean DEFAULT false,
    id integer NOT NULL,
    journo_id integer NOT NULL,
    type text DEFAULT 'manual-edit'::text NOT NULL,
    srcurl text NOT NULL
);


ALTER TABLE public.journo_bio OWNER TO mst;

--
-- Name: journo_email; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE journo_email (
    email text NOT NULL,
    srcurl text NOT NULL,
    srctype text NOT NULL,
    journo_id integer NOT NULL,
    approved boolean DEFAULT false NOT NULL,
    id integer NOT NULL
);


ALTER TABLE public.journo_email OWNER TO mst;

--
-- Name: journo_jobtitle; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE journo_jobtitle (
    journo_id integer NOT NULL,
    jobtitle text NOT NULL,
    firstseen timestamp without time zone NOT NULL,
    lastseen timestamp without time zone NOT NULL,
    org_id integer,
    id integer NOT NULL
);


ALTER TABLE public.journo_jobtitle OWNER TO mst;

--
-- Name: journo_weblink; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE journo_weblink (
    id integer DEFAULT nextval(('journo_weblink_id_seq'::text)::regclass) NOT NULL,
    journo_id integer NOT NULL,
    url text NOT NULL,
    description text NOT NULL,
    source text,
    approved boolean DEFAULT false NOT NULL,
    type text DEFAULT 'manual-edit'::text NOT NULL
);


ALTER TABLE public.journo_weblink OWNER TO mst;

--
-- Name: organisation; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE organisation (
    id integer DEFAULT nextval(('organisation_id_seq'::text)::regclass) NOT NULL,
    shortname text NOT NULL,
    prettyname text NOT NULL,
    phone text DEFAULT ''::text NOT NULL,
    email_format text DEFAULT ''::text NOT NULL
);


ALTER TABLE public.organisation OWNER TO mst;

--
-- Name: person; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE person (
    id integer NOT NULL,
    name text,
    email text NOT NULL,
    password text,
    website text,
    numlogins integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.person OWNER TO mst;

--
-- Name: requeststash; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE requeststash (
    key character(16) NOT NULL,
    whensaved timestamp without time zone DEFAULT now() NOT NULL,
    method text DEFAULT 'GET'::text NOT NULL,
    url text NOT NULL,
    post_data bytea,
    extra text,
    email text,
    CONSTRAINT requeststash_check CHECK ((((post_data IS NULL) AND (method = 'GET'::text)) OR ((post_data IS NOT NULL) AND (method = 'POST'::text)))),
    CONSTRAINT requeststash_method_check CHECK (((method = 'GET'::text) OR (method = 'POST'::text)))
);


ALTER TABLE public.requeststash OWNER TO mst;

--
-- Name: secret; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE secret (
    secret text NOT NULL
);


ALTER TABLE public.secret OWNER TO mst;

--
-- Name: tag_blacklist; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE tag_blacklist (
    bannedtag text NOT NULL
);


ALTER TABLE public.tag_blacklist OWNER TO mst;

--
-- Name: tag_synonym; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE tag_synonym (
    alternate text NOT NULL,
    tag text NOT NULL
);


ALTER TABLE public.tag_synonym OWNER TO mst;

--
-- Name: token; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE token (
    scope text NOT NULL,
    token text NOT NULL,
    data bytea NOT NULL,
    created timestamp without time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.token OWNER TO mst;

--
-- Name: article_update_total_bloglinks(); Type: FUNCTION; Schema: public; Owner: mst
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


ALTER FUNCTION public.article_update_total_bloglinks() OWNER TO mst;

--
-- Name: article_update_total_comments(); Type: FUNCTION; Schema: public; Owner: mst
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


ALTER FUNCTION public.article_update_total_comments() OWNER TO mst;

--
-- Name: ms_current_timestamp(); Type: FUNCTION; Schema: public; Owner: mst
--

CREATE FUNCTION ms_current_timestamp() RETURNS timestamp without time zone
    AS $$
    begin
        return current_timestamp;
    end;
$$
    LANGUAGE plpgsql;


ALTER FUNCTION public.ms_current_timestamp() OWNER TO mst;

--
-- Name: alert_id_seq; Type: SEQUENCE; Schema: public; Owner: mst
--

CREATE SEQUENCE alert_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


ALTER TABLE public.alert_id_seq OWNER TO mst;

--
-- Name: alert_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mst
--

ALTER SEQUENCE alert_id_seq OWNED BY alert.id;


--
-- Name: article_bloglink_id_seq; Type: SEQUENCE; Schema: public; Owner: mst
--

CREATE SEQUENCE article_bloglink_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


ALTER TABLE public.article_bloglink_id_seq OWNER TO mst;

--
-- Name: article_bloglink_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mst
--

ALTER SEQUENCE article_bloglink_id_seq OWNED BY article_bloglink.id;


--
-- Name: article_id_seq; Type: SEQUENCE; Schema: public; Owner: mst
--

CREATE SEQUENCE article_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


ALTER TABLE public.article_id_seq OWNER TO mst;

--
-- Name: custompaper_criteria_journo_id_seq; Type: SEQUENCE; Schema: public; Owner: mst
--

CREATE SEQUENCE custompaper_criteria_journo_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


ALTER TABLE public.custompaper_criteria_journo_id_seq OWNER TO mst;

--
-- Name: custompaper_criteria_journo_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mst
--

ALTER SEQUENCE custompaper_criteria_journo_id_seq OWNED BY custompaper_criteria_journo.id;


--
-- Name: custompaper_criteria_text_id_seq; Type: SEQUENCE; Schema: public; Owner: mst
--

CREATE SEQUENCE custompaper_criteria_text_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


ALTER TABLE public.custompaper_criteria_text_id_seq OWNER TO mst;

--
-- Name: custompaper_criteria_text_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mst
--

ALTER SEQUENCE custompaper_criteria_text_id_seq OWNED BY custompaper_criteria_text.id;


--
-- Name: custompaper_id_seq; Type: SEQUENCE; Schema: public; Owner: mst
--

CREATE SEQUENCE custompaper_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


ALTER TABLE public.custompaper_id_seq OWNER TO mst;

--
-- Name: custompaper_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mst
--

ALTER SEQUENCE custompaper_id_seq OWNED BY custompaper.id;


--
-- Name: journo_bio_id_seq; Type: SEQUENCE; Schema: public; Owner: mst
--

CREATE SEQUENCE journo_bio_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


ALTER TABLE public.journo_bio_id_seq OWNER TO mst;

--
-- Name: journo_bio_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mst
--

ALTER SEQUENCE journo_bio_id_seq OWNED BY journo_bio.id;


--
-- Name: journo_email_id_seq; Type: SEQUENCE; Schema: public; Owner: mst
--

CREATE SEQUENCE journo_email_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


ALTER TABLE public.journo_email_id_seq OWNER TO mst;

--
-- Name: journo_email_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mst
--

ALTER SEQUENCE journo_email_id_seq OWNED BY journo_email.id;


--
-- Name: journo_id_seq; Type: SEQUENCE; Schema: public; Owner: mst
--

CREATE SEQUENCE journo_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


ALTER TABLE public.journo_id_seq OWNER TO mst;

--
-- Name: journo_jobtitle_id_seq; Type: SEQUENCE; Schema: public; Owner: mst
--

CREATE SEQUENCE journo_jobtitle_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


ALTER TABLE public.journo_jobtitle_id_seq OWNER TO mst;

--
-- Name: journo_jobtitle_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mst
--

ALTER SEQUENCE journo_jobtitle_id_seq OWNED BY journo_jobtitle.id;


--
-- Name: journo_weblink_id_seq; Type: SEQUENCE; Schema: public; Owner: mst
--

CREATE SEQUENCE journo_weblink_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


ALTER TABLE public.journo_weblink_id_seq OWNER TO mst;

--
-- Name: organisation_id_seq; Type: SEQUENCE; Schema: public; Owner: mst
--

CREATE SEQUENCE organisation_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


ALTER TABLE public.organisation_id_seq OWNER TO mst;

--
-- Name: person_id_seq; Type: SEQUENCE; Schema: public; Owner: mst
--

CREATE SEQUENCE person_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


ALTER TABLE public.person_id_seq OWNER TO mst;

--
-- Name: person_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mst
--

ALTER SEQUENCE person_id_seq OWNED BY person.id;


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: mst
--

ALTER TABLE alert ALTER COLUMN id SET DEFAULT nextval('alert_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: mst
--

ALTER TABLE article_bloglink ALTER COLUMN id SET DEFAULT nextval('article_bloglink_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: mst
--

ALTER TABLE custompaper ALTER COLUMN id SET DEFAULT nextval('custompaper_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: mst
--

ALTER TABLE custompaper_criteria_journo ALTER COLUMN id SET DEFAULT nextval('custompaper_criteria_journo_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: mst
--

ALTER TABLE custompaper_criteria_text ALTER COLUMN id SET DEFAULT nextval('custompaper_criteria_text_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: mst
--

ALTER TABLE journo_bio ALTER COLUMN id SET DEFAULT nextval('journo_bio_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: mst
--

ALTER TABLE journo_email ALTER COLUMN id SET DEFAULT nextval('journo_email_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: mst
--

ALTER TABLE journo_jobtitle ALTER COLUMN id SET DEFAULT nextval('journo_jobtitle_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: mst
--

ALTER TABLE person ALTER COLUMN id SET DEFAULT nextval('person_id_seq'::regclass);


--
-- Name: alert_pkey; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY alert
    ADD CONSTRAINT alert_pkey PRIMARY KEY (id);


--
-- Name: article_bloglink_pkey; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY article_bloglink
    ADD CONSTRAINT article_bloglink_pkey PRIMARY KEY (id);


--
-- Name: article_commentlink_pkey; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY article_commentlink
    ADD CONSTRAINT article_commentlink_pkey PRIMARY KEY (article_id, source);


--
-- Name: article_dupe_pkey; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY article_dupe
    ADD CONSTRAINT article_dupe_pkey PRIMARY KEY (article_id);


--
-- Name: article_pkey; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY article
    ADD CONSTRAINT article_pkey PRIMARY KEY (id);


--
-- Name: article_srcid_key; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY article
    ADD CONSTRAINT article_srcid_key UNIQUE (srcid);


--
-- Name: article_tag_pkey; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY article_tag
    ADD CONSTRAINT article_tag_pkey PRIMARY KEY (article_id, tag);


--
-- Name: custompaper_criteria_journo_pkey; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY custompaper_criteria_journo
    ADD CONSTRAINT custompaper_criteria_journo_pkey PRIMARY KEY (id);


--
-- Name: custompaper_criteria_text_pkey; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY custompaper_criteria_text
    ADD CONSTRAINT custompaper_criteria_text_pkey PRIMARY KEY (id);


--
-- Name: custompaper_pkey; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY custompaper
    ADD CONSTRAINT custompaper_pkey PRIMARY KEY (id);


--
-- Name: error_articlescrape_pkey; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY error_articlescrape
    ADD CONSTRAINT error_articlescrape_pkey PRIMARY KEY (srcid);


--
-- Name: htmlcache_pkey; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY htmlcache
    ADD CONSTRAINT htmlcache_pkey PRIMARY KEY (name);


--
-- Name: journo_alias_pkey; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY journo_alias
    ADD CONSTRAINT journo_alias_pkey PRIMARY KEY (journo_id, alias);


--
-- Name: journo_attr_pkey; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY journo_attr
    ADD CONSTRAINT journo_attr_pkey PRIMARY KEY (journo_id, article_id);


--
-- Name: journo_jobtitle_pkey; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY journo_jobtitle
    ADD CONSTRAINT journo_jobtitle_pkey PRIMARY KEY (id);


--
-- Name: journo_pkey; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY journo
    ADD CONSTRAINT journo_pkey PRIMARY KEY (id);


--
-- Name: journo_uniquename_key; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY journo
    ADD CONSTRAINT journo_uniquename_key UNIQUE (ref);


--
-- Name: journo_weblink_pkey; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY journo_weblink
    ADD CONSTRAINT journo_weblink_pkey PRIMARY KEY (id);


--
-- Name: organisation_pkey; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY organisation
    ADD CONSTRAINT organisation_pkey PRIMARY KEY (id);


--
-- Name: person_pkey; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY person
    ADD CONSTRAINT person_pkey PRIMARY KEY (id);


--
-- Name: requeststash_pkey; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY requeststash
    ADD CONSTRAINT requeststash_pkey PRIMARY KEY (key);


--
-- Name: tag_blacklist_pkey; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY tag_blacklist
    ADD CONSTRAINT tag_blacklist_pkey PRIMARY KEY (bannedtag);


--
-- Name: tag_synonym_pkey; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY tag_synonym
    ADD CONSTRAINT tag_synonym_pkey PRIMARY KEY (alternate);


--
-- Name: token_pkey; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY token
    ADD CONSTRAINT token_pkey PRIMARY KEY (scope, token);


--
-- Name: alert_person_id_idx; Type: INDEX; Schema: public; Owner: mst; Tablespace: 
--

CREATE INDEX alert_person_id_idx ON alert USING btree (person_id);


--
-- Name: article_lastscraped_idx; Type: INDEX; Schema: public; Owner: mst; Tablespace: 
--

CREATE INDEX article_lastscraped_idx ON article USING btree (lastscraped);


--
-- Name: article_pubdate_idx; Type: INDEX; Schema: public; Owner: mst; Tablespace: 
--

CREATE INDEX article_pubdate_idx ON article USING btree (pubdate);


--
-- Name: article_srcid_idx; Type: INDEX; Schema: public; Owner: mst; Tablespace: 
--

CREATE INDEX article_srcid_idx ON article USING btree (srcid);


--
-- Name: article_tag_article_id_idx; Type: INDEX; Schema: public; Owner: mst; Tablespace: 
--

CREATE INDEX article_tag_article_id_idx ON article_tag USING btree (article_id);


--
-- Name: article_tag_tag_idx; Type: INDEX; Schema: public; Owner: mst; Tablespace: 
--

CREATE INDEX article_tag_tag_idx ON article_tag USING btree (tag);


--
-- Name: article_title_idx; Type: INDEX; Schema: public; Owner: mst; Tablespace: 
--

CREATE INDEX article_title_idx ON article USING btree (title);


--
-- Name: journo_bio_idkey; Type: INDEX; Schema: public; Owner: mst; Tablespace: 
--

CREATE UNIQUE INDEX journo_bio_idkey ON journo_bio USING btree (id);


--
-- Name: journo_email_idkey; Type: INDEX; Schema: public; Owner: mst; Tablespace: 
--

CREATE UNIQUE INDEX journo_email_idkey ON journo_email USING btree (id);


--
-- Name: journo_weblink_idx_journo_id; Type: INDEX; Schema: public; Owner: mst; Tablespace: 
--

CREATE INDEX journo_weblink_idx_journo_id ON journo_weblink USING btree (journo_id);


--
-- Name: journo_weblink_idx_url; Type: INDEX; Schema: public; Owner: mst; Tablespace: 
--

CREATE INDEX journo_weblink_idx_url ON journo_weblink USING btree (url);


--
-- Name: person_email_idx; Type: INDEX; Schema: public; Owner: mst; Tablespace: 
--

CREATE UNIQUE INDEX person_email_idx ON person USING btree (email);


--
-- Name: requeststash_whensaved_idx; Type: INDEX; Schema: public; Owner: mst; Tablespace: 
--

CREATE INDEX requeststash_whensaved_idx ON requeststash USING btree (whensaved);


--
-- Name: article_update_total_bloglinks_on_delete; Type: TRIGGER; Schema: public; Owner: mst
--

CREATE TRIGGER article_update_total_bloglinks_on_delete
    AFTER DELETE ON article_bloglink
    FOR EACH ROW
    EXECUTE PROCEDURE article_update_total_bloglinks();


--
-- Name: article_update_total_bloglinks_on_insert; Type: TRIGGER; Schema: public; Owner: mst
--

CREATE TRIGGER article_update_total_bloglinks_on_insert
    AFTER INSERT ON article_bloglink
    FOR EACH ROW
    EXECUTE PROCEDURE article_update_total_bloglinks();


--
-- Name: article_update_total_comments_on_delete; Type: TRIGGER; Schema: public; Owner: mst
--

CREATE TRIGGER article_update_total_comments_on_delete
    AFTER DELETE ON article_commentlink
    FOR EACH ROW
    EXECUTE PROCEDURE article_update_total_comments();


--
-- Name: article_update_total_comments_on_insert; Type: TRIGGER; Schema: public; Owner: mst
--

CREATE TRIGGER article_update_total_comments_on_insert
    AFTER INSERT ON article_commentlink
    FOR EACH ROW
    EXECUTE PROCEDURE article_update_total_comments();


--
-- Name: article_update_total_comments_on_update; Type: TRIGGER; Schema: public; Owner: mst
--

CREATE TRIGGER article_update_total_comments_on_update
    AFTER UPDATE ON article_commentlink
    FOR EACH ROW
    EXECUTE PROCEDURE article_update_total_comments();


--
-- Name: $1; Type: FK CONSTRAINT; Schema: public; Owner: mst
--

ALTER TABLE ONLY article
    ADD CONSTRAINT "$1" FOREIGN KEY (srcorg) REFERENCES organisation(id);


--
-- Name: alert_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mst
--

ALTER TABLE ONLY alert
    ADD CONSTRAINT alert_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: alert_person_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mst
--

ALTER TABLE ONLY alert
    ADD CONSTRAINT alert_person_id_fkey FOREIGN KEY (person_id) REFERENCES person(id) ON DELETE CASCADE;


--
-- Name: article_bloglink_article_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mst
--

ALTER TABLE ONLY article_bloglink
    ADD CONSTRAINT article_bloglink_article_id_fkey FOREIGN KEY (article_id) REFERENCES article(id) ON DELETE CASCADE;


--
-- Name: article_commentlink_article_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mst
--

ALTER TABLE ONLY article_commentlink
    ADD CONSTRAINT article_commentlink_article_id_fkey FOREIGN KEY (article_id) REFERENCES article(id) ON DELETE CASCADE;


--
-- Name: article_dupe_article_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mst
--

ALTER TABLE ONLY article_dupe
    ADD CONSTRAINT article_dupe_article_id_fkey FOREIGN KEY (article_id) REFERENCES article(id) ON DELETE CASCADE;


--
-- Name: article_dupe_dupeof_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mst
--

ALTER TABLE ONLY article_dupe
    ADD CONSTRAINT article_dupe_dupeof_id_fkey FOREIGN KEY (dupeof_id) REFERENCES article(id) ON DELETE CASCADE;


--
-- Name: article_tag_article_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mst
--

ALTER TABLE ONLY article_tag
    ADD CONSTRAINT article_tag_article_id_fkey FOREIGN KEY (article_id) REFERENCES article(id) ON DELETE CASCADE;


--
-- Name: custompaper_criteria_journo_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mst
--

ALTER TABLE ONLY custompaper_criteria_journo
    ADD CONSTRAINT custompaper_criteria_journo_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: custompaper_criteria_journo_paper_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mst
--

ALTER TABLE ONLY custompaper_criteria_journo
    ADD CONSTRAINT custompaper_criteria_journo_paper_id_fkey FOREIGN KEY (paper_id) REFERENCES custompaper(id) ON DELETE CASCADE;


--
-- Name: custompaper_criteria_text_paper_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mst
--

ALTER TABLE ONLY custompaper_criteria_text
    ADD CONSTRAINT custompaper_criteria_text_paper_id_fkey FOREIGN KEY (paper_id) REFERENCES custompaper(id) ON DELETE CASCADE;


--
-- Name: custompaper_owner_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mst
--

ALTER TABLE ONLY custompaper
    ADD CONSTRAINT custompaper_owner_fkey FOREIGN KEY (owner) REFERENCES person(id);


--
-- Name: journo_alias_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mst
--

ALTER TABLE ONLY journo_alias
    ADD CONSTRAINT journo_alias_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: journo_attr_article_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mst
--

ALTER TABLE ONLY journo_attr
    ADD CONSTRAINT journo_attr_article_id_fkey FOREIGN KEY (article_id) REFERENCES article(id) ON DELETE CASCADE;


--
-- Name: journo_attr_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mst
--

ALTER TABLE ONLY journo_attr
    ADD CONSTRAINT journo_attr_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: journo_bio_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mst
--

ALTER TABLE ONLY journo_bio
    ADD CONSTRAINT journo_bio_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id);


--
-- Name: journo_email_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mst
--

ALTER TABLE ONLY journo_email
    ADD CONSTRAINT journo_email_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id);


--
-- Name: journo_jobtitle_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mst
--

ALTER TABLE ONLY journo_jobtitle
    ADD CONSTRAINT journo_jobtitle_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: journo_jobtitle_org_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mst
--

ALTER TABLE ONLY journo_jobtitle
    ADD CONSTRAINT journo_jobtitle_org_id_fkey FOREIGN KEY (org_id) REFERENCES organisation(id);


--
-- Name: journo_weblink_journo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mst
--

ALTER TABLE ONLY journo_weblink
    ADD CONSTRAINT journo_weblink_journo_id_fkey FOREIGN KEY (journo_id) REFERENCES journo(id) ON DELETE CASCADE;


--
-- Name: public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

