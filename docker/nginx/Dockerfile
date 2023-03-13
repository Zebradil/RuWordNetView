FROM jonasal/nginx-certbot:4.2.0-nginx1.23.3

COPY docker/nginx/app.conf /etc/nginx/user_conf.d/app.conf
COPY docker/nginx/cloudflare.ini /etc/letsencrypt/cloudflare.ini
