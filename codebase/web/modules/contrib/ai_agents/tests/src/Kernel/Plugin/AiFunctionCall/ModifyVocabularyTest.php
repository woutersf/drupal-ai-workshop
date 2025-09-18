<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the ModifyVocabularyTest class.
 *
 * @group ai_agents
 */
#[Group('ai_agents')]
final class ModifyVocabularyTest extends KernelTestBase {

  /**
   * The function call plugin manager.
   *
   * @var \Drupal\ai\Plugin\AiFunctionCall\AiFunctionCallManager
   */
  protected $functionCallManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The admin user account.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * The normal user account.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $normalUser;

  /**
   * Special user with access to see taxonomy terms.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $taxonomyViewerUser;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'user',
    'ai',
    'ai_agents',
    'system',
    'field',
    'link',
    'text',
    'field_ui',
    'filter',
    'taxonomy',
  ];

  /**
   * A parent id to test against.
   *
   * @var int
   */
  protected $parentId = 0;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install required modules.
    $this->container->get('module_installer')->install(self::$modules);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('taxonomy_vocabulary');

    // Set up the dependencies.
    $this->functionCallManager = $this->container->get('plugin.manager.ai.function_calls');
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->currentUser = $this->container->get('current_user');
    $this->entityDisplayRepository = $this->container->get('entity_display.repository');

    // Create an admin user account.
    $this->adminUser = $this->entityTypeManager->getStorage('user')->create([
      'name' => 'admin',
      'mail' => 'example@example.com',
      'status' => 1,
      'roles' => ['administrator'],
    ]);
    $this->adminUser->save();

    // Create a normal user account.
    $this->normalUser = $this->entityTypeManager->getStorage('user')->create([
      'name' => 'normal_user',
      'mail' => 'normal@example.com',
      'status' => 1,
      'roles' => ['authenticated'],
    ]);
    $this->normalUser->save();

    // Create a role that view taxonomy terms.
    /** @var \Drupal\user\Entity\UserRole $role */
    $role = $this->entityTypeManager->getStorage('user_role')->create([
      'id' => 'taxonomy_viewer',
      'label' => 'Taxonomy Viewer',
    ]);
    $role->grantPermission('administer taxonomy');
    $role->save();
    // Create a user with the taxonomy viewer role.
    $this->taxonomyViewerUser = $this->entityTypeManager->getStorage('user')->create([
      'name' => 'taxonomy_viewer_user',
      'mail' => 'taxonomy@example.com',
      'status' => 1,
      'roles' => ['taxonomy_viewer'],
    ]);
    $this->taxonomyViewerUser->save();

    // Create a vocabulary for testing.
    $vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->create([
      'name' => 'Test Vocabulary',
      'vid' => 'test_vocabulary',
      'description' => 'A vocabulary for testing purposes.',
    ]);
    $vocabulary->save();

    // Create some taxonomy terms for the vocabulary.
    $term1 = $this->entityTypeManager->getStorage('taxonomy_term')->create([
      'name' => 'Term 1',
      'vid' => 'test_vocabulary',
    ]);
    $term1->save();

    $term2 = $this->entityTypeManager->getStorage('taxonomy_term')->create([
      'name' => 'Term 2',
      'vid' => 'test_vocabulary',
    ]);
    $term2->save();
    $this->parentId = $term1->id();

    $term3 = $this->entityTypeManager->getStorage('taxonomy_term')->create([
      'name' => 'Term 3',
      'vid' => 'test_vocabulary',
      'description' => 'This term is a child of Term 1.',
      'parent' => [$term1->id()],
    ]);
    $term3->save();

    // Create another vocabulary for testing.
    $vocabulary2 = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->create([
      'name' => 'Another Vocabulary',
      'vid' => 'another_vocabulary',
      'description' => 'Another vocabulary for testing purposes.',
    ]);
    $vocabulary2->save();

