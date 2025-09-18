<?php

namespace Drupal\ai\OperationType\Chat;

use Drupal\ai\OperationType\Chat\Tools\ToolsInputInterface;
use Drupal\ai\OperationType\InputBase;
use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for chat input.
 */
class ChatInput extends InputBase implements InputInterface {

  /**
   * The message to convert to text.
   *
   * @var array
   */
  private array $messages;

  /**
   * The debug data.
   *
   * @var array
   */
  private array $debugData = [];

  /**
   * The tools input.
   *
   * @var \Drupal\ai\OperationType\Chat\Tools\ToolsInputInterface|null
   */
  private ?ToolsInputInterface $chatTools = NULL;

  /**
   * The structured JSON schema.
   *
   * @var array
   */
  protected array $chatStructuredJsonSchema = [];

  /**
   * If strict schema exists, should it be followed.
   *
   * @var bool
   */
  protected bool $chatStrictSchema = FALSE;

  /**
   * The constructor.
   *
   * @param array $messages
   *   The messages to chat.
   */
  public function __construct(array $messages) {
    $this->messages = $messages;
  }

  /**
   * Get the messages to chat.
   *
   * @return array
   *   The messages.
   */
  public function getMessages(): array {
    return $this->messages;
  }

  /**
   * Set the messages to chat.
   *
   * @param array $messages
   *   The messages.
   */
  public function setMessages(array $messages) {
    $this->messages = $messages;
  }

  /**
   * {@inheritDoc}
   */
  public function setChatStructuredJsonSchema(array $schema): void {
    $this->setDebugDataValue('chat_structured_json_schema', $schema);
    $this->chatStructuredJsonSchema = $schema;
  }

  /**
   * {@inheritDoc}
   */
  public function getChatStructuredJsonSchema(): array {
    return $this->chatStructuredJsonSchema;
  }

  /**
   * {@inheritDoc}
   */
  public function setChatTools(ToolsInputInterface $tools): void {
    $this->setDebugDataValue('chat_tools', $tools);
    $this->chatTools = $tools;
  }

  /**
   * {@inheritDoc}
   */
  public function getChatTools(): ?ToolsInputInterface {
    return $this->chatTools;
  }

  /**
   * {@inheritdoc}
   */
  public function toString(): string {
    $string = "";
    foreach ($this->messages as $message) {
      $string .= $message->getRole() . "\n" . $message->getText() . "\n";
    }
    return $string;
  }

  /**
   * Return the input as string.
   *
   * @return string
   *   The input as string.
   */
  public function __toString(): string {
    return $this->toString();
  }

}
