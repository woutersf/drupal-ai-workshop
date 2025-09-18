<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the CreateContentTypeTest class.
 *
 * @group ai_agents
 */
#[Group('ai_agents')]
final class CreateContentTypeTest extends KernelTestBase {

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
   * Special user with content type creation permissions.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $contentTypeCreatorUser;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'user', 'ai', 'ai_agents', 'system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install required modules.
    $this->container->get('module_installer')->install(['user', 'node', 'ai', 'ai_agents', 'system']);
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

    // Create a role that has the permission to create content types.
    /** @var \Drupal\user\Entity\UserRole $role */
    $role = $this->entityTypeManager->getStorage('user_role')->create([
      'id' => 'content_type_creator',
      'label' => 'Content Type Creator',
    ]);
    $role->grantPermission('administer content types');
    $role->save();

    // Create a user with the content type creation permissions.
    $this->contentTypeCreatorUser = $this->entityTypeManager->getStorage('user')->create([
      'name' => 'content_type_creator',
      'mail' => 'content@example.com',
      'status' => 1,
      'roles' => ['content_type_creator'],
    ]);
    $this->contentTypeCreatorUser->save();
  }

  /**
   * Test create content type as admin.
   */
  public function testCreateContentTypeAsAdmin(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the content type tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:create_content_type');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values.
    $tool->setContextValue('data_name', 'test_content_type');
    $tool->setContextValue('label', 'Test Content Type');
    $tool->setContextValue('description', 'This is a test content type.');
    $tool->setContextValue('new_revision', TRUE);
    $tool->setContextValue('preview_mode', FALSE);
    $tool->setContextValue('display_submitted', TRUE);
    $tool->setContextValue('published_by_default', TRUE);
    $tool->setContextValue('promoted_by_default', FALSE);
    $tool->setContextValue('sticky_by_default', FALSE);
    // Execute the tool.
    $tool->execute();
    // Check if the content type was created.
    $node_type_storage = $this->container->get('entity_type.manager')->getStorage('node_type');
    $node_type = $node_type_storage->load('test_content_type');
    $this->assertNotNull($node_type, 'The content type was created successfully.');
    $this->assertEquals('Test Content Type', $node_type->label(), 'The content type label is correct.');
    $this->assertEquals('This is a test content type.', $node_type->getDescription(), 'The content type description is correct.');
  }

  /**
   * Test create content type as normal user.
   */
  public function testCreateContentTypeAsNormalUser(): void {
    // Make sure that the current user is a normal user.
    $this->currentUser->setAccount($this->normalUser);
    // Get the content type tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:create_content_type');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values.
    $tool->setContextValue('data_name', 'test_content_type');
    $tool->setContextValue('label', 'Test Content Type');
    $tool->setContextValue('description', 'This is a test content type.');
    $tool->setContextValue('new_revision', TRUE);
    $tool->setContextValue('preview_mode', FALSE);
    $tool->setContextValue('display_submitted', TRUE);
    $tool->setContextValue('published_by_default', TRUE);
    $tool->setContextValue('promoted_by_default', FALSE);
    $tool->setContextValue('sticky_by_default', FALSE);

    // Expect an exception due to insufficient permissions.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('The current user does not have the right permissions to run this tool.');

    // Execute the tool.
    $tool->execute();
    // Check that the content type was not created.
    $node_type_storage = $this->container->get('entity_type.manager')->getStorage('node_type');
    $node_type = $node_type_storage->load('test_content_type');
    $this->assertNull($node_type, 'The content type was not created due to insufficient permissions.');
  }

  /**
   * Test create content type as content type creator user.
   */
  public function testCreateContentTypeAsContentTypeCreator(): void {
    // Make sure that the current user is a content type creator.
    $this->currentUser->setAccount($this->contentTypeCreatorUser);
    // Get the content type tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:create_content_type');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values.
    $tool->setContextValue('data_name', 'test_content_type');
    $tool->setContextValue('label', 'Test Content Type');
    $tool->setContextValue('description', 'This is a test content type.');
    $tool->setContextValue('new_revision', TRUE);
    $tool->setContextValue('preview_mode', FALSE);
    $tool->setContextValue('display_submitted', TRUE);
    $tool->setContextValue('published_by_default', TRUE);
    $tool->setContextValue('promoted_by_default', FALSE);
    $tool->setContextValue('sticky_by_default', FALSE);

    // Execute the tool.
    $tool->execute();

    // Check if the content type was created.
    $node_type_storage = $this->container->get('entity_type.manager')->getStorage('node_type');
    $node_type = $node_type_storage->load('test_content_type');
    $this->assertNotNull($node_type, 'The content type was created successfully.');
    $this->assertEquals('Test Content Type', $node_type->label(), 'The content type label is correct.');
    $this->assertEquals('This is a test content type.', $node_type->getDescription(), 'The content type description is correct.');
  }

}
