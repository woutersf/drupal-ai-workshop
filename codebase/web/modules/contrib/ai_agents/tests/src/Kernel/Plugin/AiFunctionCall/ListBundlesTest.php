<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the ListBundlesTest class.
 *
 * @group ai_agents
 */
#[Group('ai_agents')]
final class ListBundlesTest extends KernelTestBase {

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
    'media',
    'image',
    'file',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install required modules.
    $this->container->get('module_installer')->install(self::$modules);
    $this->installEntitySchema('user');
    $this->installEntitySchema('media');

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

    // Create an image media type.
    $media_type = $this->entityTypeManager->getStorage('media_type')->create([
      'id' => 'image',
      'label' => 'Image',
      'source' => 'image',
      'status' => 1,
    ]);
    $media_type->save();

    // Create an audio media type.
    $media_type = $this->entityTypeManager->getStorage('media_type')->create([
      'id' => 'audio',
      'label' => 'Audio',
      'source' => 'audio_file',
      'status' => 1,
    ]);
    $media_type->save();

    // Create an article node type.
    $node_type = $this->entityTypeManager->getStorage('node_type')->create([
      'type' => 'article',
      'name' => 'Article',
      'description' => 'A basic article content type.',
      'status' => 1,
    ]);
    $node_type->save();
  }

  /**
   * List media bundles as admin.
   */
  public function testToListMediaBundlesAsAdmin(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the list bundles tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_bundles');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the entity type context value to 'media'.
    $tool->setContextValue('entity_type', 'media');

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the field information.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('Entity Type, Bundle, Readable Name', $result, 'The header is present in the result.');
    $this->assertStringContainsString('media, image, Image', $result, 'The image media type is present in the result.');
    $this->assertStringContainsString('media, audio, Audio', $result, 'The audio media type is present in the result.');
  }

  /**
   * List media bundles as normal user.
   */
  public function testToListMediaBundlesAsNormalUser(): void {
    // Make sure that the current user is a normal user.
    $this->currentUser->setAccount($this->normalUser);
    // Get the list bundles tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_bundles');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Set the entity type context value to 'media'.
    $tool->setContextValue('entity_type', 'media');
    // Expect an exception because normal users should not have access.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('You do not have permission to access this tool.');

    // Execute the tool.
    $tool->execute();
  }

  /**
   * List all bundles as admin.
   */
  public function testToListAllBundlesAsAdmin(): void {
    // Make sure that the current user is an admin.
    $this->currentUser->setAccount($this->adminUser);
    // Get the list bundles tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:list_bundles');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the field information.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('Entity Type, Bundle, Readable Name', $result, 'The header is present in the result.');
    $this->assertStringContainsString('media, image, Image', $result, 'The image media type is present in the result.');
    $this->assertStringContainsString('media, audio, Audio', $result, 'The audio media type is present in the result.');
    $this->assertStringContainsString('node, article, Article', $result, 'The article node type is present in the result.');
  }

}
