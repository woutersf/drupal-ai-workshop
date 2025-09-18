<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the create content type function.
 */
#[FunctionCall(
  id: 'ai_agent:create_content_type',
  function_name: 'create_content_type',
  name: 'Create Content Type',
  description: 'This function is used to create a content type.',
  group: 'modification_tools',
  module_dependencies: ['node'],
  context_definitions: [
    'data_name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Data Name"),
      description: new TranslatableMarkup("The required node type data name. Allows for underscore and alphanumeric characters."),
      required: TRUE,
      constraints: ['Regex' => '/^[a-zA-Z0-9_]+$/'],
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Label"),
      description: new TranslatableMarkup("The required node type label."),
      required: TRUE,
    ),
    'description' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Description"),
      description: new TranslatableMarkup("The optional node type description."),
    ),
    'new_revision' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup("New Revision"),
      description: new TranslatableMarkup("Whether new revisions are enabled or not."),
      default_value: TRUE,
    ),
    'preview_mode' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup("Preview Mode"),
      description: new TranslatableMarkup("Whether preview mode is enabled or not. 0 is disabled, 2 is required, 1 is optional."),
      default_value: DRUPAL_OPTIONAL,
      constraints: [
        'Choice' => [DRUPAL_REQUIRED, DRUPAL_OPTIONAL, DRUPAL_DISABLED],
      ],
    ),
    'display_submitted' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup("Display Submitted"),
      description: new TranslatableMarkup("Whether we display author and publish date or not."),
      default_value: TRUE,
    ),
    'published_by_default' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup("Published By Default"),
      description: new TranslatableMarkup("Whether the node is published by default."),
      default_value: TRUE,
    ),
    'promoted_by_default' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup("Promoted By Default"),
      description: new TranslatableMarkup("Whether the node is promoted to front page by default."),
      default_value: TRUE,
    ),
    'sticky_by_default' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup("Sticky By Default"),
      description: new TranslatableMarkup("Whether the node is sticky by default."),
      default_value: FALSE,
    ),
  ],
)]
class CreateContentType extends FunctionCallBase implements ExecutableFunctionCallInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

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
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * The information of what was created.
   *
   * @var array
   */
  protected array $information = [];

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // Collect the context values.
    $data_name = $this->getContextValue('data_name');
    $label = $this->getContextValue('label');
    $description = $this->getContextValue('description');
    $new_revision = $this->getContextValue('new_revision');
    $preview_mode = $this->getContextValue('preview_mode');
    $display_submitted = $this->getContextValue('display_submitted');
    $published_by_default = $this->getContextValue('published_by_default');
    $promoted_by_default = $this->getContextValue('promoted_by_default');
    $sticky_by_default = $this->getContextValue('sticky_by_default');

    // Make sure that the user has the right permissions.
    if (!$this->currentUser->hasPermission('administer content types')) {
      throw new \Exception('The current user does not have the right permissions to run this tool.');
    }
    if (empty($data_name) || empty($label)) {
      $this->t('The data name and label are required.');
      return;
    }
    $node_type_storage = $this->entityTypeManager->getStorage('node_type');
    // Check if the node type exists.
    if ($node_type_storage->load($data_name)) {
      $this->information[] = $this->t('The node type %node_type (%data_name) already exists.', [
        '%node_type' => $label,
        '%data_name' => $data_name,
      ]);
      return;
    }
    $node_type = $node_type_storage->create([
      'type' => $data_name,
      'name' => $label,
      'description' => $description,
      'new_revision' => $new_revision,
      'preview_mode' => $preview_mode,
      'display_submitted' => $display_submitted,
    ]);
    if ($node_type->save()) {
      foreach ([
        'sticky' => $sticky_by_default,
        'promote' => $promoted_by_default,
        'status' => $published_by_default,
      ] as $key => $value) {
        $fields = $this->entityFieldManager->getFieldDefinitions('node', $data_name);
        $fields[$key]->getConfig($data_name)->setDefaultValue($value)->save();
      }
    }
    $url = Url::fromRoute('entity.node_type.edit_form', ['node_type' => $data_name]);

    $this->information[] = $this->t('The node type %node_type (%data_name) has been created. Its available to check under @link.', [
      '%node_type' => $label,
      '%data_name' => $data_name,
      '@link' => $url->toString(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return implode("\n", $this->information);
  }

}
