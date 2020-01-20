FROM drupal:8.8.1-apache
ADD smaily_for_drupal /var/www/html/modules/smaily_for_drupal
WORKDIR /var/www/html/core/lib/Drupal/Core/Database/Install
## Autofill MySQL database=drupal, username=root, host=database.
RUN sed -i -e "s/\['database']) ? ''/['database']) ? 'drupal'/" Tasks.php \
    && sed -i -e "s/\['username']) ? ''/['username']) ? 'root'/" Tasks.php \
    && sed -i "s/localhost/database/" Tasks.php /var/www/html/core/modules/user/src/AccountForm.php
WORKDIR /var/www/html/core/lib/Drupal/Core/Installer/Form
# Autofill site title, admin username and emails.
RUN sed -i -e "/'#weight' => -20,/a '#value' => 'Smaily sandbox'," SiteConfigureForm.php \
    && sed -i -e "/'#maxlength' => UserInterface::USERNAME_MAX_LENGTH,/a '#value' => 'admin'," SiteConfigureForm.php \
    && sed -i -e "/'email',/a '#value' => 'testing@smaily.sandbox'," SiteConfigureForm.php
