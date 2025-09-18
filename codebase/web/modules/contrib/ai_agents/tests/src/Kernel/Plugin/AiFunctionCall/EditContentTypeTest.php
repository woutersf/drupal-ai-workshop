<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the EditContentTypeTest class.
 *
 * @group ai_agents
 */
#[Group('ai_agents')]
final class EditContentTypeTest extends KernelTestBase {

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
   * Special user with content type creation permissions.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $contentTypeCreatorUser;

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

    // Create a role that has the permission to create content types.
    /** @var \Drupal\user\Entity\UserRole $role */
    $role = $this->entityTypeManager->getStorage('user_role')->create([
      'id' => 'content_type_creator',
      'label' => 'Content Type Creator',
    ]);
    $role->grantPermission('administer content types');
    $role->save();

    // Create a user with the content type creation permissions.
    $this->contentTypeCreatorUser = $this->entityTypeManager->getStorage('user')->create([
      'name' => 'content_type_creator',
      'mail' => 'content@example.com',
      'status' => 1,
      'roles' => ['content_type_creator'],
    ]);
    $this->contentTypeCreatorUser->save();

    // Create an article content type to ensure the system is ready for testing.
    $node_type_storage = $this->entityTypeManager->getStorage('node_type');
    $node_type_storage->create([
      'type' => 'article',
      'name' => 'Article',
      'description' => 'An article content type for testing.',
      'new_revision' => TRUE,
      'preview_mode' => FALSE,
      'display_submitted' => TRUE,
      'published_by_default' => TRUE,
      'promoted_by_default' => FALSE,
      'sticky_by_default' => FALSE,
    ])->save();
  }

  /**
   * Test editing the title/description of an existing content type as an admin.
   */
  public function testEditContentTypeAsAdmin(): void {
    $this->currentUser->setAccount($this->adminUser);

    $tool = $this->functionCallManager->createInstance('ai_agent:edit_content_type');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    // Set the context values for the function call.
    $tool->setContextValue('data_name', 'article');
    $tool->setContextValue('label', 'Updated Article');
    $tool->setContextValue('description', 'Updated description for the article content type.');

    $result = $tool->execute();
    // Load the article content type to verify the changes.
    $node_type_storage = $this->entityTypeManager->getStorage('node_type');
    /** @var \Drupal\node\Entity\NodeType $article_type */
    $article_type = $node_type_storage->load('article');
    $this->assertEquals('Updated Article', $article_type->label(), 'The content type label was updated successfully.');
    $this->assertEquals('Updated description for the article content type.', $article_type->getDescription(), 'The content type description was updated successfully.');
  }

  /**
   * The a normal user tries to edit a content type.
   */
  public function testEditContentTypeAsNormalUser(): void {
    $this->currentUser->setAccount($this->normalUser);

    $tool = $this->functionCallManager->createInstance('ai_agent:edit_content_type');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    // Set the context values for the function call.
    $tool->setContextValue('data_name', 'article');
    $tool->setContextValue('label', 'Updated Article');
    $tool->setContextValue('description', 'Updated description for the article content type.');

    // Expect an exception due to insufficient permissions.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('The current user does not have the right permissions to run this tool.');
    $tool->execute();
  }

  /**
   * Test editing a content type with a user that permissions.
   */
  public function testEditContentTypeAsContentTypeCreator(): void {
    $this->currentUser->setAccount($this->contentTypeCreatorUser);

    $tool = $this->functionCallManager->createInstance('ai_agent:edit_content_type');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    // Set the context values for the function call.
    $tool->setContextValue('data_name', 'article');
    $tool->setContextValue('label', 'Updated Article');
    $tool->setContextValue('description', 'Updated description for the article content type.');

    $result = $tool->execute();
    // Load the article content type to verify the changes.
    $node_type_storage = $this->entityTypeManager->getStorage('node_type');
    /** @var \Drupal\node\Entity\NodeType $article_type */
    $article_type = $node_type_storage->load('article');
    $this->assertEquals('Updated Article', $article_type->label(), 'The content type label was updated successfully.');
    $this->assertEquals('Updated description for the article content type.', $article_type->getDescription(), 'The content type description was updated successfully.');
  }

}
