<?php

namespace Drupal\simple_crawler\Plugin\AiAutomatorType;

use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The rules for a string_long field.
 */
#[AiAutomatorType(
  id: 'simple_crawler_string_long',
  label: new TranslatableMarkup('Simple Crawler: HTML Crawler'),
  field_rule: 'string_long',
  target: '',
)]
class StringLongCrawler extends LongCrawlerBase {

  /**
   * {@inheritDoc}
   */
  public $title = 'Simple Crawler: HTML Crawler';

}
