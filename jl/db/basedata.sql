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

COPY organisation (id, shortname, prettyname, phone, email_format, home_url, sop_name, sop_url) FROM stdin;
10	bbcnews	BBC News	0208 743 8000	{FIRST}.{LAST}@bbc.co.uk	http://news.bbc.co.uk	BBC Editorial Guidelines	http://www.bbc.co.uk/guidelines/editorialguidelines
1	independent	The Independent	0207 005 2000	{INITIAL}.{LAST}@independent.co.uk, {FIRST}.{LAST}@independent.co.uk	http://www.independent.co.uk	PCC Code	http://www.pcc.org.uk/cop/practice.html
2	dailymail	The Daily Mail	0207 938 6000	{FIRST}.{LAST}@dailymail.co.uk, {INITIAL}.{LAST}@dailymail.co.uk	http://www.dailymail.co.uk	PCC Code	http://www.pcc.org.uk/cop/practice.html
3	express	The Daily Express	0871 434 1010	{FIRST}.{LAST}@express.co.uk	http://www.express.co.uk	PCC Code	http://www.pcc.org.uk/cop/practice.html
4	guardian	The Guardian	0207 278 2332	{FIRST}.{LAST}@guardian.co.uk	http://www.guardian.co.uk	Guardian Editorial Code	http://image.guardian.co.uk/sys-files/Guardian/documents/2007/06/14/EditorialCode2007.pdf
5	mirror	The Mirror	0207 293 3000	{FIRST}.{LAST}@mirror.co.uk, {FIRST}.{LAST}@sundaymirror.co.uk	http://www.mirror.co.uk	PCC Code	http://www.pcc.org.uk/cop/practice.html
6	sun	The Sun	0207 782 4000	{FIRST}.{LAST}@the-sun.co.uk 	http://www.thesun.co.uk	PCC Code	http://www.pcc.org.uk/cop/practice.html
7	telegraph	The Daily Telegraph	0207 931 2000	{FIRST}.{LAST}@telegraph.co.uk	http://www.telegraph.co.uk	PCC Code	http://www.pcc.org.uk/cop/practice.html
8	times	The Times	0207 782 5000	{FIRST}.{LAST}@thetimes.co.uk 	http://www.timesonline.co.uk	PCC Code	http://www.pcc.org.uk/cop/practice.html
9	sundaytimes	The Sunday Times	0207 782 5000	{FIRST}.{LAST}@sunday-times.co.uk 	http://www.timesonline.co.uk	PCC Code	http://www.pcc.org.uk/cop/practice.html
11	observer	The Observer	0207 278 2332	{FIRST}.{LAST}@observer.co.uk	http://observer.guardian.co.uk	Guardian Editorial Code	http://image.guardian.co.uk/sys-files/Guardian/documents/2007/06/14/EditorialCode2007.pdf
12	sundaymirror	The Sunday Mirror	0207 293 3000	{FIRST}.{LAST}@sundaymirror.co.uk, {FIRST}.{LAST}@mirror.co.uk	http://www.mirror.co.uk	PCC Code	http://www.pcc.org.uk/cop/practice.html
13	sundaytelegraph	The Sunday Telegraph	0207 931 2000	{FIRST}.{LAST}@telegraph.co.uk	http://www.telegraph.co.uk	PCC Code	http://www.pcc.org.uk/cop/practice.html
14	skynews	Sky News	0207 705 3000	{FIRST}.{LAST}@bskyb.com	http://www.sky.com	PCC Code	http://www.pcc.org.uk/cop/practice.html
15	scotsman	The Scotsman	0131 620 8620	{FIRST}.{LAST}@scotsman.com, {INITIAL}{LAST}@scotsman.com	http://www.scotsman.com	PCC Code	http://www.pcc.org.uk/cop/practice.html
18	ft	Financial Times	0207 873 3000	{FIRST}.{LAST}@ft.com	http://www.ft.com	PCC Code	http://www.pcc.org.uk/cop/practice.html
16	scotlandonsunday	Scotland on Sunday	0131 620 8620	{FIRST}.{LAST}@scotlandonsunday.com	http://scotlandonsunday.scotsman.com	PCC Code	http://www.pcc.org.uk/cop/practice.html
19	herald	The Herald	0141 302 7000	{FIRST}.{LAST}@theherald.co.uk 	http://theherald.co.uk	PCC Code	http://www.pcc.org.uk/cop/practice.html
17	notw	News of the World					
\.


--
-- PostgreSQL database dump complete
--

