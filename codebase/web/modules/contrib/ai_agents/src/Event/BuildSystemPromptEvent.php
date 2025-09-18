<?php

namespace Drupal\ai_agents\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * This can change tokens or the base prompt before its being built.
 */
class BuildSystemPromptEvent extends Event {

  // The event name.
  const EVENT_NAME = 'ai_agents.pre_system_prompt';

  /**
   * The system prompt.
   *
   * @var string
   */
  protected $systemPrompt;

  /**
   * The secured system prompt.
   *
   * @var string
   */
  protected $securedSystemPrompt;

  /**
   * The agent id.
   *
   * @var array
   */
  protected $agentId;

  /**
   * The tokens.
   *
   * @var array
   */
  protected $tokens;

  /**
   * Constructs the object.
   *
   * @param string $system_prompt
   *   The system prompt (Agent Instructions).
   * @param string $agent_id
   *   The agent id.
   * @param array $tokens
   *   The tokens.
   * @param string $secured_system_prompt
   *   The secured system prompt.
   */
  public function __construct(string $system_prompt, string $agent_id, array $tokens, string $secured_system_prompt = '') {
    $this->systemPrompt = $system_prompt;
    $this->agentId = $agent_id;
    $this->tokens = $tokens;
    $this->securedSystemPrompt = $secured_system_prompt;
  }

  /**
   * Gets the system prompt.
   *
   * @return string
   *   The system prompt.
   */
  public function getSystemPrompt() {
    return $this->systemPrompt;
  }

  /**
   * Sets the system prompt.
   *
   * @param string $system_prompt
   *   The system prompt.
   */
  public function setSystemPrompt(string $system_prompt) {
    $this->systemPrompt = $system_prompt;
  }

  /**
   * Gets the secured system prompt.
   *
   * @return string
   *   The secured system prompt.
   */
  public function getSecuredSystemPrompt(): string {
    return $this->securedSystemPrompt;
  }

  /**
   * Sets the secured system prompt.
   *
   * @param string $secured_system_prompt
   *   The secured system prompt.
   */
  public function setSecuredSystemPrompt(string $secured_system_prompt): void {
    $this->securedSystemPrompt = $secured_system_prompt;
  }

  /**
   * Gets the agent id.
   *
   * @return string
   *   The agent id.
   */
  public function getAgentId() {
    return $this->agentId;
  }

  /**
   * Gets the tokens.
   *
   * @return array
   *   The tokens.
   */
  public function getTokens() {
    return $this->tokens;
  }

  /**
   * Sets the tokens.
   *
   * @param array $tokens
   *   The tokens.
   */
  public function setTokens(array $tokens) {
    $this->tokens = $tokens;
  }

  /**
   * Set one token.
   *
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value.
   */
  public function setToken(string $key, mixed $value) {
    $this->tokens[$key] = $value;
  }

}
