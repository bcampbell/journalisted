--
-- PostgreSQL database dump
--

SET client_encoding = 'LATIN1';
SET standard_conforming_strings = off;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET escape_string_warning = off;

SET search_path = public, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;

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
-- Data for Name: organisation; Type: TABLE DATA; Schema: public; Owner: mst
--

COPY organisation (id, shortname, prettyname) FROM stdin;
10	bbcnews	BBC News
11	observer	The Observer
12	sundaymirror	The Sunday Mirror
13	sundaytelegraph	The Sunday Telegraph
3	express	The Daily Express
1	independent	The Independent
2	dailymail	The Daily Mail
4	guardian	The Guardian
5	mirror	The Mirror
6	sun	The Sun
8	times	The Times
9	sundaytimes	The Sunday Times
7	telegraph	The Daily Telegraph
14	skynews	Sky News
15	scotsman	The Scotsman
16	scotlandonsunday	Scotland on Sunday
17	notw	News of the World
18	herald	The Herald
\.


--
-- Name: organisation_pkey; Type: CONSTRAINT; Schema: public; Owner: mst; Tablespace: 
--

ALTER TABLE ONLY organisation
    ADD CONSTRAINT organisation_pkey PRIMARY KEY (id);


--
-- PostgreSQL database dump complete
--

