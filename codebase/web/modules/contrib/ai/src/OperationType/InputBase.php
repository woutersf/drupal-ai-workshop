<?php

namespace Drupal\ai\OperationType;

/**
 * Base Input Interface class.
 */
abstract class InputBase implements InputInterface {

  /**
   * The debug data.
   *
   * @var array
   */
  private array $debugData = [];

  /**
   * {@inheritdoc}
   */
  public function getDebugData(): array {
    return $this->debugData;
  }

  /**
   * {@inheritdoc}
   */
  public function setDebugData(array $debug_data): void {
    $this->debugData = $debug_data;
  }

  /**
   * {@inheritdoc}
   */
  public function setDebugDataValue(string $key, $value): void {
    $this->debugData[$key] = $value;
  }

}
