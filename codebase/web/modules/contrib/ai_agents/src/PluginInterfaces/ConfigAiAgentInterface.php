<?php

namespace Drupal\ai_agents\PluginInterfaces;

/**
 * Interface for the config AI Agent extras.
 */
interface ConfigAiAgentInterface extends AiAgentInterface {

  /**
   * Get token contexts.
   *
   * @return array
   *   The tokens.
   */
  public function getTokenContexts(): array;

  /**
   * Set token contexts.
   *
   * @param array $tokens
   *   The tokens to add or override.
   */
  public function setTokenContexts(array $tokens): void;

  /**
   * Set looped.
   *
   * This is used to set the looped property of the agent and can control if
   * you want to do step by step tracing or not.
   *
   * @param bool $enabled
   *   The looped property.
   */
  public function setLooped(bool $enabled);

  /**
   * Get the system prompt.
   *
   * This is used to get the system prompt of the agent. This is used to
   * display the system prompt when needed.
   */
  public function getSystemPrompt();

  /**
   * Get the tool results.
   *
   * This is used to get the tool results of the agent. This is used to
   * display the tool results when needed.
   *
   * @param bool $recursive
   *   If TRUE, the tool results will be fetched recursively for any sub agent.
   *
   * @return array
   *   The tool results.
   */
  public function getToolResults(bool $recursive = FALSE): array;

  /**
   * Get the tool results for a specific tool.
   *
   * This is used to get the tool results of the agent for a specific tool.
   *
   * @param string $plugin_id
   *   The plugin_id of the tool to get the results for.
   * @param bool $recursive
   *   If TRUE, the tool results will be fetched recursively for any sub agent.
   *
   * @return array
   *   The tool results for the specific tool.
   */
  public function getToolResultsByPluginId(string $plugin_id, bool $recursive = FALSE): array;

  /**
   * Get the tool results for a specific class name.
   *
   * Is used to get the tool results of the agent for a specific class name.
   *
   * @param string $class_name
   *   The class name of the tool to get the results for.
   * @param bool $recursive
   *   If TRUE, the tool results will be fetched recursively for any sub agent.
   *
   * @return array
   *   The tool results for the specific class name.
   */
  public function getToolResultsByClassName(string $class_name, bool $recursive = FALSE): array;

  /**
   * Gets the chat history.
   *
   * This is used to get the chat history of the agent. This is used to
   * display the chat history when needed.
   *
   * @return array
   *   The chat history.
   */
  public function getChatHistory(): array;

  /**
   * Set the chat history.
   *
   * This is used to set the chat history of the agent. This is used to
   * display the chat history when needed.
   *
   * @param array $history
   *   The chat history.
   */
  public function setChatHistory(array $history): void;

  /**
   * If the agent is finished.
   *
   * This is used to check if the agent is finished. This is used to
   * determine if the agent has completed its task or not.
   */
  public function isFinished(): bool;

  /**
   * To array.
   *
   * This is used to get the agent as an array. This is used to
   * get the agent from the configuration, so we can store it temporarily
   * between requests.
   */
  public function toArray(): array;

  /**
   * From array.
   *
   * This is used to set the agent from an array. This is used to
   * set the agent from the configuration. This is used to
   * restore the agent from the configuration, so we can store it temporarily
   * between requests.
   */
  public function fromArray(array $data): void;

}
