<?php

namespace Drupal\simple_crawler\Plugin\AiAutomatorType;

use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use ivan_boring\Readability\Configuration;
use ivan_boring\Readability\Readability;

/**
 * The rules for a image field.
 */
#[AiAutomatorType(
  id: 'simple_crawler_image',
  label: new TranslatableMarkup('Simple Crawler: Image Crawler'),
  field_rule: 'image',
  target: 'file',
)]
class ImageCrawler extends CrawlerBase implements AiAutomatorTypeInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'Simple Crawler: Image Crawler';

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
      $values[] = $done ? $readability->getImage() : '';
    }
    return $values;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Should be an url.
    if (!filter_var($value, FILTER_VALIDATE_URL)) {
      return FALSE;
    }
    // Otherwise it is ok.
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $fileHelper = $this->getFileHelper();
    // Transform string to boolean.
    $fileEntities = [];

    // Successful counter, to only download as many as max.
    $successFul = 0;
    foreach ($values as $value) {
      // Save filename.
      $fileName = explode('?', basename($value))[0];
      // If no ending exists.
      if (!strstr($fileName, '.')) {
        $fileName .= '.jpg';
      }
      // Everything validated, then we prepare the file path to save to.
      $filePath = $fileHelper->createFilePathFromFieldConfig($fileName, $fieldDefinition, $entity);
      // Create file entity from string.
      $binary = file_get_contents($value);
      $image = $fileHelper->generateImageMetaDataFromBinary($binary, $filePath);

      // If we can save, we attach it.
      if ($image) {
        // Add to the entities list.
        $fileEntities[] = $image;

        $successFul++;
        // If we have enough images, give up.
        if ($successFul == $fieldDefinition->getFieldStorageDefinition()->getCardinality()) {
          break;
        }
      }
    }

    // Then set the value.
    $entity->set($fieldDefinition->getName(), $fileEntities);
  }

}
