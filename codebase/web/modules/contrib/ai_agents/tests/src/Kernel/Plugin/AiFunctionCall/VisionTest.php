<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the VisionTest class.
 *
 * @group ai_agents
 */
#[Group('ai_agents')]
final class VisionTest extends KernelTestBase {

  /**
   * The function call plugin manager.
   *
   * @var \Drupal\ai\Plugin\AiFunctionCall\AiFunctionCallManager
   */
  protected $functionCallManager;

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
    'file',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install required modules.
    $this->container->get('module_installer')->install(self::$modules);

    // Set up the dependencies.
    $this->functionCallManager = $this->container->get('plugin.manager.ai.function_calls');
  }

  /**
   * Check so vision fails when no image model is setup.
   */
  public function testNoImageModel(): void {
    // Get the list field types form tool.
    $tool = $this->functionCallManager->createInstance('ai_agent:vision');
    // Make sure it exists and is a ExecutableFunctionCallInterface.
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // We create a new vocabulary, so we need to set the context.
    $tool->setContextValue('prompt', 'Describe this image.');
    $tool->setContextValue('image_id', '1');

    // Execute the tool.
    $tool->execute();

    // Get the output.
    $result = $tool->getReadableOutput();
    // Check so the result is string and contains the error message.
    $this->assertIsString($result, 'The result is a string.');
    $this->assertStringContainsString('No AI provider is configured for the vision operation.', $result, 'The error message is correct.');
  }

}
