<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the ListFieldTypesTest class.
 *
 * @group ai_agents
 */
#[Group('ai_agents')]
final class ListFieldTypesTest extends KernelTestBase {

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
   * List all field types as admin.
   */
  public function testListAllFieldTypesAsAdmin(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the list field types form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_field_types');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the field information.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('\Drupal\Core\Field\FieldItemList', $result, 'The result contains the FieldItemList class.');
    $this->assertStringContainsString('label: UUID', $result, 'The result contains the UUID field label.');
    $this->assertStringContainsString('uuid:', $result, 'The result contains the UUID field type.');
  }

  /**
   * List all field types as normal user.
   */
  public function testToGetDisplayFormAsNormalUser(): void {
    // Make sure that the current user is a normal user.
    $this->currentUser->setAccount($this->normalUser);
    // Get the list field types form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_field_types');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    // Should throw an exception because normal users do not have permissions.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('You do not have permission to create or edit field configs.');

    // Execute the tool.
    $tool->execute();
  }

  /**
   * List all field types as field creator user.
   */
  public function testListAllFieldTypesAsFieldCreatorUser(): void {
    // Make sure that the current user is a field creator user.
    $this->currentUser->setAccount($this->fieldCreatorUser);
    // Get the list field types form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_field_types');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the field information.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('\Drupal\Core\Field\FieldItemList', $result, 'The result contains the FieldItemList class.');
    $this->assertStringContainsString('label: UUID', $result, 'The result contains the UUID field label.');
    $this->assertStringContainsString('uuid:', $result, 'The result contains the UUID field type.');
  }

  /**
   * List string field types as admin.
   */
  public function testListStringFieldTypesAsAdmin(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the list field types form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_field_types');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    // Set the context values for field type and simple representation.
    $tool->setContextValue('field_type', 'string');

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the field information.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('id: string', $result, 'The result contains the string field type id.');
    $this->assertStringContainsString("label: 'Text (plain)'", $result, 'The result contains the label for string field type.');
    $this->assertStringContainsString('category: plain_text', $result, 'The result contains the category for string field type.');
    $this->assertStringNotContainsString('uuid:', $result, 'The result does not contain the UUID field type.');
  }

  /**
   * List all field types in simple representation as admin.
   */
  public function testListAllFieldTypesSimpleRepresentationAsAdmin(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the list field types form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_field_types');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    // Set the context value for simple representation.
    $tool->setContextValue('simple_representation', TRUE);

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the field information.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('string', $result, 'The result contains the string field type in simple representation.');
    $this->assertStringContainsString("Text (plain)", $result, 'The result contains the label for string field type in simple representation.');
    $this->assertStringNotContainsString('\Drupal\Core\Field\FieldItemList', $result, 'The result does not contain the FieldItemList class in simple representation.');
  }

}
