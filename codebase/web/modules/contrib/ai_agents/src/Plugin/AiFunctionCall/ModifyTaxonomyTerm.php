<?php

namespace Drupal\ai_agents\Plugin\AiFunctionCall;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of that can add or edit a taxonomy term.
 */
#[FunctionCall(
  id: 'ai_agent:modify_taxonomy_term',
  function_name: 'ai_agent_modify_taxonomy_term',
  name: 'Modify Taxonomy Term',
  description: 'This function is used to either create or edit a taxonomy term.',
  group: 'modification_tools',
  context_definitions: [
    'vid' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Vocabulary ID"),
      description: new TranslatableMarkup("The vocabulary id to save the term on, only needed for creation."),
      required: FALSE,
    ),
    'tid' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Taxonomy ID"),
      description: new TranslatableMarkup("The taxonomy id, only needed for updates."),
      required: FALSE,
    ),
    'name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Name"),
      description: new TranslatableMarkup("The required name for the term."),
      required: TRUE,
    ),
    'description' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Description"),
      description: new TranslatableMarkup("The description of the term."),
      required: FALSE,
    ),
    'parent' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup("Parent"),
      description: new TranslatableMarkup("If the vocabulary is hierarchical, the id of the parent term."),
      required: FALSE,
    ),
    'weight' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup("Weight"),
      description: new TranslatableMarkup("The weight of the term."),
      required: FALSE,
    ),
    'term_language' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Term Language"),
      description: new TranslatableMarkup("The language of the term. Only set if you know/want to set it."),
      required: FALSE,
    ),
  ],
)]
class ModifyTaxonomyTerm extends FunctionCallBase implements ExecutableFunctionCallInterface {

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
    // Check so the user has access to create/edit taxonomy terms.
    if (!$this->currentUser->hasPermission('administer taxonomy')) {
      throw new \Exception('The current user does not have the right permissions to create/edit taxonomy terms.');
    }

    // Collect the context values.
    $vid = $this->getContextValue('vid');
    $tid = $this->getContextValue('tid');
    $name = $this->getContextValue('name');
    $description = $this->getContextValue('description');
    $parent = $this->getContextValue('parent');
    $weight = $this->getContextValue('weight');
    $term_language = $this->getContextValue('term_language') ?? 'und';

    // Check so either vid or tid is set.
    if (empty($vid) && empty($tid)) {
      throw new \Exception('Either vid or tid must be set. vid is used for creating a new term, and tid is used for editing an existing term.');
    }

    // Get the term storage.
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    // Get the vocabulary storage.
    if ($vid) {
      $vocabulary_storage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
      if (!$vocabulary_storage->load($vid)) {
        throw new \Exception("The vocabulary with the id $vid does not exist.");
      }
    }
    // Check if its an edit or create.
    if ($tid) {
      // Load the term.
      /** @var \Drupal\taxonomy\TermInterface $term */
      $term = $storage->load($tid);
      if (!$term) {
        throw new \Exception("The term with the id $tid does not exist.");
      }
      // Make sure that the user has access to edit.
      if (!$term->access('update')) {
        throw new \Exception('The current user does not have the right permissions to edit this term.');
      }
      // Set the label if it is not empty.
      if (!empty($name)) {
        $term->set('name', $name);
      }
      // Set the description if it is not empty.
      if (!empty($description)) {
        $term->set('description', $description);
      }
      // Set the parent if it is not empty.
      if (is_numeric($parent)) {
        $term->set('parent', [$parent]);
      }
      // Set the weight if it is not empty.
      if (is_numeric($weight)) {
        $term->set('weight', $weight);
      }
      // Set the language if it is not empty.
      if (!empty($term_language)) {
        $term->set('langcode', $term_language);
      }
    }
    else {
      $parent = $parent ?? 0;
      // Create a new term.
      $term = $storage->create([
        'vid' => $vid,
        'name' => $name,
        'description' => $description,
        'parent' => [$parent],
        'weight' => $weight ?? 0,
        'langcode' => $term_language,
        'new_revision' => TRUE,
        'uid' => $this->currentUser->id(),
      ]);
    }

    if ($term->save()) {
      $this->setOutput("The term $name was successfully created/edited.");
    }
    else {
      throw new \Exception("The term $name could not be created.");
    }
  }

}
