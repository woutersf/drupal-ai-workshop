<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the ListContentEntitiesTest class.
 *
 * @group ai_agents
 */
#[Group('ai_agents')]
final class ListContentEntitiesTest extends KernelTestBase {

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
   * Special user with permission to view published content.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $viewPublishContentUser;

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
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install required modules.
    $this->container->get('module_installer')->install(self::$modules);
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

    // Create a normal user account.
    $this->normalUser = $this->entityTypeManager->getStorage('user')->create([
      'name' => 'normal_user',
      'mail' => 'normal@example.com',
      'status' => 1,
      'roles' => ['authenticated'],
    ]);
    $this->normalUser->save();

    // Create a role that can view published content.
    /** @var \Drupal\user\Entity\UserRole $role */
    $role = $this->entityTypeManager->getStorage('user_role')->create([
      'id' => 'view_publish_content',
      'label' => 'View Published Content',
    ]);
    $role->grantPermission('access content');
    $role->save();

    // Create a role that can view published content.
    $this->viewPublishContentUser = $this->entityTypeManager->getStorage('user')->create([
      'name' => 'view_publish_content_user',
      'mail' => 'view@example.com',
      'status' => 1,
      'roles' => ['view_publish_content'],
    ]);
    $this->viewPublishContentUser->save();

    // Create an article content type.
    $this->container->get('entity_type.manager')->getStorage('node_type')->create([
      'type' => 'article',
      'name' => 'Article',
      'description' => 'An article content type.',
      'base' => 'node_content',
      'translatable' => FALSE,
    ])->save();

    // Create a body field for the article content type.
    $this->entityTypeManager->getStorage('field_storage_config')->create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'type' => 'text_long',
      'cardinality' => 1,
      'translatable' => FALSE,
      'settings' => ['max_length' => 500],
    ])->save();
    // Create the field configuration for the body field.
    $this->entityTypeManager->getStorage('field_config')->create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Body',
      'required' => FALSE,
      'settings' => [
        'max_length' => 500,
        'text_processing' => 1,
        'display_summary' => TRUE,
      ],
    ])->save();

    // Create an article.
    $article = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'article',
      'title' => 'Test Article',
      'body' => [
        'value' => 'This is the body of the test article.',
        'format' => 'basic_html',
      ],
      'created' => 1749637078,
    ]);
    $article->save();

    // Create another article that is created earlier.
    $article2 = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'article',
      'title' => 'Test Article 2',
      'body' => [
        'value' => 'This is the body of the second test article.',
        'format' => 'basic_html',
      ],
      'created' => 1749637077,
    ]);
    $article2->save();
  }

  /**
   * List content entities as admin user.
   */
  public function testListContentEntitiesAsAdmin(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the entity field config form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_content_entities');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values to get node information.
    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('bundle', 'article');
    $tool->setContextValue('amount', 10);
    $tool->setContextValue('offset', 0);

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the articles.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('Test Article', $result, 'The result contains the first article.');
    $this->assertStringContainsString('Test Article 2', $result, 'The result contains the second article.');
    $this->assertStringContainsString('This is the body of the test article.', $result, 'The result contains the body of the first article.');
    $this->assertStringContainsString('This is the body of the second test article.', $result, 'The result contains the body of the second article.');
    $this->assertStringContainsString('1749637078', $result, 'The result contains the created timestamp of the first article.');
    $this->assertStringContainsString('1749637077', $result, 'The result contains the created timestamp of the second article.');
  }

  /**
   * Make sure that you can't load a config entity.
   */
  public function testListConfigEntities(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the entity field config form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_content_entities');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values to get node information.
    $tool->setContextValue('entity_type', 'node_type');
    $tool->setContextValue('bundle', 'article');
    $tool->setContextValue('amount', 10);
    $tool->setContextValue('offset', 0);

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the articles.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('{  }', $result, 'The result contains no content entities found.');
  }

  /**
   * Make sure that you can't load a content entity as a normal user.
   */
  public function testListContentEntitiesAsNormalUser(): void {
    // Make sure that the current user is a normal user.
    $this->currentUser->setAccount($this->normalUser);
    // Get the entity field config form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_content_entities');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values to get node information.
    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('bundle', 'article');
    $tool->setContextValue('amount', 10);
    $tool->setContextValue('offset', 0);

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the articles.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('{  }', $result, 'The result contains no content entities found.');
  }

  /**
   * Make sure that limit works as expected.
   */
  public function testListContentEntitiesWithLimit(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the entity field config form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_content_entities');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values to get node information.
    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('bundle', 'article');
    $tool->setContextValue('amount', 1);
    $tool->setContextValue('offset', 0);

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains only one article.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('Test Article', $result, 'The result contains the first article.');
    $this->assertStringNotContainsString('Test Article 2', $result, 'The result does not contain the second article.');
  }

  /**
   * Make sure that offset works as expected.
   */
  public function testListContentEntitiesWithOffset(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the entity field config form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_content_entities');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values to get node information.
    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('bundle', 'article');
    $tool->setContextValue('amount', 1);
    $tool->setContextValue('offset', 1);

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains only one article.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('Test Article 2', $result, 'The result contains the second article.');
    $this->assertStringNotContainsString('1749637078', $result, 'The result does not contain the created timestamp of the first article.');
  }

  /**
   * Make sure that sorting works as expected.
   */
  public function testListContentEntitiesWithSorting(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the entity field config form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_content_entities');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values to get node information.
    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('bundle', 'article');
    $tool->setContextValue('amount', 1);
    $tool->setContextValue('offset', 0);
    $tool->setContextValue('sort_field', 'created');
    $tool->setContextValue('sort_order', 'DESC');

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the articles in correct order.
    $this->assertIsString($result, 'The result is a string.');
    // Only first article should be returned due to amount and date.
    $this->assertStringContainsString('Test Article', $result, 'The result contains the first article.');
    $this->assertStringContainsString('1749637078', $result, 'The result contains the created timestamp of the first article.');
  }

  /**
   * Make sure that you can load a content entity as view content user.
   */
  public function testListContentEntitiesAsViewPublishedContentUser(): void {
    // Make sure that the current user is a view published content user.
    $this->currentUser->setAccount($this->viewPublishContentUser);
    // Get the entity field config form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_content_entities');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values to get node information.
    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('bundle', 'article');
    $tool->setContextValue('amount', 10);
    $tool->setContextValue('offset', 0);

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the articles.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('Test Article', $result, 'The result contains the first article.');
    $this->assertStringContainsString('Test Article 2', $result, 'The result contains the second article.');
    $this->assertStringContainsString('This is the body of the test article.', $result, 'The result contains the body of the first article.');
    $this->assertStringContainsString('This is the body of the second test article.', $result, 'The result contains the body of the second article.');
  }

  /**
   * Only get nid and title of the content entities.
   */
  public function testListContentEntitiesWithFields(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the entity field config form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_content_entities');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values to get node information.
    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('bundle', 'article');
    $tool->setContextValue('amount', 10);
    $tool->setContextValue('offset', 0);
    $tool->setContextValue('fields', ['nid', 'title']);

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the articles.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('Test Article', $result, 'The result contains the first article title.');
    $this->assertStringContainsString('Test Article 2', $result, 'The result contains the second article title.');
    $this->assertStringContainsString('nid:', $result, 'The result contains the nid of the first article.');
    $this->assertStringNotContainsString('This is the body of the test article.', $result, 'The result does not contain the body of the first article.');
    $this->assertStringNotContainsString('This is the body of the second test article.', $result, 'The result does not contain the body of the second article.');
  }

}
