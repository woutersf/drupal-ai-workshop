<?php

namespace Drupal\simple_crawler\Plugin\AiAutomatorType;

use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\simple_crawler\Crawler;
use Drupal\simple_crawler\CrawlerHelper;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The rules for a text_long field.
 */
#[AiAutomatorType(
  id: 'simple_crawler_depth_text_long',
  label: new TranslatableMarkup('Simple Crawler: HTML Depth Crawler'),
  field_rule: 'text_long',
  target: '',
)]
class TextLongDepthCrawler extends DepthCrawlerRule implements AiAutomatorTypeInterface {

  /**
   * The simple crawler.
   *
   * @var \Drupal\simple_crawler\Crawler
   */
  public Crawler $crawler;

  /**
   * Crawling helper.
   *
   * @var \Drupal\simple_crawler\CrawlerHelper
   */
  public CrawlerHelper $crawlerHelper;

  /**
   * Construct a boolean field.
   *
   * @param array $configuration
   *   Inherited configuration.
   * @param string $plugin_id
   *   Inherited plugin id.
   * @param mixed $plugin_definition
   *   Inherited plugin definition.
   * @param \Drupal\simple_crawler\Crawler $crawler
   *   The crawler.
   * @param \Drupal\simple_crawler\CrawlerHelper $crawlerHelper
   *   The crawler helper.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Crawler $crawler, CrawlerHelper $crawlerHelper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $crawler, $crawlerHelper);
    $this->crawler = $crawler;
    $this->crawlerHelper = $crawlerHelper;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('simple_crawler.crawler'),
      $container->get('simple_crawler.crawler_helper')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Get text format.
    $textFormat = $this->crawlerHelper->getTextFormat($fieldDefinition);

    // Then set the value.
    $cleanedValues = [];
    foreach ($values as $value) {
      $cleanedValues[] = [
        'value' => $value,
        'format' => $textFormat,
      ];
    }
    $entity->set($fieldDefinition->getName(), $cleanedValues);
  }

}
