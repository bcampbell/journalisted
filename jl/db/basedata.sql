--
-- PostgreSQL database dump
--

SET client_encoding = 'SQL_ASCII';
SET standard_conforming_strings = off;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET escape_string_warning = off;

SET search_path = public, pg_catalog;

--
-- Data for Name: organisation; Type: TABLE DATA; Schema: public; Owner: mst
--

COPY organisation (id, shortname, prettyname, phone, email_format) FROM stdin;
10	bbcnews	BBC News	0208 743 8000	{FIRST}.{LAST}@bbc.co.uk
11	observer	The Observer	0207 278 2332	{FIRST}.{LAST}@observer.co.uk
12	sundaymirror	The Sunday Mirror	0207 293 3000	{FIRST}.{LAST}@sundaymirror.co.uk, {FIRST}.{LAST}@mirror.co.uk
13	sundaytelegraph	The Sunday Telegraph	0207 931 2000	{FIRST}.{LAST}@telegraph.co.uk
3	express	The Daily Express	0871 434 1010	{FIRST}.{LAST}@express.co.uk
1	independent	The Independent	0207 005 2000	{INITIAL}.{LAST}@independent.co.uk, {FIRST}.{LAST}@independent.co.uk
2	dailymail	The Daily Mail	0207 938 6000	{FIRST}.{LAST}@dailymail.co.uk, {INITIAL}.{LAST}@dailymail.co.uk
4	guardian	The Guardian	0207 278 2332	{FIRST}.{LAST}@guardian.co.uk
5	mirror	The Mirror	0207 293 3000	{FIRST}.{LAST}@mirror.co.uk, {FIRST}.{LAST}@sundaymirror.co.uk
6	sun	The Sun	0207 782 4000	{FIRST}.{LAST}@the-sun.co.uk 
8	times	The Times	0207 782 5000	{FIRST}.{LAST}@thetimes.co.uk 
9	sundaytimes	The Sunday Times	0207 782 5000	{FIRST}.{LAST}@sunday-times.co.uk 
7	telegraph	The Daily Telegraph	0207 931 2000	{FIRST}.{LAST}@telegraph.co.uk
14	skynews	Sky News	0207 705 3000	{FIRST}.{LAST}@bskyb.com
15	scotsman	The Scotsman	0131 620 8620	{FIRST}.{LAST}@scotsman.com, {INITIAL}{LAST}@scotsman.com
16	scotlandonsunday	Scotland on Sunday	0131 620 8620	{FIRST}.{LAST}@scotlandonsunday.com
18	ft	Financial Times	0207 873 3000	{FIRST}.{LAST}@ft.com
19	herald	The Herald	0141 302 7000	{FIRST}.{LAST}@theherald.co.uk 
\.


--
-- PostgreSQL database dump complete
--

