<?php

namespace Drupal\ai\OperationType\Chat;

use Drupal\ai\OperationType\OutputInterface;

/**
 * Data transfer output object for chat output.
 */
class ChatOutput implements OutputInterface {

  /**
   * The chat message.
   *
   * @var \Drupal\ai\OperationType\Chat\ChatMessage|\Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface
   */
  private ChatMessage|StreamedChatMessageIteratorInterface $normalized;

  /**
   * The raw output from the AI provider.
   *
   * @var mixed
   */
  private mixed $rawOutput;

  /**
   * The metadata from the AI provider.
   *
   * @var mixed
   */
  private mixed $metadata;

  /**
   * The amount of input tokens from the AI provider.
   */
  private ?int $inputTokensUsage = NULL;

  /**
   * The amount of output tokens from the AI provider.
   */
  private ?int $outputTokensUsage = NULL;

  /**
   * The amount of total tokens from the AI provider.
   */
  private ?int $totalTokensUsage = NULL;

  /**
   * The amount of reasoning tokens from the AI provider.
   */
  private ?int $reasoningTokensUsage = NULL;

  /**
   * The amount of cached tokens from the AI provider.
   */
  private ?int $cachedTokensUsage = NULL;

  /**
   * The constructor.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatMessage|\Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface $normalized
   *   The chat message.
   * @param mixed $rawOutput
   *   The raw output from the AI provider.
   * @param mixed $metadata
   *   The metadata from the AI provider.
   */
  public function __construct(ChatMessage|StreamedChatMessageIteratorInterface $normalized, mixed $rawOutput, mixed $metadata) {
    $this->normalized = $normalized;
    $this->rawOutput = $rawOutput;
    $this->metadata = $metadata;
  }

  /**
   * Returns the new chat message.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatMessage|\Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface
   *   The text string.
   */
  public function getNormalized(): ChatMessage|StreamedChatMessageIteratorInterface {
    return $this->normalized;
  }

  /**
   * Gets the raw output from the AI provider.
   *
   * @return mixed
   *   The raw output.
   */
  public function getRawOutput(): mixed {
    return $this->rawOutput;
  }

  /**
   * Gets the metadata from the AI provider.
   *
   * @return mixed
   *   The metadata.
   */
  public function getMetadata(): mixed {
    return $this->metadata;
  }

  /**
   * Set the total tokens used by the AI provider.
   *
   * @param int $tokens
   *   The amount of tokens.
   */
  public function setTotalTokenUsage(int $tokens): void {
    $this->totalTokensUsage = $tokens;
  }

  /**
   * Set the input tokens used by the AI provider.
   *
   * @param int $tokens
   *   The amount of tokens.
   */
  public function setInputTokenUsage(int $tokens): void {
    $this->inputTokensUsage = $tokens;
  }

  /**
   * Set the output tokens used by the AI provider.
   *
   * @param int $tokens
   *   The amount of tokens.
   */
  public function setOutputTokenUsage(int $tokens): void {
    $this->outputTokensUsage = $tokens;
  }

  /**
   * Set the reasoning tokens used by the AI provider.
   *
   * @param int $tokens
   *   The amount of tokens.
   */
  public function setReasoningTokenUsage(int $tokens): void {
    $this->reasoningTokensUsage = $tokens;
  }

  /**
   * Set the cached tokens used by the AI provider.
   *
   * @param int $tokens
   *   The amount of tokens.
   */
  public function setCachedTokenUsage(int $tokens): void {
    $this->cachedTokensUsage = $tokens;
  }

  /**
   * Gets the total tokens used by the AI provider.
   *
   * @return int|null
   *   The total token usage.
   */
  public function getTotalTokenUsage(): ?int {
    return $this->totalTokensUsage;
  }

  /**
   * Gets the input tokens used by the AI provider.
   *
   * @return int|null
   *   The input token usage.
   */
  public function getInputTokenUsage(): ?int {
    return $this->inputTokensUsage;
  }

  /**
   * Gets the output tokens used by the AI provider.
   *
   * @return int|null
   *   The output token usage.
   */
  public function getOutputTokenUsage(): ?int {
    return $this->outputTokensUsage;
  }

  /**
   * Gets the reasoning tokens used by the AI provider.
   *
   * @return int|null
   *   The reasoning token usage.
   */
  public function getReasoningTokenUsage(): ?int {
    return $this->reasoningTokensUsage;
  }

  /**
   * Gets the cached tokens used by the AI provider.
   *
   * @return int|null
   *   The cached token usage.
   */
  public function getCachedTokenUsage(): ?int {
    return $this->cachedTokensUsage;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return [
      'normalized' => $this->normalized,
      'rawOutput' => $this->rawOutput,
      'metadata' => $this->metadata,
      'tokenUsage' => [
        'input' => $this->inputTokensUsage,
        'output' => $this->outputTokensUsage,
        'total' => $this->totalTokensUsage,
        'reasoning' => $this->reasoningTokensUsage,
        'cached' => $this->cachedTokensUsage,
      ],
    ];
  }

}
