FROM drupal:7-apache
RUN apt-get update && apt-get install -y mysql-client libnotify-bin \
    && rm -rf /var/lib/apt/lists/*
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer global require drush/drush:8.* \
  && rm -f /usr/local/bin/drush \
  && ln -s ~/.composer/vendor/bin/drush /usr/local/bin/drush \
  && drush core-status -y
RUN chown -R www-data:www-data /var/www \
    && find /var/www -type d -exec chmod 2750 {} \+ \
    && find /var/www -type f -exec chmod 640 {} \+
COPY drush_install.sh /drush_install.sh
RUN chmod +x /drush_install.sh
ENTRYPOINT ["/drush_install.sh"]
CMD ["apache2-foreground"]
