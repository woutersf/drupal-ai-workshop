<?php

namespace Drupal\simple_crawler\Plugin\AiAutomatorType;

use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use ivan_boring\Readability\Configuration;
use ivan_boring\Readability\Readability;

/**
 * The rules for a long string field.
 */
class LongCrawlerBase extends CrawlerBase implements AiAutomatorTypeInterface {

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
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $form_state, array $defaultvalues = []) {
    $form = parent::extraAdvancedFormFields($entity, $fieldDefinition, $form_state, $defaultvalues);

    $form['automator_crawler_strip_tags'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Strip Tags'),
      '#description' => $this->t('Strip tags from the HTML.'),
      '#default_value' => $defaultValues['automator_crawler_strip_tags'] ?? FALSE,
      '#weight' => -10,
    ];

    $form['automator_crawler_mode'] = [
      '#type' => 'select',
      '#options' => [
        'all' => $this->t('Raw Dump'),
        'readability' => $this->t('Article Segmentation (Readability)'),
        'selector' => $this->t('HTML Selector'),
      ],
      '#attributes' => [
        'name' => 'automator_crawler_mode',
      ],
      '#required' => TRUE,
      '#title' => $this->t('Crawler Mode'),
      '#description' => $this->t("Choose the mode to fetch the page. The options are:<ul>
      <li><strong>Raw Dump</strong> - This fetches the whole body.</li>
      <li><strong>Article Segmentation (Readability)</strong> - This uses the Readability segmentation algorithm of trying to figure out the main content.</li>
      <li><strong>HTML Selector</strong> - Use a tag type and optionally class or id to fetch parts. Can also remove tags.</li></ul>"),
      '#default_value' => $defaultValues['automator_crawler_mode'] ?? 'readability',
      '#weight' => -10,
    ];

    $form['automator_crawler_tag'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Tag to get'),
      '#default_value' => $defaultValues['automator_crawler_tag'] ?? 'body',
      '#weight' => -10,
      '#states' => [
        'visible' => [
          ':input[name="automator_crawler_mode"]' => [
            'value' => 'selector',
          ],
        ],
      ],
    ];

    $form['automator_crawler_remove'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Tags to remove'),
      '#description' => $this->t('These are tags that are just garbage and can be removed. One per line.'),
      '#default_value' => $defaultValues['automator_crawler_remove'] ?? "style\nscript\n",
      '#weight' => -10,
      '#states' => [
        'visible' => [
          ':input[name="automator_crawler_mode"]' => [
            'value' => 'selector',
          ],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $uris = $entity->get($automatorConfig['base_field'])->getValue();
    // Scrape.
    $values = [];
    foreach ($uris as $uri) {
      $value = '';
      $rawHtml = (string) $this->crawler->request($uri['uri'], $automatorConfig);

      // Return depending on crawler mode.
      switch ($automatorConfig['crawler_mode']) {
        case 'all':
          $value = mb_convert_encoding((string) $rawHtml, 'utf-8', 'utf-8');
          break;

        case 'readability':
          $readability = new Readability(new Configuration());
          $done = $readability->parse($rawHtml);
          $value = $done ? $readability->getContent() : 'No scrape';
          break;

        case 'selector':
          $value = $this->getPartial($rawHtml, $automatorConfig['crawler_tag'], $automatorConfig['crawler_remove']);
          break;
      }
      $values[] = $automatorConfig['crawler_strip_tags'] ? strip_tags(str_replace([
        '</p>',
        '<br>',
        '<br/>',
      ],
      [
        "\n\n",
        "\n",
        "\n",
      ], $value)) : $value;
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

  /**
   * Simple DOM traverser.
   *
   * @var string $html
   *   The html.
   * @var string $tag
   *   The tag to get.
   * @var string $remove
   *   The tags to remove.
   *
   * @return string
   *   The cut out html.
   */
  public function getPartial($html, $tag = 'body', $remove = "") {
    $dom = new \DOMDocument();
    $dom->loadHTML($html);
    $mock = new \DOMDocument();

    $parts = explode('.', $tag);
    $tag = isset($parts[1]) ? $parts[0] : $tag;
    $class = $parts[1] ?? '';
    $parts = explode('#', $tag);
    $tag = isset($parts[1]) ? $parts[0] : $tag;
    $id = $parts[1] ?? '';

    // Remove.
    foreach (explode("\n", $remove) as $tagRemove) {
      $removals = $dom->getElementsByTagName($tagRemove);
      for ($t = 0; $t < $removals->count(); $t++) {
        $dom->removeChild($removals->item($t));
      }
    }

    // Get the rest.
    $tags = $dom->getElementsByTagName($tag);

    for ($t = 0; $t < $tags->count(); $t++) {
      /** @var DOMNode */
      $tag = $tags->item($t);
      if ($class && $tag->getAttribute('class') != $class) {
        continue;
      }
      if ($id && $tag->getAttribute('id') != $id) {
        continue;
      }
      foreach ($tag->childNodes as $child) {
        $mock->appendChild($mock->importNode($child, TRUE));
      }
    }
    return $mock->saveHTML();
  }

}
