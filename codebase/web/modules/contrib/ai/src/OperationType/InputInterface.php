<?php

namespace Drupal\ai\OperationType;

/**
 * Base Input Interface class.
 */
interface InputInterface {

  /**
   * Return the input as string.
   *
   * @return string
   *   The input as string.
   */
  public function toString(): string;

  /**
   * Returns all debug data.
   *
   * @return array
   *   The debug data.
   */
  public function getDebugData(): array;

  /**
   * Set the debug data.
   *
   * @param array $debugData
   *   The debug data.
   */
  public function setDebugData(array $debugData): void;

  /**
   * Set one debug data.
   *
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value.
   */
  public function setDebugDataValue(string $key, $value): void;

}
