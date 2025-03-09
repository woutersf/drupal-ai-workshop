<?php

namespace Drupal\backup_migrate\Core\Filter;

use Drupal\backup_migrate\Core\Plugin\PluginBase;
use Drupal\backup_migrate\Core\Plugin\PluginCallerInterface;
use Drupal\backup_migrate\Core\Plugin\PluginCallerTrait;
use Drupal\backup_migrate\Core\Service\StashLogger;
use Drupal\backup_migrate\Core\Service\TeeLogger;
use Drupal\backup_migrate\Core\File\BackupFileReadableInterface;

/**
 * Notifies by email when a backup succeeds or fails.
 *
 * @package Drupal\backup_migrate\Core\Filter
 */
class Notify extends PluginBase implements PluginCallerInterface {
  use PluginCallerTrait;

  /**
   * @var \Drupal\backup_migrate\Core\Service\StashLogger
   */
  protected $logstash;

  /**
   * {@inheritdoc}
   */
  public function configSchema(array $params = []) {
    $schema = [];
    // Backup configuration.
    if ($params['operation'] == 'backup') {
      $schema['groups']['notify'] = [
        'title' => 'Email Settings',
      ];

      $schema['fields']['notify_success_enable'] = [
        'group' => 'notify',
        'type' => 'boolean',
        'title' => 'Send an email if backup succeeds',
      ];
      $schema['fields']['notify_success_email'] = [
        'group' => 'notify',
        'type' => 'text',
        'title' => 'Email Address for Success Notices',
        'default_value' => \Drupal::config('system.site')->get('mail'),
        'description' => 'The email added to send a notification about backup.',
      ];

      $schema['fields']['notify_failure_enable'] = [
        'group' => 'notify',
        'type' => 'boolean',
        'title' => 'Send an email if backup fails',
      ];
      $schema['fields']['notify_failure_email'] = [
        'group' => 'notify',
        'type' => 'text',
        'title' => 'Email Address for Failure Notices',
        'default_value' => \Drupal::config('system.site')->get('mail'),
        'description' => 'The email added to send a notification about backup.',
      ];
    }

    return $schema;
  }

  /**
   * Add a weight so that our before* operations run before any others.
   *
   * Primarily to ensure this one runs before other plugins have a chance to
   * write any log entries.
   *
   * @return array
   */
  public function supportedOps() {
    return [
      'beforeBackup' => ['weight' => -100000],
      'beforeRestore' => ['weight' => -100000],
    ];
  }

  /**
   *
   */
  public function beforeBackup() {
    $this->addLogger();
  }

  /**
   *
   */
  public function beforeRestore(BackupFileReadableInterface $file) {
    $this->addLogger();
    return $file;
  }

  /**
   * Call notification function if backup was succeed
   */
  public function backupSuccess() {
    if ($this->config->get('notify_success_enable')) {
      $subject = 'Backup finished successfully';
      $body = t('Site backup succeeded ' . \Drupal::config('system.site')->get('name'));
      $recipient = $this->config->get('notify_success_email');
      $this->sendNotification('backup_success', $subject, $body, $recipient);
    }
  }

  /**
   * Call notification function if backup was failed.
   */
  public function backupFailure(\Exception $e) {
    if ($this->config->get('notify_failure_enable')) {
      $subject = t('Backup finished with failure');
      $body = t('Site backup failed ' . \Drupal::config('system.site')->get('name') . "\n");
      $body = $body . t('Exception Message: ') . $e;
      $recipient = $this->config->get('notify_success_email');
      $this->sendNotification('backup_failure', $subject, $body, $recipient);
    }
  }

  /**
   *
   */
  public function restoreSucceed() {
  }

  /**
   *
   */
  public function restoreFail() {
  }

  /**
   * @param $subject
   * @param $body
   * @param $recipient
   */
  protected function sendNotification($key, $subject, $body, $recipient) {
    \Drupal::service('backup_migrate.mailer')->send($key, $recipient, $subject, $body);
  }

  /**
   * Add the stash logger to the service locator to capture all logged messages.
   */
  protected function addLogger() {
    $services = $this->plugins()->services();

    // Get the current logger.
    $logger = $services->get('Logger');

    // Create a new stash logger to save messages.
    $this->logstash = new StashLogger();

    // Add a tee to send logs to both the regular logger and our stash.
    $services->add('Logger', new TeeLogger([$logger, $this->logstash]));

    // Add the services back into the plugin manager to re-inject existing
    // plugins.
    $this->plugins()->setServiceManager($services);
  }

  // @todo Add a tee to the logger to capture all messages.
  // @todo Implement backup/restore fail/succeed ops and send a notification.
}
