<?php

namespace Drupal\ai_agents\Event;

use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * This can be used to log the responses for each loop.
 */
class AgentResponseEvent extends Event {

  // The event name.
  const EVENT_NAME = 'ai_agents.response';

  /**
   * Constructs the object.
   *
   * @param \Drupal\ai_agents\PluginInterfaces\AiAgentInterface $agent
   *   The agent.
   * @param string $systemPrompt
   *   The system prompt.
   * @param string $agentId
   *   The agent id.
   * @param string $instructions
   *   The instructions.
   * @param array $chatHistory
   *   The chat messages.
   * @param \Drupal\ai\OperationType\Chat\ChatOutput $response
   *   The response.
   * @param int $loopCount
   *   The loop count.
   */
  public function __construct(
    protected AiAgentInterface $agent,
    protected string $systemPrompt,
    protected string $agentId,
    protected string $instructions,
    protected array $chatHistory,
    protected ChatOutput $response,
    protected int $loopCount,
  ) {
  }

  /**
   * Gets the agent.
   *
   * @return \Drupal\ai_agents\PluginInterfaces\AiAgentInterface
   *   The agent.
   */
  public function getAgent(): AiAgentInterface {
    return $this->agent;
  }

  /**
   * Gets the system prompt.
   *
   * @return string
   *   The system prompt.
   */
  public function getSystemPrompt(): string {
    return $this->systemPrompt;
  }

  /**
   * Gets the agent id.
   *
   * @return string
   *   The agent id.
   */
  public function getAgentId(): string {
    return $this->agentId;
  }

  /**
   * Gets the instructions.
   *
   * @return string
   *   The instructions.
   */
  public function getInstructions(): string {
    return $this->instructions;
  }

  /**
   * Gets the chat history.
   *
   * @return array
   *   The chat history.
   */
  public function getChatHistory(): array {
    return $this->chatHistory;
  }

  /**
   * Gets the response.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The response.
   */
  public function getResponse(): ChatOutput {
    return $this->response;
  }

  /**
   * Gets the loop count.
   *
   * @return int
   *   The loop count.
   */
  public function getLoopCount(): int {
    return $this->loopCount;
  }

}
