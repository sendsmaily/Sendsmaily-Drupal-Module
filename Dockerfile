FROM drupal:8-apache

# Ensure packages required for tooling are installed.
RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y \
    default-mysql-client-core \
    libnotify-bin \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer.
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Drush 8.
RUN composer global require drush/drush:8.* \
  && rm -f /usr/local/bin/drush \
  && ln -s ~/.composer/vendor/bin/drush /usr/local/bin/drush \
  && drush core-status -y

# Ensure Drupal files path exists and is writable.
RUN mkdir sites/default/files \
    && chown -R www-data:www-data sites/default/files \
    && chmod 2775 sites/default/files

# Copy automated Drupal and module installer to image.
COPY .sandbox/bootstrap.sh /bootstrap.sh

ENTRYPOINT ["/bootstrap.sh"]
CMD ["apache2-foreground"]
