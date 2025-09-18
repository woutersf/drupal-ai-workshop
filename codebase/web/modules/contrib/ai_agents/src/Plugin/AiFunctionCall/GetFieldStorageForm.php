<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormState;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of the getting field storage form function.
 */
#[FunctionCall(
  id: 'ai_agent:get_field_storage_form',
  function_name: 'ai_agent_get_field_storage_form',
  name: 'Get Field Storage Form',
  description: 'This method gets the field storage form, to check what values can be set on a field storage.',
  group: 'information_tools',
  context_definitions: [
    'field_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Field Type"),
      description: new TranslatableMarkup("The field type of the field you want to get field storage form for."),
      required: TRUE,
    ),
  ],
)]
class GetFieldStorageForm extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * The entity type service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The field type plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypeManager;

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.context_definition_normalizer'),
    );
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->configFactory = $container->get('config.factory');
    $instance->currentUser = $container->get('current_user');
    $instance->fieldTypeManager = $container->get('plugin.manager.field.field_type');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // Get the context.
    $field_type = $this->getContextValue('field_type');

    // Load all the content entity types.
    $entity_types = $this->entityTypeManager->getDefinitions();
    $has_permission = FALSE;
    foreach ($entity_types as $entity_type_id => $entity_type) {
      // Check so its a content entity type and if the user has permissions.
      if ($entity_type->entityClassImplements(ContentEntityInterface::class) && $this->currentUser->hasPermission('administer ' . $entity_type_id . ' fields')) {
        // Its enough to have permission on one content entity type to be able
        // to list the field display types.
        $has_permission = TRUE;
        break;
      }
    }

    if (!$has_permission) {
      throw new \Exception('You do not have permission to access this tool.');
    }

    // We check permissions against higher access, since no clear permission.
    if (!$this->currentUser->hasPermission('administer site configuration')) {
      throw new \Exception('You do not have permission to access this tool.');
    }

    $field_storage_definition = BaseFieldDefinition::create($field_type)
      ->setLabel('Custom Field')
      ->setDescription('A custom field for demonstration purposes.')
      ->setSettings([])
      ->setCardinality(1)
      ->setRequired(FALSE);
    $instance = $this->fieldTypeManager->createInstance($field_type, [
      'field_definition' => $field_storage_definition,
      'field_name' => 'field_custom',
      'name' => 'Field Custom',
      'parent' => NULL,
      'bundle' => 'testing',
      'entity_type' => 'node',
    ]);
    $settings = $instance->defaultStorageSettings();
    $form = [];
    $element = $instance->storageSettingsForm($form, new FormState(), FALSE);

    $extra_settings = [];
    foreach ($settings as $key => $setting) {
      $this->extractSettingsFromForm($key, $setting, $element, $extra_settings);
    }

    $this->setOutput("This is how you create a field storage config settings for $field_type:\n" . Yaml::dump($extra_settings, 10, 2));
  }

  /**
   * Extract information from a default settings and form recursively.
   *
   * @param array $key
   *   The settings key.
   * @param array $value
   *   The settings value.
   * @param array $form
   *   The form.
   * @param array $extraSettings
   *   Extra settings.
   */
  public function extractSettingsFromForm($key, $value, $form, &$extraSettings = []) {
    if (is_array($value) && isset($form[$key])) {
      foreach ($value as $subKey => $subValue) {
        $originalSettings = $extraSettings;
        $this->extractSettingsFromForm($subKey, $subValue, $form[$key], $extraSettings);
        // Make a difference between the original and the new settings keys.
        $diff = array_diff_key($extraSettings, $originalSettings);
        if (!empty($diff)) {
          foreach ($diff as $diffKey => $diffValue) {
            $extraSettings[$key][$diffKey] = $diffValue;
            unset($extraSettings[$diffKey]);
          }
        }
      }
    }
    else {
      $extraSettings[$key]['default'] = $value;
      if (isset($form[$key])) {
        if (isset($form[$key]['#description']) && (is_string($form[$key]['#description']) || $form[$key]['#description'] instanceof TranslatableMarkup)) {
          $extraSettings[$key]['description'] = (string) $form[$key]['#description'];
        }
        if (isset($form[$key]['#title'])) {
          $extraSettings[$key]['label'] = (string) $form[$key]['#title'];
        }
        if (isset($form[$key]['#options']) && is_array($form[$key]['#options']) && count($form[$key]['#options'])) {
          foreach ($form[$key]['#options'] as $option_key => $option_value) {
            $extraSettings[$key]['options'][$option_key] = is_array($option_value) ? $option_value : (string) $option_value;
          }
        }
      }
    }
  }

}
