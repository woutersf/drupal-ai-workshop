<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the GetFieldValuesAndContextTest class.
 *
 * @group ai_agents
 */
#[Group('ai_agents')]
final class GetFieldValuesAndContextTest extends KernelTestBase {

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
    'taxonomy',
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
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('taxonomy_vocabulary');

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

    // Create a tags category/vocabulary.
    $vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->create([
      'name' => 'Tags',
      'vid' => 'tags',
    ]);
    $vocabulary->save();
  }

  /**
   * Get field information as admin.
   */
  public function testToGetFieldInformationAsAdmin(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the entity field information tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:get_field_values_and_context');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values to get the description field.
    $tool->setContextValue('entity_type', 'taxonomy_term');
    $tool->setContextValue('bundle', 'tags');
    $tool->setContextValue('field_name', 'description');

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the field information.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('field_name: description', $result, 'The field name is present in the result.');
    $this->assertStringContainsString('default_value: {  }', $result, 'The field default value is present in the result.');
    $this->assertStringContainsString('target_entity_type: null', $result, 'The target entity type is present in the result.');
    $this->assertStringContainsString('values_to_fill:', $result, 'The values to fill section is present in the result.');
    // Should not get label information as we did not request it.
    $this->assertStringNotContainsString('field_name: label', $result, 'The label field information is not present in the result.');
  }

  /**
   * Get field information as normal user.
   */
  public function testToGetFieldInformationAsNormalUser(): void {
    // Make sure that the current user is a normal user.
    $this->currentUser->setAccount($this->normalUser);
    // Get the entity field information form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:get_field_values_and_context');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values to get the description field.
    $tool->setContextValue('entity_type', 'taxonomy_term');
    $tool->setContextValue('bundle', 'tags');
    $tool->setContextValue('field_name', 'description');

    // Expect an exception because normal users should not have access.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('You do not have permission to access this tool.');

    // Execute the tool.
    $tool->execute();
  }

}
