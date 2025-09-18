<?php

namespace Drupal\ai_test\OperationType\Echo;

use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for echo operations.
 */
class EchoInput implements InputInterface {

  /**
   * The constructor.
   *
   * @param string $input
   *   The echo input.
   */
  public function __construct(private string $input) {}

  /**
   * {@inheritdoc}
   */
  public function toString(): string {
    return $this->input;
  }

  /**
   * {@inheritdoc}
   */
  public function getDebugData(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function setDebugData(array $debugData): void {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function setDebugDataValue(string $key, $value): void {
    // Do nothing.
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
