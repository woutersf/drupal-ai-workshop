<?php

namespace Drupal\convertapi\Plugin\AiAutomatorType;

use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for a text long field.
 */
#[AiAutomatorType(
  id: 'convertapi_text_long',
  label: new TranslatableMarkup('ConvertAPI: File to Text'),
  field_rule: 'text_long',
  target: '',
)]
class TextLongFromDocument extends TextDocument implements AiAutomatorTypeInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'ConvertAPI: File to Text';

}
