<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the GetConfigSchemaTest class.
 *
 * @group ai_agents
 */
#[Group('ai_agents')]
final class GetConfigSchemaTest extends KernelTestBase {

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
   * Special user with config read usage.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $configReadUser;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'user', 'ai', 'ai_agents', 'system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install required modules.
    $this->container->get('module_installer')->install(['user', 'node', 'ai', 'ai_agents', 'system']);
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

    // Create a role that has the permission to administer configs.
    /** @var \Drupal\user\Entity\UserRole $role */
    $role = $this->entityTypeManager->getStorage('user_role')->create([
      'id' => 'config_admin',
      'label' => 'Configuration Administrator',
    ]);
    $role->grantPermission('administer permissions');
    $role->save();

    // Create a user with the content type creation permissions.
    $this->configReadUser = $this->entityTypeManager->getStorage('user')->create([
      'name' => 'config_admin',
      'mail' => 'config@example.com',
      'status' => 1,
      'roles' => ['config_admin'],
    ]);
    $this->configReadUser->save();
  }

  /**
   * Tests the GetConfigSchema function call as an admin user.
   */
  public function testGetConfigSchemaAsAdmin() {
    $this->currentUser->setAccount($this->adminUser);

    $function_call = $this->functionCallManager->createInstance('ai_agent:get_config_schema');
    $function_call->setContextValue('schema_id', 'system.site');

    $function_call->execute();
    $textual_result = $function_call->getReadableOutput();

    $this->assertIsString($textual_result);
    $this->assertStringContainsString('admin_compact_mode', $textual_result);
    $this->assertStringContainsString('weight_select_max', $textual_result);
  }

  /**
   * Tests the GetConfigSchema function call as a normal user.
   */
  public function testGetConfigSchemaAsNormalUser() {
    $this->currentUser->setAccount($this->normalUser);

    $function_call = $this->functionCallManager->createInstance('ai_agent:get_config_schema');
    $function_call->setContextValue('schema_id', 'system.site');

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('You do not have permission to access this function.');

    $function_call->execute();
  }

  /**
   * Tests the GetConfigSchema function call as a user with config permissions.
   */
  public function testGetConfigSchemaAsConfigReadUser() {
    $this->currentUser->setAccount($this->configReadUser);

    $function_call = $this->functionCallManager->createInstance('ai_agent:get_config_schema');
    $function_call->setContextValue('schema_id', 'system.site');

    $function_call->execute();
    $textual_result = $function_call->getReadableOutput();

    $this->assertIsString($textual_result);
    $this->assertStringContainsString('admin_compact_mode', $textual_result);
    $this->assertStringContainsString('weight_select_max', $textual_result);
  }

}
