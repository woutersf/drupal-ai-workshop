<?php

namespace Drupal\backup_migrate\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\backup_migrate\Drupal\Config\DrupalConfigHelper;
use Drupal\backup_migrate\Entity\Schedule;
use Drupal\backup_migrate\Entity\SettingsProfile;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\DrushCommands;

/**
 * Implements the drush commands for backup_migrate.
 */
class BackupMigrateCommands extends DrushCommands {

  const QUICK_BACKUP_OPTIONS = [
    'source_id' => 'default_db',
    'destination_id' => 'private_files',
    'profile_id' => '',
    'i' => FALSE,
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_manager) {
    $this->entityTypeManager = $entity_manager;
  }

  /**
   * Drush command to run a quick backup.
   *
   * @param array $options
   *   Optional. The command options.
   *
   * @command backup_migrate:quick_backup
   * @aliases bb
   * @option i Run the command in a interactive way.
   * @usage backup_migrate:quick_backup
   */
  public function quickBackup(array $options = self::QUICK_BACKUP_OPTIONS) {
    $is_interactive = $options['i'];

    if ($is_interactive) {
      $this->runInteractiveQuickBackup();
    }
    else {
      $source = $options['source_id'];
      $destination = $options['destination_id'];

      $profile_config = [];
      if (!empty($options['profile_id'])) {
        $profile_config = SettingsProfile::load($options['profile_id'])->get('config');
      }

      backup_migrate_perform_backup($source, $destination, $profile_config);
    }
  }

  /**
   * Run interactive quick backup.
   */
  public function runInteractiveQuickBackup() {
    $bam = backup_migrate_get_service_object();
    $source_options = DrupalConfigHelper::getPluginSelector($bam->sources(), 'Backup Source')['#options'];
    $source_options = array_keys($source_options);
    $source_choice = $this->io()->choice(dt("Choose the backup source."), $source_options);

    $config = [];
    $profile_settings_options = DrupalConfigHelper::getSettingsProfileSelector('Settings Profile');
    if (isset($profile_settings_options['#options'])) {
      $profile_settings_options = array_keys($profile_settings_options['#options']);
      $profile_settings_choice = $this->io()->choice(dt("Choose the backup settings profile."), $profile_settings_options);
      $config = SettingsProfile::load($profile_settings_options[$profile_settings_choice])->get('config');
    }

    $destination_options = DrupalConfigHelper::getDestinationSelector($bam, 'Backup Destination')['#options'];
    unset($destination_options['upload']);
    $destination_options = array_keys($destination_options);
    $destination_choice = $this->io()->choice(dt("Choose the destination."), $destination_options);

    backup_migrate_perform_backup($source_options[$source_choice], $destination_options[$destination_choice], $config);
  }

  /**
   * Get a list of available destinations.
   *
   * @command backup_migrate:destinations
   * @aliases bam-destinations
   * @usage backup_migrate:destinations
   *   Get a list of available destinations.
   */
  public function destinations() {
    $storage = $this->entityTypeManager
      ->getStorage('backup_migrate_destination');

    $rows = [[dt('Machine name'), dt('Backup Destination')]];
    foreach ($storage->getQuery()->accessCheck(FALSE)->execute() as $key) {
      $entity = $storage->load($key);
      $rows[] = [
        $entity->id(),
        $entity->label(),
      ];
    }

    return new RowsOfFields($rows);
  }

  /**
   * Get a list of available settings profiles.
   *
   * @command backup_migrate:profiles
   * @aliases bam-profiles
   * @usage backup_migrate:profiles
   *   Get a list of available settings profiles.
   */
  public function profiles() {
    $storage = $this->entityTypeManager
      ->getStorage('backup_migrate_settings');

    $rows = [[dt('Machine name'), dt('Profile Name')]];
    foreach ($storage->getQuery()->accessCheck(FALSE)->execute() as $key) {
      $entity = $storage->load($key);
      $rows[] = [
        $entity->id(),
        $entity->label(),
      ];
    }

    return new RowsOfFields($rows);
  }

  /**
   * Get a list of available schedules.
   *
   * @command backup_migrate:schedules
   * @aliases bam-schedules
   * @usage backup_migrate:schedules
   *   Get a list of available schedules.
   */
  public function schedules() {
    $storage = $this->entityTypeManager
      ->getStorage('backup_migrate_schedule');

    $rows = [[dt('Machine name'), dt('Schedule Name')]];
    foreach ($storage->getQuery()->accessCheck(FALSE)->execute() as $key) {
      $entity = $storage->load($key);
      $rows[] = [
        $entity->id(),
        $entity->label(),
      ];
    }

    return new RowsOfFields($rows);
  }

  /**
   * Get a list of available sources.
   *
   * @command backup_migrate:sources
   * @aliases bam-sources
   * @usage backup_migrate:sources
   *   Get a list of available sources.
   */
  public function sources() {
    $storage = $this->entityTypeManager
      ->getStorage('backup_migrate_source');

    $rows = [[dt('Machine name'), dt('Backup Source'), dt('Type')]];
    foreach ($storage->getQuery()->accessCheck(FALSE)->execute() as $key) {
      $entity = $storage->load($key);
      if ($plugin = $entity->getPlugin()) {
        $info = $plugin->getPluginDefinition();
      }
      $rows[] = [
        $entity->id(),
        $entity->label(),
        isset($info) ? $info['title'] : $entity->get('type'),
      ];
    }

    return new RowsOfFields($rows);
  }

  /**
   * Drush command to run a scheduled backup.
   *
   * @command backup_migrate:schedule_backup
   * @aliases schedule_backup
   * @usage backup_migrate:schedule_backup
   */
  public function runScheduledBackup() {
    $bam = backup_migrate_get_service_object();

    $schedules = Schedule::loadMultiple();
    foreach ($schedules as $schedule) {
      if ($schedule->get('enabled')) {
        $schedules_id[] = $schedule->id();
      }
    }
    $schedule_choice = $this->io()->choice(dt("Choose the schedule backup you want to run."), $schedules_id);

    $schedules[$schedules_id[$schedule_choice]]->run($bam);
  }

}
