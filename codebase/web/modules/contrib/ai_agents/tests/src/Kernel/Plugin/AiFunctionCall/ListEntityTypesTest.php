<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the ListEntityTypesTest class.
 *
 * @group ai_agents
 */
#[Group('ai_agents')]
final class ListEntityTypesTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'user',
    'ai',
    'ai_agents',
    'system',
    'taxonomy',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install required modules.
    $this->container->get('module_installer')->install(static::$modules);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_vocabulary');
    $this->installEntitySchema('taxonomy_term');

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
  }

  /**
   * Test listing the entity types as an admin.
   */
  public function testEditListAllEntityTypesAsAdmin(): void {
    $this->currentUser->setAccount($this->adminUser);

    $tool = $this->functionCallManager->createInstance('ai_agent:list_entity_types');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    $tool->execute();
    $result = $tool->getReadableOutput();

    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('node', $result, 'The node entity type is listed.');
    $this->assertStringContainsString('user', $result, 'The user entity type is listed.');
    $this->assertStringContainsString('taxonomy_vocabulary', $result, 'The taxonomy vocabulary entity type is listed.');
    $this->assertStringContainsString('taxonomy_term', $result, 'The taxonomy term entity type is listed.');
  }

  /**
   * Test listing the entity types as a normal user.
   */
  public function testEditListAllEntityTypesAsNormalUser(): void {
    $this->currentUser->setAccount($this->normalUser);

    $tool = $this->functionCallManager->createInstance('ai_agent:list_entity_types');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    // The user should not have permission to list entity types.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('You do not have permission to list entity types.');

    $tool->execute();
  }

  /**
   * Test listing only content entity types.
   */
  public function testListContentEntityTypes(): void {
    $this->currentUser->setAccount($this->adminUser);

    $tool = $this->functionCallManager->createInstance('ai_agent:list_entity_types');
    $tool->setContextValue('type_of_entity', 'content');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    $tool->execute();
    $result = $tool->getReadableOutput();

    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('node', $result, 'The node entity type is listed.');
    $this->assertStringContainsString('user', $result, 'The user entity type is listed.');
    $this->assertStringNotContainsString('taxonomy_vocabulary', $result, 'The taxonomy vocabulary entity type is not listed.');
    $this->assertStringContainsString('taxonomy_term', $result, 'The taxonomy term entity type is listed.');
  }

  /**
   * Test listing only config entity types.
   */
  public function testListConfigEntityTypes(): void {
    $this->currentUser->setAccount($this->adminUser);

    $tool = $this->functionCallManager->createInstance('ai_agent:list_entity_types');
    $tool->setContextValue('type_of_entity', 'config');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    $tool->execute();
    $result = $tool->getReadableOutput();

    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('taxonomy_vocabulary', $result, 'The taxonomy vocabulary entity type is listed.');
    $this->assertStringNotContainsString('taxonomy_term', $result, 'The taxonomy term entity type is not listed.');
  }

}
