FROM jonasal/nginx-certbot:6.2.0-nginx1.29.8@sha256:5f67f05bf2ce6a0bfa62682af18009317f39b616c0c44fdc4faa1d1b087a6f14

COPY docker/nginx/app.conf /etc/nginx/user_conf.d/app.conf
COPY docker/nginx/cloudflare.ini /etc/letsencrypt/cloudflare.ini
