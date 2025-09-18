<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the ListConfigEntitiesTest class.
 *
 * @group ai_agents
 */
#[Group('ai_agents')]
final class ListConfigEntitiesTest extends KernelTestBase {

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

    // Create a description field for the article content type.
    $this->entityTypeManager->getStorage('field_storage_config')->create([
      'field_name' => 'description',
      'entity_type' => 'node',
      'type' => 'text',
      'cardinality' => 1,
      'translatable' => FALSE,
      'settings' => ['max_length' => 255],
    ])->save();
    // Create the field configuration for the description field.
    $this->entityTypeManager->getStorage('field_config')->create([
      'field_name' => 'description',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Description',
      'required' => FALSE,
      'settings' => [
        'max_length' => 255,
        'text_processing' => 1,
        'display_summary' => TRUE,
      ],
    ])->save();
  }

  /**
   * List config entities as admin user.
   */
  public function testListConfigEntitiesAsAdmin(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the entity field config form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_config_entities');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values to get field config information.
    $tool->setContextValue('entity_type', 'field_config');
    $tool->setContextValue('amount', 10);
    $tool->setContextValue('offset', 0);

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the field information.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('node.article.body', $result, 'The result contains the body field id.');
    $this->assertStringContainsString('node.article.description', $result, 'The result contains the description field id.');
  }

  /**
   * List config entities as normal user.
   */
  public function testListConfigEntitiesAsNormalUser(): void {
    // Make sure that the current user is a normal user.
    $this->currentUser->setAccount($this->normalUser);
    // Get the entity field config form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_config_entities');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values to get field config information.
    $tool->setContextValue('entity_type', 'field_config');
    $tool->setContextValue('amount', 10);
    $tool->setContextValue('offset', 0);

    // Expect an exception because the normal user does not have permission.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('You do not have permission to list config entities.');

    // Execute the tool.
    $tool->execute();
  }

  /**
   * List only one config entity.
   */
  public function testListSingleConfigEntity(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the entity field config form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_config_entities');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values to get field config information.
    $tool->setContextValue('entity_type', 'field_config');
    $tool->setContextValue('amount', 1);
    $tool->setContextValue('offset', 0);

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the field information.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('node.article.body', $result, 'The result contains the body field id.');
    $this->assertStringNotContainsString('node.article.description', $result, 'The result does not contain the description field id.');
  }

  /**
   * List id and label of config entities.
   */
  public function testListConfigEntityIdAndLabel(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the entity field config form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_config_entities');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values to get field config information.
    $tool->setContextValue('entity_type', 'field_config');
    $tool->setContextValue('amount', 10);
    $tool->setContextValue('offset', 0);
    $tool->setContextValue('fields', ['id', 'label']);

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the field information.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('node.article.body', $result, 'The result contains the body field id.');
    $this->assertStringContainsString('Body', $result, 'The result contains the body field label.');
    $this->assertStringContainsString('node.article.description', $result, 'The result contains the description field id.');
    $this->assertStringContainsString('Description', $result, 'The result contains the description field label.');
  }

  /**
   * Test so the offset and amount context values work.
   */
  public function testListConfigEntitiesWithOffsetAndAmount(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the entity field config form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_config_entities');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the context values to get field config information.
    $tool->setContextValue('entity_type', 'field_config');
    $tool->setContextValue('amount', 1);
    $tool->setContextValue('offset', 1);

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the field information.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('node.article.description', $result, 'The result contains the description field id.');
    $this->assertStringNotContainsString('node.article.body', $result, 'The result does not contain the body field id.');
  }

}
