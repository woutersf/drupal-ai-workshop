<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the GetContentTypeInfoTest class.
 *
 * @group ai_agents
 */
#[Group('ai_agents')]
final class GetContentTypeInfoTest extends KernelTestBase {

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
   * Special user with content type view permissions.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $contentTypeViewUser;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

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
    $this->entityFieldManager = $this->container->get('entity_field.manager');

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

    // Create a role that has the permission to create content types.
    /** @var \Drupal\user\Entity\UserRole $role */
    $role = $this->entityTypeManager->getStorage('user_role')->create([
      'id' => 'content_type_creator',
      'label' => 'Content Type Creator',
    ]);
    $role->grantPermission('administer content types');
    $role->save();

    // Create a user with the content type creation permissions.
    $this->contentTypeViewUser = $this->entityTypeManager->getStorage('user')->create([
      'name' => 'content_type_creator',
      'mail' => 'content@example.com',
      'status' => 1,
      'roles' => ['content_type_creator'],
    ]);
    $this->contentTypeViewUser->save();

    // Create an article content type to ensure the system is ready for testing.
    $node_type_storage = $this->entityTypeManager->getStorage('node_type');
    $node_type_storage->create([
      'type' => 'article',
      'name' => 'Article',
      'description' => 'An article content type for testing.',
      'new_revision' => TRUE,
      'preview_mode' => FALSE,
      'display_submitted' => TRUE,
    ])->save();
    $fields = $this->entityFieldManager->getFieldDefinitions('node', 'article');
    $fields['sticky']->getConfig('article')->setDefaultValue(TRUE)->save();
    $fields['promote']->getConfig('article')->setDefaultValue(TRUE)->save();
    $fields['status']->getConfig('article')->setDefaultValue(FALSE)->save();
  }

  /**
   * Tests the GetContentTypeInfo function call as admin.
   */
  public function testGetContentTypeInfo(): void {
    // Set the current user to the admin user.
    $this->currentUser->setAccount($this->adminUser);

    // Get the function call plugin.
    $function_call = $this->functionCallManager->createInstance('ai_agent:get_content_type_info');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $function_call);

    // Set the function call context.
    $function_call->setContextValue('node_type', 'article');

    // Execute the function call with the 'article' content type.
    $function_call->execute();
    $result = $function_call->getReadableOutput();

    // Assert that the result is an string and contains expected keys.
    $this->assertIsString($result);
    $this->assertStringContainsString('Article - dataname: article', $result);
    $this->assertStringContainsString('Description: An article content type for testing.', $result);
    $this->assertStringContainsString('New revision: true', $result);
    $this->assertStringContainsString('Preview mode: false', $result);
    $this->assertStringContainsString('Display submitted: true', $result);
    $this->assertStringContainsString('Published by default: false', $result);
    $this->assertStringContainsString('Promoted to front page by default: true', $result);
    $this->assertStringContainsString('Sticky enabled by default: true', $result);
  }

  /**
   * Try to get info about a non-existing content type.
   */
  public function testGetNonExistingContentTypeInfo(): void {
    // Set the current user to the admin user.
    $this->currentUser->setAccount($this->adminUser);

    // Get the function call plugin.
    $function_call = $this->functionCallManager->createInstance('ai_agent:get_content_type_info');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $function_call);

    // Set the function call context for a non-existing content type.
    $function_call->setContextValue('node_type', 'non_existing_type');

    // Execute the function call with a non-existing content type.
    $function_call->execute();
    $result = $function_call->getReadableOutput();

    // Assert that the result is an empty string or contains an error message.
    $this->assertIsString($result);
    $this->assertStringContainsString('Node type with data name "non_existing_type" does not exist.', $result);
  }

  /**
   * Tests the GetContentTypeInfo function call as a normal user.
   */
  public function testGetContentTypeInfoAsNormalUser(): void {
    // Set the current user to the normal user.
    $this->currentUser->setAccount($this->normalUser);

    // Get the function call plugin.
    $function_call = $this->functionCallManager->createInstance('ai_agent:get_content_type_info');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $function_call);

    // Set the function call context.
    $function_call->setContextValue('node_type', 'article');

    // Make sure to catch the exception for insufficient permissions.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('You do not have permission to administer content types.');
    // Execute the function call with the 'article' content type.
    $function_call->execute();
  }

  /**
   * Tests the GetContentTypeInfo function call as a user with permissions.
   */
  public function testGetContentTypeInfoAsContentTypeViewUser(): void {
    // Set the current user to the content type view user.
    $this->currentUser->setAccount($this->contentTypeViewUser);

    // Get the function call plugin.
    $function_call = $this->functionCallManager->createInstance('ai_agent:get_content_type_info');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $function_call);

    // Set the function call context.
    $function_call->setContextValue('node_type', 'article');

    // Execute the function call with the 'article' content type.
    $function_call->execute();
    $result = $function_call->getReadableOutput();

    // Assert that the result is an string and contains expected keys.
    $this->assertIsString($result);
    $this->assertStringContainsString('Article - dataname: article', $result);
    $this->assertStringContainsString('Description: An article content type for testing.', $result);
    $this->assertStringContainsString('New revision: true', $result);
    $this->assertStringContainsString('Preview mode: false', $result);
    $this->assertStringContainsString('Display submitted: true', $result);
    $this->assertStringContainsString('Published by default: false', $result);
    $this->assertStringContainsString('Promoted to front page by default: true', $result);
    $this->assertStringContainsString('Sticky enabled by default: true', $result);
  }

}
