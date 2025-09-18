<?php

namespace Drupal\simple_crawler\Plugin\AiAutomatorType;

use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use ivan_boring\Readability\Configuration;
use ivan_boring\Readability\Readability;

/**
 * The rules for a title field.
 */
#[AiAutomatorType(
  id: 'simple_crawler_title_string',
  label: new TranslatableMarkup('Simple Crawler: Title Crawler'),
  field_rule: 'string',
  target: '',
)]
class TitleCrawler extends CrawlerBase implements AiAutomatorTypeInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'Simple Crawler: Title Crawler';

  /**
   * {@inheritDoc}
   */
  public function needsPrompt() {
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function advancedMode() {
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "";
  }

  /**
   * {@inheritDoc}
   */
  public function allowedInputs() {
    return ['link'];
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $readability = new Readability(new Configuration());
    $uris = $entity->get($automatorConfig['base_field'])->getValue();
    // Scrape.
    $values = [];
    foreach ($uris as $uri) {
      $rawHtml = $this->crawler->request($uri['uri'], $automatorConfig);

      $done = $readability->parse($rawHtml);
      $values[] = $done ? $readability->getTitle() : '';
    }
    return $values;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Should be a string.
    if (!is_string($value)) {
      return FALSE;
    }
    // Otherwise it is ok.
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Then set the value.
    $entity->set($fieldDefinition->getName(), $values);
  }

}
