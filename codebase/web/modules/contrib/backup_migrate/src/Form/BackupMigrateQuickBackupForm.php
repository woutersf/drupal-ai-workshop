<?php

namespace Drupal\backup_migrate\Form;

use Drupal\backup_migrate\Drupal\Config\DrupalConfigHelper;
use Drupal\backup_migrate\Entity\SettingsProfile;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Provides a form for performing a 1-click site backup.
 */
class BackupMigrateQuickBackupForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'backup_migrate_ui_manual_backup_quick';
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
    // @todo Fix this.
    // @code
    // $form['#theme'] = 'backup_migrate_ui_manual_quick_backup_form_inline';
    // @endcode
    $bam = backup_migrate_get_service_object();

    $form['quickbackup'] = [
      '#type' => 'fieldset',
      "#title" => $this->t("Quick Backup"),
      "#collapsible" => FALSE,
      "#collapsed" => FALSE,
      "#tree" => FALSE,
    ];

    $form['quickbackup']['source_id'] = DrupalConfigHelper::getSourceSelector($bam, $this->t('Backup Source'));
    $form['quickbackup']['destination_id'] = DrupalConfigHelper::getDestinationSelector($bam, $this->t('Backup Destination'));
    $form['quickbackup']['settings_profile_id'] = DrupalConfigHelper::getSettingsProfileSelector($this->t('Settings Profile'));
    unset($form['quickbackup']['destination_id']['#options']['upload']);
    $form['quickbackup']['add_backup_description'] = [
      '#type' => 'checkbox',
      "#title" => $this->t("Add a note to the backup"),
    ];
    $form['quickbackup']['description'] = [
      '#type' => 'textarea',
      "#title" => $this->t("Note"),
      '#states' => [
        'invisible' => [
          ':input[name="add_backup_description"]' => ['checked' => FALSE],
        ],
      ],
    ];
    // @todo Is this needed?
    // Create the service.
    // @code
    // $bam = backup_migrate_get_service_object();
    // $bam->setConfig($config);
    // $bam->plugins()->get('namer')->confGet('filename');
    // $form['quickbackup']['source_id'] = _backup_migrate_get_source_pulldown(\Drupal::config('backup_migrate.settings')->get('backup_migrate_source_id'));.
    // $form['quickbackup']['destination'] = _backup_migrate_get_destination_pulldown('manual backup', \Drupal::config('backup_migrate.settings')->get('backup_migrate_destination_id'), \Drupal::config('backup_migrate.settings')->get('backup_migrate_copy_destination_id'));
    // @endcode
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config = [];

    // Load the settings profile if one is selected.
    if (!empty($values['settings_profile_id'])) {
      $config = SettingsProfile::load($values['settings_profile_id'])->get('config');
    }

    // Check if user added a backup has a description.
    if ($values['add_backup_description']) {
      $config['metadata']['description'] = $values['description'];
    }

    backup_migrate_perform_backup($values['source_id'], $values['destination_id'], $config);
  }

}
