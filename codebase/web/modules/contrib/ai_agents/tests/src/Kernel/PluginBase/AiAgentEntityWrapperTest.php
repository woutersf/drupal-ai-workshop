<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\PluginBase;

use Drupal\ai_agents\Plugin\AiFunctionCall\AiAgentWrapper;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_agents\Plugin\AiFunctionCall\GetEntityFieldInformation;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests for the AiAgentEntityWrapperTest class.
 *
 * @group ai_agents
 */
#[Group('ai_agents')]
final class AiAgentEntityWrapperTest extends KernelTestBase {

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
    'key',
    'ai',
    'ai_agents',
    'system',
    'field',
    'link',
    'text',
    'field_ui',
    'ai_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->functionCallManager = $this->container->get('plugin.manager.ai.function_calls');

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    // Setup the configurations for AI Agents.
    $this->installConfig('ai_agents');

    // Install the Drupal CMS Assistant config.
    $data = Yaml::parseFile(__DIR__ . '/../../../assets/config/ai_agents.ai_agent.drupal_cms_assistant.yml');
    $agent = $this->container->get('entity_type.manager')
      ->getStorage('ai_agent')
      ->create($data);
    $agent->save();
  }

  /**
   * Test to check if the field agent can be loaded.
   */
  public function testFieldAgentCanBeLoaded(): void {
    $this->assertTrue(TRUE, 'Field agent can be loaded successfully.');
    $agent = $this->functionCallManager->createInstance('ai_agents::ai_agent::field_agent_triage');
    $this->assertNotNull($agent, 'Field agent instance is created successfully.');
  }

  /**
   * Load an agent with some tools and check if we can get tool results.
   */
  public function testAgentWithToolsCanGetResults(): void {
    $sub_agent = $this->functionCallManager->createInstance('ai_agents::ai_agent::field_agent_triage');
    $data = Yaml::parseFile(__DIR__ . '/../../../assets/agent_states/group_1_field_agent_info_1.yml');
    $sub_agent->fromArray($data);

    $this->assertNotEmpty($sub_agent->getToolResults(), 'Agent with tools can get results successfully.');
  }

  /**
   * Load an agent with a sub-agent, that has all run and check results.
   */
  public function testAgentWithSubAgentCanGetResults(): void {
    $agent = $this->functionCallManager->createInstance('ai_agents::ai_agent::drupal_cms_assistant');
    $data = Yaml::parseFile(__DIR__ . '/../../../assets/agent_states/group_1_assistant_1.yml');
    $agent->fromArray($data);

    $results = $agent->getToolResults(TRUE);
    // Make sure that we have 2 results.
    $this->assertCount(2, $results, 'Agent with sub-agent can get results recursively.');
  }

  /**
   * Load an agent without a sub-agent and check results.
   */
  public function testAgentWithoutSubAgentCanGetResults(): void {
    $agent = $this->functionCallManager->createInstance('ai_agents::ai_agent::drupal_cms_assistant');
    $data = Yaml::parseFile(__DIR__ . '/../../../assets/agent_states/group_1_assistant_1.yml');
    $agent->fromArray($data);

    // Get the field agent, without recursive.
    $results = $agent->getToolResults(FALSE);
    // Make sure that we have 1 results.
    $this->assertCount(1, $results, 'Agent without sub-agent can get results successfully.');
    // Result should be of class AiAgentWrapper.
    $this->assertInstanceOf(AiAgentWrapper::class, $results[0], 'Result is of type AiAgentWrapper.');
  }

  /**
   * Load an agent with a sub-agent and only get the specific tool results.
   */
  public function testAgentWithSubAgentCanGetSpecificToolResults(): void {
    $agent = $this->functionCallManager->createInstance('ai_agents::ai_agent::drupal_cms_assistant');
    $data = Yaml::parseFile(__DIR__ . '/../../../assets/agent_states/group_1_assistant_1.yml');
    $agent->fromArray($data);

    // Get the get entity field information recursively.
    $results = $agent->getToolResultsByPluginId('ai_agent:get_entity_field_information', TRUE);
    // Make sure that we have 1 results.
    $this->assertCount(1, $results, 'Got specific tool results from agent with sub-agent successfully.');
    // Result should be of class GetEntityFieldInformation.
    $this->assertInstanceOf(GetEntityFieldInformation::class, $results[0], 'Result is of type GetEntityFieldInformation.');
    // Check so the text exists.
    $this->assertStringContainsString('field_id: nid', $results[0]->getReadableOutput(), 'Result contains the expected text.');
  }

  /**
   * Load an agent with a sub-agent only get a specific class of tool results.
   */
  public function testAgentWithSubAgentCanGetSpecificClassToolResults(): void {
    $agent = $this->functionCallManager->createInstance('ai_agents::ai_agent::drupal_cms_assistant');
    $data = Yaml::parseFile(__DIR__ . '/../../../assets/agent_states/group_1_assistant_1.yml');
    $agent->fromArray($data);

    // Get the get entity field information recursively.
    $results = $agent->getToolResultsByClassName(GetEntityFieldInformation::class, TRUE);
    // Make sure that we have 1 results.
    $this->assertCount(1, $results, 'Got specific class tool results from agent with sub-agent successfully.');
    // Result should be of class GetEntityFieldInformation.
    $this->assertInstanceOf(GetEntityFieldInformation::class, $results[0], 'Result is of type GetEntityFieldInformation.');
    // Check so the text exists.
    $this->assertStringContainsString('field_id: nid', $results[0]->getReadableOutput(), 'Result contains the expected text.');
  }

  /**
   * Test so it works to get a tool result via plugin id without sub-agent.
   */
  public function testAgentWithoutSubAgentCanGetSpecificToolResults(): void {
    $agent = $this->functionCallManager->createInstance('ai_agents::ai_agent::drupal_cms_assistant');
    $data = Yaml::parseFile(__DIR__ . '/../../../assets/agent_states/group_1_assistant_1.yml');
    $agent->fromArray($data);

    // Get the field agent, without recursive.
    $results = $agent->getToolResultsByPluginId('ai_agents::ai_agent::field_agent_triage');
    // Make sure that we have 1 results.
    $this->assertCount(1, $results, 'Got specific tool results from agent without sub-agent successfully.');
    // Result should be of class AiAgentWrapper.
    $this->assertInstanceOf(AiAgentWrapper::class, $results[0], 'Result is of type AiAgentWrapper.');
  }

  /**
   * Test so it works to get a tool result via class name without sub-agent.
   */
  public function testAgentWithoutSubAgentCanGetSpecificClassToolResults(): void {
    $agent = $this->functionCallManager->createInstance('ai_agents::ai_agent::drupal_cms_assistant');
    $data = Yaml::parseFile(__DIR__ . '/../../../assets/agent_states/group_1_assistant_1.yml');
    $agent->fromArray($data);

    // Get the field agent, without recursive.
    $results = $agent->getToolResultsByClassName(AiAgentWrapper::class, FALSE);
    // Make sure that we have 1 results.
    $this->assertCount(1, $results, 'Got specific class tool results from agent without sub-agent successfully.');
    // Result should be of class AiAgentWrapper.
    $this->assertInstanceOf(AiAgentWrapper::class, $results[0], 'Result is of type AiAgentWrapper.');
  }

}
