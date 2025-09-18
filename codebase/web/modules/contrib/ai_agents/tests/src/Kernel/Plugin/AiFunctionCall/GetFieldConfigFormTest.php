<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the GetFieldConfigFormTest class.
 *
 * @group ai_agents
 */
#[Group('ai_agents')]
final class GetFieldConfigFormTest extends KernelTestBase {

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
  }

  /**
   * Get field config form as admin.
   */
  public function testToGetFieldInformationAsAdmin(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the entity field config form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:get_field_config_form');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values to get the body field.
    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('bundle', 'article');
    $tool->setContextValue('field_name', 'body');

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the field information.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('allowed_formats', $result, 'The result contains the allowed formats.');
    $this->assertStringContainsString("#type': checkboxes", $result, 'The result contains the checkboxes type.');
  }

  /**
   * Get field config form as normal user.
   */
  public function testToGetFieldInformationAsNormalUser(): void {
    // Make sure that the current user is a normal user.
    $this->currentUser->setAccount($this->normalUser);
    // Get the entity field config form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:get_field_config_form');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values to get the body field.
    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('bundle', 'article');
    $tool->setContextValue('field_name', 'body');

    // Expect an exception.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('You do not have permission to access this function.');

    // Execute the tool.
    $tool->execute();
  }

  /**
   * Try to get for a field type as admin.
   */
  public function testToGetFieldTypeAsAdmin(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the entity field config form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:get_field_config_form');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values to get the body field.
    $tool->setContextValue('field_type', 'text_long');

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the field type information.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('text_long', $result, 'The field type is present in the result.');
  }

  /**
   * Try to get for a field type as normal user.
   */
  public function testToGetFieldTypeAsNormalUser(): void {
    // Make sure that the current user is a normal user.
    $this->currentUser->setAccount($this->normalUser);
    // Get the entity field config form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:get_field_config_form');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values to get the body field.
    $tool->setContextValue('field_type', 'text_long');

    // Expect an exception.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('You do not have permission to access this function.');

    // Execute the tool.
    $tool->execute();
  }

  /**
   * Try to get with just entity type, but no bundle or field name.
   */
  public function testToGetFieldInformationWithEntityTypeOnly(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the entity field config form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:get_field_config_form');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values to get the body field.
    $tool->setContextValue('entity_type', 'node');

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains a textual error.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('You need to set the entity, bundle and field name if you are not using field type.', $result, 'The error text message was returned.');
  }

}
