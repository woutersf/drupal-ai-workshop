<?php

namespace Drupal\eca_base;

/**
 * Defines events provided by the ECA Base module.
 */
final class BaseEvents {

  /**
   * Dispatches an event when Drupal's core cron is executed.
   *
   * @Event
   *
   * @var string
   */
  public const CRON = 'eca_base.cron';

  /**
   * Dispatches a user-defined custom event.
   *
   * @Event
   *
   * @var string
   */
  public const CUSTOM = 'eca_base.custom';

  /**
   * Dispatches a tool event.
   *
   * @Event
   *
   * @var string
   */
  public const TOOL = 'eca_base.tool';

  /**
   * Dispatches a field widget event.
   *
   * @Event
   *
   * @var string
   */
  public const FIELD_WIDGET = 'eca_base.field_widget';

}
