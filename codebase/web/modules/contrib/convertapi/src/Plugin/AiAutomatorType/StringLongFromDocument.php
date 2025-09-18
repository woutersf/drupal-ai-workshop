<?php

namespace Drupal\convertapi\Plugin\AiAutomatorType;

use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for a string long field.
 */
#[AiAutomatorType(
  id: 'convertapi_string_long',
  label: new TranslatableMarkup('ConvertAPI: File to Text'),
  field_rule: 'string_long',
  target: '',
)]
class StringLongFromDocument extends TextDocument implements AiAutomatorTypeInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'ConvertAPI: File to Text';

}
