# Backup and Migrate for Drupal 9

This is a rewrite of Backup and Migrate for Drupal 9.

## Installation

### Install without composer

* Download the zip or tgz archive of the latest release from the project page: https://www.drupal.org/project/backup_migrate
* Extra the archive and rename it so that there is just a directory called `backup_migrate`.
* Move the directory to the site's `modules/contrib` directory.

### Install using composer

`composer require drupal/backup_migrate`

### Optional: php-encryption

In order to encrypt backup files, please install the Defuse PHP-encryption
library via Composer with the command:

`composer require defuse/php-encryption`

See the Defuse PHP Encryption Documentation Page for more information:

* https://www.drupal.org/docs/contributed-modules/backup-and-migrate/encrypting-backups

Note: if that page is inaccessible it may have been renamed, try this URL
instead:

* https://www.drupal.org/node/3185484

## Related modules

The following modules can extend the functionality of your backup solution:

* Backup & Migrate: Flysystem
  https://www.drupal.org/project/backup_migrate_flysystem
  Provides a wrapper around the Flysystem abstraction system which allows use of
  a wide variety of backup destinations without additional changes to the B&M
  module itself. Please see that module's README.md file for details.
