<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the ListTaxonomyTermsTest class.
 *
 * @group ai_agents
 */
#[Group('ai_agents')]
final class ListTaxonomyTermsTest extends KernelTestBase {

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
   * List all taxonomy terms as an admin user.
   */
  public function testListAllFieldTypesAsAdmin(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the list field types form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_taxonomy_term');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains all terms.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('Term 1', $result, 'The result contains Term 1.');
    $this->assertStringContainsString('Term 2', $result, 'The result contains Term 2.');
    $this->assertStringContainsString('Term 3', $result, 'The result contains Term 3.');
    $this->assertStringContainsString('Term 4', $result, 'The result contains Term 4.');
    $this->assertStringContainsString('Term 5', $result, 'The result contains Term 5.');
    $this->assertStringContainsString('parent:', $result, 'The result contains parent information for Term 3.');
    $this->assertStringContainsString("description: 'This term is a child of Term 1.'", $result, 'The result contains description information for Term 3.');
  }

  /**
   * List all taxonomy terms as a normal user.
   */
  public function testListAllFieldTypesAsNormalUser(): void {
    // Make sure that the current user is a normal user.
    $this->currentUser->setAccount($this->normalUser);
    // Get the list field types form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_taxonomy_term');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    // Should throw an exception because of permissions.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('The current user does not have the right permissions to list taxonomy terms.');

    // Execute the tool.
    $tool->execute();
  }

  /**
   * List taxonomy terms with a specific vocabulary as an admin user.
   */
  public function testListTaxonomyTermsWithVocabularyAsAdmin(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the list field types form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_taxonomy_term');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    // Set the context for the vocabulary.
    $tool->setContextValue('vid', 'test_vocabulary');

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains all terms in the vocabulary.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('Term 1', $result, 'The result contains Term 1.');
    $this->assertStringContainsString('Term 2', $result, 'The result contains Term 2.');
    $this->assertStringContainsString('Term 3', $result, 'The result contains Term 3.');
    // Should not contain terms from another vocabulary.
    $this->assertStringNotContainsString('Term 4', $result, 'The result does not contain Term 4.');
    $this->assertStringNotContainsString('Term 5', $result, 'The result does not contain Term 5.');
  }

  /**
   * List taxonomy terms against a specific parent term as an admin user.
   */
  public function testListTaxonomyTermsWithParentAsAdmin(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the list field types form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_taxonomy_term');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    // Set the context for the parent term.
    $tool->setContextValue('parent', $this->parentId);

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains only child terms of parent.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('Term 3', $result, 'The result contains Term 3 as a child of Term 1.');
    // Should not contain terms that are not children of the parent term.
    $this->assertStringNotContainsString("name: 'Term 1'", $result, 'The result does not contain Term 1.');
    $this->assertStringNotContainsString('Term 2', $result, 'The result does not contain Term 2.');
    $this->assertStringNotContainsString('Term 4', $result, 'The result does not contain Term 4.');
    $this->assertStringNotContainsString('Term 5', $result, 'The result does not contain Term 5.');
  }

  /**
   * List taxonomy terms with a specific vocabulary as the taxonomy viewer user.
   */
  public function testListTaxonomyTermsWithVocabularyAsTaxonomyViewerUser(): void {
    // Make sure that the current user is the taxonomy viewer user.
    $this->currentUser->setAccount($this->taxonomyViewerUser);
    // Get the list field types form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_taxonomy_term');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    // Set the context for the vocabulary.
    $tool->setContextValue('vid', 'test_vocabulary');

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains all terms in the vocabulary.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('Term 1', $result, 'The result contains Term 1.');
    $this->assertStringContainsString('Term 2', $result, 'The result contains Term 2.');
    $this->assertStringContainsString('Term 3', $result, 'The result contains Term 3.');
    // Should not contain terms from another vocabulary.
    $this->assertStringNotContainsString('Term 4', $result, 'The result does not contain Term 4.');
    $this->assertStringNotContainsString('Term 5', $result, 'The result does not contain Term 5.');
  }

}
