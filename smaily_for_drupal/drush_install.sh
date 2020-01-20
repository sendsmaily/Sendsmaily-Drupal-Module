#!/bin/sh
set -e
if drush status | grep -q Successful ; then #check whether Drupal is installed
    echo "Drupal not found, starting Drush."
    drush site-install standard -y --notify global \
        --db-url=mysql://root:smaily1@database/drupal \
        --site-name=Drupal Sandbox \
        --site-mail=testing@smaily.sandbox \
        --account-mail=testing@smaily.sandbox \
        --account-name=admin \
        --account-pass=smailydev1
else
    echo "Found Drupal installation, continuing startup."
fi
docker-php-entrypoint "$@"
