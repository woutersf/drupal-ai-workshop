<?php

namespace Drupal\ai_agents\PluginInterfaces;

use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;

/**
 * AI Agent Function Interface.
 */
interface AiAgentFunctionInterface extends FunctionCallInterface {

  /**
   * Get the agent.
   *
   * @return \Drupal\ai_agents\PluginInterfaces\AiAgentInterface
   *   The agent.
   */
  public function getAgent(): AiAgentInterface;

  /**
   * Set the agent.
   *
   * @param \Drupal\ai_agents\PluginInterfaces\AiAgentInterface $agent
   *   The agent.
   */
  public function setAgent(AiAgentInterface $agent);

}
