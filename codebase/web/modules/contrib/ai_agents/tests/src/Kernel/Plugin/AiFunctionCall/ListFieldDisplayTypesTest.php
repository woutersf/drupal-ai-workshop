<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the ListFieldDisplayTypesTest class.
 *
 * @group ai_agents
 */
#[Group('ai_agents')]
final class ListFieldDisplayTypesTest extends KernelTestBase {

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
  }

  /**
   * List all form field display types as admin.
   */
  public function testToGetDisplayFormAsAdmin(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the list field display types form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_field_display_types');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    $tool->setContextValue('type_of_display', 'form');

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the field information.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('text_textarea', $result, 'The result contains the text_textarea field display type.');
    $this->assertStringContainsString('text_textarea_with_summary', $result, 'The result contains the text_textarea_with_summary field display type.');
    $this->assertStringContainsString('entity_reference_autocomplete', $result, 'The result contains the entity_reference_autocomplete field display type.');
    $this->assertStringContainsString('Autocomplete (Tags style)', $result, 'The result contains the Autocomplete (Tags style) field display type.');
  }

  /**
   * List all view field display types as admin.
   */
  public function testToGetDisplayViewAsAdmin(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the list field display types view tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_field_display_types');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    $tool->setContextValue('type_of_display', 'display');

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the field information.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('entity_reference_label', $result, 'The result contains the entity_reference_label field display type.');
    $this->assertStringContainsString('basic_string', $result, 'The result contains the basic_string field display type.');
    $this->assertStringContainsString('timestamp', $result, 'The result contains the timestamp field display type.');
    $this->assertStringContainsString('Plain text', $result, 'The result contains the Plain Text field display type.');
  }

  /**
   * List all form field display types as normal user.
   */
  public function testToGetDisplayFormAsNormalUser(): void {
    // Make sure that the current user is a normal user.
    $this->currentUser->setAccount($this->normalUser);
    // Get the list field display types form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_field_display_types');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    $tool->setContextValue('type_of_display', 'form');

    // Should throw an exception because normal users do not have permissions.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('You do not have permission to create or edit field configs.');

    // Execute the tool.
    $tool->execute();
  }

  /**
   * List all form field display types as field creator user.
   */
  public function testToGetDisplayFormAsFieldCreatorUser(): void {
    // Make sure that the current user is a field creator user.
    $this->currentUser->setAccount($this->fieldCreatorUser);
    // Get the list field display types form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_field_display_types');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    $tool->setContextValue('type_of_display', 'form');

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the field information.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('text_textarea', $result, 'The result contains the text_textarea field display type.');
  }

  /**
   * List all view field display types for text_long as admin.
   */
  public function testToGetDisplayViewForTextLongAsAdmin(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the list field display types view tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_field_display_types');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    $tool->setContextValue('type_of_display', 'display');
    $tool->setContextValue('field_type', 'text_long');

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the field information.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('text_default', $result, 'The result contains the text_default field display type.');
    $this->assertStringContainsString('text_trimmed', $result, 'The result contains the text_trimmed field display type.');
    $this->assertStringContainsString('Trimmed', $result, 'The result contains the Trimmed field display type.');
    $this->assertStringNotContainsString('entity_reference_label', $result, 'The result does not contain the entity_reference_label field display type.');
  }

  /**
   * List all form field display types for text_long as admin.
   */
  public function testToGetDisplayFormForTextLongAsAdmin(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the list field display types form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_field_display_types');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    $tool->setContextValue('type_of_display', 'form');
    $tool->setContextValue('field_type', 'text_long');

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the field information.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('text_textarea', $result, 'The result contains the text_textarea field display type.');
    $this->assertStringContainsString('Text area (multiple rows)', $result, 'The result contains the Text area (multiple rows) field display type.');
    $this->assertStringNotContainsString('entity_reference_autocomplete', $result, 'The result does not contain the entity_reference_autocomplete field display type.');
  }

}
