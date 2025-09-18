<?php

namespace Drupal\simple_crawler\Plugin\AiAutomatorType;

use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for a string_long field.
 */
#[AiAutomatorType(
  id: 'simple_crawler_depth_string_long',
  label: new TranslatableMarkup('Simple Crawler: HTML Depth Crawler'),
  field_rule: 'string_long',
  target: '',
)]
class StringLongDepthCrawler extends DepthCrawlerRule implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public function getMode() {
    return 'string';
  }

}
