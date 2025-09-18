<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall;

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
 * Plugin implementation of that can add or edit a vocabulary.
 */
#[FunctionCall(
  id: 'ai_agent:modify_vocabulary',
  function_name: 'ai_agent_modify_vocabulary',
  name: 'Modify Vocabulary',
  description: 'This function is used to either create or edit a vocabulary.',
  group: 'modification_tools',
  context_definitions: [
    'vid' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Vocabulary ID"),
      description: new TranslatableMarkup("The data name of the vocabulary. Should be unique if its getting created."),
      required: TRUE,
      constraints: ['Regex' => '/^[a-zA-Z0-9_]+$/'],
    ),
    'name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Name"),
      description: new TranslatableMarkup("The required name for the vocabulary."),
      required: TRUE,
    ),
    'description' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Description"),
      description: new TranslatableMarkup("The description of the vocabulary."),
      required: FALSE,
    ),
    'create_new_revisions' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup("Create New Revisions"),
      description: new TranslatableMarkup("If the vocabulary should create new revisions."),
      required: FALSE,
    ),
    'vocabulary_language' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Vocabulary Language"),
      description: new TranslatableMarkup("The language of the vocabulary. Only set if you know/want to set it."),
      required: FALSE,
    ),
  ],
)]
class ModifyVocabulary extends FunctionCallBase implements ExecutableFunctionCallInterface {

  /**
   * The entity type manager.
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
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * The information of what was created.
   *
   * @var string
   */
  protected string $information = '';

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // Check so the user has access to create/edit vocabularies.
    if (!$this->currentUser->hasPermission('administer taxonomy')) {
      throw new \Exception('The current user does not have the right permissions to create/edit vocabularies.');
    }

    // Collect the context values.
    $vid = $this->getContextValue('vid');
    $name = $this->getContextValue('name');
    $description = $this->getContextValue('description');
    $create_new_revisions = $this->getContextValue('create_new_revisions');
    $vocabulary_language = $this->getContextValue('vocabulary_language') ?? 'und';

    // Get the vocabulary storage.
    $storage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');

    // Check if its an edit or create.
    /** @var \Drupal\taxonomy\VocabularyInterface $vocabulary */
    $vocabulary = $storage->load($vid);
    $modification = 'created';
    if ($vocabulary) {
      $modification = 'edited';
      // Set the label if it is not empty.
      if (!empty($name)) {
        $vocabulary->set('name', $name);
      }
      // Set the description if it is not empty.
      if (!empty($description)) {
        $vocabulary->set('description', $description);
      }
    }
    else {
      $vocabulary = $storage->create([
        'vid' => $vid,
        'name' => $name,
        'description' => $description,
        'langcode' => $vocabulary_language,
        'new_revision' => $create_new_revisions,
        'uid' => $this->currentUser->id(),
      ]);
    }
    if ($vocabulary->save()) {
      // Link to the vocabulary page.
      $url = Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString();
      $this->setOutput($this->t('The vocabulary @name has been @modification. You can see it here: @url', [
        '@url' => $url,
        '@name' => $name,
        '@modification' => $modification,
      ]));
    }
    else {
      throw new \Exception('The vocabulary could not be created.');
    }
  }

}
