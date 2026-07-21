FROM php:8.2-apache

RUN docker-php-ext-install mysqli

# Guarantee exactly one MPM (prefork) is loaded. Disabling alone is not
# enough: some runtimes mishandle layer whiteouts, resurrecting the
# mpm_event/mpm_worker symlinks that a2dismod deleted ("More than one MPM
# loaded" crash-loop). Truncating the module files in mods-available makes
# any resurrected symlink load nothing, without relying on deletions.
RUN for m in mpm_event mpm_worker; do \
      a2dismod "$m" >/dev/null 2>&1 || true; \
      : > "/etc/apache2/mods-available/$m.load"; \
      : > "/etc/apache2/mods-available/$m.conf"; \
    done \
 && a2enmod mpm_prefork

# Session/cookie hardening + hide the PHP version header
RUN printf 'session.cookie_httponly=1\nsession.use_strict_mode=1\nexpose_php=0\ndisplay_errors=Off\nlog_errors=On\n' \
    > /usr/local/etc/php/conf.d/hardening.ini

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
