#!/bin/sh
# Returns true once MySQL has fully loaded
mysql_ready() {
    mysqladmin ping --host=database --user=root --password=smaily1 > /dev/null 2>&1
}
while !(mysql_ready)
do
    sleep 1
    echo "Waiting for MySQL to finish..."
done

echo "MySQL loaded. Starting the main script"
# Check whether Drupal is installed
if drush status | grep -q Successful ; then
    echo "Found Drupal installation, continuing..."
else
    echo "Drupal not found, starting Drush."
    drush site-install standard -y --notify global \
        --db-url=mysql://root:smaily1@database/drupal \
        --site-name=Drupal Sandbox \
        --site-mail=testing@smaily.sandbox \
        --account-mail=testing@smaily.sandbox \
        --account-name=admin \
        --account-pass=smailydev1
    drush en -y smaily_for_drupal
fi
docker-php-entrypoint "$@"
