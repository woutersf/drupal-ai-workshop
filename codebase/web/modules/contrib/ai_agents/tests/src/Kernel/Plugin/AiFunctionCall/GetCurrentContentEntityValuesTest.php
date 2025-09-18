<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the GetCurrentContentEntityValuesTest class.
 *
 * @group ai_agents
 */
#[Group('ai_agents')]
final class GetCurrentContentEntityValuesTest extends KernelTestBase {

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
   * The anonymous user account.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $anonymousUser;

  /**
   * Special user with article view permissions.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $articleViewer;

  /**
   * Article content entity ID.
   *
   * @var int
   */
  protected $articleOne;

  /**
   * The second article content entity ID.
   *
   * @var int
   */
  protected $articleTwo;

  /**
   * The unpublished article content entity ID.
   *
   * @var int
   */
  protected $unpublishedArticle;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'user', 'ai', 'ai_agents', 'system', 'field', 'field_ui', 'text', 'options'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install required modules.
    $this->container->get('module_installer')->install(self::$modules);
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    // Set up the dependencies.
    $this->functionCallManager = $this->container->get('plugin.manager.ai.function_calls');
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->currentUser = $this->container->get('current_user');

    // Create an admin user account.
    $this->adminUser = $this->entityTypeManager->getStorage('user')->create([
      'name' => 'admin',
      'mail' => 'example@example.com',
      'status' => 1,
      'roles' => ['administrator'],
    ]);
    $this->adminUser->save();

    // The anonymous user account.
    $this->anonymousUser = $this->entityTypeManager->getStorage('user')->create([
      'name' => 'anonymous',
      'mail' => 'anon@example.com',
      'status' => 1,
      'roles' => ['anonymous user'],
    ]);
    $this->anonymousUser->save();

    // Create a role that has the permission to view the articles.
    /** @var \Drupal\user\Entity\UserRole $role */
    $role = $this->entityTypeManager->getStorage('user_role')->create([
      'id' => 'article_viewer',
      'label' => 'Article Viewer',
    ]);
    $role->grantPermission('access content');
    $role->save();

    // Create a user with the content type creation permissions.
    $this->articleViewer = $this->entityTypeManager->getStorage('user')->create([
      'name' => 'article_viewer',
      'mail' => 'content@example.com',
      'status' => 1,
      'roles' => ['article_viewer'],
    ]);
    $this->articleViewer->save();

    // Create an article content type to ensure the system is ready for testing.
    $node_type_storage = $this->entityTypeManager->getStorage('node_type');
    $node_type_storage->create([
      'type' => 'article',
      'name' => 'Article',
      'description' => 'An article content type for testing.',
    ])->save();

