FROM alpine:3.9

RUN apk update \
 && apk add php7 \
            php7-intl \
            php7-pdo_pgsql \
            php7-json \
            php7-session \
            php7-ctype \
            composer

WORKDIR /opt/app

EXPOSE 8000

CMD php -S 0.0.0.0:8000 -t web
