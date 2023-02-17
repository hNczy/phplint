FROM composer:2.5 AS build
RUN composer global require overtrue/phplint ^9.0

FROM php:8.2-cli-alpine
COPY --from=build /tmp/vendor /root/.composer/vendor
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
RUN cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini

WORKDIR /workdir
ENTRYPOINT ["/entrypoint.sh"]
