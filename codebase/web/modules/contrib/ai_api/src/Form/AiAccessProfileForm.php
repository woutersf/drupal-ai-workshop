<?php

declare(strict_types=1);

namespace Drupal\ai_api\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_api\Entity\AiAccessProfile;
use Drupal\ai_api\PluginManager\AiApiAccessPointManager;
use Drupal\user\PermissionHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * AI Access Profile form.
 */
final class AiAccessProfileForm extends EntityForm {

  /**
   * Constructor.
   *
   * @param \Drupal\ai_api\PluginManager\AiApiAccessPointManager $aiApiAccessPointManager
   *   The AI API access point manager.
   * @param \Drupal\ai\AiProviderPluginManager $aiProvider
   *   The AI provider plugin manager.
   * @param \Drupal\user\PermissionHandler $userPermissions
   *   The user permissions.
   */
  public function __construct(
    protected readonly AiApiAccessPointManager $aiApiAccessPointManager,
    protected readonly AiProviderPluginManager $aiProvider,
    protected readonly PermissionHandler $userPermissions,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.ai_api_access_point'),
      $container->get('ai.provider'),
      $container->get('user.permissions'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {

    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Profile Label'),
      '#description' => $this->t('This is the label of the access profile.'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => $this->t('Airline Chatbot'),
      ],
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => [AiAccessProfile::class, 'load'],
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#description' => $this->t('If you unchecked this box, the access profile will be disabled and will not be reachable.'),
      '#default_value' => $this->entity->status(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('This is the description of the access profile.'),
      '#default_value' => $this->entity->get('description'),
    ];

    $form['access_method'] = [
      '#type' => 'select',
      '#title' => $this->t('Access Method'),
      '#description' => $this->t('This is the method of sending the access key to the API. Only change this if you know what you are doing.'),
      '#options' => [
        'headers' => $this->t('Headers'),
        'querystring' => $this->t('Query String'),
      ],
      '#default_value' => $this->entity->get('access_method') ?? 'headers',
      '#required' => TRUE,
    ];

    $form['access_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Key'),
      '#description' => $this->t('This is the name of the access key to recognize this profile being used. Only change this if you know what you are doing. If a colon exists, it will be used as a separator for the key and value.'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->get('access_key') ?? 'Authorization: Bearer',
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => $this->t('Authorization: Bearer'),
      ],
    ];

    // Get all permissions in Drupal system.
    $permissions = $this->userPermissions->getPermissions();
    $permission_options = [];
    foreach ($permissions as $permission => $permission_data) {
      $permission_options[$permission] = $permission_data['title'];
    }

    $form['permission'] = [
      '#type' => 'select',
      '#title' => $this->t('Permission'),
      '#description' => $this->t('You always need to connect a permission for an access profile. If you connect a permission that is available to the anonymous user, please refer to the readme.md file to learn how to enable this and to understand the risks with this.'),
      '#options' => $permission_options,
    ];

    // Get all operation types.
    $operation_types = $this->aiProvider->getOperationTypes();
    // Loop through and get all the access points that fits.
    $access_points = $this->aiApiAccessPointManager->getDefinitions();
    foreach ($operation_types as $operation_type) {
      $options = [];
      foreach ($access_points as $access_point) {
        if ($access_point['operation_type'] === $operation_type['id']) {
          $options[$access_point['id']] = $access_point['label'];
        }
      }
      if (count($options) > 0) {
        $form[$operation_type['id'] . '_group'] = [
          '#type' => 'details',
          '#title' => $operation_type['label'],
          '#open' => TRUE,
        ];

        $form[$operation_type['id'] . '_group']['enabled_' . $operation_type['id']] = [
          '#type' => 'select',
          '#title' => $operation_type['label'],
          '#options' => $options,
          '#empty_option' => $this->t('- Select -'),
          '#description' => $this->t('If you want to enable and access point for this operation type, please select it here.'),
          '#default_value' => $this->entity->get('operation_types')[$operation_type['id']] ?? [],
        ];

        $allowed_model_options = [
          'default' => $this->t('Default @type Provider/Model', [
            '@type' => $operation_type['label'],
          ]),
          'all' => $this->t('All Providers/Models'),
          'specific' => $this->t('Specific Providers/Models'),
        ];

        $form[$operation_type['id'] . '_group']['allowed_models_' . $operation_type['id']] = [
          '#type' => 'select',
          '#title' => $this->t('Required Provider'),
          '#options' => $allowed_model_options,
          '#description' => $this->t('If you want to enable and access point for this operation type, please select it here. If you want specific models, do know that the end user will need to have the provider and access to these models.'),
          '#default_value' => $this->entity->get('required_providers')[$operation_type['id']] ?? [],
          '#states' => [
            'visible' => [
              ':input[name="enabled_' . $operation_type['id'] . '"]' => ['!value' => ''],
            ],
          ],
        ];

        $form[$operation_type['id'] . '_group']['specific_models_' . $operation_type['id']] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Specific Providers/Models'),
          '#description' => $this->t('If you want to enable and access point for this operation type, please select it here.'),
          '#default_value' => $this->entity->get('required_providers')[$operation_type['id']] ?? [],
          '#options' => $this->aiProvider->getSimpleProviderModelOptions($operation_type['id'], FALSE),
          '#states' => [
            'visible' => [
              ':input[name="allowed_models_' . $operation_type['id'] . '"]' => ['value' => 'specific'],
            ],
          ],
        ];

      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $operation_types = [];
    foreach ($form_state->getValues() as $key => $value) {
      if (str_starts_with($key, 'enabled_')) {
        $operation_type = str_replace('enabled_', '', $key);
        if ($value) {
          $operation_types[$operation_type]['plugin'] = $value;
          $operation_types[$operation_type]['allowed_models'] = $form_state->getValue('allowed_models_' . $operation_type);
          if ($form_state->getValue('allowed_models_' . $operation_type) === 'specific') {
            $operation_types[$operation_type]['specific_models'] = $form_state->getValue('specific_models_' . $operation_type);
          }
        }
      }
    }
    $this->entity->set('operation_types', $operation_types);
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus(
      match($result) {
        \SAVED_NEW => $this->t('Created new example %label.', $message_args),
        \SAVED_UPDATED => $this->t('Updated example %label.', $message_args),
      }
    );
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
