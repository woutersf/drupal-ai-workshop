<?php

namespace Drupal\backup_migrate\Plugin\BackupMigrateSource;

use Drupal\Core\Database\Database;
use Drupal\backup_migrate\Core\Config\Config;
use Drupal\backup_migrate\Drupal\Source\DrupalMySQLiSource;
use Drupal\backup_migrate\Core\Main\BackupMigrateInterface;
use Drupal\backup_migrate\Drupal\EntityPlugins\SourcePluginBase;
use Drupal\backup_migrate\Drupal\Source\DrupalSiteArchiveSource;

/**
 * Defines an default database source plugin.
 *
 * @BackupMigrateSourcePlugin(
 *   id = "EntireSite",
 *   title = @Translation("Entire Site (do not use)"),
 *   description = @Translation("Back up the entire Drupal site. This is not recommended for use on most sites, hopefully it will be fixed in a future release."),
 *   locked = true
 * )
 */
class EntireSiteSourcePlugin extends SourcePluginBase {

  protected $dbSource;

  /**
   * Get the Backup and Migrate plugin object.
   *
   * @return Drupal\backup_migrate\Core\Plugin\PluginInterface
   */
  public function getObject() {
    // Add the default database.
    $info = Database::getConnectionInfo('default', 'default');
    $info = $info['default'];
    if ($info['driver'] == 'mysql') {
      $conf = $this->getConfig();
      $conf->set('directory', DRUPAL_ROOT);
      $this->dbSource = new DrupalMySQLiSource(new Config($info));
      return new DrupalSiteArchiveSource($conf, $this->dbSource);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function alterBackupMigrate(BackupMigrateInterface $bam, $key, array $options = []) {
    if ($source = $this->getObject()) {
      $bam->sources()->add($key, $source);
      // @todo Enable this, fix it.
      // $bam->sources()->add('default_db', $this->dbSource);
    }
  }

}
