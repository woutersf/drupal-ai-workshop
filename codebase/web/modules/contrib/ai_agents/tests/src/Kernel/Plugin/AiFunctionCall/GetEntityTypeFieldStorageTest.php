<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the GetEntityTypeFieldStorageTest class.
 *
 * @group ai_agents
 */
#[Group('ai_agents')]
final class GetEntityTypeFieldStorageTest extends KernelTestBase {

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
   * Special user with field storage creation permissions.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $fieldStorageCreator;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'user', 'ai', 'ai_agents', 'system', 'field', 'link', 'text', 'field_ui'];

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

    // Create a role that has the permission to create field storage.
    /** @var \Drupal\user\Entity\UserRole $role */
    $role = $this->entityTypeManager->getStorage('user_role')->create([
      'id' => 'field_storage_creator',
      'label' => 'Field Storage Creator',
    ]);
    $role->grantPermission('administer node fields');
    $role->save();

    // Create a user with the content type creation permissions.
    $this->fieldStorageCreator = $this->entityTypeManager->getStorage('user')->create([
      'name' => 'field_storage_creator',
      'mail' => 'field@example.com',
      'status' => 1,
      'roles' => ['field_storage_creator'],
    ]);
    $this->fieldStorageCreator->save();

    // Create and article content type for testing.
    $node_type_storage = $this->entityTypeManager->getStorage('node_type');
    $node_type_storage->create([
      'type' => 'article',
      'name' => 'Article',
      'description' => 'An article content type for testing.',
      'new_revision' => TRUE,
      'preview_mode' => FALSE,
      'display_submitted' => TRUE,
    ])->save();

    // Add a body field to the article content type.
    $field_storage = $this->entityTypeManager->getStorage('field_storage_config')->create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'type' => 'text',
      'cardinality' => 1,
      'translatable' => FALSE,
      'settings' => ['max_length' => 500],
    ]);
    $field_storage->save();
    $field_instance = $this->entityTypeManager->getStorage('field_config')->create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Body',
      'required' => FALSE,
      'settings' => ['display_summary' => TRUE],
    ]);
    $field_instance->save();
  }

  /**
   * Test create entity type field storage as admin.
   */
  public function testCreateContentTypeAsAdmin(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the entity type field storage tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:get_entity_type_field_storage');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values.
    $tool->setContextValue('entity_type', 'node');

    // Execute the tool.
    $tool->execute();
    // Get the string results.
    $result = $tool->getReadableOutput();
    // Check if the result is a string.
    $this->assertIsString($result, 'The result is a string.');
    // Check if the result contains the body field.
    $this->assertStringContainsString('node, body', $result, 'The result contains the body field storage.');
  }

  /**
   * Test create entity type field storage as normal user.
   */
  public function testCreateContentTypeAsNormalUser(): void {
    // Make sure that the current user is a normal user.
    $this->currentUser->setAccount($this->normalUser);
    // Get the entity type field storage tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:get_entity_type_field_storage');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values.
    $tool->setContextValue('entity_type', 'node');

    // Assume exception is thrown for normal user.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('You do not have permission to view field storage configs.');
    // Execute the tool.
    $tool->execute();
  }

}
