FROM jonasal/nginx-certbot:6.2.0-nginx1.29.8

COPY docker/nginx/app.conf /etc/nginx/user_conf.d/app.conf
COPY docker/nginx/cloudflare.ini /etc/letsencrypt/cloudflare.ini
