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
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of that list a taxonomy term.
 */
#[FunctionCall(
  id: 'ai_agent:list_taxonomy_term',
  function_name: 'ai_agent_list_taxonomy_term',
  name: 'List Taxonomy Term',
  description: 'This function is used to list taxonomy term.',
  group: 'information_tools',
  context_definitions: [
    'vid' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Vocabulary ID"),
      description: new TranslatableMarkup("The vocabulary id to list the terms from. If not set, all terms will be listed."),
      required: FALSE,
    ),
    'tid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup("Taxonomy ID"),
      description: new TranslatableMarkup("The term to list."),
      required: FALSE,
    ),
    'parent' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup("Parent"),
      description: new TranslatableMarkup("Only list terms under this parent. If not set, all terms will be listed."),
      required: FALSE,
    ),
  ],
)]
class ListTaxonomyTerms extends FunctionCallBase implements ExecutableFunctionCallInterface {

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
      throw new \Exception('The current user does not have the right permissions to list taxonomy terms.');
    }

    // Collect the context values.
    $vid = $this->getContextValue('vid');
    $tid = $this->getContextValue('tid');
    $parent = $this->getContextValue('parent');

    /** @var \Drupal\taxonomy\TermStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $query = $storage->getQuery();
    if ($vid) {
      $query->condition('vid', $vid);
    }
    if ($tid) {
      $query->condition('tid', $tid);
    }
    if ($parent) {
      $query->condition('parent', $parent);
    }
    $query->accessCheck(TRUE);

    $query->sort('weight', 'ASC');

    $entity_ids = $query->execute();
    $terms = $storage->loadMultiple($entity_ids);
    $output = [];
    /** @var \Drupal\taxonomy\TermInterface[] $terms */
    foreach ($terms as $term) {
      $output[] = [
        'tid' => $term->id(),
        'name' => $term->getName(),
        'vid' => $term->bundle(),
        'parent' => $term->parent->target_id ?? 0,
        'description' => $term->getDescription(),
        'weight' => $term->getWeight(),
      ];
    }
    $this->setOutput(Yaml::dump($output, 10, 2));
  }

}