    // Create some taxonomy terms for the second vocabulary.
    $term4 = $this->entityTypeManager->getStorage('taxonomy_term')->create([
      'name' => 'Term 4',
      'vid' => 'another_vocabulary',
    ]);
    $term4->save();
    $term5 = $this->entityTypeManager->getStorage('taxonomy_term')->create([
      'name' => 'Term 5',
      'vid' => 'another_vocabulary',
    ]);
    $term5->save();
  }

  /**
   * Modify a vocabulary.
   */
  public function testModifyTaxonomyTerm(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the list field types form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:modify_vocabulary');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // We create a new vocabulary, so we need to set the context.
    $tool->setContextValue('vid', 'modified_vocabulary');
    $tool->setContextValue('name', 'Modified Vocabulary');
    $tool->setContextValue('description', 'This is a modified vocabulary.');
    $tool->setContextValue('create_new_revisions', TRUE);
    $tool->setContextValue('vocabulary_language', 'en');

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains all vocabulary.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('The vocabulary Modified Vocabulary has been created. You can see it here: /admin/structure/taxonomy', $result, 'The term was successfully created/edited.');

    // Load the vocabulary to check if it was created.
    /** @var \Drupal\taxonomy\VocabularyInterface $vocabulary */
    $vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load('modified_vocabulary');
    $this->assertNotNull($vocabulary, 'The vocabulary was successfully created.');
    $this->assertEquals('Modified Vocabulary', $vocabulary->label(), 'The vocabulary label is correct.');
    $this->assertEquals('This is a modified vocabulary.', $vocabulary->getDescription(), 'The vocabulary description is correct.');
    $this->assertEquals('en', $vocabulary->get('langcode'), 'The vocabulary language is correct.');
  }

  /**
   * Test modifying a vocabulary with a normal user.
   */
  public function testModifyTaxonomyTermWithNormalUser(): void {
    // Make sure that the current user is a normal user.
    $this->currentUser->setAccount($this->normalUser);
    // Get the list field types form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:modify_vocabulary');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    // We create a new vocabulary, so we need to set the context.
    $tool->setContextValue('vid', 'modified_vocabulary');
    $tool->setContextValue('name', 'Modified Vocabulary');
    $tool->setContextValue('description', 'This is a modified vocabulary.');
    $tool->setContextValue('create_new_revisions', TRUE);
    $tool->setContextValue('vocabulary_language', 'en');

    // Execute the tool.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('The current user does not have the right permissions to create/edit vocabularies.');
    $tool->execute();
  }

  /**
   * Test modifying a vocabulary with a user that has access taxonomy terms.
   */
  public function testModifyTaxonomyTermWithTaxonomyViewerUser(): void {
    // Make sure that the current user is a taxonomy viewer user.
    $this->currentUser->setAccount($this->taxonomyViewerUser);
    // Get the list field types form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:modify_vocabulary');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    // We create a new vocabulary, so we need to set the context.
    $tool->setContextValue('vid', 'modified_vocabulary');
    $tool->setContextValue('name', 'Modified Vocabulary');
    $tool->setContextValue('description', 'This is a modified vocabulary.');
    $tool->setContextValue('create_new_revisions', TRUE);
    $tool->setContextValue('vocabulary_language', 'en');

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains all vocabulary.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('The vocabulary Modified Vocabulary has been created. You can see it here: /admin/structure/taxonomy', $result, 'The term was successfully created/edited.');
    // Load the vocabulary to check if it was created.
    /** @var \Drupal\taxonomy\VocabularyInterface $vocabulary */
    $vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load('modified_vocabulary');
    $this->assertNotNull($vocabulary, 'The vocabulary was successfully created.');
    $this->assertEquals('Modified Vocabulary', $vocabulary->label(), 'The vocabulary label is correct.');
    $this->assertEquals('This is a modified vocabulary.', $vocabulary->getDescription(), 'The vocabulary description is correct.');
    $this->assertEquals('en', $vocabulary->get('langcode'), 'The vocabulary language is correct.');
  }

  /**
   * Test editing an existing vocabulary.
   */
  public function testEditExistingVocabulary(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the list field types form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:modify_vocabulary');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    // We edit an existing vocabulary, so we need to set the context.
    $tool->setContextValue('vid', 'test_vocabulary');
    $tool->setContextValue('name', 'Edited Test Vocabulary');
    $tool->setContextValue('description', 'This is an edited vocabulary.');
    $tool->setContextValue('create_new_revisions', TRUE);
    $tool->setContextValue('vocabulary_language', 'en');

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains all vocabulary.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('The vocabulary Edited Test Vocabulary has been edited. You can see it here: /admin/structure/taxonomy', $result, 'The term was successfully created/edited.');

    // Load the vocabulary to check if it was edited.
    /** @var \Drupal\taxonomy\VocabularyInterface $vocabulary */
    $vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load('test_vocabulary');
    $this->assertNotNull($vocabulary, 'The vocabulary was successfully edited.');
    $this->assertEquals('Edited Test Vocabulary', $vocabulary->label(), 'The vocabulary label is correct.');
    $this->assertEquals('This is an edited vocabulary.', $vocabulary->getDescription(), 'The vocabulary description is correct.');
  }

}
