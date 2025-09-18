<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the GetFieldConfigTest class.
 *
 * @group ai_agents
 */
#[Group('ai_agents')]
final class GetFieldConfigTest extends KernelTestBase {

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
      'settings' => ['max_length' => 500],
    ])->save();
  }

  /**
   * Get field config as admin.
   */
  public function testToGetFieldInformationAsAdmin(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the entity field config tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:get_field_config');
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
    $this->assertStringContainsString('field.storage.node.body', $result, 'The dependency for body is present in the result.');
    $this->assertStringContainsString('node.type.article', $result, 'The dependency for the article content type is present in the result.');
    $this->assertStringContainsString("description: ''", $result, 'The field description is present in the result.');
    $this->assertStringContainsString('field_name: body', $result, 'The field name is present in the result.');
    $this->assertStringContainsString('required: false', $result, 'The field required status is present in the result.');
    $this->assertStringContainsString('label: Body', $result, 'The field label is present in the result.');
    $this->assertStringContainsString('default_value: {  }', $result, 'The default value is present in the result.');
    // Should not get title information as we did not request it.
    $this->assertStringNotContainsString('field_name: title', $result, 'The title field information is not present in the result.');
  }

  /**
   * Get field config as normal user.
   */
  public function testToGetFieldInformationAsNormalUser(): void {
    // Make sure that the current user is a normal user.
    $this->currentUser->setAccount($this->normalUser);
    // Get the entity field config tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:get_field_config');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values to get the body field.
    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('bundle', 'article');
    $tool->setContextValue('field_name', 'body');

    // This will should throw an exception.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('You do not have permission to access this field configuration.');
    // Execute the tool.
    $tool->execute();
  }

}
