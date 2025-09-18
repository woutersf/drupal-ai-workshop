<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the ManipulateFieldDisplayFormTest class.
 *
 * @group ai_agents
 */
#[Group('ai_agents')]
final class ManipulateFieldDisplayFormTest extends KernelTestBase {

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
    $role->grantPermission('administer node form display');
    $role->grantPermission('administer node display');
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

    // Set the default display for the 'article' bundle.
    $this->entityDisplayRepository->getFormDisplay('node', 'article', 'default')
      ->setComponent('test_field', [
        'type' => 'text_textarea',
        'weight' => 0,
      ])
      ->save();
    $this->entityDisplayRepository->getViewDisplay('node', 'article', 'default')
      ->setComponent('test_field', [
        'type' => 'text_default',
        'weight' => 0,
      ])
      ->save();
  }

  /**
   * Tries to edit field config as admin.
   */
  public function testToEditFieldConfigAsAdmin(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the manipulate field config tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:manipulate_field_display_form');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values for the tool.
    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('bundle', 'article');
    $tool->setContextValue('field_name', 'test_field');
    $tool->setContextValue('type_of_display', 'form');
    $tool->setContextValue('type', 'text_textarea');
    // Change the weight of the field.
    $tool->setContextValue('weight', 10);

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the field information.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString("The form display for node.article.test_field has been updated.", $result, 'The field display was updated successfully.');

    // Load the field display configuration.
    $form_display = $this->entityDisplayRepository->getFormDisplay('node', 'article', 'default');
    $component = $form_display->getComponent('test_field');
    // Check if the weight and label were updated.
    $this->assertEquals(10, $component['weight'], 'The weight of the field was updated successfully.');
  }

  /**
   * Tries to edit field config as normal user, should throw exception.
   */
  public function testToEditFieldConfigAsNormalUser(): void {
    // Make sure that the current user is a normal user.
    $this->currentUser->setAccount($this->normalUser);
    // Get the manipulate field config tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:manipulate_field_display_form');
    // Set the context values for the tool.
    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('bundle', 'article');
    $tool->setContextValue('field_name', 'test_field');
    $tool->setContextValue('type_of_display', 'form');
    $tool->setContextValue('type', 'text_textarea');

    // Execute the tool and expect an exception.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('You do not have permission to create or edit form display for node.');
    $tool->execute();
  }

  /**
   * Tries to edit field config as field creator user.
   */
  public function testToEditFieldConfigAsFieldCreatorUser(): void {
    // Make sure that the current user is a field creator user.
    $this->currentUser->setAccount($this->fieldCreatorUser);
    // Get the manipulate field config tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:manipulate_field_display_form');
    // Set the context values for the tool.
    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('bundle', 'article');
    $tool->setContextValue('field_name', 'test_field');
    $tool->setContextValue('type_of_display', 'form');
    $tool->setContextValue('type', 'text_textarea');

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the field information.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString("The form display for node.article.test_field has been updated.", $result, 'The field display was updated successfully.');
  }

  /**
   * Tries to edit field config with invalid JSON settings.
   */
  public function testToEditFieldConfigWithInvalidJsonSettings(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the manipulate field config tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:manipulate_field_display_form');
    // Set the context values for the tool.
    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('bundle', 'article');
    $tool->setContextValue('field_name', 'test_field');
    $tool->setContextValue('type_of_display', 'form');
    $tool->setContextValue('type', 'text_textarea');
    // Set invalid JSON settings.
    $tool->setContextValue('settings', '{invalid_json}');

    $tool->execute();
    // Get the output.
    $result = $tool->getReadableOutput();

    // A readable error message should be returned.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('The settings are not valid JSON.', $result, 'The error message for invalid JSON settings was returned.');
  }

  /**
   * Tries to edit field config with non-existing entity type.
   */
  public function testToEditFieldConfigWithNonExistingEntityType(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the manipulate field config tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:manipulate_field_display_form');
    // Set the context values for the tool.
    $tool->setContextValue('entity_type', 'non_existing_entity');
    $tool->setContextValue('bundle', 'article');
    $tool->setContextValue('field_name', 'test_field');
    $tool->setContextValue('type_of_display', 'form');
    $tool->setContextValue('type', 'text_textarea');

    // Execute the tool and expect an exception.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('The "non_existing_entity" entity type does not exist.');
    $tool->execute();

  }

  /**
   * Tries to edit display as the field creator user.
   */
  public function testToEditDisplayAsFieldCreatorUser(): void {
    // Make sure that the current user is a field creator user.
    $this->currentUser->setAccount($this->fieldCreatorUser);
    // Get the manipulate field config tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:manipulate_field_display_form');
    // Set the context values for the tool.
    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('bundle', 'article');
    $tool->setContextValue('field_name', 'test_field');
    $tool->setContextValue('type_of_display', 'display');
    $tool->setContextValue('type', 'text_default');

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the field information.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString("The display for node.article.test_field has been updated.", $result, 'The field display was updated successfully.');
  }

  /**
   * Tries to edit display as normal user, should throw exception.
   */
  public function testToEditDisplayAsNormalUser(): void {
    // Make sure that the current user is a normal user.
    $this->currentUser->setAccount($this->normalUser);
    // Get the manipulate field config tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:manipulate_field_display_form');
    // Set the context values for the tool.
    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('bundle', 'article');
    $tool->setContextValue('field_name', 'test_field');
    $tool->setContextValue('type_of_display', 'display');
    $tool->setContextValue('type', 'text_default');

    // Execute the tool and expect an exception.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('You do not have permission to create or edit display for node.');
    $tool->execute();
  }

}
