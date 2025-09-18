<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the ManipulateFieldConfigTest class.
 *
 * @group ai_agents
 */
#[Group('ai_agents')]
final class ManipulateFieldConfigTest extends KernelTestBase {

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
   * Special user with field creation permissions.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $fieldCreatorUser;

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

    // Create a role that can administer node fields.
    /** @var \Drupal\user\Entity\UserRole $role */
    $role = $this->entityTypeManager->getStorage('user_role')->create([
      'id' => 'field_creator',
      'label' => 'Field Creator',
    ]);
    $role->grantPermission('administer node fields');
    $role->save();
    // Create a user with the field creator role.
    $this->fieldCreatorUser = $this->entityTypeManager->getStorage('user')->create([
      'name' => 'field_creator_user',
      'mail' => 'field@example.com',
      'status' => 1,
      'roles' => ['field_creator'],
    ]);
    $this->fieldCreatorUser->save();

    // Create a node type called 'article'.
    $node_type = $this->entityTypeManager->getStorage('node_type')->create([
      'type' => 'article',
      'name' => 'Article',
      'description' => 'A test article content type.',
      'base' => 'node_content',
      'new_revision' => TRUE,
      'display_submitted' => FALSE,
    ]);
    $node_type->save();

    // Create a field called 'test_field' of type 'text_long'.
    $field_storage = $this->entityTypeManager->getStorage('field_storage_config')->create([
      'field_name' => 'test_field',
      'entity_type' => 'node',
      'type' => 'text_long',
      'cardinality' => 1,
    ]);
    $field_storage->save();
    // Create a field instance for the 'article' bundle.
    $field_instance = $this->entityTypeManager->getStorage('field_config')->create([
      'field_name' => 'test_field',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Test Field',
      'required' => FALSE,
      'settings' => [],
    ]);
    $field_instance->save();
  }

  /**
   * Tries to edit field config as admin.
   */
  public function testToEditFieldConfigAsAdmin(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the manipulate field config tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:manipulate_field_config');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values for the tool.
    $tool->setContextValue('type_of_operation', 'edit');
    $tool->setContextValue('field_name', 'test_field');
    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('bundle', 'article');
    // Change label of the field.
    $tool->setContextValue('label', 'Test Field 2');

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the field information.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString("label: 'Test Field 2'", $result, 'The field label was updated successfully.');

    // Load the field config to check if it was updated.
    /** @var \Drupal\field\FieldConfigInterface $field_config */
    $field_config = $this->entityTypeManager->getStorage('field_config')->load('node.article.test_field');
    $this->assertNotNull($field_config, 'The field config was created.');
    $this->assertEquals('Test Field 2', $field_config->getLabel(), 'The field label was updated successfully.');
  }

  /**
   * Tries to create field config as a user without permissions.
   */
  public function testToCreateFieldConfigAsUserWithoutPermissions(): void {
    // Make sure that the current user is a normal user.
    $this->currentUser->setAccount($this->normalUser);
    // Get the manipulate field config tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:manipulate_field_config');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values for the tool.
    $tool->setContextValue('type_of_operation', 'create');
    $tool->setContextValue('field_name', 'test_field');
    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('bundle', 'article');
    $tool->setContextValue('field_type', 'text_long');
    $tool->setContextValue('label', 'Test Field');

    // Should throw an exception because the user does not have permissions.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('You do not have permission to create or edit field configs.');

    // Execute the tool.
    $tool->execute();
  }

  /**
   * Tries to create field config as a user with permissions.
   */
  public function testToCreateFieldConfigAsUserWithPermissions(): void {
    // Make sure that the current user is a field creator user.
    $this->currentUser->setAccount($this->fieldCreatorUser);
    // Get the manipulate field config tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:manipulate_field_config');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values for the tool.
    $tool->setContextValue('type_of_operation', 'edit');
    $tool->setContextValue('field_name', 'test_field');
    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('bundle', 'article');
    $tool->setContextValue('label', 'New Test Field');

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the field information.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString("label: 'New Test Field'", $result, 'The field was created successfully.');

    // Load the field config to check if it was created.
    /** @var \Drupal\field\FieldConfigInterface $field_config */
    $field_config = $this->entityTypeManager->getStorage('field_config')->load('node.article.test_field');
    $this->assertNotNull($field_config, 'The field config was created.');
    $this->assertEquals('New Test Field', $field_config->getLabel(), 'The field label was set correctly.');
  }

  /**
   * Try to create a field config.
   */
  public function testToCreateFieldConfigWithInvalidFieldName(): void {
    // Make sure that the current user is a field creator user.
    $this->currentUser->setAccount($this->adminUser);
    // Get the manipulate field config tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:manipulate_field_config');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values for the tool.
    $tool->setContextValue('type_of_operation', 'create');
    $tool->setContextValue('field_name', 'cool_field');
    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('bundle', 'article');
    $tool->setContextValue('label', 'Cool Field');

    // Create a field storage config.
    $field_storage = $this->entityTypeManager->getStorage('field_storage_config')->create([
      'field_name' => 'cool_field',
      'entity_type' => 'node',
      'type' => 'text_long',
      'cardinality' => 1,
    ]);
    $field_storage->save();

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();

    // Check so the result is string and contains the field information.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString("field_name: cool_field", $result, 'The field name was set correctly.');
    $this->assertStringContainsString("label: 'Cool Field'", $result, 'The field label was set correctly.');
    $this->assertStringContainsString("entity_type: node", $result, 'The entity type was set correctly.');
    $this->assertStringContainsString("bundle: article", $result, 'The bundle was set correctly.');
  }

  /**
   * Try to edit with a faulty settings.
   */
  public function testToEditFieldConfigWithFaultySettings(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the manipulate field config tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:manipulate_field_config');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values for the tool.
    $tool->setContextValue('type_of_operation', 'edit');
    $tool->setContextValue('field_name', 'test_field');
    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('bundle', 'article');
    // Change the label of the field.
    $tool->setContextValue('label', 'Test Field 2');
    // Set faulty settings.
    $tool->setContextValue('settings', '{"invalid_json: "value"}');

    // Execute the tool.
    $tool->execute();

    // The error message should indicate that the settings are invalid.
    $result = $tool->getReadableOutput();
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('The settings could not be decoded. Please make sure it is a valid JSON string.', $result, 'The error message for invalid settings was returned.');

    // Load the field config to check that it was not updated.
    /** @var \Drupal\field\FieldConfigInterface $field_config */
    $field_config = $this->entityTypeManager->getStorage('field_config')->load('node.article.test_field');
    $this->assertNotNull($field_config, 'The field config was created.');
    $this->assertNotEquals('Test Field 2', $field_config->getLabel(), 'The field label was not updated due to invalid settings.');
  }

}
