<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the CreateFieldStorageConfigTest class.
 *
 * @group ai_agents
 */
#[Group('ai_agents')]
final class CreateFieldStorageConfigTest extends KernelTestBase {

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
  }

  /**
   * Test create content type as admin.
   */
  public function testCreateContentTypeAsAdmin(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the content type tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:create_field_storage_config');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values.
    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('field_name', 'test_field');
    $tool->setContextValue('field_type', 'text');
    $tool->setContextValue('cardinality', '1');
    $tool->setContextValue('translatable', FALSE);
    $tool->setContextValue('settings', json_encode(['max_length' => 255]));

    // Execute the tool.
    $tool->execute();
    // Check if the field storage config was created.
    /** @var \Drupal\field\Entity\FieldStorageConfig $field_storage_config */
    $field_storage_config = $this->entityTypeManager->getStorage('field_storage_config')->load('node.test_field');
    $this->assertNotNull($field_storage_config, 'The field storage config was created successfully.');
    $this->assertEquals('text', $field_storage_config->getType(), 'The field type is correct.');
    $this->assertEquals(1, $field_storage_config->getCardinality(), 'The field cardinality is correct.');
    $this->assertEquals(['max_length' => 255], $field_storage_config->getSettings(), 'The field settings are correct.');
    $this->assertEquals('node.test_field', $field_storage_config->getLabel(), 'The field label is correct.');
  }

  /**
   * Test create content type as normal user.
   */
  public function testCreateContentTypeAsNormalUser(): void {
    // Make sure that the current user is a normal user.
    $this->currentUser->setAccount($this->normalUser);
    // Get the content type tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:create_field_storage_config');
    // Set the context values.
    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('field_name', 'test_field');
    $tool->setContextValue('field_type', 'text');
    $tool->setContextValue('cardinality', '1');
    $tool->setContextValue('translatable', FALSE);
    $tool->setContextValue('settings', json_encode(['max_length' => 255]));
    // Try to execute the tool.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('You do not have permission to create field storage configs.');
    $tool->execute();
  }

  /**
   * Test create content type as field storage creator.
   */
  public function testCreateContentTypeAsFieldStorageCreator(): void {
    // Make sure that the current user is a field storage creator.
    $this->currentUser->setAccount($this->fieldStorageCreator);
    // Get the content type tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:create_field_storage_config');
    // Set the context values.
    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('field_name', 'test_field');
    $tool->setContextValue('field_type', 'text');
    $tool->setContextValue('cardinality', '1');
    $tool->setContextValue('translatable', FALSE);
    $tool->setContextValue('settings', json_encode(['max_length' => 255]));
    // Execute the tool.
    $tool->execute();
    // Check if the field storage config was created.
    /** @var \Drupal\field\Entity\FieldStorageConfig $field_storage_config */
    $field_storage_config = $this->entityTypeManager->getStorage('field_storage_config')->load('node.test_field');
    $this->assertNotNull($field_storage_config, 'The field storage config was created successfully.');
    $this->assertEquals('text', $field_storage_config->getType(), 'The field type is correct.');
    $this->assertEquals(1, $field_storage_config->getCardinality(), 'The field cardinality is correct.');
    $this->assertEquals(['max_length' => 255], $field_storage_config->getSettings(), 'The field settings are correct.');
    $this->assertEquals('node.test_field', $field_storage_config->getLabel(), 'The field label is correct.');
  }

  /**
   * Test to create a field storage with broken json settings.
   */
  public function testCreateFieldStorageWithBrokenJsonSettings(): void {
    // Make sure that the current user is a field storage creator.
    $this->currentUser->setAccount($this->fieldStorageCreator);
    // Get the content type tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:create_field_storage_config');
    // Set the context values with broken JSON.
    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('field_name', 'test_field_broken_json');
    $tool->setContextValue('field_type', 'text');
    $tool->setContextValue('cardinality', '1');
    $tool->setContextValue('translatable', FALSE);
    $tool->setContextValue('settings', '[broken json string');
    // Try to execute the tool.
    $tool->execute();
    // The field storage config should not be created.
    $field_storage_config = $this->entityTypeManager->getStorage('field_storage_config')->load('node.test_field_broken_json');
    $this->assertNull($field_storage_config, 'The field storage config was not created due to broken JSON settings.');
  }

}
