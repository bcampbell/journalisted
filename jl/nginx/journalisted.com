# vim: set filetype=nginx:
#
# Nginx config file for journalisted.com

# redirect non-canonical domains
server {
	listen 80;
#	listen [::]:80 ipv6only=on;
    server_name www.journalisted.com (www[.])?journalisted.net (www[.])?journalisted.co.uk (www[.])?journalisted.org (www[.])?journa-list.com (www[.])?journa-list.co.uk (www[.])?journa-list.org (www[.])?journa-list.org.uk (www[.])?journa-list.net;
    return 301 $scheme://journalisted.com$request_uri;
}


server {
	listen 80; 
	listen [::]:80 ipv6only=on;
	server_name journalisted.com;

	root BLAHBLAHBLAH/journalisted/jl/web/;

	access_log BLAHBLAHBLAH/logs/access.log;
	error_log BLAHBLAHBLAH/logs/error.log;

	location ~* \.(png|gif|jpg|jpeg|css|js|swf|ico|txt|xml|bmp|pdf|doc|docx|ppt|pptx|zip)$ {
		access_log off;
		expires 30d;
	}

	location / {
		index  index.php;
		# /<journo-ref>
		rewrite ^/([a-zA-Z0-9]+-[-a-zA-Z0-9]+)$ /journo?ref=$1;
		# /<journo-ref>/rss
		rewrite ^/([a-zA-Z0-9]+-[-a-zA-Z0-9]+)/rss$ /journo_rss?ref=$1;
		# /<journo-ref>.json, /<journo-ref>.txt
		rewrite ^/([a-zA-Z0-9]+-[-a-zA-Z0-9]+).json$ /journo?ref=$1&fmt=json;
		rewrite ^/([a-zA-Z0-9]+-[-a-zA-Z0-9]+).txt$ /journo?ref=$1&fmt=text;

		# /article/<id36>
		rewrite ^/article/([a-zA-Z0-9]+)$ /article?id36=$1;

		# /news/<slug-or-id>
		rewrite ^/news/([-a-zA-Z0-9]+)$ /news?id=$1;
		# /faq/<slug>
		rewrite ^/faq/([-a-zA-Z0-9]+)$ /faq?q=$1;
		# /tags/<tag>
		rewrite ^/tags/([^/]*)$ /tags?tag=$1;
		# /tags/<period>/<tag>
		rewrite ^/tags/([^/]*)/([^/]*)$ tags.php?period=$1&tag=$2;

		# /L/<token>
		rewrite ^/[Ll]/([^?/]*)$ /login?t=$1;

		# RDF stuff
		# /id/journo/<journo-ref>
		rewrite ^/id/journo/([a-zA-Z0-9]+-[-a-zA-Z0-9]+)$ /id.php?type=journo&ref=$1;
		# /id/article/<id36>
		rewrite ^/id/article/([a-zA-Z0-9]+)$ /id?type=article&id36=$1;

		# /data/journo/<journo-ref>
		rewrite ^/data/journo/([a-zA-Z0-9]+-[-a-zA-Z0-9]+)$ /journo?ref=$1&fmt=rdfxml;
		# /data/article/<id36>
		rewrite ^/data/article/([a-zA-Z0-9]+)$ article?id36=$1&fmt=rdfxml;


		# API
		rewrite ^/api/((?:get|find).*)$ /api?method=$1;
		rewrite ^/api/docs/?$ /api;
		rewrite ^/api/docs/(.+)$ /api?docs=1&method=$1;

		# admin pages
		rewrite ^/adm/([a-zA-Z0-9]+-[-a-zA-Z0-9]+)$ /adm/journo?ref=$1;
		rewrite ^/adm/article/([a-zA-Z0-9]+)$ /adm/article?id36=$1;

		# add .php extension
		try_files $uri $uri/ $uri.php?$args;
	}

	location ~ \.php$ {
		try_files $uri =404;

		fastcgi_split_path_info ^(.+\.php)(/.+)$;
		# NOTE: You should have "cgi.fix_pathinfo = 0;" in php.ini

		fastcgi_pass unix:/var/run/php5-fpm.sock;
		fastcgi_index index.php;
		include fastcgi_params;
	}

	# Disable viewing .htaccess & .htpassword
	location ~ /\.ht {
		deny all;
	}
}
