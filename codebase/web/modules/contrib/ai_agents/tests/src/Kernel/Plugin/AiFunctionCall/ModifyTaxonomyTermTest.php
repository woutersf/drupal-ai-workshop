<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the ModifyTaxonomyTermTest class.
 *
 * @group ai_agents
 */
#[Group('ai_agents')]
final class ModifyTaxonomyTermTest extends KernelTestBase {

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
   * Modify a taxonomy term.
   */
  public function testModifyTaxonomyTerm(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the list field types form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:modify_taxonomy_term');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // We create a new term, so we don't need to set a tid.
    $tool->setContextValue('vid', 'test_vocabulary');
    $tool->setContextValue('name', 'Modified Term');
    $tool->setContextValue('description', 'This is a modified term.');
    $tool->setContextValue('parent', $this->parentId);
    $tool->setContextValue('weight', 10);
    $tool->setContextValue('term_language', 'en');

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains all terms.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('The term Modified Term was successfully created/edited.', $result, 'The term was successfully created/edited.');

    // Load the term to check if it was created.
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    /** @var \Drupal\taxonomy\TermInterface[] $term */
    $term = $storage->loadByProperties(['name' => 'Modified Term', 'vid' => 'test_vocabulary']);
    $this->assertNotEmpty($term, 'The term was successfully created.');
    $term = reset($term);
    $this->assertEquals('Modified Term', $term->getName(), 'The term name is correct.');
    $this->assertEquals('This is a modified term.', $term->getDescription(), 'The term description is correct.');
    $this->assertEquals($this->parentId, $term->get('parent')->target_id, 'The term parent is correct.');
    $this->assertEquals(10, $term->get('weight')->value, 'The term weight is correct.');
    $this->assertEquals('en', $term->get('langcode')->value, 'The term language is correct.');
  }

  /**
   * Test modifying a taxonomy term with a normal user, should throw exception.
   */
  public function testModifyTaxonomyTermWithNormalUser(): void {
    // Make sure that the current user is a normal user.
    $this->currentUser->setAccount($this->normalUser);
    // Get the list field types form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:modify_taxonomy_term');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    // We create a new term, so we don't need to set a tid.
    $tool->setContextValue('vid', 'test_vocabulary');
    $tool->setContextValue('name', 'Modified Term');
    $tool->setContextValue('description', 'This is a modified term.');
    $tool->setContextValue('parent', $this->parentId);
    $tool->setContextValue('weight', 10);
    $tool->setContextValue('term_language', 'en');

    // Execute the tool and expect an exception.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('The current user does not have the right permissions to create/edit taxonomy terms.');
    $tool->execute();
  }

  /**
   * Test modifying a taxonomy term with a user that has view permissions.
   */
  public function testModifyTaxonomyTermWithTaxonomyViewerUser(): void {
    // Make sure that the current user is a taxonomy viewer user.
    $this->currentUser->setAccount($this->taxonomyViewerUser);
    // Get the list field types form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:modify_taxonomy_term');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    // We create a new term, so we don't need to set a tid.
    $tool->setContextValue('vid', 'test_vocabulary');
    $tool->setContextValue('name', 'Modified Term');
    $tool->setContextValue('description', 'This is a modified term.');
    $tool->setContextValue('parent', $this->parentId);
    $tool->setContextValue('weight', 10);
    $tool->setContextValue('term_language', 'en');

    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains all terms.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('The term Modified Term was successfully created/edited.', $result, 'The term was successfully created/edited.');
    // Load the term to check if it was created.
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    /** @var \Drupal\taxonomy\TermInterface[] $term */
    $term = $storage->loadByProperties(['name' => 'Modified Term', 'vid' => 'test_vocabulary']);
    $this->assertNotEmpty($term, 'The term was successfully created.');
    $term = reset($term);
    $this->assertEquals('Modified Term', $term->getName(), 'The term name is correct.');
    $this->assertEquals('This is a modified term.', $term->getDescription(), 'The term description is correct.');
    $this->assertEquals($this->parentId, $term->get('parent')->target_id, 'The term parent is correct.');
    $this->assertEquals(10, $term->get('weight')->value, 'The term weight is correct.');
    $this->assertEquals('en', $term->get('langcode')->value, 'The term language is correct.');
  }

  /**
   * Try editing a term.
   */
  public function testEditTaxonomyTerm(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the list field types form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:modify_taxonomy_term');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    // We edit an existing term, so we need to set a tid.
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
      'name' => 'Term 1',
      'vid' => 'test_vocabulary',
    ]);
    $term = reset($term);
    $tid = $term->id();

    $tool->setContextValue('tid', $tid);
    $tool->setContextValue('vid', 'test_vocabulary');
    $tool->setContextValue('name', 'Edited Term 1');
    $tool->setContextValue('description', 'This is an edited term.');
    $tool->setContextValue('parent', 0);
    $tool->setContextValue('weight', 5);
    $tool->setContextValue('term_language', 'en');

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains all terms.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('The term Edited Term 1 was successfully created/edited.', $result, 'The term was successfully created/edited.');

    // Load the term to check if it was edited.
    /** @var \Drupal\taxonomy\TermInterface[] $term */
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
    $this->assertNotEmpty($term, 'The term was successfully edited.');
    $this->assertEquals('Edited Term 1', $term->getName(), 'The term name is correct.');
    $this->assertEquals('This is an edited term.', $term->getDescription(), 'The term description is correct.');
    $this->assertEquals(0, $term->get('parent')->target_id, 'The term parent is correct.');
    $this->assertEquals(5, $term->get('weight')->value, 'The term weight is correct.');
    $this->assertEquals('en', $term->get('langcode')->value, 'The term language is correct.');
  }

}
