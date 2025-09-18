<?php

namespace Drupal\ai\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Enum\AiModelCapability;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure AI module.
 */
class AiSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai.settings';

  /**
   * The AI Provider service.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $providerManager;

  /**
   * The hard coded selections to add for filtering purposes.
   *
   * @var array
   */
  protected $hardcodedSelections = [
    [
      'id' => 'chat_with_image_vision',
      'actual_type' => 'chat',
      'label' => 'Chat with Image Vision',
      'filter' => [AiModelCapability::ChatWithImageVision],
    ],
    [
      'id' => 'chat_with_complex_json',
      'actual_type' => 'chat',
      'label' => 'Chat with Complex JSON',
      'filter' => [AiModelCapability::ChatJsonOutput],
    ],
    [
      'id' => 'chat_with_structured_response',
      'actual_type' => 'chat',
      'label' => 'Chat with Structured Response',
      'filter' => [AiModelCapability::ChatStructuredResponse],
    ],
    [
      'id' => 'chat_with_tools',
      'actual_type' => 'chat',
      'label' => 'Chat with Tools/Function Calling',
      'filter' => [AiModelCapability::ChatTools],
    ],
  ];

  /**
   * Constructor.
   */
  final public function __construct(AiProviderPluginManager $provider_manager) {
    $this->providerManager = $provider_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $nojs = NULL) {
    $form = [];

    $config = $this->config(static::CONFIG_NAME);

    $form['default_providers'] = [
      '#type' => 'details',
      '#title' => $this->t('Default Providers'),
      '#open' => TRUE,
      '#weight' => 10,
      '#description' => $this->t('These are default providers for each operation type that external modules can use or show on their configurations pages. Choose a provider from the <a href="@ai">AI module homepage</a>, add it to your project, then install and <a href="@configure">configure</a> it first.', [
        '@ai' => 'https://www.drupal.org/project/ai',
        '@configure' => Url::fromRoute('ai.admin_providers')->toString(),
      ]),
    ];

    $operation_types = $this->providerManager->getOperationTypes();
    $default_providers = $config->get('default_providers') ?? [];

    // Get all providers.
    /** @var \Drupal\ai\AiProviderInterface[] $providers */
    $providers = [];
    foreach ($this->providerManager->getDefinitions() as $id => $definition) {
      $providers[$id] = $this->providerManager->createInstance($id);
    }
    if (count($providers) === 0) {
      $this->messenger()->addWarning($this->t('Choose at least one AI provider module from those listed on the AI module homepage, add to your project, install and configure it. Then update the AI Settings on this page.'));
    }

    // Add the hardcoded selections of filtered types.
    $operation_types = array_merge($operation_types, $this->hardcodedSelections);

    // Check if we're simulating no JavaScript or if a non-JS button
    // was clicked.
    $is_nojs = ($nojs === 'nojs');
    $triggering_element = $form_state->getTriggeringElement();
    $is_nojs_submit = $triggering_element && !empty($triggering_element['#name']) && strpos($triggering_element['#name'], 'select_provider_') === 0;

    foreach ($operation_types as $operation_type) {
      // Get all providers that allows for a specific operation type.
      $options = [
        '' => 'No default',
      ];
      $filters = $operation_type['filter'] ?? [];
      foreach ($providers as $provider) {
        if ($provider->isUsable($operation_type['actual_type'] ?? $operation_type['id'], $filters)) {
          $options[$provider->getPluginId()] = $provider->getPluginDefinition()['label'];
        }
      }

      // Determine the selected provider.
      $selected_provider = '';

      // Get the operation key for this operation type.
      $operation_key = 'operation__' . $operation_type['id'];

      // If this is a non-JS submission, get the value from user input.
      if ($is_nojs_submit && $form_state->getUserInput()['operation__' . $operation_type['id']]) {
        $selected_provider = $form_state->getUserInput()['operation__' . $operation_type['id']];
      }
      // Otherwise get from form state if available.
      elseif ($form_state->hasValue($operation_key)) {
        $selected_provider = $form_state->getValue($operation_key);
      }
      // Fallback to the configured default if none of the above.
      else {
        $selected_provider = $default_providers[$operation_type['id']]['provider_id'] ?? '';
      }

      $form['default_providers'][$operation_type['id']] = [
        '#type' => 'fieldset',
        '#title' => $operation_type['label'],
      ];

      $form['default_providers'][$operation_type['id']][$operation_key] = [
        '#type' => 'select',
        '#title' => $this->t('Default Provider'),
        '#options' => $options,
        '#default_value' => $selected_provider,
        '#ajax' => [
          'callback' => '::loadModels',
          'wrapper' => 'model__' . $operation_type['id'],
          'event' => 'change',
        ],
      ];

      // Create a button for non-JS functionality to select the provider.
      $form['default_providers'][$operation_type['id']]['select_provider_' . $operation_type['id']] = [
        '#type' => 'submit',
        '#value' => $this->t('Choose Model'),
        '#name' => 'select_provider_' . $operation_type['id'],
        '#attributes' => ['class' => ['js-hide', 'button--small']],
        '#submit' => ['::selectProviderSubmit'],
        '#access' => count($options) > 1,
      ];

      // If we're simulating no JavaScript, remove the AJAX property.
      if ($is_nojs) {
        unset($form['default_providers'][$operation_type['id']][$operation_key]['#ajax']);
        // Make the select provider button visible.
        unset($form['default_providers'][$operation_type['id']]['select_provider_' . $operation_type['id']]['#attributes']['class'][0]);
      }

      // Create a container for the model dropdown that will be
      // replaced by AJAX.
      $form['default_providers'][$operation_type['id']]['model'] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'model__' . $operation_type['id'],
        ],
      ];

      // Default empty model dropdown.
      $model_options = ['' => $this->t('- Select -')];
      $default_model = '';

      // Populate models if a provider is selected.
      if ($selected_provider && !empty($providers[$selected_provider])) {
        try {
          // Get the model options for this provider.
          if ($providers[$selected_provider]->isUsable($operation_type['actual_type'] ?? $operation_type['id'], $filters)) {
            $model_options = $providers[$selected_provider]->getConfiguredModels($operation_type['actual_type'] ?? $operation_type['id'], $filters);
            // Set the default model value.
            $default_model = $form_state->getValue('model__' . $operation_type['id']) ??
                             $default_providers[$operation_type['id']]['model_id'] ?? '';
          }
          else {
            $this->messenger()->addWarning($this->t('The default %operation provider (%provider_id) is not currently usable. Please review your configuration.', [
              '%operation' => $operation_type['label'],
              '%provider_id' => $selected_provider,
            ]));
          }
        }
        catch (\Exception $e) {
          // Don't crash if the provider is not fully configured.
          $this->messenger()->addError($e->getMessage());
          // In case the exception is related to authentication.
          if ($e->getCode() == 401 || (method_exists($e, 'getStatusCode') && $e->getStatusCode() == 401)) {
            $api_key = $providers[$selected_provider]->getConfig()->get('api_key');
            if (!empty($api_key)) {
              $this->messenger()->addError($this->t('You can update or add the API Key <a href="@url" target="_blank">here</a>', ['@url' => Url::fromRoute('entity.key.edit_form', ['key' => $api_key])->toString()]));
            }
          }
        }
      }

      // Add the model dropdown to the form.
      $form['default_providers'][$operation_type['id']]['model']['model__' . $operation_type['id']] = [
        '#type' => 'select',
        '#title' => $this->t('Default Model'),
        '#options' => $model_options,
        '#empty_option' => $this->t('- Select -'),
        '#default_value' => $default_model,
        '#disabled' => empty($selected_provider),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Submit handler for provider selection buttons (non-JS).
   */
  public function selectProviderSubmit(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Skip validation if we're just selecting a provider (non-JS flow).
    $triggering_element = $form_state->getTriggeringElement();
    if ($triggering_element && !empty($triggering_element['#name']) &&
        (strpos($triggering_element['#name'], 'select_provider_') === 0)) {
      return;
    }

    $values = $form_state->getValues();
    $operation_types = array_merge($this->providerManager->getOperationTypes(), $this->hardcodedSelections);
    foreach ($operation_types as $operation_type) {
      // We only want to ensure a model is selected for each operation that
      // has a default.
      if (empty($values['operation__' . $operation_type['id']])) {
        continue;
      }

      if (empty($values['model__' . $operation_type['id']])) {
        // The user has the option to select a model but has not, show a
        // validation error.
        $message = $this->t('You have selected a provider for @operation but have not selected a model.', [
          '@operation' => $operation_type['label'],
        ]);
        $form_state->setErrorByName('model__' . $operation_type['id'], $message);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Skip saving if we're just selecting a provider (non-JS flow).
    $triggering_element = $form_state->getTriggeringElement();
    if ($triggering_element && !empty($triggering_element['#name']) &&
        (strpos($triggering_element['#name'], 'select_provider_') === 0)) {
      $form_state->setRebuild();
      return;
    }

    // Set the default providers array.
    $default_providers = [];
    foreach ($form_state->getValues() as $key => $value) {
      if (strpos($key, 'operation__') === 0) {
        $operation_type = substr($key, 11);
        if (empty($value)) {
          continue;
        }
        $default_providers[$operation_type] = [
          'provider_id' => $value,
          'model_id' => $form_state->getValue('model__' . $operation_type),
        ];
      }
    }

    // Retrieve the configuration.
    $this->config(static::CONFIG_NAME)
      ->set('default_providers', $default_providers)
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Ajax callback to load models.
   */
  public function loadModels(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $operation_type = substr($trigger['#name'], 11);

    // Get the selected provider from user input.
    $user_input = $form_state->getUserInput();
    $provider_id = $user_input['operation__' . $operation_type] ?? '';

    // Get the current model value from user input.
    $current_model = $user_input['model__' . $operation_type] ?? '';

    // If no provider is selected, return empty model container.
    if (empty($provider_id)) {
      $form['default_providers'][$operation_type]['model']['model__' . $operation_type]['#options'] = ['' => $this->t('- Select -')];
      $form['default_providers'][$operation_type]['model']['model__' . $operation_type]['#value'] = '';
      $form['default_providers'][$operation_type]['model']['model__' . $operation_type]['#disabled'] = TRUE;
      $form_state->setValue('model__' . $operation_type, '');
      return $form['default_providers'][$operation_type]['model'];
    }

    // Get the provider instance.
    $provider = $this->providerManager->createInstance($provider_id);

    // Get the operation type definition and filters.
    $operation_types = array_merge($this->providerManager->getOperationTypes(), $this->hardcodedSelections);
    $operation_type_definition = NULL;
    $filters = [];
    foreach ($operation_types as $type) {
      if ($type['id'] === $operation_type) {
        $operation_type_definition = $type;
        $filters = $type['filter'] ?? [];
        break;
      }
    }

    if (!$operation_type_definition) {
      return $form['default_providers'][$operation_type]['model'];
    }

    // Get the models for this provider and operation type.
    $models = [];
    try {
      if ($provider->isUsable($operation_type_definition['actual_type'] ?? $operation_type, $filters)) {
        $models = $provider->getConfiguredModels($operation_type_definition['actual_type'] ?? $operation_type, $filters);
      }
    }
    catch (\Exception $e) {
      $this->messenger()->addError($e->getMessage());
    }

    // If we have a current model value, check if it's still
    // valid for the new provider.
    if ($current_model && !isset($models[$current_model])) {
      // If the current model is not valid for the new provider, clear it.
      $current_model = '';
      unset($user_input['model__' . $operation_type]);
      $form_state->setUserInput($user_input);
    }

    // Update the model select element.
    $form['default_providers'][$operation_type]['model']['model__' . $operation_type]['#options'] = $models;
    $form['default_providers'][$operation_type]['model']['model__' . $operation_type]['#value'] = $current_model;
    $form['default_providers'][$operation_type]['model']['model__' . $operation_type]['#disabled'] = FALSE;

    // Ensure the form state maintains the model value.
    $form_state->setValue('model__' . $operation_type, $current_model);

    return $form['default_providers'][$operation_type]['model'];
  }

}
