<?php

namespace Drupal\backup_migrate\Form;

use Drupal\backup_migrate\Drupal\Config\DrupalConfigHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Provides a form for performing a 1-click site backup.
 */
class BackupMigrateAdvancedBackupForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'backup_migrate_ui_manual_backup_advanced';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Leave a message about the Entire Site backup.
    // @see https://www.drupal.org/project/backup_migrate/issues/3151290
    $this->messenger()->addMessage($this->t('It is recommended to not use the "Entire site" backup as it has a tendency of failing on anything but the tiniest of sites. Hopefully this will be fixed in a future release.'), MessengerInterface::TYPE_WARNING);

    $form = [];

    // Theme the form if we want it inline.
    // @FIXME
    // $form['#theme'] = 'backup_migrate_ui_manual_quick_backup_form_inline';
    $bam = backup_migrate_get_service_object();

    $form['source'] = [
      '#type' => 'fieldset',
      "#title" => $this->t("Source"),
      "#collapsible" => TRUE,
      "#collapsed" => FALSE,
      "#tree" => FALSE,
    ];
    $form['source']['source_id'] = DrupalConfigHelper::getSourceSelector($bam, $this->t('Backup Source'));
    $form['source']['source_id']['#default_value'] = \Drupal::config('backup_migrate.settings')->get('backup_migrate_source_id');

    $form += DrupalConfigHelper::buildAllPluginsForm($bam->plugins(), 'backup');
    if (\Drupal::moduleHandler()->moduleExists('token')) {
      $filename_token = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['site'],
        '#dialog' => TRUE,
        '#click_insert' => TRUE,
        '#show_restricted' => TRUE,
        '#group' => 'file',
      ];
    }
    else {
      $filename_token = [
        '#type' => 'markup',
        '#markup' => 'In order to use tokens for File Name, please install & enable <a href="https://www.drupal.org/project/token" target="_blank">Token module</a>. <p></p>',
      ];
    }
    array_splice($form['file'], 4, 0, ['filename_token' => $filename_token]);

    $form['destination'] = [
      '#type' => 'fieldset',
      "#title" => $this->t("Destination"),
      "#collapsible" => TRUE,
      "#collapsed" => FALSE,
      "#tree" => FALSE,
    ];

    $form['destination']['destination_id'] = DrupalConfigHelper::getDestinationSelector($bam, $this->t('Backup Destination'));
    $form['destination']['destination_id']['#default_value'] = \Drupal::config('backup_migrate.settings')->get('backup_migrate_destination_id');

    $form['quickbackup']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Backup now'),
      '#weight' => 1,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // @todo Currently there is a problem, where the download destination does not
    // support taking the site offline.
    // @see https://www.drupal.org/project/backup_migrate/issues/3475192
    $destinationId = $form_state->getValue('destination_id');
    $siteOffline = !empty($form_state->getValue('utils')['site_offline']) ? $form_state->getValue('utils')['site_offline'] : FALSE;
    if ($destinationId === 'download' && $siteOffline) {
      $form_state->setErrorByName('destination_id', $this->t('The Backup Destination "Download" does not support taking the site offline during backup.'));
      $form_state->setErrorByName('utils][site_offline');
    }

    $bam = backup_migrate_get_service_object($form_state->getValues());

    // Let the plugins validate their own config data.
    if ($plugin_errors = $bam->plugins()->map('configErrors', ['operation' => 'backup'])) {
      $has_token_module = \Drupal::moduleHandler()->moduleExists('token');

      foreach ($plugin_errors as $plugin_key => $errors) {
        if ($plugin_key == "namer" && isset($errors[0])) {
          if ($errors[0]->getFieldKey() == "filename" && $has_token_module) {
            continue;
          }
        }
        foreach ($errors as $error) {
          $form_state->setErrorByName($plugin_key . '][' . $error->getFieldKey(), $this->t($error->getMessage(), $error->getReplacement()));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $form_state->getValues();
    backup_migrate_perform_backup($config['source_id'], $config['destination_id'], $config);
  }

}
