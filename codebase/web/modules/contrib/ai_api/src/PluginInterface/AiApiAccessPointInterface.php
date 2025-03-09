<?php

namespace Drupal\ai_api\PluginInterface;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interface for AI Access Point modifiers.
 */
interface AiApiAccessPointInterface {

  /**
   * Gets the plugin id.
   *
   * @return string
   *   The plugin id.
   */
  public function getId();

  /**
   * Get the module name.
   *
   * @return string
   *   The module name.
   */
  public function getModuleName();

  /**
   * Get the operation type.
   *
   * @return string
   *   The operation type.
   */
  public function getOperationType();

  /**
   * The request logic.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   */
  public function runRequest(Request $request): Response;

}
