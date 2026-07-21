FROM php:8.2-apache

RUN docker-php-ext-install mysqli

# Session/cookie hardening + hide the PHP version header
RUN printf 'session.cookie_httponly=1\nsession.use_strict_mode=1\nexpose_php=0\ndisplay_errors=Off\nlog_errors=On\n' \
    > /usr/local/etc/php/conf.d/hardening.ini

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
