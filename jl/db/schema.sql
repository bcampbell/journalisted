--
-- PostgreSQL database dump
--

SET client_encoding = 'LATIN1';
SET standard_conforming_strings = off;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET escape_string_warning = off;

--
-- Name: SCHEMA public; Type: COMMENT; Schema: -; Owner: postgres
--

COMMENT ON SCHEMA public IS 'Standard public schema';


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
    CONSTRAINT article_status_check CHECK ((((status = 'a'::bpchar) OR (status = 'h'::bpchar)) OR (status = 'd'::bpchar)))
);


ALTER TABLE public.article OWNER TO mst;

--
-- Name: article_dupe; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE article_dupe (
    article_id integer NOT NULL,
    dupeof_id integer NOT NULL
);


ALTER TABLE public.article_dupe OWNER TO mst;

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
-- Name: journo_id_seq; Type: SEQUENCE; Schema: public; Owner: mst
--

CREATE SEQUENCE journo_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


ALTER TABLE public.journo_id_seq OWNER TO mst;

--
-- Name: journo_jobtitle; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE journo_jobtitle (
    journo_id integer NOT NULL,
    jobtitle text NOT NULL,
    firstseen timestamp without time zone NOT NULL,
    lastseen timestamp without time zone NOT NULL,
    org_id integer
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
    source text
);


ALTER TABLE public.journo_weblink OWNER TO mst;

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
-- Name: organisation; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE organisation (
    id integer DEFAULT nextval(('organisation_id_seq'::text)::regclass) NOT NULL,
    shortname text NOT NULL,
    prettyname text NOT NULL
);


ALTER TABLE public.organisation OWNER TO mst;

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
-- Name: person; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE person (
    id integer NOT NULL,
    name text,
    email text NOT NULL,
    "password" text,
    website text,
    numlogins integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.person OWNER TO mst;

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
-- Name: requeststash; Type: TABLE; Schema: public; Owner: mst; Tablespace: 
--

CREATE TABLE requeststash (
    "key" character(16) NOT NULL,
    whensaved timestamp without time zone DEFAULT now() NOT NULL,
    method text DEFAULT 'GET'::text NOT NULL,
    url text NOT NULL,
    post_data bytea,
    extra text,
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
-- Name: id; Type: DEFAULT; Schema: public; Owner: mst
--

ALTER TABLE alert ALTER COLUMN id SET DEFAULT nextval('alert_id_seq'::regclass);


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
-- Name: article_tag_pkey; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY article_tag
    ADD CONSTRAINT article_tag_pkey PRIMARY KEY (article_id, tag);


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
    ADD CONSTRAINT requeststash_pkey PRIMARY KEY ("key");


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
-- Name: person_email_idx; Type: INDEX; Schema: public; Owner: mst; Tablespace: 
--

CREATE UNIQUE INDEX person_email_idx ON person USING btree (email);


--
-- Name: requeststash_whensaved_idx; Type: INDEX; Schema: public; Owner: mst; Tablespace: 
--

CREATE INDEX requeststash_whensaved_idx ON requeststash USING btree (whensaved);


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

