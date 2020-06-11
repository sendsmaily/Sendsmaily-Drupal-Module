#!/bin/sh

# Wait for MySQL to start.
mysql_ready() {
    mysql --host=database --user=root --password=smaily1 --execute "SELECT 1" > /dev/null 2>&1
}
while !(mysql_ready)
do
    sleep 1
    echo "Waiting for MySQL to finish start up..."
done

# Ensure Drupal is installed.
if drush status | grep -q Successful ; then
    echo "Found Drupal installation, continuing..."
else
    echo "Drupal not found, installing..."
    drush site-install standard -y --notify global \
        --db-url=mysql://root:smaily1@database/drupal \
        --site-name=Drupal Sandbox \
        --site-mail=testing@smaily.sandbox \
        --account-mail=testing@smaily.sandbox \
        --account-name=admin \
        --account-pass=smailydev1

    # Enable Smaily for Drupal module.
    drush en -y sendsmaily_subscribe
fi

docker-php-entrypoint "$@"
