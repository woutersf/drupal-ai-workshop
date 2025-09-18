<?php

namespace Drupal\eca_base\Event;

use Drupal\eca\Event\TokenReceiverInterface;
use Drupal\eca\Event\TokenReceiverTrait;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Provides a tool event.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_base\Event
 */
class ToolEvent extends Event implements TokenReceiverInterface {

  use TokenReceiverTrait;

  /**
   * The tool output.
   *
   * @var mixed
   */
  protected mixed $output;

  /**
   * Constructs a ToolEvent.
   *
   * @param string $wildcard
   *   The wildcard of the event in the ECA model.
   */
  public function __construct(
    protected string $wildcard,
  ) {}

  /**
   * Gets the wildcard.
   *
   * @return string
   *   The wildcard.
   */
  public function getWildcard(): string {
    return $this->wildcard;
  }

  /**
   * Gets the tool output.
   *
   * @return mixed
   *   The tool output.
   */
  public function getOutput(): mixed {
    return $this->output ?? NULL;
  }

  /**
   * Sets the tool output.
   *
   * @param mixed $output
   *   The tool output.
   */
  public function setOutput(mixed $output): void {
    $this->output = $output;
  }

}