    // Add body field to the article content type.
    $field_storage = $this->entityTypeManager->getStorage('field_storage_config');
    $field_storage->create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'type' => 'text_long',
      'cardinality' => 1,
    ])->save();
    $field_instance = $this->entityTypeManager->getStorage('field_config');
    $field_instance->create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Body',
      'required' => FALSE,
      'settings' => [
        'max_length' => 5000,
        'text_processing' => 1,
      ],
    ])->save();

    // Create two articles to ensure there are content entities to work with.
    $node_storage = $this->entityTypeManager->getStorage('node');
    $node = $node_storage->create([
      'type' => 'article',
      'title' => 'Test Article 1',
      'body' => [
        'value' => 'This is the body of test article 1.',
        'format' => 'basic_html',
      ],
      'status' => 1,
    ]);
    $node->save();

    $this->articleOne = $node->id();

    $node = $node_storage->create([
      'type' => 'article',
      'title' => 'Test Article 2',
      'body' => [
        'value' => 'This is the body of test article 2.',
        'format' => 'basic_html',
      ],
      'status' => 1,
    ]);
    $node->save();
    $this->articleTwo = $node->id();

    // Create an unpublished article.
    $node = $node_storage->create([
      'type' => 'article',
      'title' => 'Unpublished Article',
      'body' => [
        'value' => 'This is the body of unpublished article.',
        'format' => 'basic_html',
      ],
      'status' => 0,
    ]);
    $node->save();
    $this->unpublishedArticle = $node->id();

  }

  /**
   * Tests the to get article 1 as admin.
   */
  public function testGetContentTypeInfo(): void {
    // Set the current user to the admin user.
    $this->currentUser->setAccount($this->adminUser);

    // Get the function call plugin.
    $function_call = $this->functionCallManager->createInstance('ai_agent:get_current_content_entity_values');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $function_call);

    // Set the function call context.
    $function_call->setContextValue('entity_type', 'node');
    $function_call->setContextValue('entity_id', $this->articleOne);
    $function_call->setContextValue('field_names', ['nid', 'title', 'body']);

    // Execute the function call with the 'article' content type.
    $function_call->execute();
    $result = $function_call->getReadableOutput();

    // Assert the result is a string and contains the expected values.
    $this->assertIsString($result);
    $this->assertStringContainsString('Test Article 1', $result);
    $this->assertStringContainsString('This is the body of test article 1.', $result);
    $this->assertStringContainsString('title', $result);
    $this->assertStringContainsString('body', $result);
    $this->assertStringContainsString('nid', $result);
    $this->assertStringContainsString((string) $this->articleOne, $result);
  }

  /**
   * Test to get article 2, but only nid as a admin.
   */
  public function testGetContentTypeInfoWithNidOnly(): void {
    // Set the current user to the admin user.
    $this->currentUser->setAccount($this->adminUser);

    // Get the function call plugin.
    $function_call = $this->functionCallManager->createInstance('ai_agent:get_current_content_entity_values');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $function_call);

    // Set the function call context.
    $function_call->setContextValue('entity_type', 'node');
    $function_call->setContextValue('entity_id', $this->articleTwo);
    $function_call->setContextValue('field_names', ['nid']);

    // Execute the function call with the 'article' content type.
    $function_call->execute();
    $result = $function_call->getReadableOutput();

    // Assert the result is a string and contains the expected values.
    $this->assertIsString($result);
    $this->assertStringContainsString((string) $this->articleTwo, $result);
    $this->assertStringNotContainsString('Test Article 2', $result);
    $this->assertStringNotContainsString('This is the body of test article 2.', $result);
    $this->assertStringContainsString('nid', $result);
    $this->assertStringNotContainsString('title', $result);
    $this->assertStringNotContainsString('body', $result);
  }

  /**
   * Test without giving field names, should return all fields.
   */
  public function testGetContentTypeInfoWithoutFieldNames(): void {
    // Set the current user to the admin user.
    $this->currentUser->setAccount($this->adminUser);

    // Get the function call plugin.
    $function_call = $this->functionCallManager->createInstance('ai_agent:get_current_content_entity_values');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $function_call);

    // Set the function call context.
    $function_call->setContextValue('entity_type', 'node');
    $function_call->setContextValue('entity_id', $this->articleOne);
    $function_call->setContextValue('field_names', []);

    // Execute the function call with the 'article' content type.
    $function_call->execute();
    $result = $function_call->getReadableOutput();

    // Assert the result is a string and contains the expected values.
    $this->assertIsString($result);
    $this->assertStringContainsString('Test Article 1', $result);
    $this->assertStringContainsString('This is the body of test article 1.', $result);
    $this->assertStringContainsString('nid', $result);
    $this->assertStringContainsString((string) $this->articleOne, $result);
    $this->assertStringContainsString('title', $result);
    $this->assertStringContainsString('body', $result);
    $this->assertStringContainsString('created', $result);
    $this->assertStringContainsString('changed', $result);
    $this->assertStringContainsString('status', $result);
    $this->assertStringContainsString('uid', $result);
    $this->assertStringContainsString('langcode', $result);
  }

  /**
   * Test to get article 1 as a anonymous user.
   */
  public function testGetContentTypeInfoAsAnonymousUser(): void {
    // Set the current user to the anonymous user.
    $this->currentUser->setAccount($this->anonymousUser);

    // Get the function call plugin.
    $function_call = $this->functionCallManager->createInstance('ai_agent:get_current_content_entity_values');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $function_call);

    // Set the function call context.
    $function_call->setContextValue('entity_type', 'node');
    $function_call->setContextValue('entity_id', $this->articleOne);
    $function_call->setContextValue('field_names', ['nid', 'title', 'body']);

    // Should throw an exception as anonymous user cannot access the article.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Access denied to the entity.');
    $function_call->execute();
  }

  /**
   * Test to get article 1 as a user with view published content.
   */
  public function testGetContentTypeInfoAsArticleViewer(): void {
    // Set the current user to the article viewer.
    $this->currentUser->setAccount($this->articleViewer);

    // Get the function call plugin.
    $function_call = $this->functionCallManager->createInstance('ai_agent:get_current_content_entity_values');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $function_call);

    // Set the function call context.
    $function_call->setContextValue('entity_type', 'node');
    $function_call->setContextValue('entity_id', $this->articleOne);
    $function_call->setContextValue('field_names', ['nid', 'title', 'body']);

    // Execute the function call with the 'article' content type.
    $function_call->execute();
    $result = $function_call->getReadableOutput();

    // Assert the result is a string and contains the expected values.
    $this->assertIsString($result);
    $this->assertStringContainsString('Test Article 1', $result);
    $this->assertStringContainsString('This is the body of test article 1.', $result);
    $this->assertStringContainsString('title', $result);
    $this->assertStringContainsString('body', $result);
    $this->assertStringContainsString('nid', $result);
    $this->assertStringContainsString((string) $this->articleOne, $result);
  }

  /**
   * Test to get article 2 and unpublish as a user with view published content.
   */
  public function testGetContentTypeInfoAsArticleViewerUnpublished(): void {
    // Set the current user to the article viewer.
    $this->currentUser->setAccount($this->articleViewer);

    // Get the function call plugin.
    $function_call = $this->functionCallManager->createInstance('ai_agent:get_current_content_entity_values');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $function_call);

    // Set the function call context.
    $function_call->setContextValue('entity_type', 'node');
    $function_call->setContextValue('entity_id', $this->unpublishedArticle);
    $function_call->setContextValue('field_names', ['nid', 'title', 'body']);

    // Should throw an exception as the article is unpublished.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Access denied to the entity.');
    $function_call->execute();
  }

  /**
   * Test to get the unpublished article as admin.
   */
  public function testGetUnpublishedAsAdmin(): void {
    // Set the current user to the admin user.
    $this->currentUser->setAccount($this->adminUser);

    // Get the function call plugin.
    $function_call = $this->functionCallManager->createInstance('ai_agent:get_current_content_entity_values');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $function_call);

    // Set the function call context.
    $function_call->setContextValue('entity_type', 'node');
    $function_call->setContextValue('entity_id', $this->unpublishedArticle);
    $function_call->setContextValue('field_names', ['nid', 'title', 'body']);

    // Execute the function call with the 'article' content type.
    $function_call->execute();
    $result = $function_call->getReadableOutput();

    // Assert the result is a string and contains the expected values.
    $this->assertIsString($result);
    $this->assertStringContainsString('Unpublished Article', $result);
    $this->assertStringContainsString('This is the body of unpublished article.', $result);
    $this->assertStringContainsString('title', $result);
    $this->assertStringContainsString('body', $result);
    $this->assertStringContainsString('nid', $result);
    $this->assertStringContainsString((string) $this->unpublishedArticle, $result);
  }

}
