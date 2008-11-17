-- populate organisaion statement-of-interests and homeurl fields

begin;
update organisation set sop_name='PCC Code', sop_url='http://www.pcc.org.uk/cop/practice.html' where shortname not in ('guardian','observer');
update organisation set sop_name='Guardian Editorial Code', sop_url='http://image.guardian.co.uk/sys-files/Guardian/documents/2007/06/14/EditorialCode2007.pdf' where shortname in ('guardian','observer');

update organisation set home_url='http://www.independent.co.uk' where id=1;
update organisation set home_url='http://www.dailymail.co.uk' where id=2;
update organisation set home_url='http://www.express.co.uk' where id=3;
update organisation set home_url='http://www.guardian.co.uk' where id=4;
update organisation set home_url='http://www.mirror.co.uk' where id=5;
update organisation set home_url='http://www.thesun.co.uk' where id=6;
update organisation set home_url='http://www.telegraph.co.uk' where id=7;
update organisation set home_url='http://www.timesonline.co.uk' where id=8;
update organisation set home_url='http://www.timesonline.co.uk' where id=9;
update organisation set home_url='http://news.bbc.co.uk' where id=10;
update organisation set home_url='http://observer.guardian.co.uk' where id=11;
update organisation set home_url='http://www.mirror.co.uk' where id=12;
update organisation set home_url='http://www.telegraph.co.uk' where id=13;
update organisation set home_url='http://www.sky.com' where id=14;
update organisation set home_url='http://www.scotsman.com' where id=15;
update organisation set home_url='http://scotlandonsunday.scotsman.com' where id=16;
update organisation set home_url='http://www.ft.com' where id=18;
update organisation set home_url='http://theherald.co.uk' where id=19;
commit;

